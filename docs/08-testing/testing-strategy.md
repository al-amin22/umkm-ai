# Testing Strategy
# UMKM AI Platform

**Version:** 1.0.0  
**Last Updated:** 2026-05-19

---

## 1. Testing Philosophy

**Test pyramid approach:**
```
         ┌───────┐
         │  E2E  │  ← Sedikit, mahal, test golden path
        /└───────┘\
       / ┌───────┐ \
      /  │  Int  │  \  ← Integration tests, test boundary & contract
     /   └───────┘   \
    / ┌─────────────┐ \
   /  │  Unit Tests │  \  ← Banyak, cepat, test business logic
  └───└─────────────┘───┘
```

**Coverage target:** Minimum 80% untuk business logic modules.

---

## 2. Unit Testing

### 2.1 Framework & Setup
- **Framework:** Jest + ts-jest
- **Assertion:** Jest built-in + @nestjs/testing
- **Mock:** jest.mock(), jest.spyOn()
- **Location:** Co-located dengan source file (`*.spec.ts`)

### 2.2 What to Unit Test
- Service layer (business logic) — WAJIB
- Utility functions — WAJIB
- DTO validation — WAJIB
- Guard logic — WAJIB
- Controller — OPTIONAL (integration test cukup)
- Repository — SKIP (test via integration)

### 2.3 Unit Test Example

```typescript
// inventory/inventory.service.spec.ts
describe('InventoryService', () => {
  let service: InventoryService;
  let stockRepo: jest.Mocked<StockRepository>;
  let stockLogRepo: jest.Mocked<StockLogRepository>;
  let eventBus: jest.Mocked<EventBus>;

  beforeEach(async () => {
    const module = await Test.createTestingModule({
      providers: [
        InventoryService,
        {
          provide: StockRepository,
          useValue: {
            findByProduct: jest.fn(),
            save: jest.fn(),
          },
        },
        {
          provide: StockLogRepository,
          useValue: { save: jest.fn() },
        },
        {
          provide: EventBus,
          useValue: { publish: jest.fn() },
        },
      ],
    }).compile();

    service = module.get(InventoryService);
    stockRepo = module.get(StockRepository);
    stockLogRepo = module.get(StockLogRepository);
    eventBus = module.get(EventBus);
  });

  describe('updateStock', () => {
    it('should ADD stock correctly', async () => {
      const mockStock = { productId: 'p1', quantity: 50 };
      stockRepo.findByProduct.mockResolvedValue(mockStock as any);
      stockRepo.save.mockResolvedValue({ ...mockStock, quantity: 80 } as any);

      const result = await service.updateStock({
        productId: 'p1',
        mutationType: 'ADD',
        quantity: 30,
        reason: 'Restock',
      });

      expect(result.after).toBe(80);
      expect(stockRepo.save).toHaveBeenCalledWith(
        expect.objectContaining({ quantity: 80 })
      );
    });

    it('should SUBTRACT stock and publish StockCriticalEvent if below minimum', async () => {
      const mockStock = { productId: 'p1', quantity: 25, minimumStock: 20 };
      stockRepo.findByProduct.mockResolvedValue(mockStock as any);
      stockRepo.save.mockResolvedValue({ ...mockStock, quantity: 18 } as any);

      await service.updateStock({
        productId: 'p1',
        mutationType: 'SUBTRACT',
        quantity: 7,
        reason: 'Sale',
      });

      expect(eventBus.publish).toHaveBeenCalledWith(
        expect.objectContaining({ name: 'StockCriticalEvent' })
      );
    });

    it('should throw if quantity goes negative', async () => {
      const mockStock = { productId: 'p1', quantity: 5, minimumStock: 0 };
      stockRepo.findByProduct.mockResolvedValue(mockStock as any);

      await expect(
        service.updateStock({
          productId: 'p1',
          mutationType: 'SUBTRACT',
          quantity: 10,
          reason: 'Sale',
        })
      ).rejects.toThrow('Stok tidak mencukupi');
    });
  });
});
```

---

## 3. Integration Testing

### 3.1 Framework & Setup
- **Framework:** Jest + Supertest + @nestjs/testing
- **Database:** Real PostgreSQL (test database)
- **Redis:** Real Redis (test instance)
- **Location:** `test/` directory, file pattern `*.e2e-spec.ts`

### 3.2 Integration Test Example

```typescript
// test/integration/order.integration.spec.ts
describe('Order Integration Tests', () => {
  let app: INestApplication;
  let db: DataSource;
  let accessToken: string;
  let shopId: string;

  beforeAll(async () => {
    const moduleRef = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleRef.createNestApplication();
    app.useGlobalPipes(new ValidationPipe({ whitelist: true }));
    await app.init();
    
    db = moduleRef.get(DataSource);
    
    // Seed test data
    await seedTestData(db);
    accessToken = await getTestAccessToken(app);
    shopId = await getTestShopId(db);
  });

  afterAll(async () => {
    await cleanupTestData(db);
    await app.close();
  });

  describe('POST /orders', () => {
    it('should create order and decrement stock', async () => {
      // Arrange
      const initialStock = await db.query(
        'SELECT quantity FROM stocks WHERE product_id = $1',
        ['test-product-id']
      );
      const beforeQty = initialStock[0].quantity;

      // Act
      const response = await request(app.getHttpServer())
        .post('/api/v1/orders')
        .set('Authorization', `Bearer ${accessToken}`)
        .set('X-Shop-Id', shopId)
        .send({
          customerName: 'Test Customer',
          items: [{ productId: 'test-product-id', quantity: 2, unitPrice: 60000 }],
        });

      // Assert
      expect(response.status).toBe(201);
      expect(response.body.data.orderNumber).toMatch(/^ORD-/);
      
      const afterStock = await db.query(
        'SELECT quantity FROM stocks WHERE product_id = $1',
        ['test-product-id']
      );
      expect(afterStock[0].quantity).toBe(beforeQty - 2);
    });

    it('should reject order if stock insufficient', async () => {
      const response = await request(app.getHttpServer())
        .post('/api/v1/orders')
        .set('Authorization', `Bearer ${accessToken}`)
        .set('X-Shop-Id', shopId)
        .send({
          customerName: 'Test Customer',
          items: [{ productId: 'test-product-id', quantity: 9999, unitPrice: 60000 }],
        });

      expect(response.status).toBe(422);
      expect(response.body.error.code).toBe('STOCK_001');
    });
  });
});
```

---

## 4. AI Testing

### 4.1 Intent Detection Tests
```typescript
describe('IntentDetectorService', () => {
  const testCases = [
    { input: 'cek stok beras', expectedIntent: 'stock.check' },
    { input: 'ada order baru dari pak budi', expectedIntent: 'order.create' },
    { input: 'laporan penjualan bulan ini', expectedIntent: 'analytics.sales' },
    { input: 'buat caption promo bakso', expectedIntent: 'content.generate_caption' },
    { input: 'berapa hutang yang belum dibayar', expectedIntent: 'finance.debt_receivable' },
  ];

  test.each(testCases)(
    'should detect "$expectedIntent" from "$input"',
    async ({ input, expectedIntent }) => {
      const result = await intentDetector.detect(input, mockContext);
      expect(result.category).toBe(expectedIntent);
      expect(result.confidence).toBeGreaterThan(0.7);
    }
  );
});
```

### 4.2 Tool Execution Tests
```typescript
describe('Tool: get_stock_level', () => {
  it('should return stock data for valid product', async () => {
    const result = await toolRegistry.execute('get_stock_level', {
      product_name: 'Beras Premium',
    }, mockContext);

    expect(result.success).toBe(true);
    expect(result.data).toHaveProperty('quantity');
    expect(result.data).toHaveProperty('unit');
  });
});
```

---

## 5. Test Commands

```json
// package.json scripts
{
  "scripts": {
    "test": "jest",
    "test:unit": "jest --testPathPattern='.spec.ts' --coverage",
    "test:integration": "jest --testPathPattern='.integration.spec.ts' --forceExit",
    "test:e2e": "jest --testPathPattern='.e2e-spec.ts' --forceExit",
    "test:watch": "jest --watch",
    "test:coverage": "jest --coverage --coverageReporters=lcov"
  }
}
```

---

## 6. Test Coverage Requirements

| Module | Minimum Coverage |
|--------|----------------|
| auth | 90% |
| product | 85% |
| inventory | 90% |
| order | 90% |
| finance | 85% |
| ai-orchestrator | 80% |
| workflow | 80% |
| notification | 75% |

---

## 7. Test Data Management

### 7.1 Fixtures
```typescript
// test/fixtures/product.fixture.ts
export const createProductFixture = (overrides = {}) => ({
  name: 'Test Product',
  sku: `SKU-${Date.now()}`,
  unit: 'pcs',
  sellingPrice: 50000,
  ...overrides,
});
```

### 7.2 Database Seeding for Tests
```typescript
// test/seeds/test-seed.ts
export async function seedTestData(db: DataSource): Promise<void> {
  // Create test tenant
  await db.query(`INSERT INTO tenants ...`);
  // Create test shop
  await db.query(`INSERT INTO shops ...`);
  // Create test products
  await db.query(`INSERT INTO products ...`);
  // Create test stocks
  await db.query(`INSERT INTO stocks ...`);
}

export async function cleanupTestData(db: DataSource): Promise<void> {
  await db.query(`DELETE FROM orders WHERE tenant_id = 'test-tenant-id'`);
  await db.query(`DELETE FROM products WHERE tenant_id = 'test-tenant-id'`);
  await db.query(`DELETE FROM tenants WHERE id = 'test-tenant-id'`);
}
```

---

*Testing Strategy Owner: Engineering Team*  
*Version: 1.0.0 | Status: Active*
