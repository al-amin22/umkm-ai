<?php

namespace App\Services;

use App\Models\WaSession;
use Illuminate\Support\Facades\Log;

class SessionService
{
    // Lock timeout: 30 detik (cegah deadlock)
    private int $lockTimeoutSeconds = 30;

    // ── Get atau Buat Session ─────────────────────────────────────

    public function getSession(string $waNumber): WaSession
    {
        return WaSession::firstOrCreate(
            ['wa_number' => $waNumber],
            [
                'active_context' => null,
                'context_data'   => null,
                'last_activity'  => now(),
                'is_locked'      => false,
            ]
        );
    }

    // ── Update Context ────────────────────────────────────────────

    public function updateContext(string $waNumber, string $context, array $data = []): WaSession
    {
        $session = $this->getSession($waNumber);

        $session->update([
            'active_context' => $context,
            'context_data'   => $data,
            'last_activity'  => now(),
        ]);

        return $session->fresh();
    }

    // ── Clear Context ─────────────────────────────────────────────

    public function clearContext(string $waNumber): void
    {
        WaSession::where('wa_number', $waNumber)->update([
            'active_context' => null,
            'context_data'   => null,
            'is_locked'      => false,
            'last_activity'  => now(),
        ]);
    }

    // ── Lock / Unlock ─────────────────────────────────────────────

    public function lockSession(string $waNumber): bool
    {
        // Cek lock stale (timeout)
        $session = $this->getSession($waNumber);

        if ($session->is_locked) {
            $lockAge = now()->diffInSeconds($session->updated_at);

            if ($lockAge < $this->lockTimeoutSeconds) {
                Log::debug("SessionService: {$waNumber} sedang terkunci (age {$lockAge}s)");
                return false; // Tidak bisa lock
            }

            // Lock stale — paksa unlock
            Log::warning("SessionService: lock stale terdeteksi pada {$waNumber}, paksa unlock");
        }

        $affected = WaSession::where('wa_number', $waNumber)
            ->where(function ($q) {
                $q->where('is_locked', false)
                  ->orWhere('updated_at', '<', now()->subSeconds($this->lockTimeoutSeconds));
            })
            ->update(['is_locked' => true]);

        return $affected > 0;
    }

    public function unlockSession(string $waNumber): void
    {
        WaSession::where('wa_number', $waNumber)->update(['is_locked' => false]);
    }

    public function isLocked(string $waNumber): bool
    {
        $session = WaSession::where('wa_number', $waNumber)->first();

        if (! $session || ! $session->is_locked) {
            return false;
        }

        // Cek apakah lock sudah stale
        $lockAge = now()->diffInSeconds($session->updated_at);

        if ($lockAge >= $this->lockTimeoutSeconds) {
            $this->unlockSession($waNumber);
            return false;
        }

        return true;
    }

    // ── Context Helpers ───────────────────────────────────────────

    public function getContextData(string $waNumber): array
    {
        $session = WaSession::where('wa_number', $waNumber)->first();
        return $session?->context_data ?? [];
    }

    public function mergeContextData(string $waNumber, array $newData): void
    {
        $session     = $this->getSession($waNumber);
        $currentData = $session->context_data ?? [];

        $session->update([
            'context_data'  => array_merge($currentData, $newData),
            'last_activity' => now(),
        ]);
    }

    public function touchActivity(string $waNumber): void
    {
        WaSession::where('wa_number', $waNumber)
            ->update(['last_activity' => now()]);
    }

    // ── Cleanup Sessions Lama ─────────────────────────────────────

    public function cleanupStaleSessions(int $inactiveHours = 24): int
    {
        return WaSession::where('last_activity', '<', now()->subHours($inactiveHours))
            ->whereNull('active_context')
            ->delete();
    }
}
