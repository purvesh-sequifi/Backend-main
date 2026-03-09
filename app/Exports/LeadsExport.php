<?php

namespace App\Exports;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $request;

    private $lead;
    /**
     * @return \Illuminate\Support\Collection
     */
    // public function __construct($state ='', $status='', $filter ='')
    // {
    //     $this->state = $state;
    //     $this->status = $status;
    //     $this->filter = $filter;
    // }

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Freeze the first row (header row)
                $event->sheet->freezePane('A2');
            },
        ];
    }

    public function collection()
    {
        $this->lead = Lead::with('recruiter', 'reportingManager', 'state', 'comment', 'pipelineleadstatus')
            ->where('type', 'lead');

        $request = $this->request;
        $this->applyFilters($request);
        $superAdmin = Auth::user()->is_super_admin;
        $userId = Auth::user()->id;
        $positionId = Auth::user()->position_id;

        if (! $superAdmin) {
            $this->lead = $this->lead->where([
                'office_id' => auth()->user()->office_id,
            ]);
        }

        if ($superAdmin == 1) {
            $data = $this->lead->where('status', '!=', 'Hired')->get();
        } else {
            $data = $this->getDataForNonSuperAdmin($request, $userId, $positionId);
        }

        return $this->formatData(collect($data)); // Format data for export (e.g., array, collection)
        dd($request->all());

        $lead = Lead::with('recruiter', 'reportingManager', 'state')->where('type', 'lead');

        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('filter') && ! empty($request->input('filter'))) {

            $lead->where(function ($query) use ($request) {
                $query->where('first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->input('filter').'%'])
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('source', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereHas('reportingManager', function ($q) {
                        $q->where(function ($q) {
                            $q->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', [request()->input('filter')])
                                ->orWhere('first_name', 'LIKE', request()->input('filter'))
                                ->orWhere('last_name', 'LIKE', request()->input('filter'));
                        });
                    });
            });
            // ->orWhereHas('additionalEmails', function ($query) use ($request)  {
            //     $query->where('email', 'like', '%' . $request->input('filter') . '%');
            // });

        }
        if ($request->has('status_filter') && ! empty($request->input('status_filter'))) {

            $lead->where(function ($query) use ($request) {
                return $query->where('status', $request->input('status_filter'));
            });

        }
        if ($request->has('home_state_filter') && ! empty($request->input('home_state_filter'))) {

            $lead->where(function ($query) use ($request) {
                return $query->where('state_id', $request->input('home_state_filter'));
            });
        }
        if ($request->has('recruter_filter') && ! empty($request->input('recruter_filter'))) {
            $lead->where(function ($query) use ($request) {
                return $query->where('recruiter_id', $request->input('recruter_filter'));
            });
        }
        if ($request->has('reporting_manager') && ! empty($request->input('reporting_manager'))) {

            $lead->whereHas('reportingManager', function ($query) use ($request) {
                $query->where('id', $request->input('reporting_manager'));
            });

        }

        // start lead display listing  by nikhil
        $superAdmin = Auth::user()->is_super_admin;
        $user_id = Auth::user()->id;
        $positionId = Auth::user()->position_id;
        $lead->with('recruiter', 'reportingManager', 'state');
        if ($superAdmin == 1) {
            $data = $lead->where('type', 'lead')
                ->where('status', '!=', 'Hired')
                ->orderBy('id', $orderBy)->get();
        } else {
            if ($positionId != 1) {
                if ($request->has('status_filter') && ! empty($request->input('status_filter')) && empty($request->input('home_state_filter'))) {
                    $data = $lead->where('recruiter_id', $user_id)
                        ->where('status', '!=', 'Hired')
                        ->where('type', 'lead')
                        ->where(function ($query) use ($request) {
                            return $query->where('status', $request->input('status_filter'));
                        })
                        ->orWhere('reporting_manager_id', $user_id)
                        ->where('status', '!=', 'Hired')
                        ->where('type', 'lead')
                        ->where(function ($query) use ($request) {
                            return $query->where('status', $request->input('status_filter'));
                        })
                        ->orderBy('id', $orderBy)->get();
                } else {
                    if ($request->has('home_state_filter') && ! empty($request->input('home_state_filter')) && empty($request->input('status_filter'))) {
                        $data = $lead->where('recruiter_id', $user_id)
                            ->where('status', '!=', 'Hired')
                            ->where('type', 'lead')
                            ->where(function ($query) use ($request) {
                                return $query->where('state_id', $request->input('home_state_filter'));
                            })
                            ->orWhere('reporting_manager_id', $user_id)
                            ->where('status', '!=', 'Hired')
                            ->where('type', 'lead')
                            ->where(function ($query) use ($request) {
                                return $query->where('state_id', $request->input('home_state_filter'));
                            })
                            ->orderBy('id', $orderBy)->get();
                    } else {
                        if ($request->has('status_filter') && ! empty($request->input('status_filter')) && $request->has('home_state_filter') && ! empty($request->input('home_state_filter'))) {
                            $data = $lead->where('recruiter_id', $user_id)
                                ->where('status', '!=', 'Hired')
                                ->where('type', 'lead')
                                ->where(function ($query) use ($request) {
                                    return $query->where('status', $request->input('status_filter'));
                                })
                                ->where(function ($query) use ($request) {
                                    return $query->where('state_id', $request->input('home_state_filter'));
                                })
                                ->orWhere('reporting_manager_id', $user_id)
                                ->where('status', '!=', 'Hired')
                                ->where('type', 'lead')
                                ->where(function ($query) use ($request) {
                                    return $query->where('status', $request->input('status_filter'));
                                })
                                ->where(function ($query) use ($request) {
                                    return $query->where('state_id', $request->input('home_state_filter'));
                                })
                                ->orderBy('id', $orderBy)->get();
                        } else {
                            $data = $lead->where('recruiter_id', $user_id)
                                ->where('status', '!=', 'Hired')
                                ->where('type', 'lead')
                                ->orWhere('reporting_manager_id', $user_id)
                                ->where('status', '!=', 'Hired')
                                ->where('type', 'lead')
                                ->orderBy('id', $orderBy)->get();
                        }
                    }
                }
            } else {
                $recruiterIds = User::select('id', 'manager_id')->where('manager_id', $user_id)->pluck('id');
                $data = $lead->whereIn('recruiter_id', $recruiterIds)->where('type', 'lead')->where('status', '!=', 'Hired')->Orwhere('recruiter_id', $user_id)->where('type', 'lead')->where('status', '!=', 'Hired')->orderBy('id', $orderBy)->get();
            }
        }

        $data->transform(function ($result) {
            return [
                'first_name' => $result?->first_name,
                'last_name' => $result?->last_name,
                'email' => $result?->email,
                'phone_no' => $result?->mobile_no,
                'source' => $result?->source,
                'home_location_of_the_candidate' => $result?->state?->name,
                'reporting_manager' => $result?->reportingManager?->first_name.' '.$result?->reportingManager?->last_name,
                'status' => $result?->status,
                'comments' => $result?->comments,
            ];
        });

        return $data;
        /*$result = [];
    foreach ($data as $record) {
    $result[] = array(
    'first_name' => isset($record->first_name) ? $record->first_name : "",
    'last_name' => isset($record->last_name) ? $record->last_name : "",
    'email' => isset($record->email) ? $record->email : "",
    'phone_no' => isset($record->mobile_no) ? $record->mobile_no : "",
    'home_location_of_the_candidate' => isset($record->state->name) ? $record->state->name : null,
    'reporting_manager' => (isset($record->reportingManager->first_name) ? $record->reportingManager->first_name : null) . ' ' . (isset($record->reportingManager->last_name) ? $record->reportingManager->last_name : null),
    'source' => isset($record->source) ? $record->source : "",
    'status' => isset($record->status) ? $record->status : "",
    'comments' => isset($record->comments) ? $record->comments : ""
    );
    }
    return collect($result);*/
    }

    private function applyFilters($request)
    {
        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $filter = $request->input('filter');
            $this->lead->where(function ($query) use ($filter) {
                $query->where('first_name', 'LIKE', '%'.$filter.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$filter.'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$filter.'%'])
                    ->orWhere('email', 'LIKE', '%'.$filter.'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$filter.'%')
                    ->orWhere('source', 'LIKE', '%'.$filter.'%')
                    ->orWhereHas('reportingManager', function ($q) use ($filter) {
                        $q->whereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$filter.'%'])
                            ->orWhere('first_name', 'LIKE', '%'.$filter.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$filter.'%');
                    });
            });
        }

        if ($request->has('status_filter') && ! empty($request->input('status_filter'))) {
            $this->lead->where('status', $request->input('status_filter'));
        }

        if ($request->has('home_state_filter') && ! empty($request->input('home_state_filter'))) {
            $this->lead->where('state_id', $request->input('home_state_filter'));
        }

        if ($request->has('recruter_filter') && ! empty($request->input('recruter_filter'))) {
            $this->lead->where('recruiter_id', $request->input('recruter_filter'));
        }

        if ($request->has('reporting_manager') && ! empty($request->input('reporting_manager'))) {
            $this->lead->whereHas('reportingManager', function ($query) use ($request) {
                $query->where('id', $request->input('reporting_manager'));
            });
        }
    }

    private function getDataForNonSuperAdmin($request, $userId, $positionId)
    {
        if ($positionId != 1) {
            if ($request->has('status_filter') && ! empty($request->input('status_filter')) &&
                ! $request->has('home_state_filter')) {
                return $this->filterByStatus($request, $userId);
            } elseif ($request->has('home_state_filter') && ! empty($request->input('home_state_filter')) &&
                ! $request->has('status_filter')) {
                return $this->filterByState($request, $userId);
            } elseif ($request->has('status_filter') && ! empty($request->input('status_filter')) &&
                $request->has('home_state_filter') && ! empty($request->input('home_state_filter'))) {
                return $this->filterByStatusAndState($request, $userId);
            } else {
                return $this->filterByRecruiterOrManager($userId);
            }
        } else {
            return $this->filterByManager($userId);
        }
    }

    private function filterByStatus($request, $userId)
    {
        $status = $request->input('status_filter');

        return $this->lead->where('recruiter_id', $userId)
            ->where('status', '!=', 'Hired')
            ->where('status', $status)
            ->orWhere('reporting_manager_id', $userId)
            ->where('status', '!=', 'Hired')
            ->where('status', $status)
            ->get();
    }

    private function filterByState($request, $userId)
    {
        $stateId = $request->input('home_state_filter');

        return $this->lead->where('recruiter_id', $userId)
            ->where('status', '!=', 'Hired')
            ->where('state_id', $stateId)
            ->orWhere('reporting_manager_id', $userId)
            ->where('status', '!=', 'Hired')
            ->where('state_id', $stateId)
            ->get();
    }

    private function filterByStatusAndState($request, $userId)
    {
        $status = $request->input('status_filter');
        $stateId = $request->input('home_state_filter');

        return $this->lead->where('recruiter_id', $userId)
            ->where('status', '!=', 'Hired')
            ->where('status', $status)
            ->where('state_id', $stateId)
            ->orWhere('reporting_manager_id', $userId)
            ->where('status', '!=', 'Hired')
            ->where('status', $status)
            ->where('state_id', $stateId)
            ->get();
    }

    private function filterByRecruiterOrManager($userId)
    {
        return $this->lead->where('recruiter_id', $userId)
            ->where('status', '!=', 'Hired')
            ->orWhere('reporting_manager_id', $userId)
            ->where('status', '!=', 'Hired')
            ->get();
    }

    private function filterByManager($userId)
    {
        $managerUsers = User::select('id', 'manager_id')
            ->where('manager_id', $userId)
            ->get();
        $csid = $managerUsers->pluck('id')->toArray();

        return $this->lead->whereIn('recruiter_id', $csid)
            ->where('status', '!=', 'Hired')
            ->orWhere('recruiter_id', $userId)
            ->where('status', '!=', 'Hired')
            ->get();
    }

    private function formatData($data)
    {
        return $data->transform(function ($result) {
            return [
                'first_name' => ucfirst($result?->first_name),
                'last_name' => ucfirst($result?->last_name),
                'email' => $result?->email,
                'phone_no' => $result?->mobile_no,
                'source' => $result?->source,
                'home_location_of_the_candidate' => $result?->state?->name,
                'reporting_manager' => $result?->reportingManager?->first_name.' '.$result?->reportingManager?->last_name,
                'status' => $result?->status,
                'comments' => $result?->comments,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'First Name',
            'Last Name',
            'Email',
            'Phone Number',
            'Source',
            'Home location of the candidate',
            'Reporting Manager',
            'Status',
            'Comments',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999', // Background color (light gray)
                ],
            ],
        ]);
    }

    public function title(): string
    {
        return 'Leads';
    }
}
