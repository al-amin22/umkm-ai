<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;

    // Alert email jika storage > 80%
    private float $alertThreshold = 0.8;

    public function __construct()
    {
        $this->cloudName = config('services.cloudinary.cloud_name', '');
        $this->apiKey    = config('services.cloudinary.api_key', '');
        $this->apiSecret = config('services.cloudinary.api_secret', '');
        $this->baseUrl   = "https://api.cloudinary.com/v1_1/{$this->cloudName}";
    }

    // ── Upload Foto ───────────────────────────────────────────────

    public function uploadFoto(string $base64OrPath, string $folder = 'products'): ?array
    {
        $timestamp = time();
        $publicId  = "{$folder}/" . uniqid('img_', true);

        $paramsToSign = [
            'folder'         => $folder,
            'public_id'      => $publicId,
            'timestamp'      => $timestamp,
            'transformation' => 'q_auto,f_auto,w_1080,h_1080,c_limit',
        ];

        $signature = $this->generateSignature($paramsToSign);

        // Deteksi apakah input adalah base64 atau path file
        $uploadData = str_starts_with($base64OrPath, '/')
            ? ['file' => file_get_contents($base64OrPath)]
            : ['file' => "data:image/jpeg;base64,{$base64OrPath}"];

        try {
            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/image/upload", array_merge($uploadData, [
                    'api_key'        => $this->apiKey,
                    'timestamp'      => $timestamp,
                    'signature'      => $signature,
                    'folder'         => $folder,
                    'public_id'      => $publicId,
                    'transformation' => 'q_auto,f_auto,w_1080,h_1080,c_limit',
                ]));

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'url'       => $data['secure_url'],
                    'public_id' => $data['public_id'],
                    'width'     => $data['width'],
                    'height'    => $data['height'],
                    'format'    => $data['format'],
                    'bytes'     => $data['bytes'],
                ];
            }

            Log::error('CloudinaryService: upload gagal', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('CloudinaryService: upload exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Hapus Foto ────────────────────────────────────────────────

    public function deleteFoto(string $publicId): bool
    {
        $timestamp = time();

        $paramsToSign = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $signature = $this->generateSignature($paramsToSign);

        try {
            $response = Http::timeout(15)
                ->post("{$this->baseUrl}/image/destroy", [
                    'api_key'   => $this->apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                    'public_id' => $publicId,
                ]);

            if ($response->successful() && $response->json('result') === 'ok') {
                return true;
            }

            Log::warning('CloudinaryService: delete gagal', [
                'public_id' => $publicId,
                'body'      => $response->json(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('CloudinaryService: delete exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ── Usage Stats ───────────────────────────────────────────────

    public function getUsageStats(): ?array
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->timeout(10)
                ->get("https://api.cloudinary.com/v1_1/{$this->cloudName}/usage");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'storage_used_bytes' => $data['storage']['usage']      ?? 0,
                    'storage_limit_bytes'=> $data['storage']['limit']      ?? 0,
                    'storage_persen'     => $data['storage']['used_percent'] ?? 0,
                    'bandwidth_used'     => $data['bandwidth']['usage']    ?? 0,
                    'transformations'    => $data['transformations']['usage'] ?? 0,
                    'resources'          => $data['resources']              ?? 0,
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('CloudinaryService: getUsageStats exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Alert Jika Hampir Penuh ───────────────────────────────────

    public function alertJikaHampirPenuh(): void
    {
        $stats = $this->getUsageStats();

        if (! $stats) {
            return;
        }

        $persen = ($stats['storage_persen'] ?? 0) / 100;

        if ($persen >= $this->alertThreshold) {
            $persenFormatted = number_format($persen * 100, 1);

            Log::warning('CloudinaryService: storage hampir penuh', [
                'persen' => $persenFormatted,
                'used'   => $stats['storage_used_bytes'],
                'limit'  => $stats['storage_limit_bytes'],
            ]);

            $devEmail = config('mail.from.address');
            if ($devEmail) {
                Mail::raw(
                    "⚠️ Cloudinary storage sudah {$persenFormatted}% penuh.\n"
                    . "Used: " . round($stats['storage_used_bytes'] / 1024 / 1024, 1) . " MB\n"
                    . "Limit: " . round($stats['storage_limit_bytes'] / 1024 / 1024, 1) . " MB\n"
                    . "Segera upgrade plan atau hapus asset yang tidak terpakai.",
                    fn ($mail) => $mail
                        ->to($devEmail)
                        ->subject('[UMKM AI] Cloudinary Storage Hampir Penuh')
                );
            }
        }
    }

    // ── Signature Generator ───────────────────────────────────────

    private function generateSignature(array $params): string
    {
        ksort($params);

        $paramString = collect($params)
            ->map(fn ($v, $k) => "{$k}={$v}")
            ->implode('&');

        return sha1($paramString . $this->apiSecret);
    }
}
