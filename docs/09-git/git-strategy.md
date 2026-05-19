# Git Branching Strategy
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19

---

## 1. Branch Structure

```
main ─────────────────────────────────────────── Production
  │
  ├── develop ────────────────────────────────── Integration
  │     │
  │     ├── feature/AI-123-product-module ──── Feature work
  │     ├── feature/AI-124-order-module
  │     ├── fix/AI-125-stock-race-condition ── Bug fixes
  │     └── refactor/AI-126-auth-cleanup
  │
  ├── hotfix/AI-200-critical-security-fix ───── Emergency fixes
  └── release/v1.2.0 ─────────────────────────── Release prep
```

## 2. Branch Rules

| Branch | Protected | Who Can Push | Require PR |
|--------|-----------|-------------|------------|
| `main` | Yes | Nobody directly | Yes (1 review) |
| `develop` | Yes | Nobody directly | Yes (1 review) |
| `feature/*` | No | Developer | N/A |
| `fix/*` | No | Developer | N/A |
| `hotfix/*` | No | Senior Dev | N/A |

## 3. Workflow

### Feature Development
```
1. Ambil issue dari board
2. git checkout develop && git pull
3. git checkout -b feature/AI-{issue-number}-{short-description}
4. Develop dengan commit yang atomic
5. Push dan buat PR ke develop
6. Code review & CI pass
7. Merge (Squash merge untuk clean history)
8. Delete branch setelah merge
```

### Hotfix (Production Bug)
```
1. git checkout main && git pull
2. git checkout -b hotfix/AI-{issue}-{description}
3. Fix bug
4. PR ke main DAN develop (keduanya)
5. Deploy segera setelah merge
```

## 4. Commit Convention

```
feat(scope): description

Body (opsional): Penjelasan WHY jika tidak obvious

Closes #123
```

### Scope daftar:
`api`, `web`, `wa-gateway`, `db`, `auth`, `product`, `order`, `inventory`, `finance`, `crm`, `marketing`, `analytics`, `ai`, `workflow`, `notif`, `infra`, `ci`, `docs`

---

*Git Strategy Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
