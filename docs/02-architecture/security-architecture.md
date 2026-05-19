# Security Architecture
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19

---

## 1. Security Layers

```
Internet → Nginx (WAF, TLS) → Rate Limiter → JWT Guard → RBAC Guard → RLS (DB)
```

## 2. Authentication Flow

```
Login Request
    ↓
bcrypt verify (cost=12)
    ↓
Generate: Access Token (JWT, 15 min) + Refresh Token (JWT, 30 days)
    ↓
Store refresh token hash in DB
    ↓
Return both tokens to client

Access Token Usage:
    Request → Validate JWT signature → Extract claims (userId, tenantId, role)
            → Inject to request context → Guard checks permission

Token Refresh:
    POST /auth/refresh with refresh token
    → Verify hash in DB
    → Rotate: invalidate old, issue new pair
    → Old refresh token is blacklisted
```

## 3. RBAC Permission Matrix

```
Permission                   | super_admin | owner | admin | staff | read_only
-----------------------------|-------------|-------|-------|-------|----------
product:create               |      ✓      |   ✓   |   ✓   |       |
product:read                 |      ✓      |   ✓   |   ✓   |   ✓   |    ✓
product:update               |      ✓      |   ✓   |   ✓   |       |
product:delete               |      ✓      |   ✓   |       |       |
order:create                 |      ✓      |   ✓   |   ✓   |   ✓   |
order:read                   |      ✓      |   ✓   |   ✓   |   ✓   |    ✓
order:update_status          |      ✓      |   ✓   |   ✓   |   ✓   |
order:cancel                 |      ✓      |   ✓   |   ✓   |       |
finance:read                 |      ✓      |   ✓   |   ✓   |       |    ✓
finance:write                |      ✓      |   ✓   |   ✓   |       |
analytics:read               |      ✓      |   ✓   |   ✓   |       |
user:manage                  |      ✓      |   ✓   |       |       |
shop:settings                |      ✓      |   ✓   |       |       |
ai:use                       |      ✓      |   ✓   |   ✓   |   ✓   |
```

## 4. Data Encryption

- **At-rest:** PostgreSQL TDE (untuk production managed DB)
- **In-transit:** TLS 1.3 untuk semua koneksi
- **Sensitive fields:** WA webhook secret, payment keys di-encrypt di DB

## 5. Input Validation

```typescript
// Setiap DTO menggunakan class-validator
class CreateProductDto {
  @IsString()
  @MaxLength(255)
  @Transform(({ value }) => sanitizeHtml(value))
  name: string;

  @IsNumber()
  @Min(0)
  @Max(999999999)
  sellingPrice: number;

  // File upload validation
  @IsOptional()
  @IsUrl()
  imageUrl?: string;
}

// Global validation pipe
app.useGlobalPipes(new ValidationPipe({
  whitelist: true,           // Strip unknown fields
  forbidNonWhitelisted: true,
  transform: true,
  transformOptions: { enableImplicitConversion: false },
}));
```

## 6. Security Headers (Nginx)

```nginx
add_header X-Frame-Options "DENY";
add_header X-Content-Type-Options "nosniff";
add_header X-XSS-Protection "1; mode=block";
add_header Referrer-Policy "strict-origin-when-cross-origin";
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; ...";
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()";
```

## 7. Audit Logging

Semua aksi kritis dilog secara otomatis:
- User login / logout
- Data creation, update, deletion (product, order, finance, customer)
- Permission changes
- Settings changes
- Export data

```typescript
// Audit log interceptor
@Injectable()
class AuditLogInterceptor implements NestInterceptor {
  intercept(context: ExecutionContext, next: CallHandler) {
    return next.handle().pipe(
      tap(() => {
        const request = context.switchToHttp().getRequest();
        this.auditService.log({
          userId: request.user.id,
          tenantId: request.user.tenantId,
          action: `${request.method.toLowerCase()}.${request.route.path}`,
          ip: request.ip,
          userAgent: request.headers['user-agent'],
        });
      })
    );
  }
}
```

## 8. Secret Management

- Semua secrets via environment variables
- Production: secrets disimpan di secret manager (tidak di Git)
- Rotation: JWT secret dapat dirotasi tanpa downtime (dual-key support)
- Tidak ada hardcoded credential di codebase

---

*Security Architecture Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
