# Database Design & ERD
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19  
**Database:** PostgreSQL 15+

---

## 1. Database Strategy

- **Multi-tenant:** Row-Level Security (RLS) dengan `tenant_id` di setiap tabel
- **UUID v7:** Primary key time-sortable untuk performa index lebih baik
- **Soft Delete:** Data penting menggunakan `deleted_at` bukan hard delete
- **Audit Trail:** Semua tabel punya `created_at`, `updated_at`
- **JSONB:** Untuk flexible data (metadata, settings, custom fields)
- **Timezone:** Semua timestamp disimpan UTC

---

## 2. Entity Relationship Diagram (Teks)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          TENANT & AUTH DOMAIN                               │
│                                                                             │
│  ┌─────────────┐    1:N    ┌──────────────┐    1:N    ┌─────────────────┐  │
│  │   tenants   │──────────►│    shops     │──────────►│   shop_users    │  │
│  └──────┬──────┘           └──────┬───────┘           └────────┬────────┘  │
│         │1                        │1                            │           │
│         │                         │                             │           │
│         ▼N                        ▼                             │           │
│  ┌─────────────┐                  │                    ┌────────▼────────┐  │
│  │    users    │                  │                    │      roles      │  │
│  └─────────────┘                  │                    └─────────────────┘  │
│                                   │                                         │
└───────────────────────────────────┼─────────────────────────────────────────┘
                                    │
┌───────────────────────────────────┼─────────────────────────────────────────┐
│                          PRODUCT DOMAIN                                     │
│                                   │                                         │
│  ┌────────────────┐               │                                         │
│  │   categories   │               │                                         │
│  └───────┬────────┘               │                                         │
│          │N:N                     │                                         │
│  ┌───────▼────────┐  1:1   ┌─────▼────────┐  1:N   ┌──────────────────┐   │
│  │    products    │───────►│    stocks    │        │  product_images  │   │
│  └───────┬────────┘        └──────────────┘        └──────────────────┘   │
│          │                                                                  │
│          │1:N              ┌──────────────────┐                            │
│          └────────────────►│   stock_logs     │                            │
│                            └──────────────────┘                            │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                           ORDER DOMAIN                                      │
│                                                                             │
│  ┌─────────────┐  1:N  ┌──────────────┐  1:N  ┌───────────────────────┐   │
│  │  customers  │──────►│    orders    │──────►│     order_items       │   │
│  └─────────────┘       └──────┬───────┘       └───────────┬───────────┘   │
│                               │                            │               │
│                               │1:N                         │N:1            │
│                               ▼                            ▼               │
│                    ┌──────────────────┐          ┌──────────────────┐      │
│                    │  order_payments  │          │    products      │      │
│                    └──────────────────┘          └──────────────────┘      │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                          FINANCE DOMAIN                                     │
│                                                                             │
│  ┌─────────────────────┐   ┌─────────────────────┐                         │
│  │    transactions     │   │   finance_categories │                        │
│  └──────────┬──────────┘   └──────────────────────┘                        │
│             │                                                               │
│  ┌──────────▼──────────┐   ┌─────────────────────┐                         │
│  │   debt_receivables  │   │   finance_reports   │                         │
│  └─────────────────────┘   └─────────────────────┘                         │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                        AI & WORKFLOW DOMAIN                                 │
│                                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌────────────────┐ │
│  │  ai_memories │  │  ai_sessions │  │   workflows  │  │ workflow_logs  │ │
│  └──────────────┘  └──────────────┘  └──────────────┘  └────────────────┘ │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                     notification_queue                               │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Table Definitions

### 3.1 Tenant & Auth Domain

#### `tenants`
```sql
CREATE TABLE tenants (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name            VARCHAR(255) NOT NULL,
  slug            VARCHAR(100) UNIQUE NOT NULL,
  email           VARCHAR(255) UNIQUE NOT NULL,
  phone           VARCHAR(20),
  plan            VARCHAR(20) NOT NULL DEFAULT 'starter', -- 'starter', 'growth', 'enterprise'
  plan_expires_at TIMESTAMPTZ,
  status          VARCHAR(20) NOT NULL DEFAULT 'active',  -- 'active', 'suspended', 'inactive'
  settings        JSONB NOT NULL DEFAULT '{}',
  metadata        JSONB NOT NULL DEFAULT '{}',
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deleted_at      TIMESTAMPTZ
);

CREATE INDEX idx_tenants_slug ON tenants(slug);
CREATE INDEX idx_tenants_status ON tenants(status) WHERE deleted_at IS NULL;
```

#### `shops`
```sql
CREATE TABLE shops (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name            VARCHAR(255) NOT NULL,
  slug            VARCHAR(100) NOT NULL,
  description     TEXT,
  logo_url        TEXT,
  address         TEXT,
  city            VARCHAR(100),
  province        VARCHAR(100),
  phone           VARCHAR(20),
  wa_number       VARCHAR(20),              -- WhatsApp number for this shop
  wa_session_id   VARCHAR(100),            -- Active WA session ID
  business_type   VARCHAR(50),             -- 'food', 'fashion', 'electronics', etc.
  operating_hours JSONB,                   -- {mon: {open: "08:00", close: "21:00"}, ...}
  settings        JSONB NOT NULL DEFAULT '{}',
  is_active       BOOLEAN NOT NULL DEFAULT TRUE,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deleted_at      TIMESTAMPTZ,
  
  UNIQUE(tenant_id, slug)
);

CREATE INDEX idx_shops_tenant ON shops(tenant_id);
CREATE INDEX idx_shops_wa_number ON shops(wa_number) WHERE wa_number IS NOT NULL;
```

#### `users`
```sql
CREATE TABLE users (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  email           VARCHAR(255) NOT NULL,
  password_hash   VARCHAR(255),            -- NULL untuk OAuth users
  name            VARCHAR(255) NOT NULL,
  phone           VARCHAR(20),
  avatar_url      TEXT,
  auth_provider   VARCHAR(20) DEFAULT 'email', -- 'email', 'google'
  auth_provider_id VARCHAR(255),
  email_verified  BOOLEAN NOT NULL DEFAULT FALSE,
  is_active       BOOLEAN NOT NULL DEFAULT TRUE,
  last_login_at   TIMESTAMPTZ,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deleted_at      TIMESTAMPTZ,
  
  UNIQUE(tenant_id, email)
);

CREATE INDEX idx_users_tenant ON users(tenant_id);
CREATE INDEX idx_users_email ON users(email);
```

#### `shop_users` (User-Shop-Role mapping)
```sql
CREATE TABLE shop_users (
  id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  shop_id   UUID NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  user_id   UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role      VARCHAR(20) NOT NULL DEFAULT 'staff', -- 'owner', 'admin', 'staff', 'read_only'
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  
  UNIQUE(shop_id, user_id)
);

CREATE INDEX idx_shop_users_shop ON shop_users(shop_id);
CREATE INDEX idx_shop_users_user ON shop_users(user_id);
```

#### `refresh_tokens`
```sql
CREATE TABLE refresh_tokens (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash  VARCHAR(255) NOT NULL UNIQUE,
  device_info JSONB,
  ip_address  INET,
  expires_at  TIMESTAMPTZ NOT NULL,
  revoked_at  TIMESTAMPTZ,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_refresh_tokens_user ON refresh_tokens(user_id);
CREATE INDEX idx_refresh_tokens_hash ON refresh_tokens(token_hash);
```

#### `audit_logs`
```sql
CREATE TABLE audit_logs (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   UUID NOT NULL,
  shop_id     UUID,
  user_id     UUID,
  action      VARCHAR(100) NOT NULL,   -- 'product.created', 'order.cancelled', etc.
  resource    VARCHAR(50) NOT NULL,    -- 'product', 'order', etc.
  resource_id UUID,
  old_data    JSONB,
  new_data    JSONB,
  ip_address  INET,
  user_agent  TEXT,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Partition by month for performance
CREATE TABLE audit_logs_2026_05 PARTITION OF audit_logs
  FOR VALUES FROM ('2026-05-01') TO ('2026-06-01');

CREATE INDEX idx_audit_logs_tenant ON audit_logs(tenant_id, created_at DESC);
CREATE INDEX idx_audit_logs_resource ON audit_logs(resource, resource_id);
```

---

### 3.2 Product Domain

#### `categories`
```sql
CREATE TABLE categories (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   UUID NOT NULL REFERENCES tenants(id),
  shop_id     UUID NOT NULL REFERENCES shops(id),
  parent_id   UUID REFERENCES categories(id),
  name        VARCHAR(255) NOT NULL,
  slug        VARCHAR(100) NOT NULL,
  description TEXT,
  image_url   TEXT,
  sort_order  INTEGER DEFAULT 0,
  is_active   BOOLEAN NOT NULL DEFAULT TRUE,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  
  UNIQUE(shop_id, slug)
);

CREATE INDEX idx_categories_shop ON categories(shop_id);
CREATE INDEX idx_categories_parent ON categories(parent_id);
```

#### `products`
```sql
CREATE TABLE products (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id),
  shop_id         UUID NOT NULL REFERENCES shops(id),
  category_id     UUID REFERENCES categories(id),
  sku             VARCHAR(100),
  name            VARCHAR(255) NOT NULL,
  description     TEXT,
  unit            VARCHAR(20) NOT NULL DEFAULT 'pcs', -- 'kg', 'liter', 'pcs', 'pack', etc.
  base_price      BIGINT NOT NULL DEFAULT 0,          -- in IDR (smallest unit)
  selling_price   BIGINT NOT NULL DEFAULT 0,
  hpp             BIGINT DEFAULT 0,                   -- Harga Pokok Penjualan
  is_active       BOOLEAN NOT NULL DEFAULT TRUE,
  is_featured     BOOLEAN NOT NULL DEFAULT FALSE,
  images          JSONB NOT NULL DEFAULT '[]',        -- Array of image URLs
  attributes      JSONB NOT NULL DEFAULT '{}',        -- Custom attributes
  ai_tags         TEXT[],                             -- AI-generated tags for better search
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deleted_at      TIMESTAMPTZ,
  
  UNIQUE(shop_id, sku) DEFERRABLE INITIALLY DEFERRED
);

CREATE INDEX idx_products_shop ON products(shop_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_name_trgm ON products USING GIN(name gin_trgm_ops);
CREATE INDEX idx_products_ai_tags ON products USING GIN(ai_tags);
```

#### `stocks`
```sql
CREATE TABLE stocks (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id      UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  shop_id         UUID NOT NULL REFERENCES shops(id),
  quantity        DECIMAL(10,2) NOT NULL DEFAULT 0,
  minimum_stock   DECIMAL(10,2) NOT NULL DEFAULT 0,  -- Alert threshold
  maximum_stock   DECIMAL(10,2),                     -- Optional max capacity
  location        VARCHAR(100),                      -- Warehouse location
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  
  UNIQUE(product_id, shop_id)
);

CREATE INDEX idx_stocks_product ON stocks(product_id);
CREATE INDEX idx_stocks_shop ON stocks(shop_id);
-- Partial index untuk query stok kritis
CREATE INDEX idx_stocks_critical ON stocks(shop_id) 
  WHERE quantity <= minimum_stock AND minimum_stock > 0;
```

#### `stock_logs`
```sql
CREATE TABLE stock_logs (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id    UUID NOT NULL REFERENCES products(id),
  shop_id       UUID NOT NULL REFERENCES shops(id),
  type          VARCHAR(20) NOT NULL,      -- 'IN', 'OUT', 'ADJUSTMENT', 'RETURN'
  quantity      DECIMAL(10,2) NOT NULL,    -- Positive = in, Negative = out
  before_qty    DECIMAL(10,2) NOT NULL,
  after_qty     DECIMAL(10,2) NOT NULL,
  reference_type VARCHAR(50),             -- 'order', 'purchase', 'manual', 'opname'
  reference_id  UUID,
  notes         TEXT,
  created_by    UUID REFERENCES users(id),
  created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_stock_logs_product ON stock_logs(product_id, created_at DESC);
CREATE INDEX idx_stock_logs_shop ON stock_logs(shop_id, created_at DESC);
```

---

### 3.3 Customer Domain

#### `customers`
```sql
CREATE TABLE customers (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id     UUID NOT NULL REFERENCES tenants(id),
  shop_id       UUID NOT NULL REFERENCES shops(id),
  name          VARCHAR(255) NOT NULL,
  phone         VARCHAR(20),
  wa_number     VARCHAR(20),
  email         VARCHAR(255),
  address       TEXT,
  city          VARCHAR(100),
  birthday      DATE,
  gender        VARCHAR(10),
  tags          TEXT[],
  notes         TEXT,
  custom_fields JSONB DEFAULT '{}',
  -- Computed/cached fields (updated via trigger)
  total_orders      INTEGER DEFAULT 0,
  total_spent       BIGINT DEFAULT 0,
  last_order_at     TIMESTAMPTZ,
  -- Segmentation
  rfm_score     FLOAT,
  segment       VARCHAR(20),              -- 'new', 'active', 'at_risk', 'inactive', 'vip'
  created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deleted_at    TIMESTAMPTZ
);

CREATE INDEX idx_customers_shop ON customers(shop_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_customers_wa ON customers(wa_number) WHERE wa_number IS NOT NULL;
CREATE INDEX idx_customers_name_trgm ON customers USING GIN(name gin_trgm_ops);
CREATE INDEX idx_customers_segment ON customers(shop_id, segment);
```

---

### 3.4 Order Domain

#### `orders`
```sql
CREATE TABLE orders (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id),
  shop_id         UUID NOT NULL REFERENCES shops(id),
  customer_id     UUID REFERENCES customers(id),
  order_number    VARCHAR(50) NOT NULL,               -- ORD-20260519-001
  channel         VARCHAR(20) NOT NULL DEFAULT 'whatsapp', -- 'whatsapp', 'dashboard', 'api'
  status          VARCHAR(20) NOT NULL DEFAULT 'pending',
  -- PENDING, CONFIRMED, PROCESSING, SHIPPED, DELIVERED, COMPLETED, CANCELLED, REFUNDED
  
  subtotal        BIGINT NOT NULL DEFAULT 0,
  discount_amount BIGINT NOT NULL DEFAULT 0,
  tax_amount      BIGINT NOT NULL DEFAULT 0,
  shipping_amount BIGINT NOT NULL DEFAULT 0,
  total_amount    BIGINT NOT NULL DEFAULT 0,
  
  payment_status  VARCHAR(20) DEFAULT 'unpaid',       -- 'unpaid', 'partial', 'paid'
  payment_method  VARCHAR(50),
  
  shipping_address JSONB,
  notes           TEXT,
  internal_notes  TEXT,                               -- Staff only
  
  confirmed_at    TIMESTAMPTZ,
  shipped_at      TIMESTAMPTZ,
  delivered_at    TIMESTAMPTZ,
  completed_at    TIMESTAMPTZ,
  cancelled_at    TIMESTAMPTZ,
  
  created_by      UUID REFERENCES users(id),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deleted_at      TIMESTAMPTZ,
  
  UNIQUE(shop_id, order_number)
);

CREATE INDEX idx_orders_shop ON orders(shop_id, created_at DESC) WHERE deleted_at IS NULL;
CREATE INDEX idx_orders_customer ON orders(customer_id);
CREATE INDEX idx_orders_status ON orders(shop_id, status) WHERE deleted_at IS NULL;
CREATE INDEX idx_orders_number ON orders(order_number);
```

#### `order_items`
```sql
CREATE TABLE order_items (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id    UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_id  UUID REFERENCES products(id),
  product_snapshot JSONB NOT NULL,                    -- Snapshot at time of order
  quantity    DECIMAL(10,2) NOT NULL,
  unit_price  BIGINT NOT NULL,
  discount    BIGINT NOT NULL DEFAULT 0,
  total_price BIGINT NOT NULL,
  notes       TEXT
);

CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_product ON order_items(product_id);
```

#### `order_payments`
```sql
CREATE TABLE order_payments (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id        UUID NOT NULL REFERENCES orders(id),
  amount          BIGINT NOT NULL,
  method          VARCHAR(50) NOT NULL,
  provider        VARCHAR(50),                        -- 'midtrans', 'manual', etc.
  provider_ref    VARCHAR(255),                       -- External transaction ID
  status          VARCHAR(20) NOT NULL DEFAULT 'pending',
  paid_at         TIMESTAMPTZ,
  metadata        JSONB DEFAULT '{}',
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_order_payments_order ON order_payments(order_id);
```

---

### 3.5 Finance Domain

#### `finance_categories`
```sql
CREATE TABLE finance_categories (
  id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  shop_id   UUID NOT NULL REFERENCES shops(id),
  type      VARCHAR(10) NOT NULL,       -- 'INCOME', 'EXPENSE'
  name      VARCHAR(100) NOT NULL,
  icon      VARCHAR(50),
  is_system BOOLEAN DEFAULT FALSE,      -- System-default categories
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

#### `transactions`
```sql
CREATE TABLE transactions (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id),
  shop_id         UUID NOT NULL REFERENCES shops(id),
  category_id     UUID REFERENCES finance_categories(id),
  type            VARCHAR(10) NOT NULL,  -- 'INCOME', 'EXPENSE'
  amount          BIGINT NOT NULL,
  description     TEXT,
  reference_type  VARCHAR(50),          -- 'order', 'purchase', 'manual'
  reference_id    UUID,
  transaction_date DATE NOT NULL,
  notes           TEXT,
  attachments     JSONB DEFAULT '[]',
  created_by      UUID REFERENCES users(id),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_transactions_shop ON transactions(shop_id, transaction_date DESC);
CREATE INDEX idx_transactions_type ON transactions(shop_id, type, transaction_date DESC);
```

#### `debt_receivables`
```sql
CREATE TABLE debt_receivables (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id     UUID NOT NULL REFERENCES tenants(id),
  shop_id       UUID NOT NULL REFERENCES shops(id),
  type          VARCHAR(10) NOT NULL,   -- 'DEBT' (hutang), 'RECEIVABLE' (piutang)
  contact_name  VARCHAR(255) NOT NULL,
  contact_phone VARCHAR(20),
  amount        BIGINT NOT NULL,
  paid_amount   BIGINT NOT NULL DEFAULT 0,
  remaining     BIGINT GENERATED ALWAYS AS (amount - paid_amount) STORED,
  due_date      DATE,
  description   TEXT,
  status        VARCHAR(20) DEFAULT 'outstanding', -- 'outstanding', 'paid', 'overdue'
  created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_debt_receivables_shop ON debt_receivables(shop_id, type, status);
CREATE INDEX idx_debt_receivables_due ON debt_receivables(due_date) WHERE status = 'outstanding';
```

---

### 3.6 AI & Automation Domain

#### `ai_sessions`
```sql
CREATE TABLE ai_sessions (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id),
  shop_id         UUID REFERENCES shops(id),
  wa_number       VARCHAR(20),
  user_id         UUID REFERENCES users(id),
  channel         VARCHAR(20) NOT NULL,   -- 'whatsapp', 'web', 'api'
  context         JSONB NOT NULL DEFAULT '{}',  -- Current conversation context
  last_message_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  expires_at      TIMESTAMPTZ NOT NULL,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_ai_sessions_wa ON ai_sessions(wa_number, tenant_id);
CREATE INDEX idx_ai_sessions_expires ON ai_sessions(expires_at);
```

#### `ai_memories`
```sql
CREATE TABLE ai_memories (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id     UUID NOT NULL REFERENCES tenants(id),
  shop_id       UUID REFERENCES shops(id),
  wa_number     VARCHAR(20),
  memory_type   VARCHAR(20) NOT NULL,     -- 'episodic', 'semantic', 'procedural'
  key           VARCHAR(255) NOT NULL,
  content       JSONB NOT NULL,
  importance    FLOAT NOT NULL DEFAULT 0.5,
  access_count  INTEGER NOT NULL DEFAULT 0,
  last_accessed_at TIMESTAMPTZ,
  expires_at    TIMESTAMPTZ,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_ai_memories_tenant_type ON ai_memories(tenant_id, memory_type);
CREATE INDEX idx_ai_memories_wa ON ai_memories(tenant_id, wa_number) WHERE wa_number IS NOT NULL;
```

#### `ai_interaction_logs`
```sql
CREATE TABLE ai_interaction_logs (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id),
  session_id      UUID REFERENCES ai_sessions(id),
  wa_number       VARCHAR(20),
  input_message   TEXT NOT NULL,
  detected_intent VARCHAR(100),
  confidence      FLOAT,
  tools_used      TEXT[],
  output_message  TEXT,
  processing_ms   INTEGER,
  tokens_used     INTEGER,
  status          VARCHAR(20),            -- 'success', 'partial', 'failed', 'clarification'
  error_message   TEXT,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY RANGE (created_at);

CREATE INDEX idx_ai_logs_tenant ON ai_interaction_logs(tenant_id, created_at DESC);
```

#### `workflows`
```sql
CREATE TABLE workflows (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   UUID NOT NULL REFERENCES tenants(id),
  shop_id     UUID NOT NULL REFERENCES shops(id),
  name        VARCHAR(255) NOT NULL,
  description TEXT,
  trigger_type VARCHAR(50) NOT NULL,      -- 'cron', 'event', 'manual', 'webhook'
  trigger_config JSONB NOT NULL,          -- Cron expression, event name, etc.
  actions     JSONB NOT NULL,             -- Array of action definitions
  is_active   BOOLEAN NOT NULL DEFAULT TRUE,
  run_count   INTEGER DEFAULT 0,
  last_run_at TIMESTAMPTZ,
  next_run_at TIMESTAMPTZ,
  created_by  UUID REFERENCES users(id),
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_workflows_shop ON workflows(shop_id) WHERE is_active = TRUE;
CREATE INDEX idx_workflows_next_run ON workflows(next_run_at) WHERE is_active = TRUE;
```

#### `workflow_logs`
```sql
CREATE TABLE workflow_logs (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  workflow_id   UUID NOT NULL REFERENCES workflows(id),
  tenant_id     UUID NOT NULL REFERENCES tenants(id),
  status        VARCHAR(20) NOT NULL,     -- 'running', 'success', 'failed', 'partial'
  trigger_data  JSONB,
  execution_log JSONB,                    -- Detailed step-by-step execution
  duration_ms   INTEGER,
  error_message TEXT,
  started_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  completed_at  TIMESTAMPTZ
);

CREATE INDEX idx_workflow_logs_workflow ON workflow_logs(workflow_id, started_at DESC);
```

#### `notification_queue`
```sql
CREATE TABLE notification_queue (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id),
  shop_id         UUID REFERENCES shops(id),
  recipient_wa    VARCHAR(20),
  recipient_email VARCHAR(255),
  channel         VARCHAR(20) NOT NULL,   -- 'whatsapp', 'email'
  type            VARCHAR(50) NOT NULL,   -- 'order_confirm', 'stock_alert', etc.
  template_id     VARCHAR(100),
  payload         JSONB NOT NULL,
  status          VARCHAR(20) DEFAULT 'pending',
  attempts        INTEGER DEFAULT 0,
  max_attempts    INTEGER DEFAULT 3,
  scheduled_at    TIMESTAMPTZ DEFAULT NOW(),
  sent_at         TIMESTAMPTZ,
  error_message   TEXT,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_notif_queue_status ON notification_queue(status, scheduled_at) 
  WHERE status IN ('pending', 'failed');
```

---

## 4. Database Functions & Triggers

### 4.1 Auto-update `updated_at`
```sql
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply to all tables with updated_at
CREATE TRIGGER trg_products_updated_at
  BEFORE UPDATE ON products
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();
-- Repeat for all tables...
```

### 4.2 Auto-generate Order Number
```sql
CREATE OR REPLACE FUNCTION generate_order_number(p_shop_id UUID)
RETURNS VARCHAR AS $$
DECLARE
  v_count INTEGER;
  v_date VARCHAR;
BEGIN
  v_date := TO_CHAR(NOW(), 'YYYYMMDD');
  SELECT COUNT(*) + 1 INTO v_count
  FROM orders
  WHERE shop_id = p_shop_id
    AND DATE(created_at) = CURRENT_DATE;
  
  RETURN 'ORD-' || v_date || '-' || LPAD(v_count::TEXT, 3, '0');
END;
$$ LANGUAGE plpgsql;
```

### 4.3 Row-Level Security (Multi-tenant)
```sql
-- Enable RLS
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE customers ENABLE ROW LEVEL SECURITY;
-- (apply to all tenant-scoped tables)

-- RLS Policy
CREATE POLICY tenant_isolation ON products
  USING (tenant_id = current_setting('app.current_tenant_id')::uuid)
  WITH CHECK (tenant_id = current_setting('app.current_tenant_id')::uuid);
```

---

## 5. Seed Data

### 5.1 Default Finance Categories
```sql
-- System default categories untuk setiap shop baru
INSERT INTO finance_categories (tenant_id, shop_id, type, name, is_system) VALUES
  (?, ?, 'INCOME', 'Penjualan Produk', TRUE),
  (?, ?, 'INCOME', 'Jasa', TRUE),
  (?, ?, 'INCOME', 'Lain-lain', TRUE),
  (?, ?, 'EXPENSE', 'Pembelian Bahan Baku', TRUE),
  (?, ?, 'EXPENSE', 'Operasional', TRUE),
  (?, ?, 'EXPENSE', 'Gaji Karyawan', TRUE),
  (?, ?, 'EXPENSE', 'Marketing', TRUE),
  (?, ?, 'EXPENSE', 'Lain-lain', TRUE);
```

---

## 6. Migration Strategy

```
migrations/
├── 001_create_extensions.sql          -- pg_trgm, uuid-ossp, etc.
├── 002_create_tenants_auth.sql        -- tenants, users, shops
├── 003_create_products_inventory.sql  -- products, stocks, categories
├── 004_create_customers.sql           -- customers
├── 005_create_orders.sql              -- orders, order_items, payments
├── 006_create_finance.sql             -- transactions, categories, debt
├── 007_create_ai_system.sql           -- ai_sessions, memories, logs
├── 008_create_workflows.sql           -- workflows, logs, notifications
├── 009_create_rls_policies.sql        -- Row-Level Security
├── 010_create_functions_triggers.sql  -- DB functions & triggers
└── 011_seed_defaults.sql              -- Default data
```

---

*Database Architecture Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
