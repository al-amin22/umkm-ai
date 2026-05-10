<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WAService
{
    private string $waServiceUrl;
    private string $secret;

    public function __construct()
    {
        $this->waServiceUrl = rtrim(config('services.wa.url', 'http://localhost:3000'), '/');
        $this->secret       = config('services.wa.secret', '');
    }

    // ── Kirim Pesan Teks ──────────────────────────────────────────

    public function kirimPesan(string $waNumber, string $pesan): bool
    {
        $pesan = $this->escapeMarkdown($pesan);

        try {
            $response = Http::timeout(15)
                ->post("{$this->waServiceUrl}/send", [
                    'secret' => $this->secret,
                    'to'     => $waNumber,
                    'pesan'  => $pesan,
                ]);

            if ($response->successful() && $response->json('success')) {
                return true;
            }

            Log::warning('WAService: gagal kirim pesan', [
                'to'     => $waNumber,
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('WAService: exception saat kirim pesan', [
                'to'    => $waNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ── Kirim Gambar dengan Caption ───────────────────────────────

    public function kirimGambar(string $waNumber, string $imageUrl, string $caption = ''): bool
    {
        $caption = $this->escapeMarkdown($caption);

        try {
            $response = Http::timeout(30)
                ->post("{$this->waServiceUrl}/send", [
                    'secret'    => $this->secret,
                    'to'        => $waNumber,
                    'media_url' => $imageUrl,
                    'caption'   => $caption,
                ]);

            if ($response->successful() && $response->json('success')) {
                return true;
            }

            Log::warning('WAService: gagal kirim gambar', [
                'to'     => $waNumber,
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('WAService: exception saat kirim gambar', [
                'to'    => $waNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ── Utilities ─────────────────────────────────────────────────

    public function escapeMarkdown(string $teks): string
    {
        // Escape karakter spesial WhatsApp markdown
        return str_replace(
            ['*', '_', '~', '`'],
            ['\*', '\_', '\~', '\`'],
            $teks
        );
    }

    public function formatRupiah(int|float $angka): string
    {
        return 'Rp ' . number_format((float) $angka, 0, ',', '.');
    }

    public function isValidWANumber(string $number): bool
    {
        // Hapus semua non-digit
        $clean = preg_replace('/\D/', '', $number);

        // Nomor Indonesia: 08xxx (10-13 digit) atau 628xxx (11-14 digit)
        if (preg_match('/^08\d{8,11}$/', $clean)) {
            return true;
        }

        if (preg_match('/^628\d{8,11}$/', $clean)) {
            return true;
        }

        return false;
    }

    public function normalizeNumber(string $number): string
    {
        $clean = preg_replace('/\D/', '', $number);

        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        }

        if (! str_starts_with($clean, '62')) {
            $clean = '62' . $clean;
        }

        return $clean;
    }

    public function checkConnection(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->waServiceUrl}/health");
            return $response->successful()
                && $response->json('status') === 'connected';
        } catch (\Exception) {
            return false;
        }
    }
}
