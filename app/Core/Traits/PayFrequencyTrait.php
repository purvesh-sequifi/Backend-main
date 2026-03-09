<?php

namespace App\Core\Traits;

use App\Models\AdditionalPayFrequency;
use App\Models\DailyPayFrequency;
use App\Models\FrequencyType;
use App\Models\MonthlyPayFrequency;
use App\Models\payFrequencySetting;
use App\Models\Payroll;
use App\Models\PositionPayFrequency;
use App\Models\User;
use App\Models\WeeklyPayFrequency;
use Carbon\Carbon;

trait PayFrequencyTrait
{
    public function payFrequency($date, $positionId, $userId)
    {
        $payFrequency = [];
        $positionPayFrequency = PositionPayFrequency::where(['position_id' => $positionId])->first();
        if ($positionPayFrequency) {
            $user = User::find($userId);
            $workerType = isset($user->worker_type) ? $user->worker_type : '1099';
            if ($positionPayFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
                $current_data = $date;
                $payFrequency = WeeklyPayFrequency::whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
                $payId = ($payFrequency->id + 1);
                if ($user && ($workerType == 'w2' || $workerType == 'W2')) {
                    $payFrequency1 = WeeklyPayFrequency::where('id', '>=', $payId)->where('w2_closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                } else {
                    $payFrequency1 = WeeklyPayFrequency::where('id', '>=', $payId)->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                }
                $payFrequency->pay_period_from = $payFrequency->pay_period_from;
                $payFrequency->pay_period_to = $payFrequency->pay_period_to;
                $payFrequency->next_pay_period_from = $payFrequency1->pay_period_from;
                $payFrequency->next_pay_period_to = $payFrequency1->pay_period_to;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
                $current_data = $date;
                $payFrequency = MonthlyPayFrequency::whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
                $payId = ($payFrequency->id + 1);
                if ($user && ($workerType == 'w2' || $workerType == 'W2')) {
                    $payFrequency1 = MonthlyPayFrequency::where('id', '>=', $payId)->where('w2_closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                } else {
                    $payFrequency1 = MonthlyPayFrequency::where('id', '>=', $payId)->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                }
                $payFrequency->pay_period_from = $payFrequency->pay_period_from;
                $payFrequency->pay_period_to = $payFrequency->pay_period_to;
                $payFrequency->next_pay_period_from = $payFrequency1->pay_period_from;
                $payFrequency->next_pay_period_to = $payFrequency1->pay_period_to;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                $current_data = $date;
                $payFrequency = AdditionalPayFrequency::where('type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
                $payId = ($payFrequency->id + 1);
                if ($user && ($workerType == 'w2' || $workerType == 'W2')) {
                    $payFrequency1 = AdditionalPayFrequency::where('type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->where('id', '>=', $payId)->where('w2_closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                } else {
                    $payFrequency1 = AdditionalPayFrequency::where('type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->where('id', '>=', $payId)->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                }
                $payFrequency->next_pay_period_from = $payFrequency1->pay_period_from;
                $payFrequency->next_pay_period_to = $payFrequency1->pay_period_to;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $current_data = $date;
                $payFrequency = AdditionalPayFrequency::where('type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
                $payId = ($payFrequency->id + 1);
                if ($user && ($workerType == 'w2' || $workerType == 'W2')) {
                    $payFrequency1 = AdditionalPayFrequency::where('type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->where('id', '>=', $payId)->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                } else {
                    $payFrequency1 = AdditionalPayFrequency::where('type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->where('id', '>=', $payId)->where('w2_closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                }
                $payFrequency->next_pay_period_from = $payFrequency1->pay_period_from;
                $payFrequency->next_pay_period_to = $payFrequency1->pay_period_to;
            }

            if ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                return $this->daily_pay_period(DailyPayFrequency::class);
            }
        }

        if ($user && ($workerType == 'w2' || $workerType == 'W2')) {
            $payFrequency->closed_status = $payFrequency->w2_closed_status;
        }
        $payFrequency['pay_frequency'] = $positionPayFrequency->frequency_type_id;
        return $payFrequency;
    }

    public function payFrequencyNew($date, $positionId, $userId)
    {
        $positionPayFrequency = PositionPayFrequency::query()->where(['position_id' => $positionId])->first();
        if ($positionPayFrequency) {
            $type = '';
            $user = User::find($userId);
            if ($positionPayFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
                $class = WeeklyPayFrequency::class;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
                $class = MonthlyPayFrequency::class;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                $class = DailyPayFrequency::class;
            }

            if (isset($class)) {
                $payFrequency = $class::query();
                if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID || $positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                    $payFrequency = $payFrequency->where('type', $type)->whereRaw('"'.$date.'" between `pay_period_from` and `pay_period_to`')->first();
                    if (! $payFrequency && $date < date('Y-m-d')) {
                        $payFrequency = $class::query()->where('type', $type)->whereRaw('`pay_period_from` >= "'.$date.'"')->first();
                    } elseif (! $payFrequency && $date < date('Y-m-d')) {
                        $payFrequency = $class::query()->where('type', $type)->whereRaw('"'.date('Y-m-d').'" between `pay_period_from` and `pay_period_to`')->first();
                    }
                    $newClass = $class::query()->where('type', $type);
                } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                    return $this->daily_pay_period($class);
                } else {
                    $payFrequency = $payFrequency->whereRaw('"'.$date.'" between `pay_period_from` and `pay_period_to`')->first();
                    if (! $payFrequency && $date < date('Y-m-d')) {
                        $payFrequency = $class::query()->whereRaw('`pay_period_from` >= "'.$date.'"')->first();
                    } elseif (! $payFrequency && $date < date('Y-m-d')) {
                        $payFrequency = $class::query()->whereRaw('"'.date('Y-m-d').'" between `pay_period_from` and `pay_period_to`')->first();
                    }
                    $newClass = $class::query();
                }

                $closeStatus = $payFrequency->closed_status;
                $workerType = isset($user->worker_type) ? $user->worker_type : '1099';
                if ($user && ($workerType == 'w2' || $workerType == 'W2')) {
                    $closeStatus = $payFrequency->w2_closed_status;
                }
                if ($payFrequency && $closeStatus == '1') {
                    $payFrequency = $this->findNextPayroll($newClass, $workerType, $payFrequency);
                } else {
                    $payroll = Payroll::query()->where(['pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to, 'worker_type' => $workerType, 'pay_frequency' => $positionPayFrequency->frequency_type_id])->whereIn('status', ['2', '3'])->first();
                    if ($payroll) {
                        $payFrequency = $this->findNextPayroll($newClass, $workerType, $payFrequency);
                    }
                }
                $payFrequency->next_pay_period_from = $payFrequency->pay_period_from;
                $payFrequency->next_pay_period_to = $payFrequency->pay_period_to;

                // SECOND OPEN UN-FINALIZED PAYROLL
                $secondPayFrequency = $this->findNextPayroll($newClass, $workerType, $payFrequency);
                if ($secondPayFrequency) {
                    $payFrequency->second_pay_period_from = $secondPayFrequency->pay_period_from;
                    $payFrequency->second_pay_period_to = $secondPayFrequency->pay_period_to;
                }
                $payFrequency->pay_frequency = $positionPayFrequency->frequency_type_id;
                return $payFrequency;
            }
        }

        return [];
    }

    public function payFrequencyById($date, $frequencyId, $workerType = '1099')
    {
        if ($frequencyId == FrequencyType::WEEKLY_ID) {
            $class = WeeklyPayFrequency::class;
        } elseif ($frequencyId == FrequencyType::MONTHLY_ID) {
            $class = MonthlyPayFrequency::class;
        } elseif ($frequencyId == FrequencyType::BI_WEEKLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
        } elseif ($frequencyId == FrequencyType::SEMI_MONTHLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
        } elseif ($frequencyId == FrequencyType::DAILY_PAY_ID) {
            $class = DailyPayFrequency::class;
        }

        if (isset($class)) {
            $payFrequency = $class::query();
            if ($frequencyId == FrequencyType::BI_WEEKLY_ID || $frequencyId == FrequencyType::SEMI_MONTHLY_ID) {
                $payFrequency = $payFrequency->where('type', $type)->whereRaw('"'.$date.'" between `pay_period_from` and `pay_period_to`')->first();
                if (! $payFrequency && $date < date('Y-m-d')) {
                    $payFrequency = $class::query()->where('type', $type)->whereRaw('"'.date('Y-m-d').'" between `pay_period_from` and `pay_period_to`')->first();
                }
                $newClass = $class::query()->where('type', $type);
            } elseif ($frequencyId == FrequencyType::DAILY_PAY_ID) {
                return $this->daily_pay_period($class);
            } else {
                $payFrequency = $payFrequency->whereRaw('"'.$date.'" between `pay_period_from` and `pay_period_to`')->first();
                if (! $payFrequency && $date < date('Y-m-d')) {
                    $payFrequency = $class::query()->whereRaw('"'.date('Y-m-d').'" between `pay_period_from` and `pay_period_to`')->first();
                }
                $newClass = $class::query();
            }

            $closeStatus = $payFrequency->closed_status;
            if ($workerType == 'w2' || $workerType == 'W2') {
                $closeStatus = $payFrequency->w2_closed_status;
            }
            if ($payFrequency && $closeStatus == '1') {
                $payFrequency = $this->findNextPayroll($newClass, $workerType, $payFrequency);
            } else {
                $payroll = Payroll::query()->where(['pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to, 'worker_type' => $workerType, 'status' => '2'])->first();
                if ($payroll) {
                    $payFrequency = $this->findNextPayroll($newClass, $workerType, $payFrequency);
                }
            }
            $payFrequency->next_pay_period_from = $payFrequency->pay_period_from;
            $payFrequency->next_pay_period_to = $payFrequency->pay_period_to;

            // SECOND OPEN UN-FINALIZED PAYROLL
            $secondPayFrequency = $this->findNextPayroll($newClass, $workerType, $payFrequency);
            $payFrequency->second_pay_period_from = $secondPayFrequency->pay_period_from;
            $payFrequency->second_pay_period_to = $secondPayFrequency->pay_period_to;

            return $payFrequency;
        }

        return [];
    }

    public function daily_pay_period($class)
    {
        $payFrequency = $class::query()->where('closed_status', 1)->orderBy('pay_period_to', 'DESC')->first();
        if ($payFrequency) {
            $pay_period_from = Carbon::parse($payFrequency->pay_period_to)->addDay();
            $pay_period_to = Carbon::parse(date('Y-m-d'));

            if ($pay_period_from->lte($pay_period_to)) {
                $payFrequency->next_pay_period_from = date('Y-m-d');
                $payFrequency->next_pay_period_to = date('Y-m-d');
                $payFrequency->pay_period_from = date('Y-m-d');
                $payFrequency->pay_period_to = date('Y-m-d');
            } else {
                $payFrequency->next_pay_period_from = date('Y-m-d', strtotime('+1 day'));
                $payFrequency->next_pay_period_to = date('Y-m-d', strtotime('+1 day'));
                $payFrequency->pay_period_from = date('Y-m-d', strtotime('+1 day'));
                $payFrequency->pay_period_to = date('Y-m-d', strtotime('+1 day'));
            }
        } else {
            $payFrequencySetting = payFrequencySetting::where('frequency_type_id', FrequencyType::DAILY_PAY_ID)->first();
            $payFrequency = (object) [];
            $payFrequency->closed_status = 0;
            $payFrequency->open_status_from_bank = 0;

            $first_day = Carbon::parse(date('Y-m-d', strtotime($payFrequencySetting->first_day)));
            if ($payFrequencySetting && $first_day->lte(Carbon::parse(date('Y-m-d')))) {
                $payFrequency->next_pay_period_from = date('Y-m-d');
                $payFrequency->next_pay_period_to = date('Y-m-d');
                $payFrequency->pay_period_from = date('Y-m-d');
                $payFrequency->pay_period_to = date('Y-m-d');
            } elseif ($payFrequencySetting) {
                $payFrequency->next_pay_period_from = $payFrequencySetting->first_day;
                $payFrequency->next_pay_period_to = $payFrequencySetting->first_day;
                $payFrequency->pay_period_from = $payFrequencySetting->first_day;
                $payFrequency->pay_period_to = $payFrequencySetting->first_day;
            } else {
                $payFrequency->next_pay_period_from = null;
                $payFrequency->next_pay_period_to = null;
                $payFrequency->pay_period_from = null;
                $payFrequency->pay_period_to = null;
            }
        }
        $payFrequency->second_pay_period_from = date('Y-m-d', strtotime('+1 day'));
        $payFrequency->second_pay_period_to = date('Y-m-d', strtotime('+1 day'));
        $payFrequency->pay_frequency = FrequencyType::DAILY_PAY_ID;
        return $payFrequency;
    }

    public function daily_pay_period_date()
    {
        $payFrequency = DailyPayFrequency::query()->orderBy('pay_period_to', 'DESC')->first();
        $payPeriod = [];
        $current_pay_period_type = 'Current Payroll';
        if ($payFrequency) {
            $current_pay_period_from = Carbon::parse($payFrequency->pay_period_to)->addDay();
            $current_pay_period_to = Carbon::parse(date('Y-m-d'));

            if ($current_pay_period_from->lte($current_pay_period_to)) {
                $payPeriod[] = [
                    'id' => $current_pay_period_type,
                    'pay_period_from' => $current_pay_period_from->format('Y-m-d'),
                    'pay_period_to' => $current_pay_period_to->format('Y-m-d'),
                    'pay_period_type' => $current_pay_period_type,
                ];
            }
        } else {
            $payFrequencySetting = payFrequencySetting::where('frequency_type_id', FrequencyType::DAILY_PAY_ID)->first();
            $payFrequency = (object) [];

            $first_day = Carbon::parse(date('Y-m-d', strtotime($payFrequencySetting->first_day)));
            // below this condition add by as per discussion with aneesh
            // $first_day = $payFrequencySetting->first_day != null && $payFrequencySetting->first_day != '' ? Carbon::parse(date('Y-m-d',strtotime($payFrequencySetting->first_day))) : Carbon::parse('1949-01-20');
            if ($payFrequencySetting && $first_day->lte(Carbon::parse(date('Y-m-d')))) {
                $payPeriod[] = [
                    'id' => $current_pay_period_type,
                    'pay_period_from' => $first_day->format('Y-m-d'),
                    'pay_period_to' => date('Y-m-d'),
                    'pay_period_type' => $current_pay_period_type,
                ];
            }
        }
        $payPeriod[] = [
            'id' => 'Next Payroll',
            'pay_period_from' => date('Y-m-d', strtotime('+1 day')),
            'pay_period_to' => date('Y-m-d', strtotime('+1 day')),
            'pay_period_type' => 'Next Payroll',
        ];

        return $payPeriod;
    }

    public function findNextPayroll($class, $workerType, $frequency = [])
    {
        if ($workerType == 'w2' || $workerType == 'W2') {
            $frequency = $class->whereDate('pay_period_from', '>=', $frequency->pay_period_to)->where('w2_closed_status', '0')->first();
        } else {
            $frequency = $class->whereDate('pay_period_from', '>=', $frequency->pay_period_to)->where('closed_status', '0')->first();
        }
        if ($frequency) {
            $payroll = Payroll::query()->where(['pay_period_from' => $frequency->pay_period_from, 'pay_period_to' => $frequency->pay_period_to, 'worker_type' => $workerType, 'status' => '2'])->first();
            if (! $payroll) {
                return $frequency;
            }

            return $this->findNextPayroll($class, $workerType, $frequency);
        }

        return null;
    }

    public function openPayFrequency($positionId, $userId)
    {
        $user = User::find($userId);
        $positionPayFrequency = PositionPayFrequency::query()->where(['position_id' => $positionId])->first();
        if ($positionPayFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
            $class = WeeklyPayFrequency::class;
        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
            $class = MonthlyPayFrequency::class;
        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
            $class = DailyPayFrequency::class;
        }

        $payFrequency = $class::query();
        $workerType = isset($user->worker_type) ? $user->worker_type : '1099';
        if ($user && ($workerType == 'w2' || $workerType == 'W2')) {
            if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID || $positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $payFrequency = $payFrequency->where(['type' => $type, 'w2_closed_status' => '0'])->orderBy('pay_period_from')->first();
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                return $this->daily_pay_period($class);
            } else {
                $payFrequency = $payFrequency->where('w2_closed_status', '0')->orderBy('pay_period_from')->first();
            }
        } else {
            if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID || $positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $payFrequency = $payFrequency->where(['type' => $type, 'closed_status' => '0'])->orderBy('pay_period_from')->first();
            } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                return $this->daily_pay_period($class);
            } else {
                $payFrequency = $payFrequency->where('closed_status', '0')->orderBy('pay_period_from')->first();
            }
        }
        $neyClass = $class::query();
        if ($payFrequency) {
            $payRoll = PayRoll::where(['pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to, 'worker_type' => $workerType])->whereIn('finalize_status', [1, 2])->orderBy('pay_period_to', 'asc')->count();
            if ($payRoll > 0) {
                $payFrequency = $this->findNextPayroll($neyClass, $workerType, $payFrequency);
            } else {
                $payFrequency = $payFrequency;
            }
        }
        $payFrequency->next_pay_period_from = $payFrequency->pay_period_from;
        $payFrequency->next_pay_period_to = $payFrequency->pay_period_to;
        $secondPayFrequency = $this->findNextPayroll($neyClass, $workerType, $payFrequency);
        $payFrequency->second_pay_period_from = $secondPayFrequency->pay_period_from;
        $payFrequency->second_pay_period_to = $secondPayFrequency->pay_period_to;
        $payFrequency->pay_frequency = $positionPayFrequency->frequency_type_id;

        return $payFrequency;
    }

    public function separateSelfGenAndNormal($class, $conditions, $orderKey, bool $onlySelfGen = false): array
    {
        $data = $class::query();
        foreach ($conditions as $condition) {
            $data->where($condition['key'], $condition['condition'], $condition['value']);
        }
        $data = $data->orderBy($orderKey, 'DESC')->get();

        if ($onlySelfGen) {
            if (count($data) >= 2) {
                return $data[1];
            }

            return [];
        }

        $response = [];
        if (count($data) >= 2) {
            $response['normal'] = $data[0];
            $response['self_generated'] = $data[1];
        } elseif (count($data) == 1) {
            $response['normal'] = $data[0];
        }

        return $response;
    }

    public function nextPayFrequency($date, $position_id)
    {

        $positionPayFrequency = PositionPayFrequency::where(['position_id' => $position_id])->first();
        $payFrequency = [];
        if ($positionPayFrequency) {
            if ($positionPayFrequency->frequency_type_id == 2) {
                $current_data = $date;
                $payFrequency = WeeklyPayFrequency::whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
                $payId = ($payFrequency->id + 1);
                $payFrequency1 = WeeklyPayFrequency::where('id', '>=', $payId)->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                $payFrequency->pay_period_from = $payFrequency->pay_period_from;
                $payFrequency->pay_period_to = $payFrequency->pay_period_to;
                $payFrequency->next_pay_period_from = isset($payFrequency1->pay_period_from) ? $payFrequency1->pay_period_from : null;
                $payFrequency->next_pay_period_to = isset($payFrequency1->pay_period_to) ? $payFrequency1->pay_period_to : null;
            }
            if ($positionPayFrequency->frequency_type_id == 5) {
                $current_data = $date;
                $payFrequency = MonthlyPayFrequency::whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
                $payId = ($payFrequency->id + 1);
                $payFrequency1 = MonthlyPayFrequency::where('id', '>=', $payId)->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                $payFrequency->pay_period_from = $payFrequency->pay_period_from;
                $payFrequency->pay_period_to = $payFrequency->pay_period_to;
                $payFrequency->next_pay_period_from = isset($payFrequency1->pay_period_from) ? $payFrequency1->pay_period_from : null;
                $payFrequency->next_pay_period_to = isset($payFrequency1->pay_period_to) ? $payFrequency1->pay_period_to : null;
            }

            if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                $current_data = $date;
                $payFrequency = AdditionalPayFrequency::where('type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
                $payId = ($payFrequency->id + 1);
                $payFrequency1 = AdditionalPayFrequency::where('type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->where('id', '>=', $payId)->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                $payFrequency->next_pay_period_from = isset($payFrequency1->pay_period_from) ? $payFrequency1->pay_period_from : null;
                $payFrequency->next_pay_period_to = isset($payFrequency1->pay_period_to) ? $payFrequency1->pay_period_to : null;
            }
            if ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $current_data = $date;
                $payFrequency = AdditionalPayFrequency::where('type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
                $payId = ($payFrequency->id + 1);
                $payFrequency1 = AdditionalPayFrequency::where('type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->where('id', '>=', $payId)->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                $payFrequency->next_pay_period_from = isset($payFrequency1->pay_period_from) ? $payFrequency1->pay_period_from : null;
                $payFrequency->next_pay_period_to = isset($payFrequency1->pay_period_to) ? $payFrequency1->pay_period_to : null;
            }

            if ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                return $this->daily_pay_period(DailyPayFrequency::class);
            }
        }

        return $payFrequency;
    }
}
