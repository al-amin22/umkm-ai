# Module Breakdown
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19

---

## Module Map

```
API Service (NestJS)
│
├── M01: Auth Module
│   ├── register, login, logout
│   ├── JWT token management
│   ├── refresh token rotation
│   └── OAuth (Google)
│
├── M02: Tenant Module
│   ├── tenant CRUD
│   ├── subscription management
│   └── feature gating by plan
│
├── M03: Shop Module
│   ├── shop CRUD
│   ├── WA number management
│   └── shop settings
│
├── M04: Product Module
│   ├── product CRUD
│   ├── category management
│   ├── image upload
│   └── search & filter
│
├── M05: Inventory Module
│   ├── stock tracking
│   ├── stock mutations (ADD/SUBTRACT/SET/ADJUST)
│   ├── stock alert system
│   ├── stock history
│   └── supplier management (v2)
│
├── M06: Order Module
│   ├── order creation
│   ├── order lifecycle management
│   ├── invoice generation
│   ├── payment tracking
│   └── order notifications
│
├── M07: Finance Module
│   ├── transaction recording
│   ├── finance categories
│   ├── debt & receivables
│   ├── HPP calculator
│   └── finance reports
│
├── M08: Customer Module
│   ├── customer CRUD
│   ├── customer history
│   ├── RFM segmentation
│   └── customer analytics
│
├── M09: Marketing Module
│   ├── AI caption generator
│   ├── AI copywriting
│   ├── broadcast management
│   └── content calendar
│
├── M10: Analytics Module
│   ├── sales analytics
│   ├── customer analytics
│   ├── AI business insights
│   └── report export
│
├── M11: Workflow Module
│   ├── workflow CRUD
│   ├── workflow execution engine
│   ├── trigger management (cron, event)
│   └── workflow monitoring
│
├── M12: Notification Module
│   ├── WA notification sender
│   ├── email notification sender
│   ├── notification queue
│   └── template management
│
└── M13: AI Orchestrator Module
    ├── intent detection
    ├── tool registry
    ├── workflow execution
    ├── memory management
    └── response generation
```

---

## M05: Inventory Module — Detail

### Tujuan
Mengelola stok produk secara real-time dengan tracking yang akurat dan notifikasi stok kritis otomatis.

### Workflow
```
User/AI → updateStock() 
    → validate (quantity not negative)
    → update stocks table
    → create stock_log record
    → check if below minimum
    → if critical: publish StockCriticalEvent
        → NotificationModule receives event
        → Send WA alert to shop owner
```

### API Endpoints
| Method | Path | Description | Permission |
|--------|------|-------------|-----------|
| GET | /inventory/stock | Get all stock | staff+ |
| GET | /inventory/stock/:productId | Get product stock | staff+ |
| POST | /inventory/stock/update | Update stock | admin+ |
| GET | /inventory/low-stock | Get low stock products | staff+ |
| GET | /inventory/logs | Stock movement history | admin+ |
| POST | /inventory/opname | Stock opname | admin+ |

### Business Rules
1. Stok tidak boleh negatif — reject jika `quantity - deduction < 0`
2. Alert otomatis saat `quantity <= minimumStock`
3. Setiap perubahan stok HARUS dicatat di `stock_logs`
4. Stock opname membutuhkan verifikasi admin

### AI Tools
- `get_stock_level(product_name)` → current stock
- `update_stock(product, qty, type)` → update + log
- `get_low_stock_products()` → list critical products

### Edge Cases
- Concurrent orders yang mengurangi stok bersamaan → gunakan `SELECT FOR UPDATE` atau optimistic locking
- Stok 0 vs tidak ada record stok → treat keduanya sebagai out-of-stock
- Update stok dengan angka desimal → support up to 2 decimal places

### Test Cases
```
✓ Update stock ADD: 50 + 30 = 80
✓ Update stock SUBTRACT: 50 - 20 = 30
✓ Update stock SET: any → 75
✓ Reject SUBTRACT when result < 0
✓ Trigger StockCriticalEvent when qty <= minimum
✓ Log every mutation with before/after values
✓ Concurrent updates don't cause data corruption
```

---

## M06: Order Module — Detail

### Tujuan
Mengelola seluruh lifecycle pesanan dari pembuatan hingga penyelesaian, dengan otomatisasi notifikasi dan invoice.

### Order Status Flow
```
PENDING → CONFIRMED → PROCESSING → SHIPPED → DELIVERED → COMPLETED
   ↓           ↓           ↓
CANCELLED   CANCELLED   CANCELLED
                                          ↓
                                      REFUNDED
```

### Workflow: Create Order
```
1. Validate request (customer, items, quantities)
2. Check stock availability for all items (bulk check)
3. Create order record (status: PENDING)
4. Create order_items records
5. Decrement stock for each item
6. Create stock_log records
7. If customer not found: create new customer
8. Generate order number (ORD-YYYYMMDD-NNN)
9. Publish OrderCreatedEvent
   → NotificationModule: notify seller via WA
   → Generate invoice (async queue)
10. Return order with invoice URL
```

### Business Rules
1. Stock dicek SEBELUM order dibuat
2. Stock LANGSUNG dikurangi saat order dibuat (bukan saat konfirmasi)
3. Jika order dibatalkan, stok HARUS dikembalikan
4. Order number harus unik per shop per hari
5. Reminder otomatis ke seller untuk order PENDING > 24 jam

### AI Tools
- `create_order(customer, items)` → full order creation flow
- `get_order_status(order_id_or_number)` → order detail
- `update_order_status(order_id, status)` → status update
- `list_pending_orders()` → pending orders list

### Edge Cases
- Concurrent orders untuk produk yang sama dengan stok terbatas → race condition handling
- Order dengan produk yang sudah dihapus → snapshot produk di `order_items`
- Partial payment tracking
- Order dari multiple channels (WA vs Dashboard)

---

## M13: AI Orchestrator Module — Detail

### Tujuan
Menjadi otak dari sistem AI — menerima pesan, memahami intent, memilih tools, mengeksekusi task, dan menghasilkan respons yang natural.

### Workflow
```
processMessage(message, session, tenant)
    │
    ├── buildContext(session, tenant) → AIContext
    │       ├── Load working memory (Redis)
    │       ├── Load episodic memory (PostgreSQL)
    │       ├── Load business profile (PostgreSQL)
    │       └── Load current state (low stock, pending orders)
    │
    ├── detectIntent(message, context) → DetectedIntent
    │       ├── Call OpenClaw intent endpoint
    │       ├── Extract entities
    │       └── Determine confidence
    │
    ├── [if confidence < 0.7] → generateClarification()
    │
    ├── planWorkflow(intent, context) → Workflow
    │       ├── Determine sequential vs parallel tasks
    │       ├── Map intent to tool calls
    │       └── Order by dependency
    │
    ├── executeWorkflow(workflow, context) → WorkflowResult
    │       ├── Execute parallel tasks concurrently
    │       ├── Pass results to next sequential tasks
    │       ├── Handle partial failures
    │       └── Rollback on critical failure
    │
    ├── updateMemory(session, intent, results)
    │       ├── Update working memory (Redis)
    │       └── Persist important events (PostgreSQL)
    │
    └── generateResponse(results, context) → AIResponse
            ├── Call OpenClaw response generation
            ├── Format for WA (length, emoji, structure)
            └── Split if too long (WA limit)
```

### Tool Registry Registration
```typescript
// Semua tools diregister saat startup
@Injectable()
class ToolRegistryService implements OnModuleInit {
  onModuleInit() {
    this.register(inventoryTools);
    this.register(orderTools);
    this.register(financeTools);
    this.register(contentTools);
    this.register(analyticsTools);
    this.register(customerTools);
  }
}
```

---

*Module Breakdown Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
