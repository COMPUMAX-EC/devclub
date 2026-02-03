<?php

namespace App\Support;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class PasswordHistoryService
{
    public function reused(User $user, string $plain): bool
    {
        $cfg = config('password_policy.history', []);
        if (empty($cfg['enabled'])) return false;

        $limit = (int) ($cfg['remember_last'] ?? 0);

        // 1) Nunca permitir igual a la actual
        if ($user->password && Hash::check($plain, $user->password)) {
            return true;
        }

        if ($limit <= 0) return false;

        // 2) Revisar últimas N contraseñas previas
        $last = PasswordHistory::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('password_hash');

        foreach ($last as $hash) {
            if (Hash::check($plain, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function remember(User $user, ?string $oldHash): void
    {
        $cfg = config('password_policy.history', []);
        if (empty($cfg['enabled'])) return;

        if (empty($oldHash)) return; // nada que recordar

        // Guarda hash anterior
        PasswordHistory::create([
            'user_id'       => $user->id,
            'password_hash' => $oldHash,
            'created_at'    => now(),
        ]);

        // Purgar por cantidad (mantener últimas N)
        $limit = (int) ($cfg['remember_last'] ?? 0);
        if ($limit > 0) {
            $ids = PasswordHistory::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->skip($limit) // deja las N más recientes
                ->take(PHP_INT_MAX)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                PasswordHistory::whereIn('id', $ids)->delete();
            }
        }

        // Purgar por antigüedad (opcional)
        $days = (int) ($cfg['retention_days'] ?? 0);
        if ($days > 0) {
            $cut = Carbon::now()->subDays($days);
            PasswordHistory::where('user_id', $user->id)
                ->where('created_at', '<', $cut)
                ->delete();
        }
    }
}
