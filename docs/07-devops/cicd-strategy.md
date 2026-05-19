# CI/CD Strategy
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19

---

## 1. Pipeline Overview

```
Developer Push
      │
      ▼
GitHub Repository
      │
      ├── [PR to main] ──────► CI Pipeline
      │                              │
      │                    ┌─────────▼──────────┐
      │                    │   Quality Gates     │
      │                    │  • Lint             │
      │                    │  • Type Check       │
      │                    │  • Unit Tests       │
      │                    │  • Integration Test │
      │                    │  • Security Scan    │
      │                    │  • Build Check      │
      │                    └─────────┬───────────┘
      │                              │ All pass
      │                    ┌─────────▼──────────┐
      │                    │   Code Review       │
      │                    │   (Required)        │
      │                    └─────────┬───────────┘
      │                              │ Approved
      ▼                              ▼
[Merge to main] ───────► Staging Deploy (Auto)
                                     │
                          ┌──────────▼──────────┐
                          │  Smoke Tests         │
                          │  Integration Tests   │
                          └──────────┬──────────┘
                                     │ Pass
                          ┌──────────▼──────────┐
                          │  Manual Approval     │
                          │  (Production Gate)   │
                          └──────────┬──────────┘
                                     │ Approved
                          ┌──────────▼──────────┐
                          │  Production Deploy   │
                          │  (Zero-downtime)     │
                          └─────────────────────┘
```

---

## 2. GitHub Actions Workflows

### 2.1 CI Pipeline (`ci.yml`)

```yaml
# .github/workflows/ci.yml
name: CI

on:
  pull_request:
    branches: [main, develop]
  push:
    branches: [develop]

concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

jobs:
  lint-and-typecheck:
    name: Lint & Type Check
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Lint
        run: npm run lint
      
      - name: Type check
        run: npm run type-check

  test-api:
    name: Test API
    runs-on: ubuntu-latest
    needs: lint-and-typecheck
    
    services:
      postgres:
        image: postgres:15-alpine
        env:
          POSTGRES_PASSWORD: postgres
          POSTGRES_DB: umkm_test
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
      
      redis:
        image: redis:7-alpine
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Run database migrations
        run: npm run migration:run
        working-directory: apps/api
        env:
          DATABASE_URL: postgresql://postgres:postgres@localhost:5432/umkm_test
      
      - name: Run unit tests
        run: npm run test:unit
        working-directory: apps/api
        env:
          NODE_ENV: test
          DATABASE_URL: postgresql://postgres:postgres@localhost:5432/umkm_test
          REDIS_URL: redis://localhost:6379
      
      - name: Run integration tests
        run: npm run test:integration
        working-directory: apps/api
        env:
          NODE_ENV: test
          DATABASE_URL: postgresql://postgres:postgres@localhost:5432/umkm_test
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./apps/api/coverage/lcov.info
          flags: api

  test-web:
    name: Test Web
    runs-on: ubuntu-latest
    needs: lint-and-typecheck
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Run unit tests
        run: npm run test:unit
        working-directory: apps/web
      
      - name: Build
        run: npm run build
        working-directory: apps/web
        env:
          NEXT_PUBLIC_API_URL: http://api.example.com/api/v1

  security-scan:
    name: Security Scan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Run npm audit
        run: npm audit --audit-level=high
      
      - name: Run Trivy vulnerability scan
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          scan-ref: '.'
          severity: 'CRITICAL,HIGH'
          exit-code: '1'
      
      - name: SAST scan with Semgrep
        uses: returntocorp/semgrep-action@v1
        with:
          config: p/security-audit p/nodejs

  build-check:
    name: Build Check
    runs-on: ubuntu-latest
    needs: [test-api, test-web]
    steps:
      - uses: actions/checkout@v4
      
      - name: Build API Docker image
        run: docker build -t umkm-api:test ./apps/api
      
      - name: Build Web Docker image
        run: docker build -t umkm-web:test ./apps/web
      
      - name: Build WA Gateway Docker image
        run: docker build -t umkm-wa-gateway:test ./apps/wa-gateway
```

### 2.2 Staging Deploy (`staging-deploy.yml`)

```yaml
# .github/workflows/staging-deploy.yml
name: Deploy to Staging

on:
  push:
    branches: [main]

jobs:
  deploy-staging:
    name: Deploy Staging
    runs-on: ubuntu-latest
    environment: staging
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Log in to Docker Hub
        uses: docker/login-action@v3
        with:
          username: ${{ secrets.DOCKER_USERNAME }}
          password: ${{ secrets.DOCKER_TOKEN }}
      
      - name: Build and push images
        uses: docker/build-push-action@v5
        with:
          context: ./apps/api
          push: true
          tags: |
            yourorg/umkm-api:staging
            yourorg/umkm-api:${{ github.sha }}
      
      - name: Deploy to staging server
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.STAGING_HOST }}
          username: ${{ secrets.STAGING_USER }}
          key: ${{ secrets.STAGING_SSH_KEY }}
          script: |
            cd /opt/umkm-platform
            docker compose -f docker-compose.staging.yml pull
            docker compose -f docker-compose.staging.yml up -d --remove-orphans
            docker compose -f docker-compose.staging.yml exec api npm run migration:run
      
      - name: Smoke test
        run: |
          sleep 10
          curl -f https://staging-api.umkmplatform.id/health || exit 1
      
      - name: Notify Slack
        if: always()
        uses: slackapi/slack-github-action@v1
        with:
          payload: |
            {
              "text": "Staging deploy ${{ job.status }}: ${{ github.sha }}"
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

### 2.3 Production Deploy (`production-deploy.yml`)

```yaml
# .github/workflows/production-deploy.yml
name: Deploy to Production

on:
  workflow_dispatch:
    inputs:
      image_tag:
        description: 'Docker image tag to deploy'
        required: true
        type: string

jobs:
  deploy-production:
    name: Deploy Production
    runs-on: ubuntu-latest
    environment: production     # Requires manual approval
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy to production
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_KEY }}
          script: |
            cd /opt/umkm-platform
            # Zero-downtime: scale up new, then scale down old
            docker compose -f docker-compose.prod.yml pull
            docker compose -f docker-compose.prod.yml up -d \
              --scale api=2 --no-recreate
            sleep 10
            docker compose -f docker-compose.prod.yml up -d \
              --remove-orphans
            docker compose -f docker-compose.prod.yml exec api \
              npm run migration:run
      
      - name: Health check
        run: |
          sleep 15
          for i in {1..5}; do
            if curl -f https://api.umkmplatform.id/health; then
              echo "Health check passed"
              exit 0
            fi
            sleep 5
          done
          exit 1
      
      - name: Notify team
        if: always()
        uses: slackapi/slack-github-action@v1
        with:
          payload: |
            {
              "text": "Production deploy ${{ job.status }}"
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

---

## 3. Git Branching Strategy

Lihat detail di: `/docs/09-git/git-strategy.md`

---

## 4. Conventional Commit Standard

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

### Types:
| Type | Ketika Digunakan |
|------|-----------------|
| `feat` | Fitur baru |
| `fix` | Bug fix |
| `docs` | Perubahan dokumentasi |
| `refactor` | Refactor tanpa perubahan behavior |
| `test` | Menambah atau memperbaiki test |
| `chore` | Maintenance, dependency update |
| `perf` | Performance improvement |
| `ci` | Perubahan CI/CD config |
| `build` | Perubahan build system |

### Scopes:
`api`, `web`, `wa-gateway`, `db`, `auth`, `product`, `order`, `inventory`, `finance`, `ai`, `infra`

### Examples:
```
feat(product): add image upload with auto-compress
fix(order): prevent negative stock on concurrent orders
docs(api): update product endpoints documentation
test(inventory): add unit tests for stock mutation service
chore(deps): upgrade NestJS to v11
ci: add security scan job to CI pipeline
```

---

## 5. Release Process

### 5.1 Version Numbering
Mengikuti **Semantic Versioning** (SemVer): `MAJOR.MINOR.PATCH`

- `MAJOR` — Breaking changes (major re-architecture)
- `MINOR` — New features, backward-compatible
- `PATCH` — Bug fixes, minor improvements

### 5.2 Release Checklist
```
Pre-release:
[ ] Semua tests pass di CI
[ ] Security scan clean
[ ] Staging smoke test pass
[ ] Documentation updated
[ ] CHANGELOG updated
[ ] Database migrations tested

Production Deploy:
[ ] DB backup before deploy
[ ] Announce maintenance window (jika perlu)
[ ] Deploy dengan blue-green atau rolling
[ ] Monitor error rate 15 menit setelah deploy
[ ] Verify all health checks pass

Post-release:
[ ] Tag release di GitHub
[ ] Update release notes
[ ] Notify team via Slack
```

---

*CI/CD Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
