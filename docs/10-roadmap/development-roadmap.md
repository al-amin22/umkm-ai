# Development Roadmap
# UMKM AI Platform

**Version:** 1.1.0  
**Last Updated:** 2026-05-20

---

## Overview Timeline

```
Month 1-2   → Foundation & Core (MVP)
Month 3-4   → Growth Features
Month 5-6   → Enterprise & Scale
Month 7+    → SaaS Expansion
```

---

## PHASE 1 — FOUNDATION (Month 1-2)

### Week 1-2: Infrastructure & Project Setup
- [x] Monorepo setup (Turborepo)
- [x] Laravel 11 app skeleton + Docker Compose config
- [x] PostgreSQL schema & migrations (core tables: shops, products, stocks, orders, etc.)
- [x] CI/CD pipeline (GitHub Actions — PHP lint + TypeScript check)

### Week 3-4: Authentication & Tenant System
- [x] Admin web auth (register, login, logout)
- [x] Shop management (CRUD + settings)
- [x] Shop-scoped middleware (AdminShop)
- [x] Admin dashboard layout (sidebar, responsive)

### Week 5-6: Product & Inventory Module
- [x] Product CRUD (API + Admin web UI)
- [x] Stock management (jumlah_sekarang, batas_minimum)
- [x] Stock mutations & logging (StockService)
- [x] Stock alert system (StokKritis event + listener)

### Week 7-8: Order & Finance Module
- [x] Order lifecycle (pending → confirmed → shipped → done/cancelled)
- [x] Order number generation (ORD-YYYYMMDD-NNN)
- [x] Finance transactions (KeuanganService)
- [x] HPP & margin calculation
- [x] Orders web UI (list + detail + status actions)

**MILESTONE: MVP Backend Complete ✅**

---

## PHASE 2 — AI CORE (Month 2-3)

### Week 9-10: WhatsApp Gateway
- [x] WA Gateway service (Node.js + Baileys v7)
- [x] Webhook reception & validation (ValidateWASecret middleware)
- [x] Message parsing & dispatch
- [x] Outbound message sender (WAService)
- [x] Session management (WaSession + context_data JSON)
- [x] Queue setup (Laravel queues)

### Week 11-12: Groq AI Integration
- [x] Groq client integration (llama-3.3-70b-versatile)
- [x] Intent detection system (CommandRouter)
- [x] Tool-based WA flows (stock, order, finance, content)
- [x] Memory system (wa_sessions context)
- [x] AI response generation

### Week 13-14: AI Feature Completion
- [x] Multi-step conversation flows (session context)
- [x] Content generation (caption, copywriting — KontenService)
- [x] AI error handling & fallbacks
- [x] Feature gating by subscription plan (PlanGate)

**MILESTONE: WhatsApp AI MVP Live ✅**

---

## PHASE 3 — GROWTH FEATURES (Month 3-4)

### Week 15-16: CRM System
- [x] Customer management (sync on order + upsert)
- [x] Customer history & analytics
- [x] RFM segmentation (R/F/M scores 1-5 + 7 segments)
- [x] Follow-up automation (KirimFollowUpSetelahDone listener)
- [x] CRM web UI (pelanggan list + detail with RFM)

### Week 17-18: Marketing Module
- [x] AI caption generator (via WA)
- [x] Broadcast management (BroadcastService + throttle + max 200)
- [x] Content templates (KontenService)
- [x] AI copywriting (GroqService)

### Week 19-20: Analytics Dashboard
- [x] Sales analytics (AnalitikService — omzet, trend, top products)
- [x] Customer analytics (RFM breakdown + AI recommendations)
- [x] AI business insights (GroqService.generateInsightBisnis)
- [x] Report export CSV (LaporanController.exportCsv)
- [x] Report print/PDF view (LaporanController.cetak)
- [x] Admin analytics web UI (laporan index + charts)

**MILESTONE: Full Feature Set Complete ✅**

---

## PHASE 4 — ENTERPRISE & SCALE (Month 5-6)

### Week 21-22: Workflow Automation
- [x] Pre-built workflow commands (morning briefing, reminder pesanan, cek expiry)
- [x] Cron-based triggers (Laravel Schedule — 6 scheduled jobs)
- [x] Event-based triggers (Laravel Events: OrderDone, StokKritis, PesananBaru)
- [x] Event listeners with queue (ShouldQueue — 3 listeners)

### Week 23-24: Payment & Subscription
- [x] Midtrans Snap integration (MidtransService)
- [x] Subscription plan management (SubscriptionService — trial/starter/growth)
- [x] Feature gating by plan (PlanGate with 5-min cache)
- [x] Payment webhook handling (WebhookController.midtrans)

### Week 25-26: Performance & Scale
- [x] Dashboard KPI caching (Cache::remember 5min)
- [x] Database query optimization (indexes migration — 7 composite indexes)
- [x] Plan gate caching (PlanGate — Cache::remember 300s)
- [ ] Load testing
- [ ] Monitoring dashboards (Grafana)

### Week 27-28: Launch Preparation
- [x] Security headers middleware (X-Content-Type, X-Frame-Options, HSTS)
- [x] Rate limiting (admin login: 5/min, register: 5/10min)
- [x] Shop onboarding web flow (register + create shop + admin)
- [ ] Documentation finalization
- [ ] Marketing landing page
- [ ] Beta user onboarding (50 UMKM)

**MILESTONE: Production Launch — In Progress 🚧**

---

## Module Implementation Status

| Module | WA Bot | Admin Web | Status |
|--------|--------|-----------|--------|
| M01: Auth | N/A | ✅ Register/Login | Complete |
| M02: Subscription | ✅ Cek/Perpanjang | ✅ Plan gate | Complete |
| M03: Shop | ✅ Pengaturan WA | ✅ Settings page | Complete |
| M04: Product | ✅ CRUD via WA | ✅ CRUD web | Complete |
| M05: Inventory | ✅ Stock ops | ✅ Edit stok | Complete |
| M06: Order | ✅ Full lifecycle | ✅ Web UI | Complete |
| M07: Finance | ✅ HPP/margin/laporan | ✅ Laporan export | Complete |
| M08: Customer | ✅ RFM via WA | ✅ List + detail | Complete |
| M09: Marketing | ✅ Broadcast/caption | — | Partial |
| M10: Analytics | ✅ Insight/trend WA | ✅ Dashboard + export | Complete |
| M11: Workflow | ✅ Events + queue | ✅ Scheduled jobs | Complete |
| M12: Notification | ✅ WA notifications | — | WA-only |
| M13: AI Core | ✅ CommandRouter | — | WA-only |

---

## Tech Debt Budget

Setiap sprint mengalokasikan **20% kapasitas** untuk:
- Refactoring kode yang kompleks
- Meningkatkan test coverage
- Update dependencies
- Dokumentasi yang tertinggal

---

*Roadmap Owner: Engineering Team*  
*Version: 1.1.0 | Status: Active*  
*Next Review: 2026-06-20*
