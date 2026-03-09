<?php

namespace App\Exports;

use App\Models\OnboardingEmployees;
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

class OnboardingExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /**
     * @return \Illuminate\Support\Collection
     */
    // public function __construct($status='', $position='', $manager='', $officeId='',$filter='')
    // {
    //     $this->status = $status;
    //     $this->position = $position;
    //     $this->manager = $manager;
    //     $this->officeId = $officeId;
    //     $this->filter = $filter;

    // }

    // public function collection()
    // {
    //    //return  $officeId = auth()->user()->office_id;
    //      $user = OnboardingEmployees::orderBy('id','desc');
    //     $user->with('departmentDetail','positionDetail','managerDetail','statusDetail','recruiter','additionalDetail','additionalLocation','state','city','teamsDetail','subpositionDetail','office');

    //     if ($this->status && !empty($this->status))
    //     {
    //         $status= $this->status;
    //         $user->where(function($query) use ($status) {
    //             $query->where('status_id', $status);
    //         });

    //     }
    //     if ($this->position && !empty($this->position))
    //     {
    //         $position = $this->position;
    //         $user->where(function($query) use ($position) {
    //             $query->where('sub_position_id', $position);
    //         });

    //     }

    //     if ($this->manager && !empty($this->manager))
    //     {
    //         $manager = $this->manager;
    //         $user->where(function($query) use ($manager) {
    //             $query->where('manager_id',$manager);
    //         });

    //     }

    //     if ($this->officeId !=='all')
    //     {
    //         $officeId = $this->officeId;
    //         $user->where(function($query) use ($officeId) {
    //             $query->where('office_id',$officeId)
    //             ->where('first_name', 'LIKE', '%'.$this->filter.'%')
    //             ->orWhere('last_name', 'LIKE', '%'.$this->filter.'%')
    //             ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%' .$this->filter. '%']);
    //         });
    //     }

    //     if ($this->officeId =='all')
    //     {
    //         $user->where(function($query){
    //             $query->where('first_name', 'LIKE', '%'.$this->filter.'%')
    //             ->orWhere('last_name', 'LIKE', '%'.$this->filter.'%')
    //             ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%' .$this->filter. '%']);
    //         });
    //     }

    //     $user = $user->get();

    //     $result =[];
    //     foreach($user as $record){
    //         if($record->is_manager ==1){
    //             $is_manager = 'manager';
    //         }else{
    //             $is_manager = '';
    //         }
    //         $result[] = array(
    //         // 'user_id'=>isset($record->user_id)?$record->user_id:"",
    //         'aveyo_hs_id'=>isset($record->aveyo_hs_id)?$record->aveyo_hs_id:"",
    //         'employee_id'=>isset($record->employee_id)?$record->employee_id:"",
    //         'first_name'=>isset($record->first_name)?$record->first_name:"",
    //         'middle_name'=>isset($record->middle_name)?$record->middle_name:"",
    //         'last_name'=>isset($record->last_name)?$record->last_name:"",
    //         'email'=>isset($record->email)?$record->email:"",
    //         'status'=>isset($record->statusDetail->status)?$record->statusDetail->status:"",
    //         // 'api_token'=>isset($record->api_token)?$record->api_token:"",
    //         // 'password'=>isset($record->password)?$record->password:"",
    //         'mobile_no'=>isset($record->mobile_no)?$record->mobile_no:"",
    //         'state'=>isset($record->state->name)?$record->state->name:"",
    //         // 'city_id'=>isset($record->city_id)?$record->city_id:"",
    //         // 'location'=>isset($record->location)?$record->location:"",
    //         // 'department'=>isset($record->departmentDetail->name)?$record->departmentDetail->name:null,
    //         // 'position_id'=>isset($record->position_id)?$record->position_id:"",
    //         // 'position'=>isset($position)?$position:"",
    //         'manager'=>isset($record->managerDetail->name)?$record->managerDetail->name:"",
    //         // 'self_gen_accounts'=>isset($record->self_gen_accounts)?$record->self_gen_accounts:"",
    //         // 'self_gen_type'=>isset($record->self_gen_type)?$record->self_gen_type:"",
    //         // 'additional_recruiter'=>isset($record->recruiter->first_name)?$record->recruiter->first_name:"",
    //         // 'additional_locations'=>isset($record->office->business_state)?$record->office->business_state:"",
    //         // 'commission'=>isset($record->commission)?$record->commission:"",
    //         // 'redline'=>isset($record->redline)?$record->redline:"",
    //         // 'redline_amount_type'=>isset($record->redline_amount_type)?$record->redline_amount_type:"",
    //         // 'redline_type'=>isset($record->redline_type)?$record->redline_type:"",
    //         // 'upfront_pay_amount'=>isset($record->upfront_pay_amount)?$record->upfront_pay_amount:"",
    //         // 'upfront_sale_type'=>isset($record->upfront_sale_type)?$record->upfront_sale_type:"",
    //         // 'withheld_amount'=>isset($record->withheld_amount)?$record->withheld_amount:"",
    //         // 'self_gen_withheld_amount'=>isset($record->self_gen_withheld_amount)?$record->self_gen_withheld_amount:"",
    //         // 'offer_include_bonus'=>isset($record->offer_include_bonus)?$record->offer_include_bonus:"",
    //         // 'direct_overrides_amount'=>isset($record->direct_overrides_amount)?$record->direct_overrides_amount:"",
    //         // 'direct_overrides_type'=>isset($record->direct_overrides_type)?$record->direct_overrides_type:"",
    //         // 'indirect_overrides_amount'=>isset($record->indirect_overrides_amount)?$record->indirect_overrides_amount:"",
    //         // 'indirect_overrides_type'=>isset($record->indirect_overrides_type)?$record->indirect_overrides_type:"",
    //         // 'office_overrides_amount'=>isset($record->office_overrides_amount)?$record->office_overrides_amount:"",
    //         // 'office_overrides_type'=>isset($record->office_overrides_type)?$record->office_overrides_type:"",
    //         // 'office_stack_overrides_amount'=>isset($record->office_stack_overrides_amount)?$record->office_stack_overrides_amount:"",
    //         // 'probation_period'=>isset($record->probation_period)?$record->probation_period:"",
    //         // 'hiring_bonus_amount'=>isset($record->hiring_bonus_amount)?$record->hiring_bonus_amount:"",
    //         // 'date_to_be_paid'=>isset($record->date_to_be_paid)?$record->date_to_be_paid:"",
    //         // 'period_of_agreement_start_date'=>isset($record->period_of_agreement_start_date)?$record->period_of_agreement_start_date:"",
    //         // 'end_date'=>isset($record->end_date)?$record->end_date:"",
    //         // 'offer_expiry_date'=>isset($record->offer_expiry_date)?$record->offer_expiry_date:"",
    //         // 'user_offer_letter'=>isset($record->user_offer_letter)?$record->user_offer_letter:"",
    //         // 'document_id'=>isset($record->document_id)?$record->document_id:"",
    //         // 'response'=>isset($record->response)?$record->response:"",
    //         // 'sex'=>isset($record->sex)?$record->sex:"",
    //         // 'image'=>isset($record->image)?$record->image:"",
    //         // 'dob'=>isset($record->dob)?$record->dob:"",
    //         // 'zip_code'=>isset($record->zip_code)?$record->zip_code:"",
    //         // 'work_email'=>isset($record->work_email)?$record->work_email:"",
    //         // 'home_address'=>isset($record->home_address)?$record->home_address:"",
    //         // 'type'=>isset($record->type)?$record->type:"",
    //         // 'hiring_type'=>isset($record->hiring_type)?$record->hiring_type:"",
    //         'office'=>isset($record->office->office_name)?$record->office->office_name:"",
    //         // 'self_gen_redline'=>isset($record->self_gen_redline)?$record->self_gen_redline:"",
    //         // 'self_gen_redline_amount_type'=>isset($record->self_gen_redline_amount_type)?$record->self_gen_redline_amount_type:"",
    //         // 'self_gen_redline_type'=>isset($record->self_gen_redline_type)?$record->self_gen_redline_type:"",
    //         // 'self_gen_commission'=>isset($record->self_gen_commission)?$record->self_gen_commission:"",
    //         // 'self_gen_upfront_amount'=>isset($record->self_gen_upfront_amount)?$record->self_gen_upfront_amount:"",
    //         // 'self_gen_upfront_type'=>isset($record->self_gen_upfront_type)?$record->self_gen_upfront_type:"",
    //         // 'withheld_type'=>isset($record->withheld_type)?$record->withheld_type:"",
    //         // 'self_gen_withheld_type'=>isset($record->self_gen_withheld_type)?$record->self_gen_withheld_type:"",
    //         );
    //      }
    //      return collect($result);
    // }

    // public function headings(): array
    // {
    //     return [
    //         // 'user_id',
    //         'aveyo_hs_id',
    //         'employee_id',
    //         'first_name',
    //         'middle_name',
    //         'last_name',
    //         'email',
    //         'status',
    //         // 'api_token',
    //         // 'password',
    //         'mobile_no',
    //         'state',
    //         // 'city_id',
    //         // 'location',
    //         // 'department',
    //         // 'position_id',
    //         // 'position',
    //         'manager',
    //         // 'self_gen_accounts',
    //         // 'self_gen_type',
    //         // 'additional_recruiter',
    //         // 'additional_locations',
    //         // 'commission',
    //         // 'redline',
    //         // 'redline_amount_type',
    //         // 'redline_type',
    //         // 'upfront_pay_amount',
    //         // 'upfront_sale_type',
    //         // 'withheld_amount',
    //         // 'self_gen_withheld_amount',
    //         // 'offer_include_bonus',
    //         // 'direct_overrides_amount',
    //         // 'direct_overrides_type',
    //         // 'indirect_overrides_amount',
    //         // 'indirect_overrides_type',
    //         // 'office_overrides_amount',
    //         // 'office_overrides_type',
    //         // 'office_stack_overrides_amount',
    //         // 'probation_period',
    //         // 'hiring_bonus_amount',
    //         // 'date_to_be_paid',
    //         // 'period_of_agreement_start_date',
    //         // 'end_date',
    //         // 'offer_expiry_date',
    //         // 'user_offer_letter',
    //         // 'document_id',
    //         // 'response',
    //         // 'sex',
    //         // 'image',
    //         // 'dob',
    //         // 'zip_code',
    //         // 'work_email',
    //         // 'home_address',
    //         // 'type',
    //         // 'hiring_type',
    //         'office',
    //         // 'self_gen_redline',
    //         // 'self_gen_redline_amount_type',
    //         // 'self_gen_redline_type',
    //         // 'self_gen_commission',
    //         // 'self_gen_upfront_amount',
    //         // 'self_gen_upfront_type',
    //         // 'withheld_type',
    //         // 'self_gen_withheld_type'
    //     ];
    // }
    private $request;

    public function __construct($request = '')
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
        $request = $this->request;

        $officeId = auth()->user()->office_id;
        $status_id_filter = '';

        $other_status_filter = isset($request->other_status_filter) ? $request->other_status_filter : '';
        $hire_now_filter = '';
        $offer_letter_accepted_filter = '';
        $superAdmin = Auth::user()->is_super_admin;

        $user = OnboardingEmployees::orderBy('id', 'desc');
        $user->with('departmentDetail', 'positionDetail', 'managerDetail', 'statusDetail', 'recruiter', 'additionalDetail', 'additionalLocation', 'state', 'city', 'teamsDetail', 'subpositionDetail', 'office', 'OnboardingAdditionalEmails');

        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->where('status_id', '!=', 14)
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->where('status_id', '!=', 14)
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->input('filter').'%'])
                    ->where('status_id', '!=', 14)
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->where('status_id', '!=', 14)
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%')
                    ->where('status_id', '!=', 14)
                    ->orWhereHas('OnboardingAdditionalEmails', function ($query) use ($request) {
                        $query->where('email', 'like', '%'.$request->input('filter').'%');
                    });
            });
        }

        if ($request->has('status_filter') && ! empty($request->input('status_filter')) && $other_status_filter == '') {

            if ($request->input('status_filter') == 13) {
                $user->where(function ($query) {
                    $query->where('status_id', 1);
                });
                $offer_letter_accepted_filter = 1;
            } elseif ($request->input('status_filter') == 1) {
                $user->where(function ($query) {
                    $query->where('status_id', 1);
                });
                $hire_now_filter = 1;
            } else {
                $user->where(function ($query) use ($request) {
                    $query->where('status_id', $request->input('status_filter'));
                });

            }
            $status_id_filter = $request->input('status_filter');
        }

        // if($other_status_filter == 1){
        //     $user->where(function($query) use ($request) {
        //         $query->where('status_id', 7);
        //     });
        // }

        // if($other_status_filter == 2){
        //     $user->where(function($query) use ($request) {
        //         $query->where('status_id', 1);
        //     });
        // }

        if ($request->has('position_filter') && ! empty($request->input('position_filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('sub_position_id', $request->input('position_filter'));
            });

        }

        if ($request->has('manager_filter') && ! empty($request->input('manager_filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('manager_id', $request->input('manager_filter'));
            });

        }
        if ($request->has('department_id') && ! empty($request->input('department_id'))) {
            $user->where(function ($query) use ($request) {
                $query->where('department_id', $request->input('department_id'));
            });
        }
        if ($request->has('updated_at') && ! empty($request->updated_at)) {
            $user->where(function ($query) use ($request) {
                $query->wheredate('updated_at', $request->input('updated_at'));
            });
        }

        if ($request->has('office_id') && ! empty($request->input('office_id')) && $request->input('office_id') !== 'all' && $superAdmin == 1) {
            $user = $user->where('office_id', $request->input('office_id'));
        } elseif (! $superAdmin && ! empty($officeId)) {
            $user = $user->where('office_id', $officeId);
        }

        $user_data = $user->with('OnboardingEmployeesDocuments', 'OnboardingAdditionalEmails')
            ->orderBy('id', 'DESC')->where('status_id', '!=', 14);

        $user = $user_data->get();

        return $user->transform(function ($result) {
            $additionalRecruiterName = [];
            foreach ($result?->additionalDetail as $key => $value) {
                $additionalRecruiterName[] = $value->additionalRecruiterDetail?->first_name.' '.
                $value->additionalRecruiterDetail?->last_name;
            }

            // dump($result?->additionalDetail/* ?->additionalRecruiterDetail */);
            return [
                'first_name' => $result?->first_name,
                'last_name' => $result?->last_name,
                'email' => $result?->email,
                'mobile_no' => $result?->mobile_no,
                'department' => $result?->departmentDetail?->name,
                'position' => $result?->positionDetail?->position_name,
                'state' => $result?->state?->name,
                'office' => $result?->office?->office_name,
                'manager' => $result?->managerDetail?->first_name.' '.$result?->managerDetail?->last_name,
                'recruiter' => $result?->recruiter?->first_name.' '.$result?->recruiter?->last_name,
                'additional_recruiter' => implode(',', $additionalRecruiterName),
                'Act as closer and setter?' => $result?->positionDetail?->position_name,
                'is_manager' => $result?->positionDetail?->is_manager,
                'any_additional_locations' => $result?->additionalLocation?->name,
                'status' => $result?->statusDetail?->status,
                // Custom Sales Field IDs
                'commission_custom_sales_field_id' => $result?->commission_custom_sales_field_id,
                'upfront_custom_sales_field_id' => $result?->upfront_custom_sales_field_id,
                'direct_custom_sales_field_id' => $result?->direct_custom_sales_field_id,
                'indirect_custom_sales_field_id' => $result?->indirect_custom_sales_field_id,
                'office_custom_sales_field_id' => $result?->office_custom_sales_field_id,
            ];
        });
        /* $result = [];
    foreach ($user as $record) {
    if ($record->is_manager == 1) {
    $is_manager = 'manager';
    } else {
    $is_manager = '';
    }
    $result[] = array(
    'aveyo_hs_id' => isset($record->aveyo_hs_id) ? $record->aveyo_hs_id : "",
    'employee_id' => isset($record->employee_id) ? $record->employee_id : "",
    'first_name' => isset($record->first_name) ? $record->first_name : "",
    'middle_name' => isset($record->middle_name) ? $record->middle_name : "",
    'last_name' => isset($record->last_name) ? $record->last_name : "",
    'email' => isset($record->email) ? $record->email : "",
    'status' => isset($record->statusDetail->status) ? $record->statusDetail->status : "",
    'mobile_no' => isset($record->mobile_no) ? $record->mobile_no : "",
    'state' => isset($record->state->name) ? $record->state->name : "",
    'manager' => isset($record->managerDetail->name) ? $record->managerDetail->name : "",
    'office' => isset($record->office->office_name) ? $record->office->office_name : "",
    );
    }
    return collect($result); */
    }

    public function headings(): array
    {
        return [
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Department',
            'Position',
            'State',
            'Office',
            'Manager',
            'Recruiter',
            'Additional Recruiter',
            'Act as closer and setter?',
            'Is a manager',
            'Any additional  Locations',
            'Status',
            // Custom Sales Field Headers
            'Commission Custom Field ID',
            'Upfront Custom Field ID',
            'Direct Override Custom Field ID',
            'Indirect Override Custom Field ID',
            'Office Override Custom Field ID',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:T1')->applyFromArray([
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
        return 'Onboarding List';
    }
}
