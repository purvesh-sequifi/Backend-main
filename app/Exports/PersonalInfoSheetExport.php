<?php

namespace App\Exports;

use App\Models\EmployeeIdSetting;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PersonalInfoSheetExport implements FromCollection, WithHeadings, WithTitle
{
    private $data;

    private $title;

    public function __construct($data, $title)
    {
        $this->data = $data;
        $this->title = $title;
    }

    /**
     * @return string[]
     */
    public function headings(): array
    {
        $mainColumns = [
            'Employee Id', 'First Name', 'Last Name', 'Email', 'Phone', 'Middle Name', 'Sex',
            'Date of Birth', 'Phone', 'Personal Email', 'Work Emails', 'Home Address',
            'Emergency Contact', 'Emergency Contact Name', 'Relationship', 'Emergency Address',
        ];

        // Columns that should always be at the end
        $endColumns = [
            'Payroll Status', 'User Status', 'Termination Status', 'Contract Status', 'Login Status',
        ];

        $data = EmployeeIdSetting::with([
            'AdditionalInfoForEmployeeToGetStarted' => fn ($query) => $query->where('is_deleted', 0),
            'EmployeePersonalDetail' => fn ($query) => $query->where('is_deleted', 0),
        ])->find(1);

        if (! $data) {
            // Return default columns if no data is found
            return array_merge($mainColumns, $endColumns);
        }

        $getStarted = $data->AdditionalInfoForEmployeeToGetStarted
            ->pluck('field_name')
            ->map(fn ($item) => "{$item} (GS)")
            ->toArray();

        $additionalInfo = $data->EmployeePersonalDetail
            ->pluck('field_name')
            ->map(fn ($item) => "{$item} (AI)")
            ->toArray();

        // Get admin-only fields configuration
        $adminOnlyFields = \App\Models\EmployeeAdminOnlyFields::where('is_deleted', 0)
            ->pluck('field_name')
            ->map(fn ($item) => "{$item} (AO)")
            ->toArray();

        // Merge in the correct order: main -> additional -> getStarted -> admin only fields -> fixed end columns
        return array_merge($mainColumns, $additionalInfo, $getStarted, $adminOnlyFields, $endColumns);
    }

    public function collection(): Collection
    {
        return new Collection([$this->data]);
    }

    public function title(): string
    {
        return $this->title;
    }
}
