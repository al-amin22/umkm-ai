# UMKM AI Platform
## AI Business Operating System untuk UMKM Indonesia

**Version:** 1.0.0 | **Status:** In Development | **Stack:** NestJS · Next.js · PostgreSQL · Redis · OpenClaw

---

## Deskripsi

Platform AI all-in-one yang membantu UMKM Indonesia mengelola bisnis lebih cerdas melalui WhatsApp AI Assistant, Dashboard Web, dan sistem otomatisasi yang komprehensif.

**Ini BUKAN chatbot biasa.** Ini adalah AI Business Operating System yang dapat:
- Menerima dan mengeksekusi instruksi bisnis via WhatsApp
- Menjalankan workflow bisnis secara otomatis
- Mengelola produk, stok, pesanan, keuangan, dan pelanggan
- Generate konten marketing dengan AI
- Memberikan analitik dan insight bisnis

---

## Dokumentasi

| # | Dokumen | Deskripsi |
|---|---------|-----------|
| 00 | [PRD](./PRD.md) | Product Requirement Document |
| 01 | [SRS](../01-srs/SRS.md) | Software Requirement Specification (super detail) |
| 02a | [System Architecture](../02-architecture/system-architecture.md) | Arsitektur sistem keseluruhan |
| 02b | [AI Architecture](../02-architecture/ai-architecture.md) | OpenClaw AI architecture & tool system |
| 02c | [Security Architecture](../02-architecture/security-architecture.md) | Keamanan sistem |
| 03 | [ERD & Database](../03-database/ERD.md) | Database design & entity relationships |
| 04 | [API Documentation](../04-api/api-documentation.md) | REST API endpoints |
| 05 | [Module Breakdown](../05-modules/module-breakdown.md) | Detail setiap module |
| 06 | [WA Flow](../06-ai-system/wa-flow.md) | WhatsApp AI message flow |
| 07a | [Deployment](../07-devops/deployment-architecture.md) | Docker & deployment setup |
| 07b | [CI/CD](../07-devops/cicd-strategy.md) | GitHub Actions pipeline |
| 08 | [Testing](../08-testing/testing-strategy.md) | Testing strategy & examples |
| 09 | [Git Strategy](../09-git/git-strategy.md) | Branching & commit convention |
| 10 | [Roadmap](../10-roadmap/development-roadmap.md) | Development timeline |

---

## Quick Start (Development)

```bash
# 1. Clone repository
git clone https://github.com/yourorg/umkm-ai-platform.git
cd umkm-ai-platform

# 2. Install dependencies (monorepo)
npm install

# 3. Copy environment files
cp .env.example .env
# Edit .env sesuai konfigurasi lokal

# 4. Start development environment
docker-compose up -d

# 5. Run database migrations
npm run migration:run --workspace=apps/api

# 6. Seed default data
npm run seed --workspace=apps/api

# 7. Start development servers
npm run dev
```

**Services setelah startup:**
- Web Dashboard: http://localhost:3000
- API Service: http://localhost:3001
- WA Gateway: http://localhost:3002
- API Docs (Swagger): http://localhost:3001/api/docs
- MinIO Console: http://localhost:9001
- Prometheus: http://localhost:9090
- Grafana: http://localhost:3003

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Next.js 14+, TypeScript, TailwindCSS, Zustand, TanStack Query |
| Backend | NestJS, Node.js, TypeScript |
| Database | PostgreSQL 15, Redis 7 |
| AI Engine | OpenClaw, LangGraph-style orchestration |
| Queue | BullMQ (Redis-backed) |
| Storage | MinIO (S3-compatible) |
| Auth | JWT, Refresh Token, OAuth (Google) |
| Infrastructure | Docker, Docker Compose, Nginx |
| Monitoring | Prometheus, Grafana, Loki |
| CI/CD | GitHub Actions |

---

## Architecture Overview

```
WhatsApp User → WA Gateway → Message Queue → AI Orchestrator (OpenClaw)
                                                      ↓
Web User → Next.js → API Gateway (NestJS) → Domain Services
                                                      ↓
                                            PostgreSQL + Redis + MinIO
```

---

## Contributing

1. Baca [Git Strategy](../09-git/git-strategy.md)
2. Ambil issue dari project board
3. Buat branch: `feature/AI-{issue}-{description}`
4. Develop + test
5. Buat PR dengan description lengkap
6. Code review wajib sebelum merge

---

## License

Proprietary — All rights reserved.

---

*Last Updated: 2026-05-19 | Engineering Team*
