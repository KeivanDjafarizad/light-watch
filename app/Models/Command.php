<?php

namespace App\Models;

use App\Events\CommandStatusChanged;
use Carbon\CarbonImmutable;
use Database\Factories\CommandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property CommandType $type
 * @property CommandStatus $status
 * @property array{level?: int}|null $payload
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $acked_at
 * @property int|null $ack_code
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $reconcile_by
 */
class Command extends Model
{
    /** @use HasFactory<CommandFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => CommandType::class,
            'status' => CommandStatus::class,
            'payload' => 'array',
            'issued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'acked_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'reconcile_by' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Transition this command to the given status, broadcasting the change
     * when the status actually changed. Guards against races (a late vendor
     * ack vs. the timeout sweep) by re-reading a fresh locked copy: once a
     * command reaches a terminal status it never transitions again.
     *
     * @param  array<string, mixed>  $attributes  extra columns to set with the transition
     */
    public function transitionTo(CommandStatus $status, array $attributes = []): bool
    {
        $changed = DB::transaction(function () use ($status, $attributes) {
            $fresh = static::query()->lockForUpdate()->find($this->id);

            if ($fresh === null || $fresh->status === $status || $fresh->status->isTerminal()) {
                return null;
            }

            $fresh->update(['status' => $status->value, ...$attributes]);

            return $fresh;
        });

        if ($changed === null) {
            return false;
        }

        $this->setRawAttributes($changed->getAttributes(), true);

        CommandStatusChanged::dispatch($changed);

        return true;
    }
}
