# System Architecture
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19

---

## 1. Architecture Overview

UMKM AI Platform menggunakan **Modular Monolith** sebagai arsitektur awal dengan desain yang memudahkan ekstraksi ke **Microservices** di masa depan. Semua modul dikembangkan dengan bounded context yang jelas dan komunikasi antar modul melalui interface yang terdefinisi.

### 1.1 Architecture Principles

1. **Separation of Concerns** — setiap layer punya responsibility yang jelas
2. **Dependency Inversion** — depend on abstractions, not implementations
3. **Single Responsibility** — setiap module/class punya satu alasan untuk berubah
4. **Open/Closed** — open for extension, closed for modification
5. **Event-Driven** — heavy coupling dihindari via events dan queue
6. **Fail Fast** — validasi di boundary, fail early dengan error yang jelas
7. **Observability First** — setiap komponen harus observable

---

## 2. High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          CLIENT LAYER                                    │
│                                                                          │
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────────────┐    │
│  │  Web Dashboard  │  │  Mobile Web     │  │  WhatsApp User       │    │
│  │  (Next.js)      │  │  (Responsive)   │  │  (WA Business API)   │    │
│  └────────┬────────┘  └────────┬────────┘  └───────────┬──────────┘    │
└───────────┼─────────────────────┼──────────────────────┼───────────────┘
            │                     │                       │
            ▼                     ▼                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                       INGRESS LAYER                                      │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  Nginx (Reverse Proxy)                                          │    │
│  │  • TLS Termination  • Rate Limiting  • Load Balancing           │    │
│  │  • CORS Headers     • Security Headers  • Static Assets        │    │
│  └────────────────────────────┬────────────────────────────────────┘    │
└────────────────────────────────┼────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    ▼                         ▼
┌──────────────────────┐         ┌─────────────────────────┐
│  API Service         │         │  WA Gateway Service      │
│  (NestJS — Port 3001)│         │  (NestJS — Port 3002)    │
│                      │         │                          │
│  • REST API          │         │  • WA Webhook handler    │
│  • Auth middleware   │         │  • Message parser        │
│  • Rate limiting     │         │  • Signature validation  │
│  • Request logging   │         │  • Message sender        │
│  • Validation        │         │  • Session management    │
└──────────┬───────────┘         └────────────┬─────────────┘
           │                                  │
           └──────────────┬───────────────────┘
                          │
┌─────────────────────────▼────────────────────────────────────────────────┐
│                    APPLICATION CORE (NestJS Modules)                      │
│                                                                           │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐           │
│  │  Auth   │ │Product  │ │  Order  │ │  Stock  │ │ Finance │           │
│  │ Module  │ │ Module  │ │ Module  │ │ Module  │ │ Module  │           │
│  └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘           │
│       │           │           │           │           │                  │
│  ┌────┴──────────────────────────────────────────────┴────┐             │
│  │                   Shared Services                       │             │
│  │  Cache │ Queue │ Event Bus │ File Storage │ Notification│             │
│  └─────────────────────────────────────────────────────────┘             │
│                                                                           │
│  ┌────────────────────────────────────────────────────────────┐          │
│  │                  OpenClaw AI Module                        │          │
│  │  Intent Engine │ Tool Registry │ Memory Manager │ Executor │          │
│  └────────────────────────────────────────────────────────────┘          │
└─────────────────────────┬────────────────────────────────────────────────┘
                          │
┌─────────────────────────▼────────────────────────────────────────────────┐
│                       DATA LAYER                                          │
│                                                                           │
│  ┌──────────────────┐  ┌──────────────┐  ┌────────────┐  ┌──────────┐  │
│  │   PostgreSQL     │  │    Redis     │  │   MinIO    │  │  BullMQ  │  │
│  │   (Primary DB)   │  │  (Cache +    │  │  (Storage) │  │  (Queue) │  │
│  │                  │  │   Queue)     │  │            │  │          │  │
│  │  • Multi-tenant  │  │  • Session   │  │  • Images  │  │  • Jobs  │  │
│  │  • TypeORM       │  │  • Cache     │  │  • Files   │  │  • Tasks │  │
│  │  • RLS           │  │  • AI Memory │  │  • Docs    │  │          │  │
│  └──────────────────┘  └──────────────┘  └────────────┘  └──────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Service Architecture

### 3.1 API Service (NestJS)

**Responsibility:** Core business logic, REST API, authentication, dan orchestration.

```
apps/api/
├── src/
│   ├── app.module.ts              # Root module
│   ├── main.ts                    # Bootstrap
│   │
│   ├── config/                    # Configuration
│   │   ├── app.config.ts
│   │   ├── database.config.ts
│   │   ├── redis.config.ts
│   │   └── ai.config.ts
│   │
│   ├── common/                    # Shared utilities
│   │   ├── decorators/
│   │   ├── filters/               # Exception filters
│   │   ├── guards/                # Auth guards
│   │   ├── interceptors/          # Logging, transform
│   │   ├── pipes/                 # Validation pipes
│   │   └── middleware/
│   │
│   ├── modules/
│   │   ├── auth/
│   │   ├── tenant/
│   │   ├── product/
│   │   ├── inventory/
│   │   ├── order/
│   │   ├── finance/
│   │   ├── crm/
│   │   ├── marketing/
│   │   ├── analytics/
│   │   ├── workflow/
│   │   ├── notification/
│   │   └── ai-orchestrator/
│   │
│   └── database/
│       ├── migrations/
│       └── seeds/
```

### 3.2 WA Gateway Service (NestJS)

**Responsibility:** Menangani seluruh komunikasi dengan WhatsApp Business API.

```
apps/wa-gateway/
├── src/
│   ├── app.module.ts
│   ├── main.ts
│   │
│   ├── webhook/
│   │   ├── webhook.controller.ts  # Receive WA webhooks
│   │   └── webhook.service.ts    # Validate & parse
│   │
│   ├── sender/
│   │   ├── sender.service.ts     # Send messages
│   │   └── template.service.ts   # WA template management
│   │
│   ├── session/
│   │   └── session.service.ts    # WA session management
│   │
│   └── queue/
│       ├── message.processor.ts  # Process incoming messages
│       └── outbound.processor.ts # Send outbound messages
```

### 3.3 Web Frontend (Next.js)

**Responsibility:** Dashboard UI untuk owner dan admin.

```
apps/web/
├── src/
│   ├── app/                       # App Router
│   │   ├── (auth)/               # Auth routes
│   │   ├── (dashboard)/          # Dashboard routes
│   │   │   ├── layout.tsx
│   │   │   ├── page.tsx           # Home dashboard
│   │   │   ├── products/
│   │   │   ├── orders/
│   │   │   ├── inventory/
│   │   │   ├── finance/
│   │   │   ├── crm/
│   │   │   ├── marketing/
│   │   │   ├── analytics/
│   │   │   └── settings/
│   │   └── api/                  # Next.js API routes
│   │
│   ├── components/
│   │   ├── ui/                   # Base UI components
│   │   ├── layout/
│   │   └── modules/              # Feature-specific components
│   │
│   ├── hooks/                    # Custom React hooks
│   ├── stores/                   # Zustand stores
│   ├── services/                 # API client services
│   └── utils/
```

---

## 4. Module Architecture Pattern

Setiap domain module mengikuti pola **Hexagonal Architecture** yang konsisten:

```
modules/[module-name]/
├── [module].module.ts            # NestJS Module declaration
├── [module].controller.ts        # HTTP endpoints (Primary Adapter)
├── [module].service.ts           # Business logic
├── [module].repository.ts        # Data access (Secondary Adapter)
├── dto/
│   ├── create-[entity].dto.ts
│   ├── update-[entity].dto.ts
│   └── query-[entity].dto.ts
├── entities/
│   └── [entity].entity.ts        # TypeORM entities
├── events/
│   ├── [entity]-created.event.ts
│   └── [entity]-updated.event.ts
├── interfaces/
│   ├── [module].service.interface.ts
│   └── [module].repository.interface.ts
└── [module].service.spec.ts      # Unit tests
```

---

## 5. Dependency Flow

```
Controller → Service → Repository → Database
     ↑           ↑          ↑
     └── DTO     └── Domain  └── TypeORM Entity
                    Models

Cross-module communication:
Service A → EventBus → Service B (loose coupling)
Service A → Service B interface (direct, when necessary)
```

**Allowed dependencies:**
- Controller dapat import Service
- Service dapat import Repository, EventBus, other Services (via interface)
- Repository dapat import TypeORM + Entity
- DTO dapat import class-validator decorators

**Forbidden dependencies:**
- Controller TIDAK boleh import Repository langsung
- Service TIDAK boleh import HTTP context (Request, Response)
- Repository TIDAK boleh import business logic

---

## 6. Communication Patterns

### 6.1 Synchronous (REST)
- Client → API Server
- Internal service calls yang butuh immediate response
- Health checks

### 6.2 Asynchronous (Queue — BullMQ)
- WA message processing
- Email/WA notification sending
- AI task execution yang lama
- Report generation
- Bulk operations
- Webhook event processing

### 6.3 Event-Driven (Internal EventBus)
- OrderCreated → StockService.decrementStock
- OrderCreated → NotificationService.sendOrderConfirmation
- StockCritical → NotificationService.alertOwner
- PaymentConfirmed → OrderService.updateStatus

---

## 7. Data Storage Strategy

### 7.1 PostgreSQL — Source of Truth
- Semua persistent business data
- ACID-compliant transactions
- Row-Level Security untuk multi-tenant isolation
- Full-text search dengan pg_trgm

### 7.2 Redis — Fast Access Layer
- Session data (JWT blacklist, WA sessions)
- API response cache (TTL-based)
- AI conversation context (short-term memory)
- Rate limiting counters
- BullMQ job queues
- Pub/Sub untuk real-time events

### 7.3 MinIO — Object Storage
- Product images
- Invoice PDFs
- Export files (Excel, CSV)
- User uploads
- AI-generated content

---

## 8. Multi-Tenant Architecture

### 8.1 Tenant Isolation Strategy

**Shared Database, Row-Level Isolation** (untuk MVP):
- Semua tenant dalam satu PostgreSQL instance
- Setiap row memiliki `tenant_id`
- PostgreSQL RLS memastikan tenant hanya bisa akses data sendiri
- Application layer selalu inject `tenant_id` dari JWT

```sql
-- RLS Policy Example
CREATE POLICY tenant_isolation ON products
  USING (tenant_id = current_setting('app.current_tenant_id')::uuid);
```

### 8.2 Tenant Resolution

```
Request → JWT decode → extract tenant_id
        → inject ke request context
        → service layer selalu filter by tenant_id
        → RLS sebagai safety net
```

### 8.3 Resource Limits per Tenant

| Resource | Starter | Growth | Enterprise |
|---------|---------|--------|-----------|
| Produk | 100 | 1.000 | Unlimited |
| AI calls/hari | 100 | 1.000 | Custom |
| WA sessions | 1 | 3 | 10 |
| Storage | 1 GB | 10 GB | Custom |
| Users | 3 | 10 | Unlimited |

---

## 9. Caching Strategy

### 9.1 Cache Layers

```
Request → Nginx (static assets) 
        → Redis (API response cache)
        → Application cache (in-memory, short TTL)
        → Database
```

### 9.2 Cache TTL Strategy

| Data Type | TTL | Invalidation |
|-----------|-----|-------------|
| Product list | 5 menit | On product update |
| Stock data | 1 menit | On stock change |
| Analytics | 15 menit | On demand |
| User profile | 30 menit | On profile update |
| AI context | 24 jam | On session end |
| JWT blacklist | 15 menit | Token expiry |

### 9.3 Cache Key Convention
```
{tenant_id}:{resource}:{id}:{variation}
Example: "tenant_abc123:products:page_1:sort_name"
```

---

## 10. Queue Architecture

### 10.1 Queue Types

```
BullMQ Queues:
├── wa-inbound          # Incoming WA messages (HIGH priority)
├── wa-outbound         # Outgoing WA messages (HIGH priority)
├── ai-processing       # AI task execution (NORMAL priority)
├── notification        # Email/WA notifications (NORMAL priority)
├── report-generation   # Laporan & export (LOW priority)
├── analytics-compute   # Analytics calculation (LOW priority)
└── maintenance         # Cleanup, backup tasks (LOWEST priority)
```

### 10.2 Queue Priority

| Queue | Priority | Max Attempts | Backoff |
|-------|---------|--------------|---------|
| wa-inbound | 10 (highest) | 3 | Exponential |
| wa-outbound | 10 | 5 | Exponential |
| ai-processing | 5 | 3 | Exponential |
| notification | 5 | 5 | Exponential |
| report-generation | 2 | 2 | Fixed 30s |
| analytics-compute | 1 | 1 | None |

---

## 11. Security Architecture

```
External → Nginx → WAF Rules → Rate Limit → API Service
                                               │
                              JWT Middleware ◄─┘
                                               │
                              RBAC Guard ◄─────┘
                                               │
                              Tenant Filter ◄──┘
                                               │
                              Service Layer ◄──┘
                                               │
                              RLS (DB) ◄────────┘
```

Lihat detail di: `/docs/02-architecture/security-architecture.md`

---

## 12. Deployment Architecture

### 12.1 Docker Compose (Development & Staging)

```yaml
services:
  api:          # NestJS API
  wa-gateway:   # WA Gateway
  web:          # Next.js Frontend
  postgres:     # PostgreSQL
  redis:        # Redis
  minio:        # Object Storage
  nginx:        # Reverse Proxy
  prometheus:   # Metrics
  grafana:      # Dashboard
  loki:         # Logging
```

### 12.2 Production Scaling

```
Load Balancer
    ├── API Service (3x replicas)
    ├── WA Gateway (2x replicas)
    └── Web (2x replicas)

Data Layer (managed):
    ├── PostgreSQL (Primary + Replica)
    ├── Redis (Sentinel/Cluster)
    └── MinIO (Distributed)
```

Lihat detail di: `/docs/07-devops/deployment-architecture.md`

---

*Architecture Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
