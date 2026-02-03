<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class Audit
{
    /**
     * Registra un evento en audit_logs.
     *
     * @param  string       $action      Ej: "plan_version.created"
     * @param  array        $context     Datos adicionales (product_id, plan_version_id, etc.)
     * @param  int|null     $targetUserId Usuario afectado (si aplica)
     * @param  Request|null $request     Request explícito (opcional)
     */
    public static function log(string $action, array $context = [], ?int $targetUserId = null, ?Request $request = null): void
    {
        try {
            $user = auth()->user();
            $req  = $request ?: request();

            $realm = session('realm') ?: 'admin';

            AuditLog::create([
                'actor_user_id' => $user?->id,
                'target_user_id'=> $targetUserId,
                'realm'         => $realm,
                'action'        => $action,
                'context_json'  => $context,
                'ip'            => $req?->ip(),
                'user_agent'    => $req ? substr((string) $req->userAgent(), 0, 255) : null,
                // created_at lo maneja el default de la BD
            ]);
        } catch (\Throwable $e) {
            // No romper el flujo de la app si falla el log
            // Puedes loguear esto a logs de Laravel si quieres:
            // \Log::warning('Audit log failed', ['error' => $e->getMessage()]);
        }
    }
}
