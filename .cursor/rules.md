# Sequifi Laravel Development Standards

You are an expert Laravel PHP developer working on the Sequifi payroll and sales management platform. Follow these comprehensive coding standards to ensure consistency, maintainability, and quality across the codebase.

## Core Principles

- **Write concise, technical responses** with accurate PHP examples
- **Follow Laravel best practices** and conventions rigorously  
- **Use object-oriented programming** with SOLID principles
- **Prefer composition over inheritance** - use traits and services
- **Write self-documenting code** with descriptive names
- **Follow PSR-12 coding standards** strictly
- **Use strict typing** everywhere: `declare(strict_types=1);`
- **Favor dependency injection** and Laravel's service container

## Technology Stack & Dependencies

- **Framework**: Laravel 10.49.1 ✅ (Upgraded from 9.x)
- **PHP Version**: 8.3.24+ (minimum 8.1.0 required for Laravel 10)
- **Database**: MySQL (primary), SQLite (metrics), BigQuery (analytics), ClickHouse (analytics logs)
- **Queue System**: Database queue driver (Redis with Laravel Horizon for future)
- **Authentication**: Laravel Sanctum 3.3.3 with custom middleware
- **Cache**: File cache (Redis ready with conditional cache:prune-stale-tags)
- **Storage**: AWS S3 with Laravel Filesystem
- **Monitoring**: Sentry 4.18.0 for error tracking and performance (Monolog 3.x compatible)
- **API Documentation**: Swagger/OpenAPI 3.0 with darkaonline/l5-swagger
- **Testing**: PHPUnit 10.5.58 with Laravel's testing tools
- **AI Development**: Laravel Boost 1.6.0 with MCP server integration

## File & Directory Organization

### Directory Structure
```
app/
├── Console/Commands/           # Artisan commands
├── Core/                      # Domain-specific core logic
│   ├── Traits/               # Shared business logic traits
│   └── Bootstraps/           # Application bootstrapping
├── Http/
│   ├── Controllers/
│   │   └── API/
│   │       ├── V1/           # API version 1
│   │       ├── V2/           # API version 2
│   │       └── V3/           # API version 3
│   ├── Middleware/           # Custom middleware
│   ├── Requests/             # Form request validation
│   └── Resources/            # API resources
├── Jobs/                     # Queue jobs
├── Models/                   # Eloquent models
├── Services/                 # Business logic services
└── Traits/                   # General-purpose traits
```

### Naming Conventions

#### Files & Classes
- **Controllers**: `PascalCase` + `Controller` suffix
  - ✅ `SalesController`, `PayrollController`
  - ❌ `sales_controller`, `SalesCtrl`

- **Models**: `PascalCase`, singular
  - ✅ `User`, `SaleMaster`, `UserCommission`
  - ❌ `Users`, `sale_master`

- **Services**: `PascalCase` + `Service` suffix
  - ✅ `QuickBooksService`, `BigQueryService`
  - ❌ `QuickBooks`, `BigQueryHelper`

- **Jobs**: `PascalCase` + `Job` suffix
  - ✅ `ProcessUserBigQueryBatchJob`, `SaleProcessJob`
  - ❌ `ProcessUserBigQuery`, `SaleProcess`

- **Requests**: `PascalCase` + `Request` suffix
  - ✅ `StoreSalesRequest`, `UpdateUserRequest`
  - ❌ `SalesValidation`, `UserForm`

- **Middleware**: `PascalCase` + descriptive name
  - ✅ `VerifyExternalApiToken`, `AdminMiddleware`
  - ❌ `TokenVerify`, `Admin`

#### Methods & Variables
- **Methods**: `camelCase`, descriptive action verbs
  - ✅ `createJournalEntry()`, `verifyTokenAndScopes()`
  - ❌ `create()`, `verify()`

- **Variables**: `camelCase` for objects, `snake_case` for arrays/primitives
  - ✅ `$userService`, `$user_data`, `$commission_amount`
  - ❌ `$UserService`, `$userData`, `$commissionAmount`

- **Constants**: `SCREAMING_SNAKE_CASE`
  - ✅ `USER_STATUS_ACTIVE`, `PAYMENT_METHOD_CREDIT_CARD`
  - ❌ `userStatusActive`, `PaymentMethodCreditCard`

#### Database
- **Tables**: `snake_case`, plural
  - ✅ `users`, `sales_masters`, `user_commissions`
  - ❌ `Users`, `SalesMaster`, `userCommission`

- **Columns**: `snake_case`
  - ✅ `first_name`, `created_at`, `social_security_no`
  - ❌ `firstName`, `CreatedAt`, `SSN`

- **Foreign Keys**: `table_id` format
  - ✅ `user_id`, `position_id`, `sale_master_id`
  - ❌ `user`, `position`, `sale_master_process_id`

## Code Structure Standards

### PHP File Header
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesRequest;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
```

### Controller Standards
```php
declare(strict_types=1);

namespace App\Http\Controllers\API\V2\Sales;

/**
 * Handles sales management operations
 * 
 * @group Sales Management
 */
final class SalesController extends BaseController
{
    public function __construct(
        private readonly SalesService $salesService,
        private readonly Logger $logger,
    ) {}

    /**
     * Create a new sale record
     *
     * @OA\Post(
     *     path="/api/v2/sales",
     *     summary="Create new sale",
     *     tags={"Sales"},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StoreSalesRequest")),
     *     @OA\Response(response=201, description="Sale created successfully")
     * )
     */
    public function store(StoreSalesRequest $request): JsonResponse
    {
        try {
            $sale = $this->salesService->createSale($request->validated());
            
            $this->logger->info('Sale created successfully', [
                'sale_id' => $sale->id,
                'user_id' => auth()->id(),
            ]);
            
            return $this->successResponse(
                message: 'Sale created successfully',
                apiName: 'store-sale',
                data: $sale,
                status: 201
            );
        } catch (SalesException $e) {
            $this->logger->error('Failed to create sale', [
                'error' => $e->getMessage(),
                'request_data' => $request->validated(),
                'user_id' => auth()->id(),
            ]);
            
            return $this->errorResponse(
                message: 'Failed to create sale',
                apiName: 'store-sale',
                data: ['error' => $e->getMessage()],
                status: 422
            );
        }
    }
}
```

### Base Controller Pattern
```php
declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Base controller providing consistent API responses
 */
abstract class BaseController extends Controller
{
    /**
     * Return successful API response
     */
    protected function successResponse(
        string $message,
        string $apiName = '',
        array|object|string $data = '',
        int $status = 200
    ): JsonResponse {
        $response = [
            'status' => true,
            'ApiName' => $apiName,
            'message' => $message
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * Return error API response
     */
    protected function errorResponse(
        string $message,
        string $apiName = '',
        array|object|string $data = '',
        int $status = 500
    ): JsonResponse {
        $response = [
            'status' => false,
            'ApiName' => $apiName,
            'message' => $message
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }
}
```

### Service Layer Standards
```php
declare(strict_types=1);

namespace App\Services;

use App\Models\Sale;
use App\Models\User;
use App\Exceptions\SalesException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling sales business logic
 */
final class SalesService
{
    public function __construct(
        private readonly Logger $logger,
        private readonly QuickBooksService $quickBooksService,
    ) {}

    /**
     * Create a new sale with commission calculations
     *
     * @param array<string, mixed> $data
     * @throws SalesException
     */
    public function createSale(array $data): Sale
    {
        try {
            return DB::transaction(function () use ($data) {
                $sale = Sale::create($data);
                
                // Calculate commissions
                $this->calculateCommissions($sale);
                
                // Sync with external systems
                $this->quickBooksService->createJournalEntry($sale->toArray());
                
                $this->logger->info('Sale created with commissions', [
                    'sale_id' => $sale->id,
                ]);
                
                return $sale;
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to create sale', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            
            throw new SalesException('Unable to create sale: ' . $e->getMessage());
        }
    }

    private function calculateCommissions(Sale $sale): void
    {
        // Implementation here
    }
}
```

### Model Standards
```php
declare(strict_types=1);

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Sales Master Model
 * 
 * @property int $id
 * @property string $pid
 * @property int $user_id
 * @property decimal $commission_amount
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class SaleMaster extends Model
{
    use HasFactory, SpatieLogsActivity;

    // Constants for status values
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'sales_masters';

    protected $fillable = [
        'pid',
        'user_id',
        'customer_name',
        'commission_amount',
        'status',
        'sale_date',
    ];

    protected $hidden = [
        'internal_notes',
        'deleted_at',
    ];

    protected $casts = [
        'commission_amount' => 'decimal:2',
        'sale_date' => 'date',
        'is_processed' => 'boolean',
        'status' => SaleStatus::class, // Use enum
    ];

    // Relationships with explicit return types
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(UserCommission::class, 'sale_master_id');
    }

    // Scopes for common queries
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // Accessors with proper return types
    public function getFormattedCommissionAttribute(): string
    {
        return '$' . number_format($this->commission_amount, 2);
    }
}
```

### Request Validation Standards
```php
declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validation request for storing sales data
 */
final class StoreSalesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request
     *
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Rule>>
     */
    public function rules(): array
    {
        return [
            'pid' => ['required', 'string', 'max:50', 'unique:sales_masters,pid'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'commission_amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'sale_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['pending', 'processed', 'cancelled'])],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom error messages
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pid.unique' => 'A sale with this PID already exists.',
            'commission_amount.min' => 'Commission amount cannot be negative.',
            'sale_date.before_or_equal' => 'Sale date cannot be in the future.',
        ];
    }

    /**
     * Handle a failed validation attempt
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
```

## Background Processing Guidelines

### ⚠️ **CRITICAL: Avoid Background Processing Unless Absolutely Required**

**Background jobs create significant server load through Laravel workers. Use them ONLY when:**

1. **Operations take longer than 30 seconds** (to avoid request timeouts)
2. **External API calls that may fail** and need retry logic
3. **Bulk operations processing 1000+ records**
4. **Email sending for critical notifications**
5. **File processing/uploads > 10MB**
6. **Data exports with large datasets**

### **Use Synchronous Processing When Possible**

```php
// ✅ PREFERRED: Direct synchronous processing
public function store(StoreSalesRequest $request): JsonResponse
{
    try {
        // Process immediately - no queue needed for simple operations
        $sale = $this->salesService->createSale($request->validated());
        
        return $this->successResponse(
            message: 'Sale created successfully',
            data: $sale
        );
    } catch (SalesException $e) {
        return $this->errorResponse('Failed to create sale', data: $e->getMessage());
    }
}

// ❌ AVOID: Unnecessary background processing for simple operations
public function store(StoreSalesRequest $request): JsonResponse
{
    // Don't queue simple operations - it wastes server resources!
    ProcessSaleJob::dispatch($request->validated());
    return $this->successResponse('Sale queued for processing');
}
```

### **Decision Matrix for Background Processing**

```php
/**
 * Use this decision logic before creating any background job
 */
class BackgroundProcessingDecision
{
    public static function shouldUseQueue(string $operation, array $data = []): bool
    {
        // Check operation complexity
        if (self::isLongRunningOperation($operation)) {
            return true; // > 30 seconds
        }
        
        // Check data volume
        if (self::isLargeDataset($data)) {
            return true; // > 1000 records
        }
        
        // Check if external API involved
        if (self::involvesExternalApi($operation)) {
            return true; // Needs retry logic
        }
        
        // Default: process synchronously to reduce server load
        return false;
    }
    
    private static function isLongRunningOperation(string $operation): bool
    {
        $longRunningOps = [
            'bulk_commission_calculation',
            'large_file_processing',
            'data_migration',
            'report_generation_large'
        ];
        
        return in_array($operation, $longRunningOps, true);
    }
    
    private static function isLargeDataset(array $data): bool
    {
        return count($data) > 1000;
    }
    
    private static function involvesExternalApi(string $operation): bool
    {
        $externalApiOps = [
            'quickbooks_sync',
            'everee_integration',
            'hubspot_sync',
            'bigquery_import'
        ];
        
        return in_array($operation, $externalApiOps, true);
    }
}

// Usage in controllers
public function processCommissions(Request $request): JsonResponse
{
    $salesData = $request->validated();
    
    if (BackgroundProcessingDecision::shouldUseQueue('commission_calculation', $salesData)) {
        ProcessCommissionsJob::dispatch($salesData);
        return $this->successResponse('Large commission batch queued for processing');
    }
    
    // Process synchronously for small datasets
    $result = $this->salesService->processCommissions($salesData);
    return $this->successResponse('Commissions processed successfully', data: $result);
}
```

### **When Background Processing is Required**

Only use background jobs for these specific scenarios:

```php
// ✅ ACCEPTABLE: Large bulk operations (>1000 records)
if (count($salesData) > 1000) {
    ProcessBulkSalesJob::dispatch($salesData);
    Log::info('Large bulk operation queued', ['count' => count($salesData)]);
} else {
    // Process synchronously for smaller datasets to reduce server load
    $this->salesService->processBulkSales($salesData);
}

// ✅ ACCEPTABLE: External API with retry logic needed
if ($this->isExternalApiCall($operation)) {
    SyncToQuickBooksJob::dispatch($sale)->onQueue('external-api');
} else {
    // Process locally without queue
    $this->processLocally($sale);
}

// ✅ ACCEPTABLE: Long-running file processing (>10MB or processing time >30s)
if ($file->size > 10 * 1024 * 1024 || $estimatedProcessingTime > 30) {
    ProcessLargeFileJob::dispatch($file);
} else {
    // Process small files synchronously
    $this->processFileSync($file);
}

// ✅ ACCEPTABLE: Critical email notifications (with retry logic)
if ($this->isCriticalNotification($notification)) {
    SendCriticalNotificationJob::dispatch($notification)->onQueue('notifications');
} else {
    // Send regular notifications synchronously
    Mail::send($notification);
}
```

### Job Standards (When Absolutely Required)

```php
declare(strict_types=1);

namespace App\Jobs\Sales;

use App\Models\SaleMaster;
use App\Services\SalesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ⚠️ ONLY USE FOR OPERATIONS THAT ABSOLUTELY REQUIRE BACKGROUND PROCESSING
 * 
 * This job processes large commission calculations that would timeout
 * in a synchronous request (>30 seconds processing time)
 */
final class ProcessLargeCommissionBatchJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes for large operations
    public int $maxExceptions = 3;

    public function __construct(
        private readonly array $salesData,
        private readonly string $batchId,
    ) {
        // Only queue if dataset is large enough to justify worker load
        if (count($salesData) < 1000) {
            Log::warning('Small dataset queued unnecessarily - consider synchronous processing', [
                'count' => count($salesData),
                'job' => self::class,
                'batch_id' => $batchId
            ]);
        }

        // Set appropriate queue based on domain
        $domain = env('DOMAIN_NAME');
        $queueName = match ($domain) {
            'hawx', 'hawxw2' => 'hawx-large-batch',
            'momentum', 'momentumv2' => 'momentum-large-batch',
            default => 'default-large-batch'
        };
        
        $this->onQueue($queueName);
    }

    public function uniqueId(): string
    {
        return 'large_commission_batch_' . $this->batchId;
    }

    public function handle(SalesService $salesService): void
    {
        try {
            Log::info('Processing large commission batch', [
                'job' => self::class,
                'batch_id' => $this->batchId,
                'count' => count($this->salesData),
                'attempt' => $this->attempts(),
            ]);
            
            // Process in chunks to manage memory usage
            $chunks = array_chunk($this->salesData, 100);
            $processed = 0;
            
            foreach ($chunks as $chunk) {
                $salesService->processCommissions($chunk);
                $processed += count($chunk);
                
                // Log progress for large batches
                if ($processed % 500 === 0) {
                    Log::info('Commission batch progress', [
                        'batch_id' => $this->batchId,
                        'processed' => $processed,
                        'total' => count($this->salesData)
                    ]);
                }
            }
            
            Log::info('Large commission batch completed successfully', [
                'batch_id' => $this->batchId,
                'total_processed' => $processed,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to process large commission batch', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Large commission batch job failed permanently', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
        
        // Notify administrators of critical failure
        // Consider implementing notification here
    }
}
```

### **Queue Configuration for Minimal Server Load**

```php
// config/queue.php - Optimize for minimal resource usage
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        
        // Limit concurrent jobs to reduce server load
        'processes' => env('QUEUE_PROCESSES', 2), // Keep low
        'tries' => 3,
        'timeout' => 60, // Short timeout for most jobs
        
        // High-priority queues for critical operations only
        'queues' => [
            'critical-external-api',  // Only for external API calls
            'large-batch-processing', // Only for >1000 record operations
            'file-processing',        // Only for large files
        ],
    ],
],
```

## Multi-Tenant Architecture

### Domain-Based Logic
```php
// Use environment-based tenant logic
$domain = env('DOMAIN_NAME');

// Configuration-based approach
$config = match ($domain) {
    'hawx', 'hawxw2' => [
        'queue' => 'hawx-sale-process',
        'features' => ['advanced_commissions', 'quickbooks_integration'],
        'validation_rules' => ['description' => 'required']
    ],
    'momentum', 'momentumv2' => [
        'queue' => 'RDS_Fox_Sales', 
        'features' => ['basic_commissions'],
        'validation_rules' => []
    ],
    default => [
        'queue' => 'default',
        'features' => ['basic_commissions'],
        'validation_rules' => []
    ]
};
```

### Domain-Specific Services
```php
declare(strict_types=1);

namespace App\Services;

final class DomainConfigService
{
    public function __construct(
        private readonly string $domain = '',
    ) {
        $this->domain = env('DOMAIN_NAME', 'default');
    }

    public function getQueueName(string $operation): string
    {
        return match ($this->domain) {
            'hawx', 'hawxw2' => "hawx-{$operation}",
            'momentum', 'momentumv2' => "momentum-{$operation}",
            default => "default-{$operation}"
        };
    }

    public function hasFeature(string $feature): bool
    {
        $features = match ($this->domain) {
            'hawx', 'hawxw2' => ['advanced_commissions', 'quickbooks_integration'],
            'momentum', 'momentumv2' => ['basic_commissions'],
            default => ['basic_commissions']
        };

        return in_array($feature, $features, true);
    }
}
```

## API Design Standards

### Response Format
```php
// Success Response Structure
{
    "status": true,
    "ApiName": "endpoint-identifier", 
    "message": "Human readable success message",
    "data": {
        // Response payload
    }
}

// Error Response Structure  
{
    "status": false,
    "ApiName": "endpoint-identifier",
    "message": "Human readable error message", 
    "errors": {
        // Validation errors or error details
    }
}
```

### Versioning Strategy
```php
// Route organization by version
Route::group(['prefix' => 'v2', 'middleware' => ['auth:sanctum']], function () {
    Route::prefix('sales')->group(function () {
        include 'sequifi/v2/sales/auth.php';
    });
    
    Route::prefix('payroll')->group(function () {
        include 'sequifi/v2/payroll/auth.php';
    });
});
```

### OpenAPI Documentation
```php
/**
 * @OA\Info(
 *    title="Sequifi API",
 *    description="Sequifi Payroll and Sales Management API",
 *    version="2.0.0"
 * )
 * @OA\SecurityScheme(
 *     type="http", 
 *     securityScheme="bearerAuth",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */

/**
 * @OA\Schema(
 *     schema="SalesResponse",
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="ApiName", type="string", example="store-sale"),
 *     @OA\Property(property="message", type="string", example="Sale created successfully"),
 *     @OA\Property(property="data", ref="#/components/schemas/Sale")
 * )
 */
```

## Security Standards

### Input Validation
```php
// Always validate input data
class StoreSalesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
```

### Data Encryption
```php
// Encrypt sensitive data in models
class User extends Model
{
    protected $fillable = ['social_security_no', 'account_no', 'routing_no'];
    
    public function setSocialSecurityNoAttribute($value): void
    {
        $this->attributes['social_security_no'] = encrypt($value);
    }
    
    public function getSocialSecurityNoAttribute($value): ?string
    {
        return $value ? decrypt($value) : null;
    }
}
```

### Authentication & Authorization
```php
// Use Laravel Sanctum with custom middleware
class VerifyExternalApiToken
{
    public function handle(Request $request, Closure $next, string $requiredScopes = ''): Response
    {
        $token = $this->extractBearerToken($request);
        $validatedToken = $this->verifyTokenAndScopes($token, $requiredScopes);
        
        if (!$validatedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $request->attributes->set('verified_token', $validatedToken);
        
        return $next($request);
    }
}
```

## Testing Standards

### Test Structure
```php
declare(strict_types=1);

namespace Tests\Feature\API\V2\Sales;

use App\Models\User;
use App\Models\SaleMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test sales API endpoints
 */
final class SalesControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_create_a_sale_successfully(): void
    {
        $saleData = [
            'pid' => 'TEST-001',
            'customer_name' => 'John Doe',
            'commission_amount' => 100.50,
            'sale_date' => '2024-01-15',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/sales', $saleData);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'message' => 'Sale created successfully',
            ]);

        $this->assertDatabaseHas('sales_masters', [
            'pid' => 'TEST-001',
            'customer_name' => 'John Doe',
        ]);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/sales', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pid', 'customer_name']);
    }
}
```

### Test Categories
- **Unit Tests**: Test individual methods and classes
- **Feature Tests**: Test API endpoints and user flows
- **Integration Tests**: Test external service integrations
- **Browser Tests**: Use Laravel Dusk for frontend testing

## Error Handling & Logging

### Exception Handling
```php
declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Custom exception for sales operations
 */
final class SalesException extends Exception
{
    public static function invalidCommissionAmount(float $amount): self
    {
        return new self("Invalid commission amount: {$amount}");
    }

    public static function userNotFound(int $userId): self
    {
        return new self("User not found with ID: {$userId}");
    }
}
```

### Logging Standards
```php
// Use structured logging with context
Log::info('Sale processed successfully', [
    'sale_id' => $sale->id,
    'user_id' => $user->id,
    'commission_amount' => $sale->commission_amount,
    'processing_time_ms' => $processingTime,
]);

Log::error('Failed to process sale', [
    'sale_id' => $sale->id,
    'error' => $exception->getMessage(),
    'stack_trace' => $exception->getTraceAsString(),
    'request_data' => $request->all(),
]);
```

## Performance & Optimization

### Database Optimization
```php
// Use eager loading to prevent N+1 queries
$users = User::with(['position', 'commissions', 'payrolls'])->get();

// Use chunks for large datasets
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        // Process user
    }
});

// Use database transactions for data integrity
DB::transaction(function () use ($data) {
    $sale = SaleMaster::create($data);
    $sale->commissions()->create($commissionData);
});
```

### Caching Strategies
```php
// Cache expensive queries
$commissionRates = Cache::remember('commission_rates', 3600, function () {
    return CommissionRate::with('position')->get();
});

// Use Redis for session data and job queues
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
    ],
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],
]
```

## Configuration Management

### Environment-Specific Config
```php
// config/sequifi.php
return [
    'domains' => [
        'hawx' => [
            'features' => ['advanced_commissions', 'quickbooks'],
            'queue_prefix' => 'hawx',
            'validation_rules' => ['description' => 'required'],
        ],
        'momentum' => [
            'features' => ['basic_commissions'],
            'queue_prefix' => 'momentum',
            'validation_rules' => [],
        ],
    ],
    
    'external_apis' => [
        'quickbooks' => [
            'enabled' => env('QUICKBOOKS_ENABLED', false),
            'sandbox' => env('QUICKBOOKS_SANDBOX', true),
        ],
        'bigquery' => [
            'enabled' => env('BIGQUERY_ENABLED', false),
            'project_id' => env('BIGQUERY_PROJECT_ID'),
        ],
    ],
];
```

## Migration & Deployment

### Migration Standards
```php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add commission calculation fields to sales_masters table
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_masters', function (Blueprint $table) {
            $table->decimal('base_commission', 10, 2)->nullable()->after('commission_amount');
            $table->decimal('override_commission', 10, 2)->nullable()->after('base_commission');
            $table->boolean('is_commission_calculated')->default(false)->after('override_commission');
            $table->timestamp('commission_calculated_at')->nullable()->after('is_commission_calculated');
            
            // Add indexes for performance
            $table->index(['user_id', 'is_commission_calculated']);
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_masters', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_commission_calculated']);
            $table->dropIndex(['created_at', 'status']);
            
            $table->dropColumn([
                'base_commission',
                'override_commission', 
                'is_commission_calculated',
                'commission_calculated_at'
            ]);
        });
    }
};
```

## Code Review Checklist

### Before Submitting Code
- [ ] All methods have explicit return types
- [ ] `declare(strict_types=1);` is present
- [ ] PHPDoc comments are complete and accurate
- [ ] No hardcoded values - use configuration or constants
- [ ] Error handling is implemented with proper logging
- [ ] Tests are written and passing (PHPUnit 10.5+)
- [ ] No N+1 query problems
- [ ] Sensitive data is encrypted
- [ ] API responses follow standard format
- [ ] Domain-specific logic is properly handled
- [ ] **Background processing is avoided unless absolutely necessary** (>30s operations, >1000 records, external APIs)
- [ ] If using queues, proper justification is documented in code comments
- [ ] Small operations are processed synchronously to reduce server load

### Laravel 10 Compatibility Checklist
- [ ] **NO deprecated $dates property** - use $casts with 'datetime' instead
- [ ] **Event broadcastOn() returns array** - wrap Channel objects: [new Channel('name')]
- [ ] **NO void return types on methods that return values**
- [ ] **Child class method signatures match parent exactly** - no extra type hints
- [ ] **Console Commands return int** from handle() method (Command::SUCCESS or Command::FAILURE)
- [ ] **NO DispatchJobs trait** - use dispatch() helper function instead
- [ ] **ValidationRule contract** for new custom rules (old Rule contract still works but deprecated)
- [ ] **DB::raw() not cast to string** - only used in query builder methods
- [ ] **Monolog 3.x compatible** - updated formatter/handler syntax if using custom logging
- [ ] **Anonymous migrations** - return new class extends Migration
- [ ] **PHP 8.1+ compatible** - no deprecated features used

### Critical Files (Require Multiple Approvals)
Per CODEOWNERS file, these files require approval from @gorakhoo7 @jaysequifi @garydalal:
- `app/Core/Traits/SaleTraits/SubroutineTrait.php`
- `app/Http/Controllers/API/V2/Sales/SalesController.php`  
- `app/Jobs/Sales/SaleProcessJob.php`
- `app/Models/UserCommission.php`
- `app/Models/UserOverrides.php`
- All commission and override calculation logic

## Tools & IDE Configuration

### Required Tools
- **PHP_CodeSniffer**: For PSR-12 compliance
- **PHPStan**: For static analysis (Level 8)
- **Laravel Pint**: For code formatting
- **Composer**: For dependency management
- **Laravel Debugbar**: For development debugging
- **Laravel Telescope**: For application monitoring

### IDE Settings
```json
// VSCode settings.json
{
    "php.validate.executablePath": "/usr/bin/php",
    "php.suggest.basic": false,
    "phpcs.standard": "PSR12",
    "editor.formatOnSave": true,
    "files.trimTrailingWhitespace": true
}
```

This comprehensive standard ensures consistent, maintainable, secure, and performant code across the Sequifi platform. Follow these guidelines strictly for all new development and refactoring efforts.