<?php

namespace App\Imports\Sales;

use App\Models\RoofingSalesImportTemplateDetail;

class RoofingSalesImport extends AbstractSalesImport
{
    protected const COMPANY_TYPE = 'Roofing';

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
        'gross_account_value',
        'cash_amount',
        'loan_amount',
        'financing_term',
        'kw',
        'adders',
        'cancel_fee',
        'epc',
        'net_epc',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function initTemplateFields(): void
    {
        $details = RoofingSalesImportTemplateDetail::with('field')
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
