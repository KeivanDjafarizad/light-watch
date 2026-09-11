<?php

use App\Http\Controllers\Api\AlarmController;
use App\Http\Controllers\Api\CabinetController;
use App\Http\Controllers\Api\CommandController;
use App\Http\Controllers\Api\FleetSnapshotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard API (PRD §7)
|--------------------------------------------------------------------------
|
| Session-authenticated (single operator, PRD §3: no multi-tenant access
| control in scope — noted in NOTES.md) and CSRF-protected by riding the
| web middleware group: Inertia's XHR client sends the XSRF token
| automatically. Broadcast channels stay public per PRD §6.
|
*/

Route::prefix('api')->middleware('auth')->group(function () {
    Route::get('fleet/snapshot', FleetSnapshotController::class)->name('api.fleet.snapshot');
    Route::get('cabinets', [CabinetController::class, 'index'])->name('api.cabinets.index');
    Route::get('cabinets/{code}', [CabinetController::class, 'show'])->name('api.cabinets.show');
    Route::get('alarms', [AlarmController::class, 'index'])->name('api.alarms.index');
    Route::post('commands', [CommandController::class, 'store'])->name('api.commands.store');
    Route::get('commands/{command:id}', [CommandController::class, 'show'])->name('api.commands.show');
});
