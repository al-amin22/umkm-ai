# WhatsApp AI Flow
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19

---

## 1. WhatsApp Integration Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                    WhatsApp Business API                              │
│                   (Meta / Official Provider)                          │
└──────────────────────────┬───────────────────────────────────────────┘
                           │ Webhook (POST)
                           │ X-Hub-Signature-256
                           ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    WA Gateway Service                                 │
│                                                                      │
│  WebhookController.receiveMessage()                                  │
│      │                                                               │
│      ├── validateSignature() ──── REJECT if invalid                 │
│      ├── parseMessage()                                              │
│      ├── identifyTenant() ──────── by wa_number                     │
│      └── enqueueForProcessing()                                      │
│                                                                      │
│  [Queue: wa-inbound]                                                 │
└──────────────────────────┬───────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    AI Processing Worker                               │
│                                                                      │
│  InboundMessageProcessor.process(job)                                │
│      │                                                               │
│      ├── loadTenantContext()                                         │
│      ├── loadOrCreateSession()                                       │
│      ├── sendToAiOrchestrator()                                      │
│      └── enqueueResponse()                                           │
│                                                                      │
│  [Queue: wa-outbound]                                                │
└──────────────────────────┬───────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    Outbound Message Processor                         │
│                                                                      │
│  OutboundMessageProcessor.process(job)                               │
│      │                                                               │
│      ├── formatMessage()                                             │
│      ├── sendViaWAAPI()                                              │
│      └── logDelivery()                                               │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 2. Message Flow Detail

### 2.1 Incoming Message Processing

```typescript
// Step 1: Webhook Reception
class WebhookController {
  @Post('whatsapp')
  async receiveWebhook(
    @Headers('x-hub-signature-256') signature: string,
    @Body() payload: WAWebhookPayload,
    @RawBody() rawBody: Buffer,
  ): Promise<{ success: boolean }> {
    // CRITICAL: Always respond 200 immediately
    // Process asynchronously via queue
    
    this.webhookService.validateSignature(rawBody, signature);
    const messages = this.webhookService.parseMessages(payload);
    
    for (const msg of messages) {
      await this.inboundQueue.add('process-message', msg, {
        priority: 10,
        attempts: 3,
      });
    }
    
    return { success: true };
  }
}
```

```typescript
// Step 2: Message Processing Worker
class InboundMessageProcessor {
  async process(job: Job<WAMessage>): Promise<void> {
    const { from, text, type, waId, timestamp } = job.data;
    
    // 1. Find tenant by WA number
    const shop = await this.shopRepo.findByWaNumber(from);
    if (!shop) {
      // Unknown number — optionally send onboarding message
      await this.sendUnknownNumberResponse(from);
      return;
    }
    
    // 2. Load or create session
    const session = await this.sessionService.getOrCreate(from, shop);
    
    // 3. Check if session is in multi-step flow
    if (session.pendingFlow) {
      await this.handlePendingFlow(session, text, shop);
      return;
    }
    
    // 4. Send to AI Orchestrator
    const aiResponse = await this.aiOrchestrator.processMessage(
      text, 
      session, 
      shop.tenant,
    );
    
    // 5. Queue outbound response
    await this.outboundQueue.add('send-message', {
      to: from,
      messages: aiResponse.messages,
      shopId: shop.id,
    });
    
    // 6. Update session context
    await this.sessionService.updateContext(session.id, {
      lastMessage: text,
      lastResponse: aiResponse.text,
      lastIntent: aiResponse.intent,
    });
  }
}
```

---

## 3. Message Type Handling

### 3.1 Supported Message Types

| Type | Handling |
|------|---------|
| `text` | Direct AI processing |
| `image` | Extract + describe via vision AI, then process |
| `document` | Extract text/data, then process |
| `audio` | Transcribe via Whisper/STT, then process |
| `location` | Extract coordinates, use for delivery context |
| `interactive` (button reply) | Map to predefined action |
| `template` | Track delivery status |

### 3.2 Image Processing
```typescript
async handleImageMessage(image: WAImage, session: WASession): Promise<string> {
  // Download image from WA
  const imageBuffer = await this.waClient.downloadMedia(image.id);
  
  // Store in MinIO temporarily
  const tempUrl = await this.storage.uploadTemp(imageBuffer, 'jpg');
  
  // Send to vision AI for description
  const description = await this.openClaw.describeImage(tempUrl, {
    context: 'UMKM product or business document',
    language: 'Indonesian',
  });
  
  // Process as text
  return this.processTextMessage(description, session);
}
```

---

## 4. Session Management

### 4.1 Session State Machine

```
┌─────────────┐
│    NEW      │ ← First message from unknown number
└──────┬──────┘
       │ register/connect
┌──────▼──────┐
│   ACTIVE    │ ← Normal conversation state
└──────┬──────┘
       │ start complex flow
┌──────▼──────┐
│  IN_FLOW    │ ← Multi-step flow (e.g., create order step by step)
└──────┬──────┘
       │ complete/cancel
┌──────▼──────┐
│   ACTIVE    │
└──────┬──────┘
       │ 24h inactivity
┌──────▼──────┐
│   EXPIRED   │
└─────────────┘
```

### 4.2 Session Data Structure
```typescript
interface WASession {
  id: string;
  waNumber: string;
  tenantId: string;
  shopId: string;
  
  // Conversation context
  conversationHistory: Message[];   // Last 10 messages
  currentIntent?: string;
  
  // Multi-step flow state
  pendingFlow?: {
    type: 'create_order' | 'create_product' | 'generate_report';
    step: number;
    data: Record<string, unknown>;
    startedAt: Date;
  };
  
  // User preferences
  language: 'id' | 'en';
  timezone: string;
  
  // Metadata
  lastActivityAt: Date;
  expiresAt: Date;
  createdAt: Date;
}
```

---

## 5. Multi-Step Flow Examples

### 5.1 Order Creation Flow

```
User: "Budi mau order beras 2 kg sama gula 1 kg"
AI:   "Sip! Mau konfirmasi dulu nih:
       1️⃣ Beras Premium — 2 kg — Rp 24.000
       2️⃣ Gula Pasir — 1 kg — Rp 15.000
       Total: Rp 39.000
       
       Atas nama siapa dan nomor HP-nya? 😊"

User: "Atas nama Pak Budi, 0812-3456-7890"
AI:   "Pesanan sudah dicatat! ✅
       
       📋 No. Order: ORD-20260519-001
       👤 Pak Budi — 0812-3456-7890
       📦 Beras 2kg + Gula 1kg
       💰 Total: Rp 39.000
       
       Invoice: [link]
       
       Mau bayar via apa, Pak? (Transfer/QRIS/Tunai)"

User: "Transfer BCA"
AI:   "Transfer ke rekening:
       🏦 BCA: 1234567890 a.n. Toko Budi
       
       Total: Rp 39.000
       Konfirmasi setelah transfer ya! 🙏"
```

### 5.2 Stock Update Flow

```
User: "Update stok, beras tinggal 30 kg"
AI:   "Oke, stok Beras Premium diupdate ke 30 kg ✅
       
       ⚠️ Stok hampir kritis nih (minimum: 20 kg).
       Mau saya ingatkan kalau tinggal 20 kg lagi?"

User: "Iya"
AI:   "Siap! Alert stok sudah diaktifkan 🔔
       Nanti saya kabari kalau beras tinggal ≤ 20 kg."
```

### 5.3 Report Flow

```
User: "Laporan penjualan hari ini"
AI:   "📊 Laporan Penjualan — Senin, 19 Mei 2026
       
       💰 Total Penjualan: Rp 2.350.000
       📦 Jumlah Order: 8 pesanan
       👥 Pelanggan: 6 orang
       🏆 Produk Terlaris: Beras Premium (15 kg)
       
       Trend vs kemarin: ↑ 15% 🎉
       
       Mau lihat detail atau grafik di dashboard?"
```

---

## 6. WA Message Templates

### 6.1 Order Confirmation Template
```
Halo, {{customer_name}}! 👋

Pesanan Anda sudah dikonfirmasi ya! 🎉

📋 *No. Order: {{order_number}}*
🏪 {{shop_name}}

📦 *Detail Pesanan:*
{{#each items}}
• {{name}} x{{quantity}} = Rp {{total_price}}
{{/each}}

💰 *Total: Rp {{total_amount}}*

{{#if payment_method}}
💳 Metode Pembayaran: {{payment_method}}
{{/if}}

Terima kasih sudah belanja! 🙏
```

### 6.2 Stock Alert Template
```
⚠️ *Alert Stok Kritis!*

Produk *{{product_name}}* stoknya hampir habis.

📦 Stok tersisa: {{quantity}} {{unit}}
⛔ Minimum stok: {{minimum_stock}} {{unit}}

Segera lakukan restock ya! 🚨

_Pesan ini dikirim otomatis oleh sistem UMKM AI Platform._
```

### 6.3 Payment Reminder Template
```
Halo, {{customer_name}}! 

Mengingatkan bahwa pesanan Anda belum lunas:

📋 No. Order: {{order_number}}
💰 Sisa tagihan: Rp {{remaining_amount}}

Mohon segera lakukan pembayaran. 
Transfer ke: {{payment_info}}

Terima kasih! 🙏
_{{shop_name}}_
```

---

## 7. Rate Limiting & Anti-Abuse

### 7.1 Per-Number Limits
```typescript
const rateLimits = {
  messagesPerMinute: 10,      // Per WA number
  messagesPerHour: 100,       // Per WA number
  aiCallsPerMinute: 5,        // AI processing calls
  broadcastPerDay: 1000,      // Total broadcast messages per shop
};
```

### 7.2 Anti-Spam Detection
```typescript
async function isSpam(waNumber: string, message: string): Promise<boolean> {
  // Check message frequency
  const recentCount = await redis.incr(`spam:${waNumber}:count`);
  if (recentCount === 1) await redis.expire(`spam:${waNumber}:count`, 60);
  
  if (recentCount > 20) return true;  // > 20 messages per minute
  
  // Check for known spam patterns
  const spamPatterns = [/buy (followers|likes)/i, /casino/i];
  return spamPatterns.some(p => p.test(message));
}
```

---

## 8. Error Handling in WA Flow

### 8.1 AI Unavailable
```
User: "Buat laporan penjualan"

[AI Service Down]

Response: "Maaf, asisten AI sedang tidak tersedia sementara. 😔
           
           Untuk saat ini, Anda bisa:
           • Lihat laporan di dashboard: dashboard.umkmplatform.id
           • Atau coba lagi dalam beberapa menit.
           
           Kami sedang memperbaiki masalah ini. 🙏"
```

### 8.2 Unknown Intent
```
User: "xyz 123 abc"

Response: "Hmm, saya kurang paham maksudnya. 🤔

           Coba ketik salah satu perintah ini:
           • *cek stok* — Lihat stok produk
           • *catat pesanan* — Buat pesanan baru
           • *laporan* — Lihat laporan penjualan
           • *konten* — Buat caption/promo
           
           Atau ketik *bantuan* untuk daftar lengkap perintah. 📋"
```

### 8.3 Permission Error
```
User: [staff account] "Hapus semua produk"

Response: "Maaf, Anda tidak punya akses untuk melakukan itu. 🔒
           
           Hubungi pemilik toko untuk mendapatkan izin. 😊"
```

---

## 9. Monitoring WA Flow

### 9.1 Key Metrics
```
wa_webhook_received_total         (counter)
wa_message_processed_total        (counter, labels: status)
wa_message_processing_duration    (histogram)
wa_queue_depth                    (gauge)
wa_ai_response_time               (histogram)
wa_outbound_sent_total            (counter, labels: status)
wa_session_active_count           (gauge)
```

### 9.2 Alerting Rules
```yaml
- alert: WAMessageProcessingHigh
  expr: wa_message_processing_duration_p95 > 10000
  message: "WA message processing > 10s (P95)"

- alert: WAQueueDepthHigh
  expr: wa_queue_depth > 500
  message: "WA inbound queue depth > 500 messages"

- alert: WAOutboundFailureHigh
  expr: rate(wa_outbound_sent_total{status="failed"}[5m]) > 0.1
  message: "WA outbound failure rate > 10%"
```

---

*WA Flow Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
