<?php

namespace App\Observers;

use App\Models\CompanyProfile;
use App\Models\ProductMilestoneHistories;
use App\Models\Products;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ProductMilestoneHistoriesObserver
{
    public function __construct(protected AuditLogService $auditLog)
    {
    }

    /**
     * Determine the appropriate timezone based on company profile.
     **/
    protected function getCompanyTimezone(): string
    {
        // Fetch the company profile (can be customized to fetch dynamically if needed)
        $company = CompanyProfile::first(); // or fetch based on context

        // Use mailing address timezone if it's set
        if (! empty($company?->mailing_address_time_zone)) {
            return $company->mailing_address_time_zone;
        }

        // If general time_zone is set, map it to a valid timezone using helper
        if (! empty($company?->time_zone)) {
            return Products::mapTimeZone($company->time_zone);
        }

        // Use application default timezone as fallback
        return config('app.timezone'); // fallback
    }

    /**
     * Handle the ProductMilestoneHistories "created" event.
     */
    public function created(ProductMilestoneHistories $productMilestoneHistories): void
    {
        // Retrieve the company's applicable timezone
        $timezone = $this->getCompanyTimezone();

        // $product = "";
        // if ($productMilestoneHistories->product_id) {
        //     $prod = Products::where('id', $productMilestoneHistories->product_id)->first();
        //     $product = $prod?->product_id;
        // }
        // $schema = "";
        // if ($productMilestoneHistories->milestone_schema_id) {
        //     $milestone = MilestoneSchema::where('id', $productMilestoneHistories->milestone_schema_id)->first();
        //     $schema = $milestone?->prefix .'-'.$milestone?->schema_name;
        // }
        // $exempt = "";
        // if ($productMilestoneHistories->clawback_exempt_on_ms_trigger_id) {
        //     $trigger = MilestoneSchemaTrigger::where('id', $productMilestoneHistories->clawback_exempt_on_ms_trigger_id)->first();
        //     $exempt = $trigger?->name;
        // }
        $data[] = [
            'product_id' => $productMilestoneHistories->product_id,
            'milestone_schema_id' => $productMilestoneHistories->milestone_schema_id,
            'effective_date' => $productMilestoneHistories->effective_date,
            'clawback_exempt_on_ms_trigger_id' => $productMilestoneHistories->clawback_exempt_on_ms_trigger_id,
            'override_on_ms_trigger_id' => $productMilestoneHistories->override_on_ms_trigger_id,
            'product_redline' => $productMilestoneHistories->product_redline,
            'created_at' => Carbon::now()->setTimezone($timezone)->toDateTimeString(),
        ];

        $this->auditLog->addChange([
            'type' => get_class($productMilestoneHistories),
            'reference_id' => $productMilestoneHistories->product_id,
            'effective_on_date' => $productMilestoneHistories->effective_date,
            'event' => 'created',
            'description' => json_encode($data),
            'user_id' => (Auth::user()?->id) ? Auth::user()?->id : 1,
        ]);
    }

    /**
     * Handle the ProductMilestoneHistories "updated" event.
     */
    public function updated(ProductMilestoneHistories $productMilestoneHistories): void
    {
        // Retrieve the company's applicable timezone
        $timezone = $this->getCompanyTimezone();

        $changes = $productMilestoneHistories->getChanges();

        $logData = [];
        foreach ($changes as $key => $newValue) {
            $oldValue = $productMilestoneHistories->getOriginal($key);
            if ($oldValue != $newValue) {
                $old = $oldValue;
                $new = $newValue;
                // if ($key == 'product_id') {
                //     $old = "";
                //     if ($oldValue) {
                //         $oldProd = Products::where('id', $oldValue)->first();
                //         $old = $oldProd?->product_id;
                //     }
                //     $new = "";
                //     if ($newValue) {
                //         $newProd = Products::where('id', $newValue)->first();
                //         $new = $newProd?->product_id;
                //     }
                // }
                // if ($key == 'milestone_schema_id') {
                //     $old = "";
                //     if ($oldValue) {
                //         $oldMilestone = MilestoneSchema::where('id', $oldValue)->first();
                //         $old = $oldMilestone?->prefix .'-'.$oldMilestone?->schema_name;
                //     }
                //     $new = "";
                //     if ($newValue) {
                //         $newMilestone = MilestoneSchema::where('id', $newValue)->first();
                //         $new = $newMilestone?->prefix .'-'.$newMilestone?->schema_name;
                //     }
                // }
                // if ($key == 'clawback_exempt_on_ms_trigger_id') {
                //     $old = "";
                //     if ($oldValue) {
                //         $oldTrigger = MilestoneSchemaTrigger::where('id', $oldValue)->first();
                //         $old = $oldTrigger?->name;
                //     }
                //     $new = "";
                //     if ($newValue) {
                //         $newTrigger = MilestoneSchemaTrigger::where('id', $newValue)->first();
                //         $new = $newTrigger?->name;
                //     }
                // }

                // If key is updated_at, convert new to company's timezone
                if ($key === 'updated_at') {
                    $new = Carbon::parse($new)->setTimezone($timezone)->toDateTimeString();
                }

                $logData[$key] = [
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        if (! empty($logData)) {
            $this->auditLog->addChange([
                'type' => get_class($productMilestoneHistories),
                'reference_id' => $productMilestoneHistories->product_id,
                'effective_on_date' => $productMilestoneHistories->effective_date,
                'event' => 'updated',
                'description' => json_encode($logData),
                'user_id' => Auth::user()?->id,
            ]);
        }
    }

    /**
     * Handle the ProductMilestoneHistories "deleted" event.
     */
    public function deleted(ProductMilestoneHistories $productMilestoneHistories): void
    {
        //
    }

    /**
     * Handle the ProductMilestoneHistories "restored" event.
     */
    public function restored(ProductMilestoneHistories $productMilestoneHistories): void
    {
        //
    }

    /**
     * Handle the ProductMilestoneHistories "force deleted" event.
     */
    public function forceDeleted(ProductMilestoneHistories $productMilestoneHistories): void
    {
        //
    }
}
