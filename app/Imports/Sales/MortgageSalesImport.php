<?php

namespace App\Imports\Sales;

use App\Models\MortgageSalesImportTemplateDetail;

class MortgageSalesImport extends AbstractSalesImport
{
    protected const COMPANY_TYPE = 'Mortgage';

    public $message;

    public $ids = [];

    public $users = [];

    public $newRecords = 0;

    public $headers = [];

    public $validateOnly;

    public $positions = [];

    public $updatedRecords = 0;

    public $maxTriggerCount;

    protected array $fieldsToValidateForSpecialChars = [
        'subscription_payment',
        'card_on_file',
        'auto_pay',
        'last_service_date',
        'bill_status',
        'install_complete_date',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function initTemplateFields(): void
    {
        $details = MortgageSalesImportTemplateDetail::with('field')
            ->where('template_id', $this->templateId)
            ->orderBy('id')
            ->get();

        $this->loadTemplateFields($details, $this->templateId);
    }

    protected function getTemplateId(): int
    {
        return $this->templateId;
    }
}
