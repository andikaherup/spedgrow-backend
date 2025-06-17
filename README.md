# Transaction History Backend - Technical Documentation

## Overview

A robust Laravel-based REST API for managing financial transactions with NFC data support, built with modern PHP practices and optimized for performance and security.

## Architecture

### System Architecture

```
┌─────────────────┐    HTTP Requests    ┌─────────────────┐
│   Client Apps   │◄──────────────────►│   Laravel API   │
│   (Web/Mobile)  │                    │                 │
└─────────────────┘                    │ ┌─────────────┐ │
                                       │ │ Controllers │ │
                                       │ │ Middleware  │ │
                                       │ │ Models      │ │
                                       │ │ Validation  │ │
                                       │ └─────────────┘ │
                                       └─────────────────┘
                                                │
                                                ▼
                                       ┌─────────────────┐
                                       │   Database      │
                                       │ (SQLite/MySQL)  │
                                       └─────────────────┘
```

### Technology Stack

-   **Framework**: Laravel 11
-   **PHP Version**: 8.1+
-   **Database**: SQLite (development), MySQL 8.0+ (production)
-   **API Style**: RESTful JSON API
-   **Authentication**: Ready for Sanctum/JWT implementation
-   **Validation**: Form Request validation
-   **Testing**: PHPUnit

### Project Structure

```
transaction-history-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── TransactionController.php
│   │   └── Middleware/
│   │       └── CorsMiddleware.php
│   └── Models/
│       └── Transaction.php
├── database/
│   ├── factories/
│   │   └── TransactionFactory.php
│   ├── migrations/
│   │   └── create_transactions_table.php
│   └── seeders/
│       └── TransactionSeeder.php
├── routes/
│   ├── api.php
│   └── web.php
└── tests/
    └── Feature/
        └── TransactionApiTest.php
```

## Database Design

### Transactions Table Schema

```sql
CREATE TABLE transactions (
    id                  BIGINT PRIMARY KEY AUTO_INCREMENT,
    transaction_id      VARCHAR(255) UNIQUE NOT NULL,
    amount             DECIMAL(10,2) NOT NULL,
    currency           VARCHAR(3) DEFAULT 'USD' NOT NULL,
    type               ENUM('debit', 'credit') NOT NULL,
    status             ENUM('pending', 'completed', 'failed') NOT NULL,
    merchant_name      VARCHAR(255) NULL,
    category           VARCHAR(100) NULL,
    nfc_data           JSON NULL,
    transaction_date   TIMESTAMP NOT NULL,
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,

    -- Performance Indexes
    INDEX idx_transaction_date_status (transaction_date, status),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id)
);
```

### NFC Data JSON Structure

```json
{
    "card_id": "CARD_ABC123XYZ",
    "terminal_id": "TERM_789456",
    "signal_strength": -45,
    "raw_data": {
        "timestamp": "2025-06-16T10:30:00Z",
        "simulated": true,
        "protocol": "ISO14443"
    }
}
```

### Data Relationships

```php
// Transaction Model Scopes
public function scopeByDateRange(Builder $query, $startDate, $endDate)
public function scopeByType(Builder $query, $type)
public function scopeByStatus(Builder $query, $status)
public function scopeWithNfc(Builder $query)
public function scopeSearch(Builder $query, $search)
```

## API Documentation

### Base Configuration

-   **Base URL**: `http://localhost:8000/api/v1` (development)
-   **Content-Type**: `application/json`
-   **Authentication**: None (development), Ready for API keys/tokens

### Endpoints

#### Transaction Management

| Method | Endpoint                      | Description                    | Auth Required |
| ------ | ----------------------------- | ------------------------------ | ------------- |
| GET    | `/transactions`               | List transactions with filters | No            |
| POST   | `/transactions`               | Create new transaction         | No            |
| GET    | `/transactions/{id}`          | Get single transaction         | No            |
| GET    | `/transactions/stats/summary` | Get summary statistics         | No            |
| GET    | `/transactions/nfc/recent`    | Get recent NFC transactions    | No            |

#### GET /transactions

**Parameters:**

```
page: integer (default: 1)
per_page: integer (default: 20, max: 100)
type: string (debit|credit)
status: string (pending|completed|failed)
start_date: date (ISO 8601 format)
end_date: date (ISO 8601 format)
search: string (searches merchant_name, transaction_id, category)
nfc_only: boolean (filter NFC transactions only)
```

**Response:**

```json
{
    "data": [
        {
            "id": 1,
            "transaction_id": "TXN_1718542200_abc123",
            "amount": "99.99",
            "currency": "USD",
            "type": "debit",
            "status": "completed",
            "merchant_name": "Coffee Shop Downtown",
            "category": "food",
            "nfc_data": {
                "card_id": "CARD_4532123456789012",
                "terminal_id": "TERM_789123",
                "signal_strength": -42
            },
            "transaction_date": "2025-06-16T08:30:00.000000Z",
            "created_at": "2025-06-16T08:30:05.000000Z",
            "updated_at": "2025-06-16T08:30:05.000000Z"
        }
    ],
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
}
```

#### POST /transactions

**Request Body:**

```json
{
    "amount": 149.99,
    "currency": "USD",
    "type": "debit",
    "status": "completed",
    "merchant_name": "Electronics Store",
    "category": "shopping",
    "nfc_data": {
        "card_id": "CARD_9876543210",
        "terminal_id": "TERM_456789",
        "signal_strength": -38
    },
    "transaction_date": "2025-06-16T14:20:00Z"
}
```

**Validation Rules:**

```php
'amount' => 'required|numeric|min:0.01|max:999999.99',
'currency' => 'required|string|size:3',
'type' => 'required|in:debit,credit',
'status' => 'required|in:pending,completed,failed',
'merchant_name' => 'nullable|string|max:255',
'category' => 'nullable|string|max:100',
'nfc_data' => 'nullable|array',
'nfc_data.card_id' => 'nullable|string|max:100',
'nfc_data.terminal_id' => 'nullable|string|max:50',
'nfc_data.signal_strength' => 'nullable|integer|min:-100|max:0',
'transaction_date' => 'required|date'
```

#### GET /transactions/stats/summary

**Response:**

```json
{
    "total_transactions": 150,
    "total_amount": 15750.5,
    "credit_amount": 3200.0,
    "debit_amount": 12550.5,
    "nfc_transactions": 45,
    "pending_transactions": 3,
    "completed_transactions": 145,
    "failed_transactions": 2
}
```

## Security Implementation

### Input Validation

```php
// TransactionController validation
$validated = $request->validate([
    'amount' => 'required|numeric|min:0.01|max:999999.99',
    'currency' => 'required|string|size:3',
    'type' => 'required|in:debit,credit',
    'status' => 'required|in:pending,completed,failed',
    'merchant_name' => 'nullable|string|max:255',
    'category' => 'nullable|string|max:100',
    'nfc_data' => 'nullable|array',
    'transaction_date' => 'required|date'
]);
```

### SQL Injection Prevention

```php
// Using Eloquent ORM with parameterized queries
$transactions = Transaction::where('type', $type)
                          ->where('status', $status)
                          ->whereBetween('transaction_date', [$startDate, $endDate])
                          ->paginate(20);
```

### CORS Configuration

```php
// CorsMiddleware.php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);

    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Key');

    if ($request->getMethod() === 'OPTIONS') {
        return response('', 200, $response->headers->all());
    }

    return $response;
}
```

### Error Handling

```php
// Centralized exception handling
try {
    $transaction = Transaction::create($validated);
    Log::info('Transaction created', ['transaction_id' => $transaction->transaction_id]);
    return response()->json($transaction, 201);
} catch (ValidationException $e) {
    return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
} catch (\Exception $e) {
    Log::error('Transaction creation error: ' . $e->getMessage());
    return response()->json(['error' => 'Failed to create transaction'], 500);
}
```

## Performance Optimizations

### Database Optimization

#### Strategic Indexing

```sql
-- Primary performance indexes
INDEX idx_transaction_date_status (transaction_date, status)  -- For date range queries
INDEX idx_type (type)                                        -- For filtering by type
INDEX idx_status (status)                                    -- For status filtering
INDEX idx_transaction_id (transaction_id)                    -- For unique lookups
```

#### Query Optimization

```php
// Efficient pagination with proper ordering
$transactions = Transaction::query()
    ->when($request->has('type'), fn($q) => $q->where('type', $request->type))
    ->when($request->has('status'), fn($q) => $q->where('status', $request->status))
    ->when($request->has('search'), fn($q) => $q->where(function($sq) use ($request) {
        $sq->where('merchant_name', 'LIKE', "%{$request->search}%")
           ->orWhere('transaction_id', 'LIKE', "%{$request->search}%")
           ->orWhere('category', 'LIKE', "%{$request->search}%");
    }))
    ->orderBy('transaction_date', 'desc')
    ->paginate($request->get('per_page', 20));
```

#### Model Scopes for Reusability

```php
// Transaction.php Model Scopes
public function scopeByDateRange(Builder $query, $startDate, $endDate)
{
    return $query->whereBetween('transaction_date', [$startDate, $endDate]);
}

public function scopeByType(Builder $query, $type)
{
    return $query->where('type', $type);
}

public function scopeWithNfc(Builder $query)
{
    return $query->whereNotNull('nfc_data');
}
```

### Response Optimization

```php
// Efficient summary calculation
$summary = [
    'total_transactions' => Transaction::byDateRange($startDate, $endDate)->count(),
    'total_amount' => round(Transaction::byDateRange($startDate, $endDate)->sum('amount'), 2),
    'credit_amount' => round(Transaction::byDateRange($startDate, $endDate)->where('type', 'credit')->sum('amount'), 2),
    'debit_amount' => round(Transaction::byDateRange($startDate, $endDate)->where('type', 'debit')->sum('amount'), 2),
    'nfc_transactions' => Transaction::byDateRange($startDate, $endDate)->whereNotNull('nfc_data')->count(),
];
```

### Caching Strategy (Production Ready)

```php
// Cache configuration for production
'cache' => [
    'summary' => 600,     // 10 minutes
    'transactions' => 300, // 5 minutes
    'nfc_recent' => 180   // 3 minutes
];

// Implementation example
$summary = Cache::remember("summary_{$startDate}_{$endDate}", 600, function () use ($startDate, $endDate) {
    return [
        'total_transactions' => Transaction::byDateRange($startDate, $endDate)->count(),
        // ... other calculations
    ];
});
```

## Testing Strategy

### Unit Tests

```php
// tests/Feature/TransactionApiTest.php
class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_transactions()
    {
        Transaction::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/transactions');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => ['*' => ['id', 'transaction_id', 'amount']],
                    'current_page',
                    'last_page',
                    'total'
                ]);
    }

    public function test_can_create_transaction()
    {
        $transactionData = [
            'amount' => 99.99,
            'currency' => 'USD',
            'type' => 'debit',
            'status' => 'completed',
            'merchant_name' => 'Test Merchant',
            'transaction_date' => now()->toISOString()
        ];

        $response = $this->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(201)
                ->assertJsonStructure(['id', 'transaction_id']);

        $this->assertDatabaseHas('transactions', [
            'amount' => 99.99,
            'merchant_name' => 'Test Merchant'
        ]);
    }

    public function test_validates_transaction_creation()
    {
        $invalidData = ['amount' => -10];

        $response = $this->postJson('/api/v1/transactions', $invalidData);

        $response->assertStatus(422);
    }
}
```

### Performance Testing

```bash
# Load testing with Apache Bench
ab -n 1000 -c 10 http://localhost:8000/api/v1/transactions

# Database performance testing
php artisan tinker
Benchmark::dd(fn() => Transaction::with('relations')->paginate(100));
```

## Deployment & DevOps

### Environment Configuration

#### Development (.env)

```env
APP_NAME="Transaction History API"
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

#### Production (.env)

```env
APP_NAME="Transaction History API"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=transaction_history_prod
DB_USERNAME=prod_user
DB_PASSWORD=secure_password

CACHE_DRIVER=redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=redis_password
```

### Deployment Commands

```bash
# Production deployment
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

### Server Configuration

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /path/to/transaction-history-backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.html index.htm index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Monitoring & Maintenance

### Logging Configuration

```php
// config/logging.php
'channels' => [
    'api' => [
        'driver' => 'daily',
        'path' => storage_path('logs/api.log'),
        'level' => 'info',
        'days' => 14,
    ],
],

// Usage in controllers
Log::channel('api')->info('Transaction created', [
    'transaction_id' => $transaction->transaction_id,
    'amount' => $transaction->amount,
    'user_ip' => $request->ip()
]);
```

### Health Checks

```php
// Built-in health check endpoint
GET /up

// Custom health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'timestamp' => now()->toISOString()
    ]);
});
```

### Performance Monitoring

```php
// Query monitoring
DB::listen(function ($query) {
    if ($query->time > 1000) { // Log slow queries (>1s)
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'time' => $query->time,
            'bindings' => $query->bindings
        ]);
    }
});
```

## run locally

# 1. Run database migrations

php artisan migrate

# 2. Seed database with sample data

php artisan db:seed

# 3. Or do both in one command

php artisan migrate --seed

# 4. Start the Laravel development server

php artisan serve

## Future Enhancements

### Planned Features

1. **Authentication System**: Sanctum token authentication
2. **Rate Limiting**: API throttling for production
3. **Advanced Caching**: Redis integration
4. **Queue System**: Background job processing
5. **Real-time Events**: WebSocket support for live updates

### Technical Improvements

1. **API Versioning**: v2 API with enhanced features
2. **Database Sharding**: Horizontal scaling support
3. **Microservices**: Service decomposition for scale
4. **Advanced Analytics**: Transaction pattern analysis
5. **Audit Logging**: Complete transaction audit trail

This backend provides a solid foundation for a production-grade transaction management system with excellent performance, security, and scalability characteristics.
