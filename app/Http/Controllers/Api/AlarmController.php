<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlarmResource;
use App\Models\Alarm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlarmController extends Controller
{
    /**
     * Alarm list for the KPI drill-down (PRD §7/§9.1). Defaults to open
     * alarms; `?status=closed` (or `all`) for anything else.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->query('status', 'open');

        $query = Alarm::query()->with('device')->orderByDesc('opened_at');

        if ($status === 'open' || $status === 'closed') {
            $query->where('status', $status);
        }

        return AlarmResource::collection($query->limit(200)->get());
    }
}
