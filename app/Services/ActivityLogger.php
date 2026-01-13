<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Enregistrer une activité
     *
     * @param string $action Nom de l'action (ex: "Product Created")
     * @param string $description Description détaillée
     * @param string $level Niveau (info, warning, error, success)
     * @param array|null $payload Données supplémentaires
     * @return ActivityLog
     */
    public static function log($action, $description, $level = 'info', $payload = null)
    {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'level' => $level,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'payload' => $payload,
        ]);
    }
}
