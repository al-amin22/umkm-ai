# Software Requirement Specification (SRS)
# UMKM AI Platform — AI Business Operating System

**Version:** 1.0.0  
**Status:** Active  
**Last Updated:** 2026-05-19  
**Classification:** Internal — Engineering Reference

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [System Overview](#2-system-overview)
3. [User Personas & Use Cases](#3-user-personas--use-cases)
4. [Functional Requirements](#4-functional-requirements)
5. [Non-Functional Requirements](#5-non-functional-requirements)
6. [Security Requirements](#6-security-requirements)
7. [AI System Requirements](#7-ai-system-requirements)
8. [API Requirements](#8-api-requirements)
9. [Database Requirements](#9-database-requirements)
10. [Infrastructure Requirements](#10-infrastructure-requirements)
11. [Integration Requirements](#11-integration-requirements)
12. [Monitoring & Observability](#12-monitoring--observability)
13. [Error Handling Strategy](#13-error-handling-strategy)
14. [Data Flow Specification](#14-data-flow-specification)
15. [User Journey Maps](#15-user-journey-maps)

---

## 1. Introduction

### 1.1 Purpose

Dokumen ini mendefinisikan secara lengkap dan formal semua persyaratan sistem untuk **UMKM AI Platform**. Dokumen ini menjadi sumber kebenaran tunggal (single source of truth) untuk semua keputusan engineering, desain, dan produk.

### 1.2 Scope

Sistem mencakup:
- **AI Orchestration Layer** menggunakan OpenClaw
- **WhatsApp AI Assistant** untuk interaksi pengguna
- **Dashboard Web** berbasis Next.js
- **Backend API** berbasis NestJS
- **Multi-tenant SaaS** infrastructure
- **Queue-based** processing untuk task berat
- **Real-time** notification system

### 1.3 Definitions & Terminology

| Term | Definition |
|------|-----------|
| Tenant | Satu unit bisnis UMKM yang mendaftar di platform |
| Shop | Satu toko milik tenant (tenant bisa punya multiple shops) |
| OpenClaw | AI orchestration engine — otak dari sistem AI |
| Tool | Fungsi spesifik yang dapat dipanggil oleh AI (e.g., `get_stock`, `create_order`) |
| Workflow | Serangkaian task yang dieksekusi secara terurut atau paralel |
| Intent | Tujuan yang dipahami AI dari perintah user |
| Agent | Instansi AI yang menjalankan task spesifik |
| Memory | Konteks dan state yang disimpan oleh AI antar sesi |
| Session | Satu sesi percakapan antara user dan AI |
| Webhook | HTTP callback untuk event real-time dari WhatsApp |

### 1.4 References

- PRD: `/docs/00-overview/PRD.md`
- Architecture: `/docs/02-architecture/`
- API Docs: `/docs/04-api/`
- ERD: `/docs/03-database/ERD.md`

---

## 2. System Overview

### 2.1 System Context

```
┌─────────────────────────────────────────────────────────────────┐
│                    EXTERNAL ACTORS                               │
│  WhatsApp User  │  Web User  │  Admin  │  External API          │
└────────┬────────┴─────┬──────┴────┬────┴────────┬───────────────┘
         │              │           │             │
┌────────▼──────────────▼───────────▼─────────────▼───────────────┐
│                    UMKM AI PLATFORM                              │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │  WA Gateway  │  │  Web/API     │  │  Admin Panel         │   │
│  │  Service     │  │  Gateway     │  │                      │   │
│  └──────┬───────┘  └──────┬───────┘  └──────────────────────┘   │
│         │                 │                                       │
│  ┌──────▼─────────────────▼──────────────────────────────────┐  │
│  │              API Gateway (NestJS)                          │  │
│  │  Auth │ Rate Limit │ Logging │ Validation │ Routing        │  │
│  └──────────────────────┬───────────────────────────────────┘  │
│                         │                                        │
│  ┌──────────────────────▼───────────────────────────────────┐   │
│  │              OpenClaw AI Orchestration                    │   │
│  │  Intent Detection → Task Planning → Tool Selection →     │   │
│  │  Execution → Memory Update → Response Generation         │   │
│  └──────┬───────────────────────────────────────────────────┘   │
│         │                                                        │
│  ┌──────▼──────────────────────────────────────────────────┐    │
│  │              Domain Services Layer                       │    │
│  │  Auth │ Product │ Order │ Stock │ Finance │ Marketing │  │    │
│  │  CRM  │ Analytics │ Workflow │ Notification │ KB       │  │    │
│  └──────┬──────────────────────────────────────────────────┘    │
│         │                                                        │
│  ┌──────▼────────────────────────────────────────────────────┐  │
│  │              Data Layer                                    │  │
│  │  PostgreSQL  │  Redis  │  MinIO  │  Queue (BullMQ)        │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Key Architectural Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Backend Framework | NestJS | TypeScript-first, modular, DI container, enterprise-ready |
| Frontend | Next.js 14+ | SSR/SSG, App Router, performance, SEO |
| Database | PostgreSQL | ACID compliance, complex queries, JSONB support |
| Cache | Redis | Fast, supports pub/sub, BullMQ queue |
| AI Engine | OpenClaw | Purpose-built orchestration, tool calling, memory |
| Architecture | Modular Monolith → Microservices | Start simple, split when needed |
| Queue | BullMQ | Redis-backed, reliable, good ecosystem |
| Storage | MinIO | S3-compatible, self-hosted option |

---

## 3. User Personas & Use Cases

### 3.1 Primary Use Cases

#### UC-001: WhatsApp AI Chat
```
Actor: UMKM Owner / Admin
Trigger: User mengirim pesan ke WhatsApp Business
Flow:
  1. User kirim pesan ke WA Business number
  2. WA Gateway menerima webhook
  3. Pesan diteruskan ke OpenClaw
  4. OpenClaw detect intent
  5. OpenClaw pilih tool yang sesuai
  6. Tool dieksekusi
  7. Hasil dikembalikan ke user via WA
Pre-condition: User sudah terdaftar dan WA session aktif
Post-condition: User mendapat respons dan task tereksekusi
Error: AI tidak mengerti → minta klarifikasi
```

#### UC-002: Cek & Update Stok
```
Actor: UMKM Owner / Staff
Trigger: "Stok beras tinggal 5 kg" atau "Cek stok produk"
Flow:
  1. AI detect intent: inventory management
  2. AI call tool: get_stock / update_stock
  3. Database diupdate
  4. Notifikasi jika stok kritis
  5. Konfirmasi ke user
```

#### UC-003: Buat Laporan Keuangan
```
Actor: UMKM Owner
Trigger: "Buat laporan bulan ini" atau "Gimana keuangan minggu ini"
Flow:
  1. AI detect intent: finance report
  2. AI query data keuangan dari database
  3. AI generate ringkasan natural language
  4. Return laporan ke user
```

#### UC-004: Generate Konten Marketing
```
Actor: UMKM Owner / Admin
Trigger: "Buat caption promo bakso"
Flow:
  1. AI detect intent: content generation
  2. AI ambil konteks bisnis dari memory
  3. AI generate caption menggunakan LLM
  4. Return konten ke user
  5. User bisa minta revisi
```

#### UC-005: Catat Pesanan Baru
```
Actor: UMKM Owner / Staff
Trigger: "Ada pesanan baru dari Budi, nasi goreng 2 porsi"
Flow:
  1. AI detect intent: create order
  2. AI extract entity: pelanggan, produk, quantity
  3. AI check stok
  4. Create order di database
  5. Kurangi stok
  6. Generate invoice otomatis
  7. Konfirmasi ke user
```

---

## 4. Functional Requirements

### 4.1 Module: Authentication & Authorization

#### FR-AUTH-001: Registrasi Tenant
- System harus mendukung registrasi tenant baru dengan nama bisnis, email, nomor WA
- System harus mengirim email verifikasi
- System harus membuat shop default saat registrasi pertama
- System harus generate API key unik per tenant

#### FR-AUTH-002: Login & Token Management
- System harus support login via email/password
- System harus generate JWT access token (15 menit) dan refresh token (30 hari)
- System harus support token rotation
- System harus blacklist token saat logout
- System harus support OAuth (Google) untuk pendaftaran mudah

#### FR-AUTH-003: RBAC (Role-Based Access Control)
- Roles: `super_admin`, `tenant_owner`, `shop_admin`, `shop_staff`, `read_only`
- Setiap role memiliki permission matrix yang terdefinisi
- Permission dapat dikonfigurasi per tenant (enterprise tier)
- System harus reject request tanpa proper permission dengan 403

#### FR-AUTH-004: Session Management
- System harus support multiple device login
- System harus bisa revoke session dari device tertentu
- System harus log semua login/logout event dengan IP dan user agent

### 4.2 Module: WhatsApp AI Assistant

#### FR-WA-001: Message Reception
- System harus menerima webhook dari WhatsApp Business API
- System harus memvalidasi signature webhook (HMAC-SHA256)
- System harus support tipe pesan: text, image, document, audio, location
- System harus queue pesan untuk diproses secara async

#### FR-WA-002: Intent Detection
- AI harus mendeteksi intent dari pesan natural language Bahasa Indonesia
- AI harus support multi-turn conversation (mengingat context sebelumnya)
- AI harus handle ambiguous intent dengan meminta klarifikasi
- AI harus support code-switching (Bahasa Indonesia + Bahasa Jawa/daerah + slang)

**Intent Categories:**
| Category | Examples |
|----------|---------|
| inventory | "cek stok", "update stok", "produk habis" |
| order | "pesanan baru", "catat order", "status pesanan" |
| finance | "laporan keuangan", "catat pemasukan", "hutang piutang" |
| marketing | "buat caption", "promo hari ini", "konten instagram" |
| analytics | "penjualan bulan ini", "produk terlaris", "performa toko" |
| customer | "data pelanggan", "riwayat beli", "pelanggan setia" |
| workflow | "otomatis balas", "reminder pelanggan", "jadwal post" |
| general | "halo", "bantuan", "cara penggunaan" |

#### FR-WA-003: Tool Execution
- AI harus dapat memanggil tools secara sequential dan parallel
- Setiap tool call harus memiliki timeout (default: 30 detik)
- Jika tool gagal, AI harus retry (max 3x) sebelum fallback
- AI harus menjelaskan apa yang sedang dikerjakan kepada user

#### FR-WA-004: Response Generation
- Respons harus dalam Bahasa Indonesia yang natural dan friendly
- Respons harus disesuaikan dengan konteks bisnis tenant
- Respons panjang harus dipecah menjadi beberapa pesan
- AI harus bisa kirim media (gambar, dokumen) sebagai respons

#### FR-WA-005: Session Management
- Setiap WhatsApp number memiliki session terpisah
- Session expire setelah 24 jam inaktivitas
- System harus dapat menangani concurrent sessions
- Context disimpan di Redis untuk akses cepat

### 4.3 Module: Product Management

#### FR-PROD-001: CRUD Produk
- User dapat membuat, membaca, update, delete produk
- Setiap produk memiliki: nama, deskripsi, harga, kategori, gambar, SKU
- SKU harus unik per shop
- System harus support produk dengan varian (ukuran, warna, dll)

#### FR-PROD-002: Kategori Produk
- User dapat membuat kategori produk custom
- Kategori support hierarki (parent-child)
- Produk dapat memiliki multiple kategori

#### FR-PROD-003: Upload Gambar Produk
- System harus support upload gambar (JPG, PNG, WebP)
- Maksimal ukuran file: 5MB per gambar
- System harus auto-compress dan generate thumbnail
- Gambar disimpan di MinIO/S3

#### FR-PROD-004: Pencarian & Filter Produk
- Search by nama, SKU, kategori
- Filter by harga, stok, kategori, status
- Sort by nama, harga, popularitas, stok

### 4.4 Module: Inventory Management

#### FR-INV-001: Stock Tracking
- System harus track stok real-time per produk per shop
- System harus support stok minimum threshold per produk
- System harus kirim alert ketika stok <= minimum threshold
- System harus support berbagai unit (kg, pcs, liter, pack, dll)

#### FR-INV-002: Stock Mutations
- Setiap perubahan stok harus tercatat dengan: tanggal, tipe, quantity, reference, user
- Tipe mutasi: `IN` (tambah stok), `OUT` (kurang stok), `ADJUSTMENT` (koreksi), `RETURN`
- Riwayat mutasi harus bisa difilter dan di-export

#### FR-INV-003: Stock Opname
- System harus support fitur stock opname (hitung fisik)
- System harus bisa generate selisih antara sistem vs fisik
- System harus membuat adjustment otomatis setelah opname diverifikasi

#### FR-INV-004: Supplier Management
- System harus bisa menyimpan data supplier
- System harus support purchase order ke supplier
- System harus track riwayat pembelian per supplier

### 4.5 Module: Order Management

#### FR-ORD-001: Create Order
- System harus support pembuatan order via WA dan Dashboard
- Order harus memiliki: nomor unik, pelanggan, items, harga, status, channel
- System harus auto-kurangi stok saat order dibuat
- System harus check ketersediaan stok sebelum order dibuat

#### FR-ORD-002: Order Status Lifecycle
```
PENDING → CONFIRMED → PROCESSING → SHIPPED → DELIVERED → COMPLETED
    ↓           ↓           ↓
CANCELLED   CANCELLED   CANCELLED
                              ↓
                          REFUNDED
```

#### FR-ORD-003: Invoice Generation
- System harus auto-generate invoice PDF saat order dikonfirmasi
- Invoice harus berisi: nomor, tanggal, detail item, subtotal, diskon, pajak, total
- Invoice dapat di-share via WhatsApp link
- Invoice dapat di-download sebagai PDF

#### FR-ORD-004: Notifikasi Pesanan
- Notifikasi ke seller saat order baru masuk
- Notifikasi ke buyer saat status berubah
- Reminder otomatis untuk order PENDING > 24 jam
- Notifikasi WA otomatis dengan template yang bisa dikustomisasi

### 4.6 Module: Finance Management

#### FR-FIN-001: Transaction Recording
- System harus support pencatatan pemasukan dan pengeluaran
- Setiap transaksi harus memiliki: tanggal, kategori, jumlah, deskripsi, reference
- System harus support kategorisasi otomatis via AI
- System harus bisa import transaksi dari CSV/Excel

#### FR-FIN-002: Hutang & Piutang
- System harus track hutang kepada supplier
- System harus track piutang dari pelanggan
- System harus kirim reminder untuk hutang/piutang jatuh tempo

#### FR-FIN-003: Laporan Keuangan
- System harus generate laporan: harian, mingguan, bulanan, custom range
- Laporan harus meliputi: total pemasukan, pengeluaran, profit/loss, cashflow
- AI harus bisa generate ringkasan natural language dari laporan
- Laporan dapat di-export ke PDF dan Excel

#### FR-FIN-004: HPP Calculator
- System harus bisa menghitung Harga Pokok Penjualan (HPP)
- AI harus bisa memberikan rekomendasi harga jual berdasarkan HPP + margin target
- System harus track biaya variabel dan tetap per produk

### 4.7 Module: CRM

#### FR-CRM-001: Customer Management
- System harus menyimpan data pelanggan: nama, kontak, alamat, catatan
- System harus auto-create pelanggan dari order baru
- System harus merge duplicate pelanggan otomatis
- System harus support custom field per tenant

#### FR-CRM-002: Customer History
- System harus track seluruh riwayat transaksi per pelanggan
- System harus hitung: total belanja, frekuensi, produk favorit, last purchase
- AI harus bisa memberikan insight per pelanggan

#### FR-CRM-003: Customer Segmentation
- System harus bisa segment pelanggan berdasarkan RFM (Recency, Frequency, Monetary)
- Segment default: Baru, Aktif, Berisiko Churn, Tidak Aktif, VIP
- User dapat membuat segment custom

#### FR-CRM-004: Follow-up Automation
- System harus bisa kirim WA otomatis untuk: ucapan ulang tahun, follow-up setelah pembelian, re-engagement pelanggan tidak aktif
- Template pesan dapat dikustomisasi dengan AI assistance

### 4.8 Module: Marketing & Content

#### FR-MKT-001: AI Caption Generator
- AI harus bisa generate caption untuk: Instagram, Facebook, WhatsApp Status, TikTok
- Caption disesuaikan dengan konteks produk dan brand voice tenant
- Support berbagai tone: formal, casual, promosi, informatif
- AI harus menyertakan hashtag yang relevan

#### FR-MKT-002: AI Copywriting
- AI harus bisa generate: headline iklan, deskripsi produk, email marketing, WA broadcast
- Support A/B variation generation
- AI harus bisa revisi berdasarkan feedback user

#### FR-MKT-003: Broadcast Management
- System harus bisa kirim WA broadcast ke segmen pelanggan
- System harus comply dengan WhatsApp policy (template approval)
- System harus track delivery rate, open rate
- System harus queue broadcast untuk avoid rate limit

#### FR-MKT-004: Content Calendar
- System harus support scheduling konten
- AI harus bisa suggest waktu terbaik untuk posting
- Integration dengan platform media sosial (fase 2)

### 4.9 Module: Analytics & Reporting

#### FR-ANA-001: Sales Analytics
- Dashboard harus menampilkan: total penjualan, jumlah order, AOV, growth rate
- Grafik trend penjualan: harian, mingguan, bulanan
- Perbandingan period (bulan ini vs bulan lalu)
- Breakdown per produk, kategori, channel

#### FR-ANA-002: Customer Analytics
- Jumlah pelanggan baru, aktif, churned per periode
- Customer acquisition by channel
- Customer lifetime value (CLV)
- Repeat purchase rate

#### FR-ANA-003: AI Business Insights
- AI harus bisa generate insight otomatis dari data bisnis
- Sistem harus bisa detect anomali (penurunan penjualan tiba-tiba)
- AI harus bisa memberikan rekomendasi aksi

#### FR-ANA-004: Report Generation
- User dapat generate laporan custom dengan filter yang fleksibel
- Laporan dapat di-export ke PDF, Excel, CSV
- Laporan dapat dijadwalkan untuk dikirim via email/WA

### 4.10 Module: Workflow Automation

#### FR-WF-001: Workflow Builder
- User dapat membuat workflow dengan trigger + action
- Trigger: waktu (cron), event (order baru, stok kritis, dll)
- Action: kirim WA, update data, generate laporan, call API
- Workflow dapat diaktifkan/nonaktifkan

#### FR-WF-002: Pre-built Workflow Templates
- Template: "Balas chat otomatis di luar jam kerja"
- Template: "Reminder pelanggan yang belum bayar"
- Template: "Notifikasi stok kritis ke pemilik"
- Template: "Laporan harian otomatis jam 9 pagi"
- Template: "Follow-up pelanggan 3 hari setelah pembelian"

#### FR-WF-003: Workflow Monitoring
- System harus log semua eksekusi workflow
- System harus alert jika workflow gagal
- User dapat melihat riwayat eksekusi dan error detail

---

## 5. Non-Functional Requirements

### 5.1 Performance Requirements

| Metric | Requirement | Measurement |
|--------|-------------|-------------|
| API Response (P50) | < 200ms | Excluding AI calls |
| API Response (P95) | < 500ms | Excluding AI calls |
| API Response (P99) | < 1000ms | Excluding AI calls |
| AI Response (P50) | < 2000ms | Including LLM inference |
| AI Response (P95) | < 5000ms | Including LLM inference |
| Dashboard FCP | < 2000ms | First Contentful Paint |
| Dashboard TTI | < 4000ms | Time to Interactive |
| DB Query (simple) | < 50ms | Single table, indexed |
| DB Query (complex) | < 500ms | Joins, aggregations |
| Queue Processing | < 5000ms | Job pickup to completion |

### 5.2 Scalability Requirements

- System harus horizontal scalable untuk semua stateless services
- Database harus support connection pooling (PgBouncer)
- Redis harus support Redis Cluster untuk scale out
- Queue harus support multiple workers
- AI calls harus support concurrent execution dengan throttling

### 5.3 Reliability Requirements

- API uptime: 99.9% SLA
- Database uptime: 99.95% SLA
- Zero data loss (RPO = 0 untuk transaksi kritis)
- RTO (Recovery Time Objective): < 30 menit
- Semua external API calls harus memiliki circuit breaker
- Retry mechanism untuk semua idempotent operations

### 5.4 Maintainability Requirements

- Test coverage: minimum 80% untuk business logic
- Cyclomatic complexity: < 10 per function
- Semua public API harus terdokumentasi (OpenAPI/Swagger)
- Breaking changes harus di-versioning dengan proper migration
- Zero-downtime deployment wajib untuk production

---

## 6. Security Requirements

### 6.1 Authentication & Authorization
- Password harus di-hash menggunakan bcrypt (cost factor 12)
- JWT access token expire dalam 15 menit
- Refresh token expire dalam 30 hari, di-rotate setiap penggunaan
- Semua endpoint kecuali auth routes harus authenticated
- RBAC harus enforced di service layer, bukan hanya di controller

### 6.2 Data Protection
- Semua data sensitif (nomor WA, data keuangan) harus encrypted at-rest
- Transport layer: TLS 1.2+ untuk semua koneksi
- Database connection harus menggunakan TLS
- Tidak ada data sensitif di logs
- PII data harus di-mask dalam API responses yang tidak perlu

### 6.3 Input Validation
- Semua input harus divalidasi di boundary (API layer)
- SQL injection prevention via parameterized queries (TypeORM)
- XSS prevention: sanitize semua user-generated content
- File upload validation: tipe, ukuran, content scanning
- Rate limiting per endpoint dan per user/IP

### 6.4 Rate Limiting Strategy

| Endpoint Type | Limit | Window |
|---------------|-------|--------|
| Auth endpoints | 10 req | 15 menit |
| API endpoints | 100 req | 1 menit |
| AI endpoints | 20 req | 1 menit |
| Upload endpoints | 10 req | 1 menit |
| Webhook endpoints | 1000 req | 1 menit |

### 6.5 Audit Logging
- Log semua: login, logout, data creation, update, deletion
- Log format: timestamp, user_id, tenant_id, action, resource, IP, user_agent
- Audit logs harus immutable (append-only)
- Audit logs retention: 1 tahun minimum
- Alert untuk suspicious activities (multiple failed logins, unusual data access)

### 6.6 OWASP Top 10 Compliance

| Threat | Mitigation |
|--------|-----------|
| Injection | TypeORM parameterized queries, input validation |
| Broken Authentication | JWT rotation, bcrypt, session management |
| Sensitive Data Exposure | Encryption at-rest, TLS, data masking |
| XML External Entities | XML parser hardening, input whitelist |
| Broken Access Control | RBAC at service layer, resource ownership check |
| Security Misconfiguration | IaC, config review, secure defaults |
| XSS | Content sanitization, CSP headers |
| Insecure Deserialization | Schema validation, class-transformer |
| Using Vulnerable Components | Dependabot, npm audit |
| Insufficient Logging | Comprehensive audit log, SIEM |

---

## 7. AI System Requirements

### 7.1 OpenClaw Integration

#### AI-001: Intent Detection
- OpenClaw harus detect intent dari pesan natural language Bahasa Indonesia
- Confidence score minimum: 0.7 untuk eksekusi otomatis
- Di bawah threshold: minta klarifikasi ke user
- Support intent chaining (satu pesan bisa trigger multiple intent)

#### AI-002: Context Management
```
Short-term Memory (Redis):
  - Active conversation context
  - Last 10 messages per session
  - Current task state
  - TTL: 24 jam

Long-term Memory (PostgreSQL):
  - Business profile tenant
  - User preferences
  - Historical patterns
  - Frequently asked questions
  - TTL: Permanent (cleanup setelah 1 tahun inaktif)
```

#### AI-003: Tool Calling System
- OpenClaw harus bisa memanggil registered tools secara dinamis
- Tool definition format:
```typescript
interface Tool {
  name: string;
  description: string;
  parameters: JSONSchema;
  handler: (params: any, context: AIContext) => Promise<ToolResult>;
  timeout: number; // milliseconds
  retries: number;
  requiresAuth: boolean;
}
```

#### AI-004: Workflow Execution
- AI harus bisa execute sequential workflow
- AI harus bisa execute parallel tasks
- AI harus handle partial failures dalam workflow
- AI harus bisa rollback pada critical failures

#### AI-005: AI Memory System
```
Memory Types:
1. Episodic Memory: "Tadi user tanya tentang stok beras"
2. Semantic Memory: "Toko ini jual sembako dan minuman"  
3. Procedural Memory: "User biasa update stok setiap senin pagi"
4. Working Memory: Current conversation state
```

### 7.2 AI Safety Requirements
- AI tidak boleh memberikan informasi sensitif ke pihak yang tidak berhak
- AI harus menolak permintaan yang melanggar kebijakan platform
- AI harus selalu transparant bahwa ini adalah sistem AI
- AI harus memiliki fallback jika model tidak tersedia

### 7.3 AI Performance Requirements
- Intent detection latency: < 500ms
- Tool execution timeout: 30 detik per tool
- Total response time: < 10 detik untuk complex workflow
- AI request queue: max 100 concurrent per tenant

---

## 8. API Requirements

### 8.1 API Design Standards
- RESTful API dengan resource-based URLs
- Versioning: `/api/v1/`, `/api/v2/`
- Content-Type: `application/json`
- Authentication: `Authorization: Bearer <token>`
- Tenant context: via JWT claims (tidak perlu header terpisah)

### 8.2 Standard Response Format

```typescript
// Success Response
{
  "success": true,
  "data": <payload>,
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 100,
    "totalPages": 5
  },
  "timestamp": "2026-05-19T10:00:00Z",
  "requestId": "req_abc123"
}

// Error Response
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Pesan error yang human-readable",
    "details": [
      { "field": "email", "message": "Format email tidak valid" }
    ]
  },
  "timestamp": "2026-05-19T10:00:00Z",
  "requestId": "req_abc123"
}
```

### 8.3 Standard Error Codes

| HTTP Code | Error Code | Description |
|-----------|-----------|-------------|
| 400 | VALIDATION_ERROR | Request tidak valid |
| 401 | UNAUTHORIZED | Token tidak ada atau expired |
| 403 | FORBIDDEN | Tidak punya permission |
| 404 | NOT_FOUND | Resource tidak ditemukan |
| 409 | CONFLICT | Data duplikat atau conflict |
| 422 | UNPROCESSABLE | Business rule violation |
| 429 | RATE_LIMIT_EXCEEDED | Terlalu banyak request |
| 500 | INTERNAL_ERROR | Error server internal |
| 503 | SERVICE_UNAVAILABLE | Service sedang down |

### 8.4 Pagination Standard
```
GET /api/v1/products?page=1&limit=20&sort=created_at:desc
```

### 8.5 Filtering Standard
```
GET /api/v1/products?filter[category]=food&filter[status]=active&filter[price][gte]=10000
```

---

## 9. Database Requirements

### 9.1 PostgreSQL Requirements
- Version: PostgreSQL 15+
- Character set: UTF-8
- Timezone: UTC (aplikasi konversi ke WIB untuk display)
- Connection pooling: PgBouncer (min 10, max 100 per service)
- SSL: required untuk production

### 9.2 Indexing Strategy
- Primary key: UUID v7 (time-sortable, better index performance)
- Semua foreign key harus di-index
- Composite index untuk query yang sering digunakan
- Partial index untuk status-based queries
- Full-text search index untuk pencarian produk/pelanggan

### 9.3 Multi-tenant Data Isolation
- Row-Level Security (RLS) di PostgreSQL
- Semua tabel yang tenant-specific memiliki `tenant_id` dan `shop_id`
- Application layer wajib filter by `tenant_id` dari JWT
- Database audit trigger untuk perubahan data kritis

### 9.4 Migration Strategy
- Gunakan TypeORM migrations
- Setiap migration harus idempotent
- Tidak ada destructive migration tanpa backup
- Blue-green deployment untuk zero-downtime migration

---

## 10. Infrastructure Requirements

### 10.1 Environment Setup

| Environment | Purpose | Config |
|-------------|---------|--------|
| local | Development | Docker Compose |
| staging | Testing & QA | Minimal resources |
| production | Live users | HA setup |

### 10.2 Docker Requirements
- Semua services harus memiliki Dockerfile
- Multi-stage build untuk production image
- Non-root user dalam container
- Health check endpoint wajib
- Resource limits terdefinisi (CPU, Memory)

### 10.3 Secrets Management
- Semua secrets via environment variables
- Production secrets di-manage via secret manager (Vault/AWS Secrets Manager)
- Tidak ada secrets di Git repository
- Secrets rotation tanpa downtime

---

## 11. Integration Requirements

### 11.1 WhatsApp Business API
- Provider: Official WhatsApp Business API atau WABA-compliant provider
- Webhook verification: X-Hub-Signature-256
- Message types supported: text, image, document, video, audio, location, template
- Rate limits: sesuai WhatsApp policy
- Template message untuk transactional notifications

### 11.2 Payment Gateway
- Provider: Midtrans (primary)
- Payment methods: QRIS, Transfer Bank, e-Wallet, Kartu Kredit
- Webhook untuk payment confirmation
- Idempotency key untuk payment requests

### 11.3 Storage (MinIO/S3)
- Bucket per tenant untuk isolasi
- Pre-signed URL untuk secure file access
- Lifecycle policy untuk auto-cleanup file lama
- CDN di depan storage untuk performa

---

## 12. Monitoring & Observability

### 12.1 Metrics (Prometheus + Grafana)
- API request rate, latency, error rate
- Queue depth dan processing time
- AI response time dan success rate
- Database connection pool usage
- Memory dan CPU utilization

### 12.2 Logging (Loki)
- Structured logging (JSON format)
- Log levels: error, warn, info, debug
- Correlation ID per request
- Tidak log data sensitif

### 12.3 Alerting
- Downtime alert (immediate)
- Error rate > 5% (5 menit)
- API latency P95 > 2s (10 menit)
- Queue depth > 1000 (10 menit)
- Disk usage > 80% (1 jam)

### 12.4 Tracing
- Distributed tracing dengan OpenTelemetry
- Trace semua request dari API ke database
- Trace AI workflow execution

---

## 13. Error Handling Strategy

### 13.1 Error Classification

| Level | Action | Example |
|-------|--------|---------|
| Recoverable | Retry dengan backoff | Network timeout |
| Degradable | Fallback ke alternative | AI tidak tersedia |
| Critical | Alert + manual intervention | Data corruption |
| User Error | Return friendly message | Invalid input |

### 13.2 Retry Policy
```
Exponential Backoff:
  Attempt 1: immediate
  Attempt 2: 1 second delay
  Attempt 3: 2 second delay
  Attempt 4: 4 second delay
  Max attempts: 3 (configurable per operation)
  
Jitter: ±20% dari delay untuk menghindari thundering herd
```

### 13.3 Circuit Breaker
- Threshold: 50% error rate dalam 10 detik
- Half-open: setelah 30 detik, test 1 request
- Monitoring: alert ketika circuit opens

---

## 14. Data Flow Specification

### 14.1 WhatsApp Message Flow

```
WhatsApp User
    │
    ▼ (webhook POST)
WA Gateway Service
    │ (validate signature)
    │ (parse message)
    ▼
Message Queue (BullMQ)
    │
    ▼ (job pickup)
AI Processing Worker
    │ (send to OpenClaw)
    ▼
OpenClaw Engine
    ├── Detect Intent
    ├── Plan Tasks
    ├── Select Tools
    ├── Execute Tools ──► Domain Services ──► Database
    ├── Update Memory
    └── Generate Response
    │
    ▼
Response Queue
    │
    ▼
WA Gateway Service
    │ (send via WA API)
    ▼
WhatsApp User
```

### 14.2 API Request Flow

```
Client Request
    │
    ▼
Nginx (TLS termination, rate limit headers)
    │
    ▼
API Gateway (NestJS)
    │ (JWT validation)
    │ (rate limiting)
    │ (request logging)
    │ (validation)
    ▼
Controller Layer
    │
    ▼
Service Layer
    │ (business logic)
    │ (tenant context injection)
    ▼
Repository Layer
    │ (TypeORM)
    ▼
PostgreSQL + Redis Cache
    │
    ▼
Response ──► Logging ──► Client
```

---

## 15. User Journey Maps

### 15.1 First-Time UMKM Onboarding

```
Day 1:
1. Temukan platform (iklan/referral)
2. Daftar akun (nama, email, WA number)
3. Verifikasi email
4. Guided setup: nama toko, kategori bisnis
5. Connect WhatsApp number
6. Input produk pertama (via WA atau dashboard)
7. Test AI assistant: kirim "halo"

Week 1:
1. Mulai input stok dan produk
2. Catat pesanan pertama via WA
3. Lihat laporan pertama
4. Setup workflow pertama (notifikasi stok kritis)

Month 1:
1. Gunakan AI untuk generate konten marketing
2. Review analytics dashboard
3. Setup broadcast pelanggan
4. Upgrade ke paket lebih tinggi jika puas
```

### 15.2 Daily UMKM Usage Journey

```
Pagi (07:00-09:00):
  WA: "Laporan kemarin dong"
  AI: [Tampilkan summary penjualan hari sebelumnya]
  
Siang (12:00-14:00):
  WA: "Ada order baru dari Pak Budi, 2 kg beras 1 kg gula"
  AI: [Buat order, kurangi stok, generate invoice, konfirmasi]
  
Sore (16:00-17:00):
  WA: "Buat caption promo untuk beras premium kita"
  AI: [Generate 3 opsi caption dengan hashtag]
  
Malam (20:00-21:00):
  WA: "Update stok beras, tinggal 20 kg"
  AI: [Update stok, notifikasi jika di bawah threshold]
```

---

*Document Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*  
*Next Review: 2026-06-19*
