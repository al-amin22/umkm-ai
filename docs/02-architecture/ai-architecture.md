# AI Architecture & OpenClaw Integration
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19

---

## 1. AI System Overview

UMKM AI Platform menggunakan **OpenClaw** sebagai AI Orchestration Engine dengan arsitektur yang terinspirasi dari **LangGraph** dan **ReAct (Reasoning + Acting)** pattern. AI berperan sebagai "AI Operator" yang dapat memahami instruksi bisnis, merencanakan task, memilih tools, dan mengeksekusi workflow secara otomatis.

---

## 2. OpenClaw Architecture

### 2.1 Core Components

```
┌────────────────────────────────────────────────────────────────────┐
│                      OpenClaw Engine                               │
│                                                                    │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │                   INPUT LAYER                                │ │
│  │                                                              │ │
│  │  User Message → Preprocessor → Context Injector             │ │
│  └──────────────────────────────┬───────────────────────────── ┘ │
│                                 │                                  │
│  ┌──────────────────────────────▼───────────────────────────────┐ │
│  │                   REASONING LAYER                            │ │
│  │                                                              │ │
│  │  Intent Detector → Task Planner → Tool Selector             │ │
│  │       │                │               │                    │ │
│  │  [LLM Call]      [Strategy]      [Registry Lookup]         │ │
│  └──────────────────────────────┬───────────────────────────── ┘ │
│                                 │                                  │
│  ┌──────────────────────────────▼───────────────────────────────┐ │
│  │                   EXECUTION LAYER                            │ │
│  │                                                              │ │
│  │  Tool Executor ──► Tool Registry ──► Domain Services        │ │
│  │       │                                      │              │ │
│  │  [Parallel/Sequential]              [DB, Queue, API]        │ │
│  └──────────────────────────────┬───────────────────────────── ┘ │
│                                 │                                  │
│  ┌──────────────────────────────▼───────────────────────────────┐ │
│  │                   MEMORY LAYER                               │ │
│  │                                                              │ │
│  │  Working Memory │ Episodic Memory │ Semantic Memory          │ │
│  │  (Redis)        │ (PostgreSQL)    │ (PostgreSQL + Vector)   │ │
│  └──────────────────────────────┬───────────────────────────── ┘ │
│                                 │                                  │
│  ┌──────────────────────────────▼───────────────────────────────┐ │
│  │                   OUTPUT LAYER                               │ │
│  │                                                              │ │
│  │  Response Generator → Formatter → Channel Adapter           │ │
│  └──────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────┘
```

### 2.2 AI Processing Flow (ReAct Pattern)

```
User Input
    │
    ▼
[OBSERVE] — Parse message, load context, retrieve memory
    │
    ▼
[THINK] — Detect intent, plan tasks, select tools
    │
    ▼
[ACT] — Execute tools, update state
    │
    ▼
[REFLECT] — Evaluate results, check if goal achieved
    │
    ├── Goal achieved? → Generate response → [RESPOND]
    │
    └── Need more action? → [THINK] (loop, max 5 iterations)
```

---

## 3. Intent Detection System

### 3.1 Intent Taxonomy

```typescript
enum IntentCategory {
  // Inventory
  STOCK_CHECK = 'stock.check',
  STOCK_UPDATE = 'stock.update',
  STOCK_ALERT_CONFIG = 'stock.alert_config',
  
  // Orders
  ORDER_CREATE = 'order.create',
  ORDER_STATUS = 'order.status',
  ORDER_UPDATE = 'order.update',
  ORDER_CANCEL = 'order.cancel',
  
  // Finance
  FINANCE_RECORD = 'finance.record',
  FINANCE_REPORT = 'finance.report',
  FINANCE_DEBT_RECEIVABLE = 'finance.debt_receivable',
  
  // Marketing
  CONTENT_GENERATE_CAPTION = 'content.generate_caption',
  CONTENT_GENERATE_COPY = 'content.generate_copy',
  CONTENT_BROADCAST = 'content.broadcast',
  
  // Analytics
  ANALYTICS_SALES = 'analytics.sales',
  ANALYTICS_CUSTOMER = 'analytics.customer',
  ANALYTICS_RECOMMENDATION = 'analytics.recommendation',
  
  // Customer
  CUSTOMER_LOOKUP = 'customer.lookup',
  CUSTOMER_UPDATE = 'customer.update',
  CUSTOMER_FOLLOWUP = 'customer.followup',
  
  // System
  SYSTEM_HELP = 'system.help',
  SYSTEM_SETTINGS = 'system.settings',
  
  // Unknown
  UNKNOWN = 'unknown',
}

interface DetectedIntent {
  category: IntentCategory;
  confidence: number;       // 0.0 - 1.0
  entities: Entity[];       // Extracted entities
  clarificationNeeded: boolean;
  clarificationQuestion?: string;
}
```

### 3.2 Entity Extraction

AI harus extract entitas dari pesan:

| Entity Type | Examples |
|-------------|---------|
| PRODUCT | "beras", "minyak goreng", "bakso" |
| QUANTITY | "5 kg", "2 lusin", "selusin" |
| CUSTOMER | "Pak Budi", "Bu Sari", "pelanggan nomor 1" |
| TIME | "hari ini", "kemarin", "bulan ini", "minggu lalu" |
| MONEY | "100 ribu", "Rp 50.000", "setengah juta" |
| STATUS | "pending", "sudah bayar", "belum dikirim" |
| ACTION | "buat", "cek", "update", "hapus", "laporan" |

---

## 4. Tool Registry System

### 4.1 Tool Definition Standard

```typescript
interface ToolDefinition {
  name: string;                    // Unique identifier: "get_stock_level"
  displayName: string;             // Human-readable: "Cek Level Stok"
  description: string;             // For AI to understand when to use this tool
  category: ToolCategory;
  parameters: {
    type: 'object';
    properties: Record<string, JSONSchemaProperty>;
    required: string[];
  };
  handler: ToolHandler;
  timeout: number;                 // Default: 30000ms
  maxRetries: number;              // Default: 3
  requiresTenantContext: boolean;
  requiresShopContext: boolean;
  permissions: Permission[];       // Required permissions to use this tool
  examples: ToolExample[];         // Examples for AI to learn usage
}

type ToolHandler = (
  params: Record<string, unknown>,
  context: AIContext,
) => Promise<ToolResult>;

interface ToolResult {
  success: boolean;
  data?: unknown;
  error?: string;
  message?: string;               // Human-readable result for AI response
}
```

### 4.2 Registered Tools

#### Inventory Tools
```typescript
tools = [
  {
    name: 'get_stock_level',
    description: 'Cek level stok produk. Gunakan saat user tanya stok tersisa.',
    parameters: {
      product_name: { type: 'string', description: 'Nama produk' },
      product_id: { type: 'string', description: 'ID produk (opsional)' },
    }
  },
  {
    name: 'update_stock',
    description: 'Update/ubah jumlah stok produk. Gunakan saat user lapor stok baru.',
    parameters: {
      product_name: { type: 'string' },
      quantity: { type: 'number', description: 'Jumlah stok baru ABSOLUT atau perubahan (+ / -)' },
      mutation_type: { enum: ['SET', 'ADD', 'SUBTRACT'] },
      reason: { type: 'string' },
    }
  },
  {
    name: 'get_low_stock_products',
    description: 'Dapatkan daftar produk yang stoknya hampir habis / di bawah minimum.',
    parameters: {}
  },
]
```

#### Order Tools
```typescript
tools = [
  {
    name: 'create_order',
    description: 'Buat pesanan baru. Gunakan saat ada pelanggan yang memesan.',
    parameters: {
      customer_name: { type: 'string' },
      customer_phone: { type: 'string' },
      items: {
        type: 'array',
        items: {
          product_name: { type: 'string' },
          quantity: { type: 'number' },
          unit_price: { type: 'number' },
        }
      },
      notes: { type: 'string' },
    }
  },
  {
    name: 'get_order_status',
    description: 'Cek status pesanan berdasarkan nomor order atau nama pelanggan.',
    parameters: {
      order_number: { type: 'string' },
      customer_name: { type: 'string' },
    }
  },
  {
    name: 'update_order_status',
    description: 'Update status pesanan (konfirmasi, kirim, selesai, batalkan).',
    parameters: {
      order_id: { type: 'string' },
      new_status: { enum: ['CONFIRMED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'COMPLETED', 'CANCELLED'] },
      notes: { type: 'string' },
    }
  },
]
```

#### Finance Tools
```typescript
tools = [
  {
    name: 'record_transaction',
    description: 'Catat transaksi keuangan (pemasukan atau pengeluaran).',
    parameters: {
      type: { enum: ['INCOME', 'EXPENSE'] },
      amount: { type: 'number' },
      category: { type: 'string' },
      description: { type: 'string' },
      date: { type: 'string', format: 'date' },
    }
  },
  {
    name: 'get_finance_summary',
    description: 'Dapatkan ringkasan keuangan untuk periode tertentu.',
    parameters: {
      period: { enum: ['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'custom'] },
      start_date: { type: 'string' },
      end_date: { type: 'string' },
    }
  },
]
```

#### Content Tools
```typescript
tools = [
  {
    name: 'generate_caption',
    description: 'Buat caption media sosial untuk produk atau promo.',
    parameters: {
      product_name: { type: 'string' },
      promo_details: { type: 'string' },
      platform: { enum: ['instagram', 'facebook', 'whatsapp', 'tiktok'] },
      tone: { enum: ['formal', 'casual', 'energetic', 'informative'] },
    }
  },
  {
    name: 'generate_broadcast_message',
    description: 'Buat pesan broadcast WhatsApp untuk pelanggan.',
    parameters: {
      purpose: { type: 'string', description: 'Tujuan broadcast (promo, pengumuman, dll)' },
      target_segment: { type: 'string' },
    }
  },
]
```

---

## 5. Memory System Architecture

### 5.1 Memory Types

```
┌──────────────────────────────────────────────────────────┐
│                    MEMORY HIERARCHY                       │
│                                                          │
│  ┌──────────────────────────────────────────────────┐   │
│  │  WORKING MEMORY (Redis — TTL: session duration)  │   │
│  │                                                  │   │
│  │  • Current conversation messages (last 10)       │   │
│  │  • Active task state                             │   │
│  │  • Pending clarifications                        │   │
│  │  • Tool execution results                        │   │
│  └──────────────────────────────────────────────────┘   │
│                         │                                │
│  ┌──────────────────────▼───────────────────────────┐   │
│  │  EPISODIC MEMORY (PostgreSQL — TTL: 90 hari)     │   │
│  │                                                  │   │
│  │  • Conversation summaries                        │   │
│  │  • Important events ("Budi beli beras tiap Senin")│  │
│  │  • Error patterns yang pernah terjadi            │   │
│  └──────────────────────────────────────────────────┘   │
│                         │                                │
│  ┌──────────────────────▼───────────────────────────┐   │
│  │  SEMANTIC MEMORY (PostgreSQL — Permanent)        │   │
│  │                                                  │   │
│  │  • Profil bisnis tenant                          │   │
│  │  • Preferensi user                               │   │
│  │  • Business rules custom                         │   │
│  │  • FAQ bisnis                                    │   │
│  │  • SOP bisnis                                    │   │
│  └──────────────────────────────────────────────────┘   │
│                         │                                │
│  ┌──────────────────────▼───────────────────────────┐   │
│  │  PROCEDURAL MEMORY (PostgreSQL — Permanent)      │   │
│  │                                                  │   │
│  │  • Pola behavior user                            │   │
│  │  • Workflow yang sering digunakan                │   │
│  │  • Shortcut commands custom                      │   │
│  └──────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

### 5.2 Memory Schema

```sql
-- AI Memory Table
CREATE TABLE ai_memories (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   UUID NOT NULL REFERENCES tenants(id),
  shop_id     UUID REFERENCES shops(id),
  user_wa_number VARCHAR(20),
  memory_type VARCHAR(20) NOT NULL, -- 'episodic', 'semantic', 'procedural'
  key         VARCHAR(255) NOT NULL,
  content     JSONB NOT NULL,
  importance  FLOAT DEFAULT 0.5,    -- 0.0 - 1.0
  access_count INTEGER DEFAULT 0,
  last_accessed_at TIMESTAMPTZ,
  expires_at  TIMESTAMPTZ,
  created_at  TIMESTAMPTZ DEFAULT NOW(),
  updated_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_memories_tenant_type ON ai_memories(tenant_id, memory_type);
CREATE INDEX idx_memories_key ON ai_memories(key);
```

### 5.3 Context Injection Flow

```typescript
async function buildContext(
  message: string,
  session: WASession,
  tenant: Tenant,
): Promise<AIContext> {
  const [
    workingMemory,
    episodicMemory,
    semanticMemory,
    businessProfile,
  ] = await Promise.all([
    redis.get(`memory:working:${session.id}`),
    memoryRepo.getRecentEpisodic(tenant.id, session.waNumber, 5),
    memoryRepo.getSemanticContext(tenant.id),
    tenantRepo.getBusinessProfile(tenant.id),
  ]);

  return {
    currentMessage: message,
    conversationHistory: workingMemory?.messages ?? [],
    recentEvents: episodicMemory,
    businessContext: semanticMemory,
    profile: businessProfile,
    currentTime: new Date(),
    currentShop: session.currentShop,
  };
}
```

---

## 6. AI Workflow Engine

### 6.1 Workflow Types

**Sequential Workflow:**
```
Task 1 → Task 2 → Task 3 → Result
(setiap task bergantung pada hasil task sebelumnya)
```

**Parallel Workflow:**
```
         ┌→ Task 1 ─┐
Input ──→┼→ Task 2 ─┼→ Merge → Result
         └→ Task 3 ─┘
(task dieksekusi bersamaan untuk efisiensi)
```

**Conditional Workflow:**
```
Input → Decision ──[yes]→ Task A → Result
                └──[no]─→ Task B → Result
```

### 6.2 Workflow Execution Example

**Contoh: "Ada pesanan baru dari Pak Budi, beli 2 kg beras sama 1 liter minyak"**

```
STEP 1: Intent Detection
  Intent: ORDER_CREATE
  Entities: 
    - customer: "Pak Budi"
    - items: [{product: "beras", qty: 2, unit: "kg"}, {product: "minyak", qty: 1, unit: "liter"}]
  Confidence: 0.95

STEP 2: Task Planning
  Tasks (sequential):
    1. find_customer("Pak Budi")         → customer_id (or create new)
    2. find_products(["beras", "minyak"]) → product_ids with prices
    3. check_stock(product_ids, quantities) → stock availability
    4. create_order(customer, items)      → order_id
    5. update_stock(product_ids, -quantities) → updated stock
    6. generate_invoice(order_id)         → invoice_pdf
    7. send_confirmation(customer, order) → notification

STEP 3: Execution
  [Parallel] find_customer + find_products
  [Sequential] check_stock
  [Sequential] create_order
  [Parallel] update_stock + generate_invoice
  [Sequential] send_confirmation

STEP 4: Response
  "✅ Pesanan Pak Budi berhasil dicatat!
  
  📋 Detail Pesanan:
  • Beras 2 kg — Rp 24.000
  • Minyak 1 liter — Rp 18.000
  Total: Rp 42.000
  
  No. Order: ORD-20260519-001
  Invoice sudah dibuat. 
  
  Stok beras tersisa: 48 kg
  Stok minyak tersisa: 12 liter"
```

---

## 7. Prompt Engineering

### 7.1 System Prompt Structure

```
[ROLE]
Kamu adalah AI Assistant untuk bisnis UMKM "{tenant.businessName}".
Kamu membantu pemilik bisnis mengelola operasional sehari-hari.

[BUSINESS CONTEXT]
Jenis bisnis: {businessType}
Produk utama: {mainProducts}
Jam operasional: {operatingHours}
Kebijakan bisnis: {customRules}

[MEMORY CONTEXT]
Percakapan terakhir: {recentHistory}
Event penting: {importantEvents}

[CURRENT STATE]
Waktu sekarang: {currentTime} WIB
Stok kritis: {lowStockProducts}
Pesanan pending: {pendingOrders}

[TOOLS AVAILABLE]
{toolDefinitions}

[INSTRUCTIONS]
1. Gunakan Bahasa Indonesia yang natural dan friendly
2. Jika tidak yakin, minta klarifikasi sebelum eksekusi
3. Selalu konfirmasi setelah eksekusi berhasil
4. Berikan respons yang ringkas dan actionable
5. Jika tool gagal, informasikan dengan sopan dan tawarkan alternatif
```

### 7.2 Tool Selection Prompt

```
Berdasarkan pesan user: "{message}"
Dan konteks bisnis yang tersedia, tentukan:
1. Intent utama user
2. Tools yang perlu dipanggil (berurutan atau paralel)
3. Parameter yang diperlukan untuk setiap tool

Format output:
{
  "intent": "ORDER_CREATE",
  "confidence": 0.95,
  "tasks": [
    {
      "tool": "find_customer",
      "parallel_group": 1,
      "params": { "name": "Pak Budi" }
    },
    {
      "tool": "find_products", 
      "parallel_group": 1,
      "params": { "names": ["beras", "minyak"] }
    }
  ],
  "clarification_needed": false
}
```

---

## 8. AI Safety & Guardrails

### 8.1 Input Guardrails
- Filter konten tidak pantas
- Detect injection attempts
- Validasi bahwa perintah sesuai dengan kapabilitas sistem
- Rate limit per session

### 8.2 Output Guardrails
- Tidak pernah expose data tenant lain
- Tidak pernah expose credential atau API key
- Konfirmasi sebelum aksi destruktif (hapus data, batalkan order)
- Log semua AI decisions untuk audit

### 8.3 Fallback Strategy

```
Primary: OpenClaw LLM (Full capability)
    ↓ (if timeout/error)
Secondary: Rule-based intent matching (Limited capability)
    ↓ (if no match)
Fallback: "Maaf, saya tidak mengerti. Ketik 'bantuan' untuk lihat daftar perintah."
```

---

## 9. AI Module Implementation (NestJS)

```typescript
// ai-orchestrator/ai-orchestrator.module.ts
@Module({
  imports: [
    ProductModule,
    OrderModule,
    StockModule,
    FinanceModule,
    CrmModule,
  ],
  providers: [
    AiOrchestratorService,
    IntentDetectorService,
    ToolRegistryService,
    MemoryManagerService,
    WorkflowEngineService,
    ResponseGeneratorService,
    // Tool handlers
    InventoryToolsService,
    OrderToolsService,
    FinanceToolsService,
    ContentToolsService,
    AnalyticsToolsService,
  ],
  exports: [AiOrchestratorService],
})
export class AiOrchestratorModule {}
```

```typescript
// ai-orchestrator/ai-orchestrator.service.ts
@Injectable()
export class AiOrchestratorService {
  async processMessage(
    message: string,
    session: WASession,
    tenant: Tenant,
  ): Promise<AIResponse> {
    // 1. Build context (memory + business profile)
    const context = await this.buildContext(message, session, tenant);
    
    // 2. Detect intent
    const intent = await this.intentDetector.detect(message, context);
    
    // 3. Check if clarification needed
    if (intent.clarificationNeeded) {
      return this.generateClarificationResponse(intent, context);
    }
    
    // 4. Plan workflow
    const workflow = await this.workflowEngine.plan(intent, context);
    
    // 5. Execute workflow
    const results = await this.workflowEngine.execute(workflow, context);
    
    // 6. Update memory
    await this.memoryManager.update(session, intent, results);
    
    // 7. Generate response
    return this.responseGenerator.generate(results, context);
  }
}
```

---

## 10. WhatsApp AI Flow

Lihat detail di: `/docs/06-ai-system/wa-flow.md`

---

*AI Architecture Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
