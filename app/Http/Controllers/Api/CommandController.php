<?php

namespace App\Http\Controllers\Api;

use App\Actions\Commands\CommandService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommandRequest;
use App\Http\Resources\CommandResource;
use App\Models\Command;
use Illuminate\Http\JsonResponse;

class CommandController extends Controller
{
    /**
     * Issue a control command (PRD §7/§9.4). Creates it as Pending and
     * dispatches the publish job; the response returns immediately with
     * the id and initial status — never blocked on the MQTT publish or
     * any ack.
     */
    public function store(StoreCommandRequest $request, CommandService $service): JsonResponse
    {
        $device = $request->resolvedDevice();

        $command = $service->issue($device, $request->commandType(), $request->payload());

        return (new CommandResource($command))
            ->response()
            ->setStatusCode(201);
    }

    /** Status poll fallback (PRD §7), e.g. recovering optimistic UI state. */
    public function show(Command $command): CommandResource
    {
        return new CommandResource($command);
    }
}
