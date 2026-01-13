<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * Get logs for Admin and Webmaster (Audit Trail)
     */
    public function auditLogs(Request $request)
    {
        $query = ActivityLog::with('user')
            ->orderByDesc('created_at');

        // Admin and Webmaster only see "business" logs
        // We filter out purely technical logs if we categorize them, 
        // but for now we follow the user's request: "Audit Trail"

        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->input('action') . '%');
        }

        $logs = $query->paginate($request->input('limit', 50));

        return response()->json([
            'message' => 'success',
            'logs' => $logs
        ], 200);
    }

    /**
     * Get all logs for Dev (Audit + Technical)
     */
    public function systemLogs(Request $request)
    {
        // For Dev, we show everything. 
        // In a real system, we might also read from storage/logs/laravel.log

        $query = ActivityLog::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }

        $logs = $query->paginate($request->input('limit', 50));

        return response()->json([
            'message' => 'success',
            'logs' => $logs
        ], 200);
    }
}
