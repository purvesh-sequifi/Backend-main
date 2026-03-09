<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierMetrics extends Model
{
    use HasFactory, SpatieLogsActivity;

    const ACCOUNT_SOLD = 'Accounts Sold';

    const ACCOUNT_INSTALLED = 'Accounts Installed';

    const ACCOUNT_SERVICED = 'Accounts Serviced'; // PEST

    const KW_SOLD = 'KW Sold';

    const SQ_FT_SOLD = 'Sq ft Sold'; // TURF

    const KW_INSTALLED = 'KW Installed';

    const SQ_FT_INSTALLED = 'Sq ft Installed'; // TURF

    const REVENUE_SOLD = 'Revenue Sold';

    const REVENUE_INSTALLED = 'Revenue Installed';

    const REVENUE_SERVICED = 'Revenue Serviced'; // PEST

    const AVERAGE_CONTRACT_VALUE = 'Average Contract Value';

    const INSTALL_RATE = 'Install Rate';

    const SERVICE_RATE = 'Service Rate'; // PEST

    const CANCELLATION_RATE = 'Cancellation Rate';

    const AUTO_PAY_ENROLMENT_RATE = 'Autopay Enrolment Rate'; // PEST

    const WORKERS_HIRED = 'Workers Hired';

    const LEADS_GENERATED = 'Leads Generated';

    const WORKERS_MANAGED = 'Workers Managed';

    const GROSS_ACCOUNT_VALUE = 'Gross Account Value';

    const GROSS_ACCOUNT_VALUE_SERVICED = 'Gross Account Value Serviced';

    const KW = 'KW';

    const SQ_FT = 'Sq ft';

    const NET_EPC = 'Net EPC';

    const NET_SQ_FT = 'Net $ per Sq ft';

    const GROSS_EPC = 'Gross EPC';

    const GROSS_SQ_FT = 'Gross $ per Sq ft';

    const DEALER_FEE_PERCENTAGE = 'Dealer Fee %';

    const DEALER_FEE_DOLLAR = 'Dealer Fee $';

    const SOW = 'SOW';

    const INITIAL_SERVICE_COST = 'Initial Service Cost';

    const SUBSCRIPTION_PAYMENT = 'Subscription Payment';

    const INSTALLER = 'Installer';

    const LOCATION_CODE = 'Location Code';

    const CUSTOMER_STATE = 'Customer State';

    const CUSTOMER_ZIP = 'Customer Zip';

    const SERVICE_PROVIDER = 'Service Provider';

    const CARD_ON_FILE = 'Card on File';

    const AUTO_PAY = 'Autopay';

    const PRODUCT_NAME = 'Product Name';

    protected $table = 'tier_metrics';

    protected $fillable = [
        'value',
        'tier_system_id',
        'symbol',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(TierSystem::class, 'tier_system_id');
    }
}
