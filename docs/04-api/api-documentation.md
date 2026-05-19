# API Documentation
# UMKM AI Platform

**Version:** v1  
**Base URL:** `https://api.umkmplatform.id/api/v1`  
**Format:** REST — JSON  
**Auth:** JWT Bearer Token  
**Last Updated:** 2026-05-19

---

## 1. Authentication

### 1.1 Register
```http
POST /auth/register
Content-Type: application/json

{
  "tenantName": "Toko Budi",
  "email": "budi@toko.com",
  "password": "StrongPassword123!",
  "phone": "081234567890",
  "waNumber": "628123456789"
}

Response 201:
{
  "success": true,
  "data": {
    "user": { "id": "uuid", "email": "budi@toko.com", "name": "Budi" },
    "tenant": { "id": "uuid", "name": "Toko Budi", "slug": "toko-budi" },
    "accessToken": "eyJ...",
    "refreshToken": "eyJ..."
  }
}
```

### 1.2 Login
```http
POST /auth/login
Content-Type: application/json

{
  "email": "budi@toko.com",
  "password": "StrongPassword123!"
}

Response 200:
{
  "success": true,
  "data": {
    "accessToken": "eyJ...",
    "refreshToken": "eyJ...",
    "expiresIn": 900
  }
}
```

### 1.3 Refresh Token
```http
POST /auth/refresh
Authorization: Bearer <refresh_token>

Response 200:
{
  "success": true,
  "data": {
    "accessToken": "eyJ...",
    "expiresIn": 900
  }
}
```

### 1.4 Logout
```http
POST /auth/logout
Authorization: Bearer <access_token>

Response 200: { "success": true }
```

---

## 2. Products

### 2.1 List Products
```http
GET /products?page=1&limit=20&sort=created_at:desc&filter[category_id]=uuid&filter[is_active]=true
Authorization: Bearer <token>
X-Shop-Id: <shop_id>

Response 200:
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "sku": "BERAS-001",
      "name": "Beras Premium 5kg",
      "description": "...",
      "unit": "kg",
      "basePrice": 50000,
      "sellingPrice": 60000,
      "category": { "id": "uuid", "name": "Bahan Pokok" },
      "stock": { "quantity": 150, "minimumStock": 20 },
      "images": ["https://..."],
      "isActive": true,
      "createdAt": "2026-05-19T00:00:00Z"
    }
  ],
  "meta": { "page": 1, "limit": 20, "total": 45, "totalPages": 3 }
}
```

### 2.2 Create Product
```http
POST /products
Authorization: Bearer <token>
X-Shop-Id: <shop_id>
Content-Type: application/json

{
  "name": "Beras Premium 5kg",
  "sku": "BERAS-001",
  "description": "Beras pulen berkualitas",
  "unit": "kg",
  "basePrice": 50000,
  "sellingPrice": 60000,
  "categoryId": "uuid",
  "minimumStock": 20,
  "initialStock": 100
}

Response 201:
{ "success": true, "data": { <product_object> } }
```

### 2.3 Get Product
```http
GET /products/:id
Authorization: Bearer <token>
X-Shop-Id: <shop_id>

Response 200:
{ "success": true, "data": { <product_object_with_stock_history> } }
```

### 2.4 Update Product
```http
PATCH /products/:id
Authorization: Bearer <token>
Content-Type: application/json

{ "sellingPrice": 65000, "description": "Updated description" }

Response 200:
{ "success": true, "data": { <updated_product> } }
```

### 2.5 Delete Product
```http
DELETE /products/:id
Authorization: Bearer <token>

Response 200: { "success": true, "data": { "deleted": true } }
```

### 2.6 Upload Product Image
```http
POST /products/:id/images
Authorization: Bearer <token>
Content-Type: multipart/form-data

Form data: file (JPG/PNG/WebP, max 5MB)

Response 200:
{ "success": true, "data": { "imageUrl": "https://..." } }
```

---

## 3. Inventory

### 3.1 Get Stock
```http
GET /inventory/stock?product_id=uuid
Authorization: Bearer <token>
X-Shop-Id: <shop_id>

Response 200:
{
  "success": true,
  "data": {
    "productId": "uuid",
    "productName": "Beras Premium 5kg",
    "quantity": 150,
    "unit": "kg",
    "minimumStock": 20,
    "status": "normal"  // "normal", "low", "out"
  }
}
```

### 3.2 Update Stock
```http
POST /inventory/stock/update
Authorization: Bearer <token>
Content-Type: application/json

{
  "productId": "uuid",
  "mutationType": "ADD",          // "SET", "ADD", "SUBTRACT"
  "quantity": 50,
  "reason": "Restock dari supplier",
  "referenceType": "purchase",
  "referenceId": "uuid"
}

Response 200:
{
  "success": true,
  "data": {
    "before": 100,
    "change": 50,
    "after": 150
  }
}
```

### 3.3 Get Low Stock Products
```http
GET /inventory/low-stock
Authorization: Bearer <token>
X-Shop-Id: <shop_id>

Response 200:
{
  "success": true,
  "data": [
    {
      "productId": "uuid",
      "name": "Minyak Goreng",
      "quantity": 5,
      "minimumStock": 10,
      "unit": "liter"
    }
  ]
}
```

### 3.4 Stock Movement History
```http
GET /inventory/logs?product_id=uuid&page=1&limit=20&date_from=2026-05-01&date_to=2026-05-31
Authorization: Bearer <token>

Response 200:
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "type": "OUT",
      "quantity": -2,
      "beforeQty": 50,
      "afterQty": 48,
      "referenceType": "order",
      "referenceId": "uuid",
      "notes": "Pesanan ORD-20260519-001",
      "createdAt": "2026-05-19T10:00:00Z"
    }
  ]
}
```

---

## 4. Orders

### 4.1 Create Order
```http
POST /orders
Authorization: Bearer <token>
X-Shop-Id: <shop_id>
Content-Type: application/json

{
  "customerName": "Pak Budi",
  "customerPhone": "081234567890",
  "customerId": "uuid",                    // optional, auto-create if null
  "channel": "whatsapp",
  "items": [
    {
      "productId": "uuid",
      "quantity": 2,
      "unitPrice": 60000,
      "discount": 0
    }
  ],
  "discountAmount": 0,
  "shippingAmount": 0,
  "notes": "Tolong pack rapi",
  "paymentMethod": "transfer"
}

Response 201:
{
  "success": true,
  "data": {
    "id": "uuid",
    "orderNumber": "ORD-20260519-001",
    "status": "pending",
    "totalAmount": 120000,
    "invoiceUrl": "https://..."
  }
}
```

### 4.2 List Orders
```http
GET /orders?page=1&limit=20&status=pending&date_from=2026-05-01
Authorization: Bearer <token>
X-Shop-Id: <shop_id>

Response 200: { "success": true, "data": [...], "meta": {...} }
```

### 4.3 Get Order Detail
```http
GET /orders/:id
Authorization: Bearer <token>

Response 200:
{
  "success": true,
  "data": {
    "id": "uuid",
    "orderNumber": "ORD-20260519-001",
    "customer": { "id": "uuid", "name": "Pak Budi", "phone": "..." },
    "items": [
      {
        "productName": "Beras Premium",
        "quantity": 2,
        "unitPrice": 60000,
        "totalPrice": 120000
      }
    ],
    "totalAmount": 120000,
    "status": "confirmed",
    "payments": [...],
    "timeline": [...]
  }
}
```

### 4.4 Update Order Status
```http
PATCH /orders/:id/status
Authorization: Bearer <token>
Content-Type: application/json

{
  "status": "CONFIRMED",
  "notes": "Sudah dikonfirmasi"
}

Response 200: { "success": true, "data": { <updated_order> } }
```

### 4.5 Generate Invoice
```http
POST /orders/:id/invoice
Authorization: Bearer <token>

Response 200:
{
  "success": true,
  "data": {
    "invoiceUrl": "https://cdn.umkmplatform.id/invoices/INV-xxx.pdf",
    "invoiceNumber": "INV-20260519-001"
  }
}
```

---

## 5. Finance

### 5.1 Record Transaction
```http
POST /finance/transactions
Authorization: Bearer <token>
X-Shop-Id: <shop_id>
Content-Type: application/json

{
  "type": "INCOME",
  "amount": 250000,
  "categoryId": "uuid",
  "description": "Penjualan beras",
  "transactionDate": "2026-05-19",
  "referenceType": "order",
  "referenceId": "uuid"
}

Response 201: { "success": true, "data": { <transaction> } }
```

### 5.2 Finance Summary
```http
GET /finance/summary?period=this_month
Authorization: Bearer <token>
X-Shop-Id: <shop_id>

Response 200:
{
  "success": true,
  "data": {
    "period": { "from": "2026-05-01", "to": "2026-05-31" },
    "totalIncome": 15000000,
    "totalExpense": 8000000,
    "netProfit": 7000000,
    "profitMargin": 46.67,
    "incomeByCategory": [...],
    "expenseByCategory": [...],
    "dailyTrend": [...]
  }
}
```

### 5.3 List Transactions
```http
GET /finance/transactions?type=INCOME&date_from=2026-05-01&date_to=2026-05-31&page=1&limit=20
Authorization: Bearer <token>

Response 200: { "success": true, "data": [...], "meta": {...} }
```

---

## 6. Customers

### 6.1 List Customers
```http
GET /customers?page=1&limit=20&q=Budi&segment=vip
Authorization: Bearer <token>

Response 200: { "success": true, "data": [...], "meta": {...} }
```

### 6.2 Create Customer
```http
POST /customers
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "Pak Budi",
  "phone": "081234567890",
  "waNumber": "628123456789",
  "email": "budi@email.com",
  "address": "Jl. Contoh No. 1",
  "city": "Jakarta"
}

Response 201: { "success": true, "data": { <customer> } }
```

### 6.3 Customer Analytics
```http
GET /customers/:id/analytics
Authorization: Bearer <token>

Response 200:
{
  "success": true,
  "data": {
    "totalOrders": 15,
    "totalSpent": 2500000,
    "avgOrderValue": 166667,
    "lastOrderAt": "2026-05-15T00:00:00Z",
    "favoriteProducts": [...],
    "orderFrequency": "weekly",
    "segment": "vip",
    "rfmScore": { "recency": 5, "frequency": 5, "monetary": 4 }
  }
}
```

---

## 7. AI / Chat

### 7.1 Send Message to AI (Web)
```http
POST /ai/chat
Authorization: Bearer <token>
X-Shop-Id: <shop_id>
Content-Type: application/json

{
  "message": "Buat laporan penjualan bulan ini",
  "sessionId": "session_uuid"           // optional, creates new if null
}

Response 200:
{
  "success": true,
  "data": {
    "sessionId": "session_uuid",
    "response": "Berikut laporan penjualan Mei 2026:\n\nTotal Penjualan: Rp 15.000.000...",
    "intent": "analytics.sales",
    "toolsUsed": ["get_finance_summary"],
    "processingMs": 1250
  }
}
```

### 7.2 Get Chat History
```http
GET /ai/sessions/:sessionId/history
Authorization: Bearer <token>

Response 200:
{
  "success": true,
  "data": [
    { "role": "user", "content": "...", "timestamp": "..." },
    { "role": "assistant", "content": "...", "timestamp": "..." }
  ]
}
```

---

## 8. Analytics

### 8.1 Sales Dashboard
```http
GET /analytics/sales?period=this_month&compare_to=last_month
Authorization: Bearer <token>
X-Shop-Id: <shop_id>

Response 200:
{
  "success": true,
  "data": {
    "current": {
      "totalSales": 15000000,
      "totalOrders": 245,
      "avgOrderValue": 61224,
      "newCustomers": 32
    },
    "previous": {
      "totalSales": 12000000,
      "totalOrders": 198,
      "avgOrderValue": 60606,
      "newCustomers": 25
    },
    "growth": {
      "sales": 25.0,
      "orders": 23.7,
      "avgOrderValue": 1.0
    },
    "topProducts": [...],
    "salesByDay": [...]
  }
}
```

### 8.2 AI Business Insights
```http
GET /analytics/insights
Authorization: Bearer <token>
X-Shop-Id: <shop_id>

Response 200:
{
  "success": true,
  "data": {
    "insights": [
      {
        "type": "opportunity",
        "title": "Produk Terlaris Mulai Menipis",
        "description": "Beras Premium, produk terlaris Anda, stoknya tinggal 20 kg (estimasi habis 3 hari). Pertimbangkan restock segera.",
        "action": "Restock sekarang",
        "severity": "high"
      }
    ],
    "generatedAt": "2026-05-19T09:00:00Z"
  }
}
```

---

## 9. Workflows

### 9.1 List Workflows
```http
GET /workflows
Authorization: Bearer <token>
X-Shop-Id: <shop_id>

Response 200: { "success": true, "data": [...] }
```

### 9.2 Create Workflow
```http
POST /workflows
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "Notifikasi Stok Kritis",
  "triggerType": "event",
  "triggerConfig": { "event": "stock.critical" },
  "actions": [
    {
      "type": "send_whatsapp",
      "config": {
        "to": "owner",
        "template": "Stok {{product_name}} hampir habis! Sisa: {{quantity}} {{unit}}"
      }
    }
  ]
}

Response 201: { "success": true, "data": { <workflow> } }
```

---

## 10. WebSocket Events (Real-time)

**Connection:**
```javascript
const socket = io('wss://api.umkmplatform.id', {
  auth: { token: '<access_token>' }
});
```

**Events:**
```javascript
// New order notification
socket.on('order:new', (data) => { /* { orderId, orderNumber, total } */ });

// Stock alert
socket.on('stock:critical', (data) => { /* { productId, name, quantity } */ });

// AI response streaming (web chat)
socket.on('ai:stream', (data) => { /* { sessionId, chunk, done } */ });

// Notification
socket.on('notification', (data) => { /* { type, title, message } */ });
```

---

## 11. WhatsApp Webhook

### 11.1 Incoming Message
```http
POST /webhook/whatsapp
X-Hub-Signature-256: sha256=<hmac>
Content-Type: application/json

{
  "object": "whatsapp_business_account",
  "entry": [{
    "id": "WHATSAPP_BUSINESS_ACCOUNT_ID",
    "changes": [{
      "value": {
        "messaging_product": "whatsapp",
        "messages": [{
          "from": "628123456789",
          "id": "wamid...",
          "timestamp": "1716123456",
          "text": { "body": "Cek stok beras" },
          "type": "text"
        }]
      }
    }]
  }]
}

Response 200: OK (harus response cepat, proses async)
```

---

## 12. Error Reference

| Code | HTTP | Message |
|------|------|---------|
| AUTH_001 | 401 | Token tidak valid atau expired |
| AUTH_002 | 401 | Refresh token tidak valid |
| AUTH_003 | 403 | Tidak punya akses ke resource ini |
| VALIDATION_001 | 400 | Request body tidak valid |
| PRODUCT_001 | 404 | Produk tidak ditemukan |
| PRODUCT_002 | 409 | SKU sudah digunakan |
| STOCK_001 | 422 | Stok tidak mencukupi |
| ORDER_001 | 404 | Order tidak ditemukan |
| ORDER_002 | 422 | Status transition tidak valid |
| AI_001 | 503 | AI service sedang tidak tersedia |
| AI_002 | 429 | AI request limit exceeded |
| RATE_LIMIT_001 | 429 | Terlalu banyak request |
| PLAN_001 | 402 | Fitur tidak tersedia di paket Anda |

---

*API Documentation Owner: Engineering Team*  
*Version: v1.0.0 | Status: Active*
