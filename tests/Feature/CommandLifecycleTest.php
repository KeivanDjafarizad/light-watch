<?php

namespace Tests\Feature;

use App\Actions\Commands\ExpireStaleCommands;
use App\Actions\Commands\MqttPublisher;
use App\Actions\Commands\VendorCommandEncoderRegistry;
use App\Events\CommandStatusChanged;
use App\Jobs\ProcessLuminaAck;
use App\Jobs\PublishCommand;
use App\Models\Command;
use App\Models\CommandStatus;
use App\Models\CommandType;
use App\Models\Device;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class CommandLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{topic: string, payload: string}> */
    private array $published = [];

    protected function fakePublisher(): MqttPublisher
    {
        $test = $this;

        return new class($test) extends MqttPublisher
        {
            public function __construct(private readonly CommandLifecycleTest $test)
            {
                // no parent constructor work needed for the fake
            }

            public function publish(string $topic, string $payload, int $qos = 1): void
            {
                $this->test->recordPublish($topic, $payload);
            }
        };
    }

    public function recordPublish(string $topic, string $payload): void
    {
        $this->published[] = ['topic' => $topic, 'payload' => $payload];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MqttPublisher::class, $this->fakePublisher());
    }

    #[TestDox('POST /api/commands validates input')]
    public function test_store_validates_input(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/commands', ['device_id' => 999999, 'type' => 'on'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_id']);

        $device = Device::factory()->create();

        $this->postJson('/api/commands', ['device_id' => $device->id, 'type' => 'explode'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);

        $this->postJson('/api/commands', ['device_id' => $device->id, 'type' => 'dim'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payload.level']);

        $this->postJson('/api/commands', ['device_id' => $device->id, 'type' => 'dim', 'payload' => ['level' => 150]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payload.level']);
    }

    #[TestDox('POST /api/commands returns the id and initial status immediately')]
    public function test_store_creates_pending_command_and_dispatches_publish(): void
    {
        $this->actingAsUser();
        Queue::fake();

        $device = Device::factory()->create();

        $response = $this->postJson('/api/commands', [
            'device_id' => $device->id,
            'type' => 'dim',
            'payload' => ['level' => 60],
        ])->assertCreated()->json();

        $this->assertSame('pending', $response['status']);
        $this->assertSame('dim', $response['type']);
        $this->assertSame(60, $response['payload']['level']);

        $command = Command::query()->findOrFail($response['id']);
        $this->assertTrue($command->device->is($device));

        Queue::assertPushed(PublishCommand::class, fn (PublishCommand $job) => $job->commandId === $command->id);
    }

    #[TestDox('Lot A commands publish JSON with the command id as correlation id')]
    public function test_publish_command_encodes_and_sends_for_lumina(): void
    {
        Event::fake([CommandStatusChanged::class]);

        $device = Device::factory()->create(); // lumina_p2p
        $command = Command::factory()->for($device, 'device')->type(CommandType::Dim, 40)->create();

        app(PublishCommand::class, ['commandId' => $command->id])->handle(
            app(VendorCommandEncoderRegistry::class),
            app(MqttPublisher::class),
        );

        $this->assertCount(1, $this->published);
        $this->assertSame("lumina/v2/sanverano/{$device->external_id}/cmd", $this->published[0]['topic']);

        $payload = json_decode($this->published[0]['payload'], true);
        $this->assertSame((string) $command->id, $payload['id']);
        $this->assertSame('dim', $payload['op']);
        $this->assertSame(40, $payload['value']);

        $command->refresh();
        $this->assertSame(CommandStatus::Sent, $command->status);
        $this->assertNull($command->reconcile_by); // ack-based vendor: no window

        Event::assertDispatched(CommandStatusChanged::class);
    }

    #[TestDox('Lot C commands publish CP-3000 syntax and open a reconciliation window')]
    public function test_publish_command_encodes_and_sends_for_cp3000(): void
    {
        Event::fake([CommandStatusChanged::class]);

        $device = Device::factory()->cp3000()->create(['external_id' => '0042']);
        $command = Command::factory()->for($device, 'device')->type(CommandType::On)->create();

        app(PublishCommand::class, ['commandId' => $command->id])->handle(
            app(VendorCommandEncoderRegistry::class),
            app(MqttPublisher::class),
        );

        $this->assertSame('cp3000/0042/cmd', $this->published[0]['topic']);
        $this->assertSame('SET;ON;0;0', $this->published[0]['payload']);

        $command->refresh();
        $this->assertSame(CommandStatus::Sent, $command->status);
        $this->assertNotNull($command->reconcile_by);
        $this->assertEqualsWithDelta(now()->addMinutes(10), $command->reconcile_by, 1);
    }

    #[TestDox('a vendor ok ack moves the command to acked')]
    public function test_ack_ok_transitions_to_acked(): void
    {
        Event::fake([CommandStatusChanged::class]);

        $command = Command::factory()->status(CommandStatus::Sent)->create();

        ProcessLuminaAck::dispatchSync('lumina/v2/sanverano/LUM-000001/ack', json_encode([
            'id' => (string) $command->id, 'res' => 'ok', 'code' => 0,
        ]));

        $this->assertSame(CommandStatus::Acked, $command->refresh()->status);
        $this->assertNotNull($command->acked_at);
        $this->assertSame(0, $command->ack_code); // vendor diagnostic: 0 = ok
    }

    #[TestDox('a vendor error ack fails the command')]
    public function test_ack_err_transitions_to_failed(): void
    {
        $command = Command::factory()->status(CommandStatus::Sent)->create();

        ProcessLuminaAck::dispatchSync('lumina/v2/sanverano/LUM-000001/ack', json_encode([
            'id' => (string) $command->id, 'res' => 'err', 'code' => 1,
        ]));

        $this->assertSame(CommandStatus::Failed, $command->refresh()->status);
        $this->assertSame(1, $command->refresh()->ack_code);
    }

    #[TestDox('acks for unknown or already settled commands are ignored')]
    public function test_late_or_unknown_acks_are_ignored(): void
    {
        $command = Command::factory()->status(CommandStatus::Failed)->create();

        ProcessLuminaAck::dispatchSync('lumina/v2/sanverano/LUM-000001/ack', json_encode([
            'id' => (string) $command->id, 'res' => 'ok',
        ]));

        ProcessLuminaAck::dispatchSync('lumina/v2/sanverano/LUM-000001/ack', json_encode([
            'id' => '999999', 'res' => 'ok',
        ]));

        ProcessLuminaAck::dispatchSync('lumina/v2/sanverano/LUM-000001/ack', 'not json');

        $this->assertSame(CommandStatus::Failed, $command->refresh()->status);
        $this->assertSame(1, Command::query()->count());
    }

    #[TestDox('a Lot A command without an ack within the vendor timeout (30s) is failed')]
    public function test_ack_timeout_fails_the_command(): void
    {
        $device = Device::factory()->create();
        $command = Command::factory()->for($device, 'device')
            ->status(CommandStatus::Sent)
            ->create(['sent_at' => now()->subSeconds(31)]);

        (new ExpireStaleCommands)->expire();

        $this->assertSame(CommandStatus::Failed, $command->refresh()->status);
    }

    #[TestDox('a Lot A command still inside the vendor timeout is left alone')]
    public function test_ack_timeout_has_not_elapsed_yet(): void
    {
        $device = Device::factory()->create();
        $command = Command::factory()->for($device, 'device')
            ->status(CommandStatus::Sent)
            ->create(['sent_at' => now()->subSeconds(29)]);

        (new ExpireStaleCommands)->expire();

        $this->assertSame(CommandStatus::Sent, $command->refresh()->status);
    }

    #[TestDox('a Lot C command past its reconciliation window becomes unconfirmed, not failed')]
    public function test_reconciliation_window_lapse_marks_unconfirmed(): void
    {
        $device = Device::factory()->cp3000()->create();
        $command = Command::factory()->for($device, 'device')
            ->status(CommandStatus::Sent)
            ->create([
                'sent_at' => now()->subMinutes(11),
                'reconcile_by' => now()->subMinute(),
            ]);

        (new ExpireStaleCommands)->expire();

        $this->assertSame(CommandStatus::Unconfirmed, $command->refresh()->status);
    }

    #[TestDox('commands still inside their window are left alone')]
    public function test_expiry_leaves_fresh_commands_alone(): void
    {
        $device = Device::factory()->cp3000()->create();
        $command = Command::factory()->for($device, 'device')
            ->status(CommandStatus::Sent)
            ->create([
                'sent_at' => now()->subMinutes(2),
                'reconcile_by' => now()->addMinutes(8),
            ]);

        (new ExpireStaleCommands)->expire();

        $this->assertSame(CommandStatus::Sent, $command->refresh()->status);
    }

    #[TestDox('GET /api/commands/{id} polls a command status')]
    public function test_show_returns_command_status(): void
    {
        $this->actingAsUser();

        $command = Command::factory()->status(CommandStatus::Acked)->create();

        $this->getJson("/api/commands/{$command->id}")
            ->assertOk()
            ->assertJsonPath('id', $command->id)
            ->assertJsonPath('status', 'acked');
    }
}
