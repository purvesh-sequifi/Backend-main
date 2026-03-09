<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserExport implements FromCollection, WithHeadings
{
    private $officeFilter;

    private $position_filter;

    private $showAdmin_filter;

    private $status_filter;

    private $filter;

    public function __construct($officeFilter, $position_filter, $showAdmin_filter, $status_filter, $filter)
    {

        $this->position_filter = $position_filter;
        $this->showAdmin_filter = $showAdmin_filter;
        $this->status_filter = $status_filter;
        $this->filter = $filter;
        $this->$officeFilter = $officeFilter;
    }

    public function collection(): Collection
    {
        $user = User::orderBy('id', 'desc');

        if ($this->position_filter && ! empty($this->position_filter)) {
            $positionFilter = $this->position_filter;
            $user->where(function ($query) use ($positionFilter) {
                return $query->where('sub_position_id', $positionFilter);
            });
        }

        if (isset($officeFilter) && ! empty($officeFilter)) {
            $officeId = $this->$officeFilter;
            if ($officeId != 'all') {
                $user->where(function ($query) use ($officeId) {
                    return $query->where('office_id', $officeId);
                });
            }

        }

        if (isset($statusFilter) && $statusFilter != '') {

            if ($statusFilter == 1) {
                $statusFilter = 1;
            } else {
                $statusFilter = 0;
            }
            $user->where(function ($query) use ($statusFilter) {
                return $query->where('dismiss', $statusFilter);
            });
        }

        if ($this->showAdmin_filter && ! empty($this->showAdmin_filter)) {
            $showAdminFilter = $this->showAdmin_filter;
            $user->where(function ($query) use ($showAdminFilter) {
                return $query->where('is_super_admin', $showAdminFilter);
            });
        }

        if ($this->filter && ! empty($this->filter)) {
            $search = $this->filter;
            $user->whereHas(
                'positionDetail', function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('mobile_no', 'like', '%'.$search.'%')
                        ->orWhere('position_name', 'like', '%'.$search.'%');
                })
                ->orWhereHas('additionalEmails', function ($query) use ($search) {
                    $query->where('email', 'like', '%'.$search.'%');
                });
        }

        $totalAdminUsersStatus = '';
        $statusFilter = $this->status_filter;
        if (isset($statusFilter) && $statusFilter != '') {

            if ($statusFilter == 1) {
                $statusFilter = 'Inactive';
            } else {
                $statusFilter = 'Active';
            }
            $totalAdminUsersStatus = $statusFilter;
        }
        $data = $user->with('positionDetail', 'office', 'departmentDetail', 'State')->get();

        // $records = User::with('additionalLocations','office','state','departmentDetail','managerDetail')->orderBy('id','desc')->get();
        $result = [];
        foreach ($data as $record) {
            if ($record->dismiss == 0) {
                $status = 'Active';
            } else {
                $status = 'Inactive';
            }
            $result[] = [
                'employee_id' => isset($record->employee_id) ? $record->employee_id : '',
                'frist_name' => isset($record->first_name) ? $record->first_name : '',
                'last_name' => isset($record->last_name) ? $record->last_name : '',
                'departmant' => isset($record->departmentDetail->name) ? $record->departmentDetail->name : '',
                'position' => isset($record->positionDetail) ? $record->positionDetail->position_name : '',
                'state' => isset($record->State) ? $record->State->name : '',
                'office_name' => isset($record->office->office_name) ? $record->office->office_name : '',
                'Phone' => isset($record->mobile_no) ? $record->mobile_no : '',
                'Email' => isset($record->email) ? $record->email : '',
            ];
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Frist Name',
            'Last Name',
            'Department',
            'Position',
            'State',
            'Office',
            'Phone',
            'Email',
        ];
    }
}
