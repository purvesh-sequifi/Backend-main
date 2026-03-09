<?php

namespace App\Imports\Sales;

use App\Models\PestSalesImportTemplateDetail;

class PestSalesImport extends AbstractSalesImport
{
    protected const COMPANY_TYPE = 'Pest';

    public $message;

    public $ids = [];

    public $users = [];

    public $newRecords = 0;

    public $headers = [];

    public $validateOnly;

    public $positions = [];

    public $updatedRecords = 0;

    public $maxTriggerCount;

    protected array $fieldsToValidateForSpecialChars = ['gross_account_value'];

    public function __construct()
    {
        parent::__construct();
    }

    public function initTemplateFields(): void
    {
        $details = PestSalesImportTemplateDetail::with('field')
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
