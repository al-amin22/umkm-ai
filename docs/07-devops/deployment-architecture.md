# Deployment Architecture
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19

---

## 1. Environment Overview

| Environment | Purpose | URL | Infrastructure |
|-------------|---------|-----|---------------|
| `local` | Development | localhost | Docker Compose |
| `staging` | QA & Testing | staging.umkmplatform.id | Docker Compose (VPS) |
| `production` | Live | umkmplatform.id | Docker Swarm / K8s |

---

## 2. Project Folder Structure

```
umkm-ai-platform/
│
├── apps/
│   ├── api/                          # NestJS Backend API
│   │   ├── src/
│   │   │   ├── app.module.ts
│   │   │   ├── main.ts
│   │   │   ├── config/
│   │   │   ├── common/
│   │   │   │   ├── decorators/
│   │   │   │   ├── filters/
│   │   │   │   ├── guards/
│   │   │   │   ├── interceptors/
│   │   │   │   ├── pipes/
│   │   │   │   └── middleware/
│   │   │   ├── modules/
│   │   │   │   ├── auth/
│   │   │   │   ├── tenant/
│   │   │   │   ├── shop/
│   │   │   │   ├── product/
│   │   │   │   ├── inventory/
│   │   │   │   ├── order/
│   │   │   │   ├── finance/
│   │   │   │   ├── customer/
│   │   │   │   ├── marketing/
│   │   │   │   ├── analytics/
│   │   │   │   ├── workflow/
│   │   │   │   ├── notification/
│   │   │   │   └── ai-orchestrator/
│   │   │   └── database/
│   │   │       ├── migrations/
│   │   │       └── seeds/
│   │   ├── test/
│   │   ├── Dockerfile
│   │   ├── package.json
│   │   ├── tsconfig.json
│   │   └── nest-cli.json
│   │
│   ├── wa-gateway/                   # WhatsApp Gateway Service
│   │   ├── src/
│   │   │   ├── app.module.ts
│   │   │   ├── main.ts
│   │   │   ├── webhook/
│   │   │   ├── sender/
│   │   │   ├── session/
│   │   │   └── queue/
│   │   ├── Dockerfile
│   │   └── package.json
│   │
│   └── web/                          # Next.js Frontend
│       ├── src/
│       │   ├── app/
│       │   │   ├── (auth)/
│       │   │   │   ├── login/
│       │   │   │   └── register/
│       │   │   ├── (dashboard)/
│       │   │   │   ├── layout.tsx
│       │   │   │   ├── page.tsx
│       │   │   │   ├── products/
│       │   │   │   ├── orders/
│       │   │   │   ├── inventory/
│       │   │   │   ├── finance/
│       │   │   │   ├── customers/
│       │   │   │   ├── marketing/
│       │   │   │   ├── analytics/
│       │   │   │   ├── workflows/
│       │   │   │   └── settings/
│       │   │   └── api/
│       │   ├── components/
│       │   │   ├── ui/               # shadcn/ui components
│       │   │   ├── layout/
│       │   │   ├── charts/
│       │   │   └── modules/
│       │   ├── hooks/
│       │   ├── stores/               # Zustand
│       │   ├── services/             # API clients
│       │   ├── types/
│       │   └── utils/
│       ├── public/
│       ├── Dockerfile
│       ├── next.config.ts
│       ├── tailwind.config.ts
│       └── package.json
│
├── packages/
│   ├── shared/                       # Shared TypeScript types & utils
│   │   ├── src/
│   │   │   ├── types/
│   │   │   ├── constants/
│   │   │   └── utils/
│   │   └── package.json
│   │
│   └── config/                       # Shared configs (ESLint, TS, etc.)
│       ├── eslint/
│       ├── typescript/
│       └── package.json
│
├── infrastructure/
│   ├── docker/
│   │   ├── nginx/
│   │   │   ├── nginx.conf
│   │   │   └── sites/
│   │   ├── postgres/
│   │   │   └── init.sql
│   │   └── monitoring/
│   │       ├── prometheus.yml
│   │       ├── grafana/
│   │       │   └── dashboards/
│   │       └── loki/
│   │           └── loki.yml
│   │
│   └── scripts/
│       ├── init-db.sh
│       ├── backup-db.sh
│       └── seed-data.sh
│
├── docs/
│   ├── 00-overview/
│   ├── 01-srs/
│   ├── 02-architecture/
│   ├── 03-database/
│   ├── 04-api/
│   ├── 05-modules/
│   ├── 06-ai-system/
│   ├── 07-devops/
│   ├── 08-testing/
│   ├── 09-git/
│   └── 10-roadmap/
│
├── .github/
│   ├── workflows/
│   │   ├── ci.yml
│   │   ├── staging-deploy.yml
│   │   └── production-deploy.yml
│   └── PULL_REQUEST_TEMPLATE.md
│
├── docker-compose.yml                # Local development
├── docker-compose.staging.yml        # Staging
├── docker-compose.prod.yml           # Production
├── turbo.json                        # Turborepo config
├── package.json                      # Root package.json
├── .env.example
├── .gitignore
└── README.md
```

---

## 3. Docker Compose — Local Development

```yaml
# docker-compose.yml
version: '3.9'

services:
  # ─────────────────────────────────
  # Application Services
  # ─────────────────────────────────
  
  api:
    build:
      context: ./apps/api
      dockerfile: Dockerfile.dev
    ports:
      - "3001:3001"
    environment:
      NODE_ENV: development
      DATABASE_URL: postgresql://postgres:postgres@postgres:5432/umkm_dev
      REDIS_URL: redis://redis:6379
      JWT_SECRET: dev-secret-key
    volumes:
      - ./apps/api/src:/app/src   # Hot reload
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - umkm-network

  wa-gateway:
    build:
      context: ./apps/wa-gateway
      dockerfile: Dockerfile.dev
    ports:
      - "3002:3002"
    environment:
      NODE_ENV: development
      API_URL: http://api:3001
      REDIS_URL: redis://redis:6379
      WA_WEBHOOK_SECRET: dev-webhook-secret
    volumes:
      - ./apps/wa-gateway/src:/app/src
    depends_on:
      - api
      - redis
    networks:
      - umkm-network

  web:
    build:
      context: ./apps/web
      dockerfile: Dockerfile.dev
    ports:
      - "3000:3000"
    environment:
      NEXT_PUBLIC_API_URL: http://localhost:3001/api/v1
      NEXT_PUBLIC_WS_URL: ws://localhost:3001
    volumes:
      - ./apps/web/src:/app/src
    depends_on:
      - api
    networks:
      - umkm-network

  # ─────────────────────────────────
  # Infrastructure Services
  # ─────────────────────────────────

  postgres:
    image: postgres:15-alpine
    ports:
      - "5432:5432"
    environment:
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: postgres
      POSTGRES_DB: umkm_dev
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./infrastructure/docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 5s
      timeout: 5s
      retries: 5
    networks:
      - umkm-network

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5
    networks:
      - umkm-network

  minio:
    image: minio/minio:latest
    ports:
      - "9000:9000"
      - "9001:9001"
    environment:
      MINIO_ROOT_USER: minioadmin
      MINIO_ROOT_PASSWORD: minioadmin123
    volumes:
      - minio_data:/data
    command: server /data --console-address ":9001"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 20s
      retries: 3
    networks:
      - umkm-network

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./infrastructure/docker/nginx/nginx.conf:/etc/nginx/nginx.conf
      - ./infrastructure/docker/nginx/sites:/etc/nginx/conf.d
    depends_on:
      - api
      - web
    networks:
      - umkm-network

  # ─────────────────────────────────
  # Monitoring (Optional for dev)
  # ─────────────────────────────────

  prometheus:
    image: prom/prometheus:latest
    ports:
      - "9090:9090"
    volumes:
      - ./infrastructure/docker/monitoring/prometheus.yml:/etc/prometheus/prometheus.yml
    networks:
      - umkm-network
    profiles:
      - monitoring

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3003:3000"
    environment:
      GF_SECURITY_ADMIN_PASSWORD: admin
    volumes:
      - grafana_data:/var/lib/grafana
      - ./infrastructure/docker/monitoring/grafana/dashboards:/etc/grafana/provisioning/dashboards
    depends_on:
      - prometheus
    networks:
      - umkm-network
    profiles:
      - monitoring

volumes:
  postgres_data:
  redis_data:
  minio_data:
  grafana_data:

networks:
  umkm-network:
    driver: bridge
```

---

## 4. Dockerfile Examples

### 4.1 API Dockerfile (Production)
```dockerfile
# apps/api/Dockerfile
FROM node:20-alpine AS base
WORKDIR /app
RUN apk add --no-cache libc6-compat

# Dependencies stage
FROM base AS deps
COPY package*.json ./
RUN npm ci --only=production && npm cache clean --force

# Build stage
FROM base AS builder
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Production stage
FROM base AS runner
ENV NODE_ENV=production
RUN addgroup --system --gid 1001 nodejs
RUN adduser --system --uid 1001 nestjs
USER nestjs

COPY --from=deps --chown=nestjs:nodejs /app/node_modules ./node_modules
COPY --from=builder --chown=nestjs:nodejs /app/dist ./dist
COPY --from=builder --chown=nestjs:nodejs /app/package.json ./package.json

EXPOSE 3001
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
  CMD curl -f http://localhost:3001/health || exit 1

CMD ["node", "dist/main.js"]
```

### 4.2 Web Dockerfile (Production)
```dockerfile
# apps/web/Dockerfile
FROM node:20-alpine AS base
WORKDIR /app

FROM base AS deps
COPY package*.json ./
RUN npm ci && npm cache clean --force

FROM base AS builder
COPY package*.json ./
RUN npm ci
COPY . .
ENV NEXT_TELEMETRY_DISABLED 1
RUN npm run build

FROM base AS runner
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED 1

RUN addgroup --system --gid 1001 nodejs
RUN adduser --system --uid 1001 nextjs
USER nextjs

COPY --from=builder /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static

EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=10s CMD curl -f http://localhost:3000/ || exit 1
CMD ["node", "server.js"]
```

---

## 5. Environment Variables

### 5.1 API Service (.env)
```bash
# App
NODE_ENV=production
PORT=3001
APP_URL=https://api.umkmplatform.id
FRONTEND_URL=https://umkmplatform.id

# Database
DATABASE_URL=postgresql://user:password@host:5432/umkm_prod
DATABASE_POOL_MIN=5
DATABASE_POOL_MAX=20

# Redis
REDIS_URL=redis://:password@host:6379

# JWT
JWT_SECRET=<very-long-random-secret>
JWT_ACCESS_EXPIRES=15m
JWT_REFRESH_EXPIRES=30d

# OpenClaw AI
OPENCLAW_API_KEY=<key>
OPENCLAW_API_URL=https://api.openclaw.ai/v1
OPENCLAW_MODEL=openclaw-pro-latest
OPENCLAW_MAX_TOKENS=4096

# WhatsApp
WA_PROVIDER=meta                        # 'meta' or 'third-party'
WA_ACCESS_TOKEN=<token>
WA_PHONE_NUMBER_ID=<id>
WA_WEBHOOK_SECRET=<secret>
WA_BUSINESS_ACCOUNT_ID=<id>

# Storage (MinIO)
MINIO_ENDPOINT=minio.umkmplatform.id
MINIO_PORT=443
MINIO_USE_SSL=true
MINIO_ACCESS_KEY=<key>
MINIO_SECRET_KEY=<secret>
MINIO_BUCKET_NAME=umkm-platform

# Payment (Midtrans)
MIDTRANS_SERVER_KEY=<key>
MIDTRANS_CLIENT_KEY=<key>
MIDTRANS_IS_PRODUCTION=true

# Email (SMTP)
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=noreply@umkmplatform.id
SMTP_PASS=<password>

# Logging
LOG_LEVEL=info
LOKI_URL=http://loki:3100
```

---

## 6. Nginx Configuration

```nginx
# infrastructure/docker/nginx/nginx.conf
worker_processes auto;
events { worker_connections 1024; }

http {
  # Rate limiting zones
  limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
  limit_req_zone $binary_remote_addr zone=auth:10m rate=10r/m;
  
  # Upstream services
  upstream api_backend {
    server api:3001;
    keepalive 32;
  }
  
  upstream web_frontend {
    server web:3000;
  }
  
  upstream wa_gateway {
    server wa-gateway:3002;
  }
  
  # API Server
  server {
    listen 80;
    server_name api.umkmplatform.id;
    
    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Auth endpoints — stricter rate limit
    location /api/v1/auth/ {
      limit_req zone=auth burst=5 nodelay;
      proxy_pass http://api_backend;
      proxy_set_header Host $host;
      proxy_set_header X-Real-IP $remote_addr;
      proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
    
    # WA Webhook — high throughput
    location /api/v1/webhook/whatsapp {
      proxy_pass http://wa_gateway;
      proxy_set_header Host $host;
      proxy_set_header X-Real-IP $remote_addr;
      proxy_read_timeout 10s;
    }
    
    # General API
    location /api/ {
      limit_req zone=api burst=20 nodelay;
      proxy_pass http://api_backend;
      proxy_set_header Host $host;
      proxy_set_header X-Real-IP $remote_addr;
      proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
      proxy_read_timeout 60s;
    }
  }
  
  # Web Frontend
  server {
    listen 80;
    server_name umkmplatform.id www.umkmplatform.id;
    
    location / {
      proxy_pass http://web_frontend;
      proxy_set_header Host $host;
      proxy_set_header X-Real-IP $remote_addr;
    }
    
    # Static assets caching
    location /_next/static/ {
      proxy_pass http://web_frontend;
      expires 365d;
      add_header Cache-Control "public, immutable";
    }
  }
}
```

---

## 7. CI/CD Pipeline

Lihat detail di: `/docs/07-devops/cicd-strategy.md`

---

## 8. Monitoring Setup

### 8.1 Health Check Endpoints
```
GET /health              → { status: "ok", uptime: 1234 }
GET /health/db           → { status: "ok", latency: 5 }
GET /health/redis        → { status: "ok", latency: 1 }
GET /health/queue        → { status: "ok", depth: 12 }
```

### 8.2 Prometheus Metrics
```
GET /metrics             → Prometheus format metrics
```

Lihat detail di: `/docs/07-devops/monitoring-logging.md`

---

*Deployment Architecture Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
