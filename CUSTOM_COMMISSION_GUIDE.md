# Custom Commission Calculation - Learning Guide

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    SEQUIFI COMMISSION SYSTEM                 │
└─────────────────────────────────────────────────────────────┘

1. COMMISSION TYPES SUPPORTED:
   ├── per sale: Fixed amount per sale
   ├── percent: Percentage based (KW or gross)
   ├── custom field: Formula using custom sales fields
   └── tier: Tiered calculation by milestone or amount

2. DATA FLOW:
   ┌─────────────┐
   │  Sale Data  │
   │ (KW, Price) │
   └──────┬──────┘
          │
          ├─→ Check Commission Settings
          │   - Get PositionCommission records
          │   - Apply effective_date filters
          │   - Get upfronts, overrides, deductions
          │
          ├─→ Calculate Commission (subroutineThree)
          │   - Base commission calculation
          │   - Apply tiers if applicable
          │   - Apply overrides
          │   - Apply upfronts
          │   - Apply deductions
          │
          ├─→ Store in UserCommission
          │   - commission_amount
          │   - commission_type
          │   - milestone_date
          │   - user_id, pid
          │
          └─→ Display/Export
              - Commission reports
              - Payroll processing
              - Reconciliation
```

---

## 3. KEY FILES & RESPONSIBILITIES

| File | Purpose | Key Methods |
|------|---------|-------------|
| `app/Models/PositionCommission.php` | Define commission settings | `booted()` - Custom field conversion |
| `app/Models/PositionCommissionUpfronts.php` | Define upfront amounts | Percentage/per sale storage |
| `app/Models/UserCommission.php` | Store calculated commission | Holds calculated commission records |
| `app/Core/Traits/SaleTraits/SubroutineTrait.php` | Main calculation engine | `subroutineThree()` - Core logic |
| `routes/sequifi/v2/positioncommission/auth.php` | API endpoints | GET, POST, DELETE operations |
| `app/Http/Controllers/API/Setting/PositionCommissionController.php` | API handling | Request validation & processing |

---

## 4. PRACTICAL EXAMPLE: "Bonus Commission" Calculation

Let's add a **BONUS COMMISSION** system that:
- Pays 10% bonus when KW > 50
- Paid on top of regular commission
- Applies to specific products only

### Step 1: Create Migration for Bonus Table

```php
// database/migrations/2026_02_25_000001_create_bonus_commissions_table.php

<?php
namespace Database\Migrations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PositionBonusCommission extends Model
{
    protected $table = 'position_bonus_commissions';

    protected $fillable = [
        'position_id',
        'product_id',
        'kw_threshold',
        'bonus_percentage',
        'bonus_amount',
        'bonus_type',
        'description',
        'effective_date',
        'status',
    ];

    protected $casts = [
        'kw_threshold' => 'decimal:2',
        'bonus_percentage' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'effective_date' => 'date',
        'status' => 'boolean',
    ];

    // Relationships
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function product()
    {
        return $this->belongsTo(Products::class);
    }

    /**
     * Get active bonus commission for given date
     */
    public static function getActiveBonus($positionId, $productId, $date)
    {
        return self::where('position_id', $positionId)
            ->where('product_id', $productId)
            ->where('status', true)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_date')
                    ->orWhere('effective_date', '<=', $date);
            })
            ->orderBy('effective_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();
    }
}
```

### Step 3: Create Bonus Calculation Trait

```php
// app/Core/Traits/SaleTraits/BonusCommissionTrait.php

<?php
namespace App\Core\Traits\SaleTraits;

use App\Models\PositionBonusCommission;
use App\Models\UserCommission;

trait BonusCommissionTrait
{
    /**
     * Calculate bonus commission
     *
     * @param object $sale - Sale object
     * @param integer $userId - User ID
     * @param integer $positionId - Position ID
     * @param integer $productId - Product ID
     * @param string $date - Effective date
     * @param float $baseCommission - Base commission amount
     * @return float - Bonus amount
     */
    public function calculateBonusCommission($sale, $userId, $positionId, $productId, $date, $baseCommission)
    {
        // Get bonus configuration
        $bonus = PositionBonusCommission::getActiveBonus(
            $positionId,
            $productId,
            $date
        );

        if (!$bonus || !$bonus->status) {
            return 0;
        }

        // Check if KW threshold is met
        $kw = $sale->kw ?? 0;
        if ($kw < $bonus->kw_threshold) {
            return 0;
        }

        // Calculate bonus amount
        $bonusAmount = 0;
        if ($bonus->bonus_type === 'percent') {
            // Bonus as percentage of base commission
            $bonusAmount = ($baseCommission * $bonus->bonus_percentage) / 100;
        } elseif ($bonus->bonus_type === 'per_sale') {
            // Flat bonus amount
            $bonusAmount = $bonus->bonus_amount ?? 0;
        }

        return round($bonusAmount, 2);
    }

    /**
     * Record bonus commission in UserCommission
     */
    public function recordBonusCommission($userId, $pid, $bonusAmount, $date, $schema)
    {
        if ($bonusAmount <= 0) {
            return null;
        }

        return UserCommission::create([
            'user_id' => $userId,
            'pid' => $pid,
            'amount' => $bonusAmount,
            'commission_type' => 'bonus',
            'amount_type' => 'bonus_commission',
            'milestone_date' => $date,
            'settlement_type' => 'standard',
            'is_displayed' => 1,
            'is_last' => 1,
            'status' => 1,
            'schema_id' => $schema->id ?? null,
        ]);
    }
}
```

### Step 4: Integrate into SubroutineTrait

```php
// In app/Core/Traits/SaleTraits/SubroutineTrait.php
// ADD at the top with other traits:

use BonusCommissionTrait; // Add this

// THEN in subroutineThree() method, after regular commission is calculated:

public function subroutineThree($sale, $schema, $info, $commission, $redLine, $redLineType, $forExternal = 0)
{
    // ... existing code ...

    // After creating UserCommission with regular commission, add:

    $userCommission = UserCommission::create([
        'user_id' => $userId,
        'pid' => $pid,
        'amount' => $commissionAmount,  // Regular commission
        'commission_type' => 'regular',
        // ... other fields ...
    ]);

    // BONUS: Calculate and record bonus commission
    $bonusAmount = $this->calculateBonusCommission(
        $sale,
        $userId,
        $subPositionId,
        $productId,
        $date,
        $commissionAmount  // Pass base commission
    );

    if ($bonusAmount > 0) {
        $this->recordBonusCommission(
            $userId,
            $pid,
            $bonusAmount,
            $date,
            $schema
        );
    }

    return true;
}
```

### Step 5: Create API Controller

```php
// app/Http/Controllers/API/V2/BonusCommission/BonusCommissionController.php

<?php
namespace App\Http\Controllers\API\V2\BonusCommission;

use App\Http\Controllers\Controller;
use App\Models\PositionBonusCommission;
use Illuminate\Http\Request;

class BonusCommissionController extends Controller
{
    /**
     * List all bonus commissions
     */
    public function index()
    {
        $bonusCommissions = PositionBonusCommission::with('position', 'product')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $bonusCommissions
        ]);
    }

    /**
     * Create new bonus commission
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'position_id' => 'required|exists:positions,id',
            'product_id' => 'required|exists:products,id',
            'kw_threshold' => 'required|numeric|min:0',
            'bonus_percentage' => 'nullable|numeric|min:0|max:100',
            'bonus_amount' => 'nullable|numeric|min:0',
            'bonus_type' => 'required|in:percent,per_sale',
            'effective_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $bonus = PositionBonusCommission::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bonus commission created',
            'data' => $bonus
        ], 201);
    }

    /**
     * Update bonus commission
     */
    public function update(Request $request, $id)
    {
        $bonus = PositionBonusCommission::findOrFail($id);

        $validated = $request->validate([
            'kw_threshold' => 'numeric|min:0',
            'bonus_percentage' => 'nullable|numeric|min:0|max:100',
            'bonus_amount' => 'nullable|numeric|min:0',
            'bonus_type' => 'in:percent,per_sale',
            'effective_date' => 'nullable|date',
            'status' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $bonus->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bonus commission updated',
            'data' => $bonus
        ]);
    }

    /**
     * Delete bonus commission
     */
    public function destroy($id)
    {
        PositionBonusCommission::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bonus commission deleted'
        ]);
    }
}
```

### Step 6: Create Routes

```php
// routes/sequifi/v2/bonuscommission/auth.php

<?php
use App\Http\Controllers\API\V2\BonusCommission\BonusCommissionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('bonus-commission', BonusCommissionController::class);
```

### Step 7: Register Routes in api.php

```php
// In routes/api.php, add:

Route::prefix('v2')->middleware('auth:sanctum')->group(function () {
    Route::prefix('bonuscommission')->group(function () {
        include 'sequifi/v2/bonuscommission/auth.php';
    });
    // ... existing routes ...
});
```

### Step 8: Create Test

```php
// tests/Feature/BonusCommissionTest.php

<?php
namespace Tests\Feature;

use App\Models\PositionBonusCommission;
use App\Models\Position;
use App\Models\Products;
use Tests\TestCase;

class BonusCommissionTest extends TestCase
{
    public function test_can_create_bonus_commission()
    {
        $position = Position::first();
        $product = Products::first();

        $response = $this->postJson('/api/v2/bonus-commission', [
            'position_id' => $position->id,
            'product_id' => $product->id,
            'kw_threshold' => 50,
            'bonus_percentage' => 10,
            'bonus_type' => 'percent',
            'effective_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('position_bonus_commissions', [
            'kw_threshold' => 50,
            'bonus_percentage' => 10,
        ]);
    }

    public function test_bonus_triggered_on_high_kw()
    {
        // Create bonus commission
        $bonus = PositionBonusCommission::create([
            'position_id' => 1,
            'product_id' => 1,
            'kw_threshold' => 50,
            'bonus_percentage' => 10,
            'bonus_type' => 'percent',
            'status' => true,
        ]);

        // Get bonus for sale with KW 75
        $activeBonu = PositionBonusCommission::getActiveBonus(1, 1, now());
        $this->assertNotNull($activeBonus);
        $this->assertEquals(50, $activeBonus->kw_threshold);
    }
}
```

### Step 9: API Endpoints

Now you can use:

```
GET    /api/v2/bonus-commission
POST   /api/v2/bonus-commission
GET    /api/v2/bonus-commission/{id}
PUT    /api/v2/bonus-commission/{id}
DELETE /api/v2/bonus-commission/{id}
```

---

## 5. INTEGRATION FLOW SUMMARY

```
┌─────────────── COMMISSION CALCULATION FLOW ──────────────────┐

1. Sale Created/Updated
   ↓
2. Trigger Subroutine (subroutineThree)
   ├─ Get base commission from PositionCommission
   ├─ Calculate base commission amount
   └─ Store in UserCommission
   ↓
3. Calculate Bonus (NEW - BonusCommissionTrait)
   ├─ Get bonus config from PositionBonusCommission
   ├─ Check KW threshold
   ├─ Calculate bonus amount
   └─ Record in UserCommission (amount_type='bonus_commission')
   ↓
4. Apply Other Calculations
   ├─ Upfronts
   ├─ Overrides
   ├─ Deductions
   └─ Reconciliation
   ↓
5. Export/Display
   └─ Combine all commission types for final report

```

---

## 6. BEST PRACTICES FOR CUSTOM COMMISSION

✅ **DO:**
- Use traits for code organization
- Filter by `effective_date` for backward compatibility
- Store metadata on models (like custom field does)
- Create dedicated tables for new commission types
- Write tests for calculations
- Add API endpoints for admin management

❌ **DON'T:**
- Modify SubroutineTrait directly (use traits instead)
- Hard-code values (use database configs)
- Skip effective_date validation
- Forget about reconciliation impacts
- Miss decimal precision on currency calculations

---

## 7. DEBUG TIPS

Enable logging in commission calculations:

```php
// In BonusCommissionTrait
Log::info('Bonus calculation', [
    'user_id' => $userId,
    'kw' => $kw,
    'threshold' => $bonus->kw_threshold,
    'bonus_amount' => $bonusAmount,
]);
```

Check UserCommission table:
```sql
SELECT * FROM user_commission
WHERE pid = 'your_pid'
ORDER BY created_at DESC;
```

---

## 8. NEXT STEPS TO EXTEND

1. **Tiered Bonus**: Different bonus % by KW ranges
2. **Per-Person Caps**: Max bonus per person per period
3. **Clawback** Rules: Recoup bonus if sale is refunded
4. **Reports**: Custom bonus commission reports
5. **Integration**: Hook into reconciliation process

