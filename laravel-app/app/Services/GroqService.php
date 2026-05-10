<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    private string $groqApiKey;
    private string $groqBaseUrl = 'https://api.groq.com/openai/v1/chat/completions';
    private string $openaiBaseUrl = 'https://api.openai.com/v1/chat/completions';
    private string $defaultModel = 'llama-3.3-70b-versatile';

    public function __construct()
    {
        $this->groqApiKey = config('services.groq.api_key', '');
    }

    // ── Parse Intent ──────────────────────────────────────────────

    public function parseIntent(string $pesan, array $konteks, int $shopId): array
    {
        $systemPrompt = $this->buildIntentSystemPrompt();

        $userPrompt = "Pesan dari pemilik toko (shop_id: {$shopId}):\n\"{$pesan}\"\n\n"
            . "Konteks percakapan sebelumnya:\n" . json_encode($konteks, JSON_UNESCAPED_UNICODE);

        $result = $this->callWithRetry([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ]);

        $parsed = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! isset($parsed['intent'])) {
            Log::warning('GroqService: parseIntent gagal parse JSON', ['raw' => $result]);
            return [
                'intent'     => 'tidak_dikenali',
                'confidence' => 0,
                'entities'   => [],
                'raw'        => $result,
            ];
        }

        return $parsed;
    }

    private function buildIntentSystemPrompt(): string
    {
        $intents = implode(', ', [
            'tambah_produk', 'edit_produk', 'hapus_produk',
            'tambah_stok', 'cek_stok', 'update_stok',
            'lihat_pesanan', 'konfirmasi_pesanan', 'tolak_pesanan',
            'lihat_laporan', 'cek_keuangan', 'update_harga',
            'buat_konten', 'tutup_toko', 'buka_toko',
            'setting_toko', 'lihat_stok_kritis', 'tidak_dikenali',
        ]);

        return <<<PROMPT
Kamu adalah sistem klasifikasi intent untuk platform toko online UMKM Indonesia.
Tugasmu HANYA mengembalikan JSON valid — tidak ada teks lain, tidak ada penjelasan.

INTENT YANG VALID: {$intents}

ATURAN:
1. Tangani bahasa Indonesia formal, informal, gaul, campur bahasa daerah (Jawa, Sunda, dll), dan typo umum.
2. Contoh variasi:
   - "tambahin stok kopi" → tambah_stok
   - "stok abis nih" → cek_stok atau tambah_stok (cek konteks)
   - "ada orderan masuk" → lihat_pesanan
   - "orderan dari budi di konfirmasi aja" → konfirmasi_pesanan
   - "bikin caption buat produk baru" → buat_konten
   - "tutup dulu ya" → tutup_toko
   - "buka lagi jam 9" → buka_toko
   - "berapa untung bulan ini" → cek_keuangan
3. Ekstrak entities relevan: nama produk, jumlah, nama pembeli, tanggal, harga, dll.
4. Berikan confidence score 0.0 - 1.0.
5. Jika tidak yakin, gunakan "tidak_dikenali" dengan confidence rendah.

FORMAT RESPONSE (JSON saja):
{
  "intent": "nama_intent",
  "confidence": 0.95,
  "entities": {
    "nama_produk": "...",
    "jumlah": 10,
    "nama_pembeli": "...",
    "harga": 50000,
    "tanggal": "..."
  },
  "pesan_asli": "...",
  "catatan": "penjelasan singkat jika diperlukan"
}
PROMPT;
    }

    // ── Generate Deskripsi Produk ─────────────────────────────────

    public function generateDeskripsiProduk(array $infoProduk): string
    {
        $prompt = "Buat deskripsi produk yang menarik dan informatif untuk:\n"
            . json_encode($infoProduk, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nBuat dalam 2-3 kalimat, bahasa Indonesia yang natural, "
            . "tonjolkan keunggulan produk. Langsung tulis deskripsinya tanpa pengantar.";

        return $this->callWithRetry([
            ['role' => 'system', 'content' => 'Kamu adalah copywriter produk UMKM Indonesia yang handal.'],
            ['role' => 'user',   'content' => $prompt],
        ]);
    }

    // ── Generate Caption ──────────────────────────────────────────

    public function generateCaption(array $produk, string $tema, array $preferensi): string
    {
        $prefContext = "Gaya bahasa: {$preferensi['gaya_bahasa']}. "
            . "Emoji: {$preferensi['emoji_preference']}. "
            . "Panjang: {$preferensi['panjang_konten']}.";

        $prompt = "Buat caption media sosial untuk produk UMKM:\n"
            . "Produk: " . json_encode($produk, JSON_UNESCAPED_UNICODE) . "\n"
            . "Tema: {$tema}\n"
            . "Preferensi konten: {$prefContext}\n\n"
            . "Sertakan hashtag relevan di akhir. Langsung tulis captionnya.";

        return $this->callWithRetry([
            ['role' => 'system', 'content' => 'Kamu adalah social media specialist untuk UMKM Indonesia.'],
            ['role' => 'user',   'content' => $prompt],
        ]);
    }

    // ── Generate Draft Balasan Komplain ───────────────────────────

    public function generateDraftBalasan(array $komplain): array
    {
        $systemPrompt = <<<PROMPT
Kamu adalah customer service profesional untuk UMKM Indonesia.
Kembalikan HANYA JSON valid tanpa teks lain.

FORMAT:
{
  "ringkasan": "ringkasan singkat komplain dalam 1 kalimat",
  "tipe": "rusak|telat|salah_item|kualitas|lainnya",
  "urgensi": "tinggi|sedang|rendah",
  "draft": "draft balasan yang empatis, profesional, dan solutif dalam bahasa Indonesia"
}
PROMPT;

        $userPrompt = "Komplain pelanggan: \"" . ($komplain['pesan'] ?? '') . "\"\n"
            . "Nama pembeli: " . ($komplain['buyer_name'] ?? 'Pelanggan') . "\n"
            . "Produk terkait: " . ($komplain['produk'] ?? '-');

        $result = $this->callWithRetry([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ]);

        $parsed = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'ringkasan' => 'Tidak bisa memproses komplain',
                'tipe'      => 'lainnya',
                'urgensi'   => 'sedang',
                'draft'     => 'Terima kasih atas masukannya. Tim kami akan segera menindaklanjuti.',
            ];
        }

        return $parsed;
    }

    // ── Generate Saran Harga ──────────────────────────────────────

    public function generateSaranHarga(float $hpp, string $kategori): array
    {
        $systemPrompt = 'Kamu adalah konsultan bisnis UMKM Indonesia. '
            . 'Kembalikan HANYA JSON valid.';

        $userPrompt = "HPP (Harga Pokok Produksi): Rp " . number_format($hpp, 0, ',', '.')
            . "\nKategori produk: {$kategori}\n\n"
            . "Berikan saran harga jual yang kompetitif. FORMAT JSON:\n"
            . '{"harga_saran": 50000, "margin_persen": 30, "alasan": "...", '
            . '"rentang_harga": {"min": 45000, "max": 55000}}';

        $result = $this->callWithRetry([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ]);

        $parsed = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $hargaSaran = round($hpp * 1.3 / 1000) * 1000;
            return [
                'harga_saran'   => $hargaSaran,
                'margin_persen' => 30,
                'alasan'        => 'Harga dihitung dengan margin standar 30%.',
                'rentang_harga' => [
                    'min' => round($hpp * 1.2 / 1000) * 1000,
                    'max' => round($hpp * 1.4 / 1000) * 1000,
                ],
            ];
        }

        return $parsed;
    }

    // ── Core API Call ─────────────────────────────────────────────

    public function callWithRetry(array $messages, int $maxRetry = 3): string
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
            try {
                $response = Http::withToken($this->groqApiKey)
                    ->timeout(30)
                    ->post($this->groqBaseUrl, [
                        'model'       => $this->defaultModel,
                        'messages'    => $messages,
                        'temperature' => 0.3,
                        'max_tokens'  => 1024,
                    ]);

                if ($response->successful()) {
                    return trim($response->json('choices.0.message.content') ?? '');
                }

                $lastError = "HTTP {$response->status()}: " . $response->body();
                Log::warning("GroqService attempt {$attempt} gagal", ['error' => $lastError]);

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::warning("GroqService attempt {$attempt} exception", ['error' => $lastError]);
            }

            if ($attempt < $maxRetry) {
                sleep($attempt); // backoff: 1s, 2s
            }
        }

        Log::error('GroqService: semua retry gagal, fallback ke OpenAI', ['error' => $lastError]);
        return $this->fallbackToOpenAI($messages);
    }

    // ── OpenAI Fallback ───────────────────────────────────────────

    public function fallbackToOpenAI(array $messages): string
    {
        $apiKey = config('services.openai.api_key', '');

        if (empty($apiKey)) {
            Log::error('GroqService fallback: OPENAI_API_KEY tidak dikonfigurasi');
            return '';
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post($this->openaiBaseUrl, [
                    'model'       => 'gpt-4o-mini',
                    'messages'    => $messages,
                    'temperature' => 0.3,
                    'max_tokens'  => 1024,
                ]);

            if ($response->successful()) {
                return trim($response->json('choices.0.message.content') ?? '');
            }

            Log::error('GroqService OpenAI fallback gagal', ['status' => $response->status()]);
        } catch (\Exception $e) {
            Log::error('GroqService OpenAI fallback exception', ['error' => $e->getMessage()]);
        }

        return '';
    }
}
