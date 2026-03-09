<?php

namespace App\Http\Controllers\API\V2\Sales;

use App\Core\Traits\SaleTraits\EditSaleTrait;
use App\Core\Traits\SaleTraits\SubroutineProjectionTrait;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\ExternalSaleProductMaster;
use App\Models\ExternalSaleWorker;
use App\Models\ProjectionUserCommission;
use App\Models\ProjectionUserOverrides;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\UserCommission;
use App\Services\SalesCalculationContext;
use App\Traits\EmailNotificationTrait;
use Illuminate\Support\Facades\Log;

class SalesProjectionsController extends Controller
{
    use EditSaleTrait, EmailNotificationTrait, SubroutineProjectionTrait;

    /**
     * Process an individual sale for projections
     * Extracted from syncSubroutineProcessData to enable chunked processing
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function syncIndividualSaleProjection(SalesMaster $sale)
    {
        $pid = $sale->pid;

        // Validate required data
        if (empty($pid)) {
            throw new \Exception('PID is required for projection calculation');
        }

        $companyProfile = CompanyProfile::first();
        if (! $companyProfile) {
            throw new \Exception('Company profile not found');
        }

        // Check if Custom Sales Fields feature is enabled for this company
        $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);

        try {
            // Set context INSIDE try block to ensure cleanup on any exception
            // This enables auto-conversion of 'custom field' to 'per sale' in model events
            // during projection calculations for UserCommissionHistory, UserUpfrontHistory, etc.
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::set($sale, $companyProfile);
            }
            
            return $this->performProjectionCalculation($sale, $companyProfile, $pid);
        } finally {
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::clear();
            }
        }
    }

    /**
     * Perform the actual projection calculation logic.
     * Separated for try-finally cleanup of SalesCalculationContext.
     * 
     * @param SalesMaster $sale
     * @param CompanyProfile $companyProfile
     * @param string|int $pid The sale PID (can be alphanumeric like "TCS2001")
     * @return bool
     */
    private function performProjectionCalculation(SalesMaster $sale, CompanyProfile $companyProfile, string|int $pid): bool
    {
        $overrideSetting = CompanySetting::where(['type' => 'overrides', 'status' => '1'])->first();

        Log::info('Starting projection calculation for PID: '.$pid, [
            'company_type' => $companyProfile->company_type,
            'has_override_setting' => ! is_null($overrideSetting),
        ]);

        $kw = $sale->kw;

        // Safely extract user IDs with null checks
        $closerId = $sale->salesMasterProcess?->closer1Detail?->id;
        $closer2Id = $sale->salesMasterProcess?->closer2Detail?->id;
        $setterId = $sale->salesMasterProcess?->setter1Detail?->id;
        $setter2Id = $sale->salesMasterProcess?->setter2Detail?->id;

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $kw = $sale->gross_account_value;
        }

        $existWorker = ExternalSaleWorker::where('pid', $pid)->pluck('user_id')->toArray();

        $saleUsers = [];
        if (! empty($existWorker)) {
            $saleUsers = array_merge($saleUsers, $existWorker);
        }

        if ($closerId) {
            $saleUsers[] = $closerId;
        }
        if ($closer2Id) {
            $saleUsers[] = $closer2Id;
        }
        if ($setterId) {
            $saleUsers[] = $setterId;
        }
        if ($setter2Id) {
            $saleUsers[] = $setter2Id;
        }

        if ($closerId) {
            $isM2Paid = false;
            $m2 = UserCommission::where(['pid' => $pid, 'is_last' => '1', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
            if ($m2) {
                $paidM2 = UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                if ($paidM2) {
                    $isM2Paid = true;
                } else {
                    $paidM2 = UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'recon_status' => '3', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                    if ($paidM2) {
                        $isM2Paid = true;
                    }
                }
            } else {
                $withheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'is_last' => '1', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                if ($withheld) {
                    $paidWithheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'is_last' => '1', 'settlement_type' => 'reconciliation', 'recon_status' => '3', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                    if ($paidWithheld) {
                        $isM2Paid = true;
                    }
                }
            }

            $missingRedLine = false;
            $lastSchemas = SaleProductMaster::with('milestoneSchemaTrigger')->where(['pid' => $pid, 'is_last_date' => '1'])->get()
                ->map(function ($item) {
                    $item->forExternal = false;

                    return $item;
                });
            $externalSchemas = ExternalSaleProductMaster::with('milestoneSchemaTrigger')->where(['pid' => $pid, 'is_last_date' => '1'])->get()
                ->map(function ($item) {
                    $item->forExternal = true;

                    return $item;
                });

            $mergedSchemas = $lastSchemas->merge($externalSchemas);

            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $redline = $this->getRedLineData($sale);

                if ($redline['closer1_is_redline_missing'] || $redline['closer2_is_redline_missing'] || $redline['setter1_is_redline_missing'] || $redline['setter2_is_redline_missing']) {
                    $missingRedLine = true;
                }

                foreach ($redline['external_data'] as $entry) {
                    if (! empty($entry['is_redline_missing'])) {
                        $missingRedLine = true;
                        break;
                    }
                }
            }

            if ($missingRedLine) {
                Log::warning('Redline is missing for solar company PID, skipping projection calculation', [
                    'pid' => $pid,
                    'company_type' => $companyProfile->company_type,
                    'redline_status' => isset($redline) ? $redline : [],
                ]);
                throw new \Exception('Redline is missing for PID: '.$pid);
            }

            $commission = [];
            if (! $isM2Paid) {
                foreach ($mergedSchemas as $lastSchema) {
                    $forExternal = $lastSchema->forExternal ? 1 : 0;
                    $info = $this->salesRepData($lastSchema, $forExternal);
                    $redLine = null;
                    $userInfoType = $forExternal ? $info['id'] : $info['type'];
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $commission[$userInfoType] = $this->upfrontTypePercentCalculationForPest($sale, $info, $companyProfile, $forExternal);
                    } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                        if ($info['type'] == 'closer') {
                            $redLine = $redline['closer1_redline'];
                        } elseif ($info['type'] == 'closer2') {
                            $redLine = $redline['closer2_redline'];
                        } elseif ($info['type'] == 'setter') {
                            $redLine = $redline['setter1_redline'];
                        } elseif ($info['type'] == 'setter2') {
                            $redLine = $redline['setter2_redline'];
                        }
                        if (config('app.domain_name') == 'frdmturf') {
                            $redLine = 0;
                        } else {
                            if ($forExternal) {
                                $userData = collect($redline['external_data'])
                                    ->where('worker_id', $info['id'])
                                    ->flatMap(function ($item) {
                                        return $item;
                                    });
                                $redLine = $userData ? $userData['redline'] : null;
                            } else {
                                if ($info['type'] == 'closer') {
                                    $redLine = $redline['closer1_redline'];
                                } elseif ($info['type'] == 'closer2') {
                                    $redLine = $redline['closer2_redline'];
                                } elseif ($info['type'] == 'setter') {
                                    $redLine = $redline['setter1_redline'];
                                } elseif ($info['type'] == 'setter2') {
                                    $redLine = $redline['setter2_redline'];
                                }
                            }
                        }
                        $commission[$userInfoType] = $this->upfrontTypePercentCalculationForTurf($sale, $info, $companyProfile, $redLine, $forExternal);
                    } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        if ($forExternal) {
                            $userData = collect($redline['external_data'])
                                ->where('worker_id', $info['id'])
                                ->flatMap(function ($item) {
                                    return $item;
                                });
                            $redLine = $userData ? $userData['redline'] : null;
                        } else {
                            if ($info['type'] == 'closer') {
                                $redLine = $redline['closer1_redline'];
                            } elseif ($info['type'] == 'closer2') {
                                $redLine = $redline['closer2_redline'];
                            } elseif ($info['type'] == 'setter') {
                                $redLine = $redline['setter1_redline'];
                            } elseif ($info['type'] == 'setter2') {
                                $redLine = $redline['setter2_redline'];
                            }
                        }

                        $commission[$userInfoType] = $this->upfrontTypePercentCalculationForMortgage($sale, $info, $companyProfile, $redLine, $forExternal);
                    } else {
                        if ($forExternal) {
                            $userData = collect($redline['external_data'])
                                ->where('worker_id', $info['id'])
                                ->flatMap(function ($item) {
                                    return $item;
                                });
                            $redLine = $userData ? $userData['redline'] : null;
                        } else {
                            if ($info['type'] == 'closer') {
                                $redLine = $redline['closer1_redline'];
                            } elseif ($info['type'] == 'closer2') {
                                $redLine = $redline['closer2_redline'];
                            } elseif ($info['type'] == 'setter') {
                                $redLine = $redline['setter1_redline'];
                            } elseif ($info['type'] == 'setter2') {
                                $redLine = $redline['setter2_redline'];
                            }
                        }
                        $commission[$userInfoType] = $this->upfrontTypePercentCalculationForSolar($sale, $info, $redLine, $companyProfile, $forExternal);
                    }
                }
            }

            $schemas = SaleProductMaster::with('milestoneSchemaTrigger')->where(['pid' => $pid, 'is_last_date' => '0'])->get()
                ->map(function ($item) {
                    $item->forExternal = false;

                    return $item;
                });
            $schemasExternal = ExternalSaleProductMaster::with('milestoneSchemaTrigger')->where(['pid' => $pid, 'is_last_date' => '0'])->get()
                ->map(function ($item) {
                    $item->forExternal = true;

                    return $item;
                });

            $mergedSchemas1 = $schemas->merge($schemasExternal);
            foreach ($mergedSchemas1 as $schema) {
                if (! $schema->milestone_date) {
                    $forExternal = $schema->forExternal ? 1 : 0;
                    $info = $this->salesRepData($schema, $forExternal);

                    $this->subroutineThree($sale, $schema, $info, $commission, $forExternal);
                }
            }

            foreach ($mergedSchemas as $lastSchema) {
                if (! $lastSchema->milestone_date) {
                    $forExternal = $lastSchema->forExternal ? 1 : 0;
                    $info = $this->salesRepData($lastSchema, $forExternal);
                    $redLine = null;

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $this->subroutineEightForPest($sale, $lastSchema, $info, $companyProfile, $forExternal);
                    } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                        $this->subroutineEightForTurf($sale, $lastSchema, $info, $companyProfile, $forExternal);
                    } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        if ($forExternal) {
                            $userData = collect($redline['external_data'])
                                ->where('worker_id', $info['id'])
                                ->flatMap(function ($item) {
                                    return $item;
                                });
                            $redLine = $userData ? $userData['redline'] : null;
                            $redLineType = $userData ? $userData['redline_type'] : null;
                        } else {
                            if ($info['type'] == 'closer') {
                                $redLine = $redline['closer1_redline'];
                                $redLineType = $redline['closer1_redline_type'];
                            } elseif ($info['type'] == 'closer2') {
                                $redLine = $redline['closer2_redline'];
                                $redLineType = $redline['closer2_redline_type'];
                            } elseif ($info['type'] == 'setter') {
                                $redLine = $redline['setter1_redline'];
                                $redLineType = $redline['setter1_redline_type'];
                            } elseif ($info['type'] == 'setter2') {
                                $redLine = $redline['setter2_redline'];
                                $redLineType = $redline['setter2_redline_type'];
                            }
                        }
                        $this->subroutineEightForMortgage($sale, $lastSchema, $info, $companyProfile, $redLine, $forExternal);
                    } else {
                        if ($forExternal) {
                            $userData = collect($redline['external_data'])
                                ->where('worker_id', $info['id'])
                                ->flatMap(function ($item) {
                                    return $item;
                                });
                            $redLine = $userData ? $userData['redline'] : null;
                            $redLineType = $userData ? $userData['redline_type'] : null;
                        } else {
                            if ($info['type'] == 'closer') {
                                $redLine = $redline['closer1_redline'];
                                $redLineType = $redline['closer1_redline_type'];
                            } elseif ($info['type'] == 'closer2') {
                                $redLine = $redline['closer2_redline'];
                                $redLineType = $redline['closer2_redline_type'];
                            } elseif ($info['type'] == 'setter') {
                                $redLine = $redline['setter1_redline'];
                                $redLineType = $redline['setter1_redline_type'];
                            } elseif ($info['type'] == 'setter2') {
                                $redLine = $redline['setter2_redline'];
                                $redLineType = $redline['setter2_redline_type'];
                            }
                        }
                        $this->subroutineEightForSolar($sale, $lastSchema, $info, $redLine, $companyProfile, $forExternal);
                    }
                }
            }

            $overrideTrigger = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1'])->whereNull('milestone_date')->groupBy('type')->first();
            $overrideTriggerSchemas = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1', 'type' => $overrideTrigger?->type])->whereNull('milestone_date')->get()->map(function ($item) {
                $item->forExternal = false;

                return $item;
            });
            $overrideTriggerSchemasExternal = ExternalSaleProductMaster::where(['pid' => $pid, 'is_override' => '1', 'type' => $overrideTrigger?->type])->whereNull('milestone_date')->get()->map(function ($item) {
                $item->forExternal = true;

                return $item;
            });
            $mergedOverrideTriggerSchemas = $overrideTriggerSchemas->merge($overrideTriggerSchemasExternal);
            foreach ($mergedOverrideTriggerSchemas as $overrideTriggerSchema) {
                if ($overrideSetting && ! $overrideTriggerSchema->milestone_date) {
                    $forExternal = $overrideTriggerSchema->forExternal ? 1 : 0;
                    $info = $this->salesRepData($overrideTriggerSchema, $forExternal);
                    if (isset($info['id']) && $forExternal == 0) {
                        $this->userOverride($info, $pid, $kw, $commission, $forExternal);
                    }
                }
            }

            foreach ($mergedSchemas as $lastSchema) {
                if (! $lastSchema->milestone_date) {
                    $forExternal = $lastSchema->forExternal ? 1 : 0;
                    $info = $this->salesRepData($lastSchema, $forExternal);
                    if ($info['type'] == 'closer' && $forExternal == 0) {
                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            $this->pestStackUserOverride($info['id'], $pid);
                        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                            $this->turfStackUserOverride($info['id'], $pid, $kw);
                        } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                            $this->mortgageStackUserOverride($info['id'], $pid, $kw);
                        } else {
                            $this->stackUserOverride($info['id'], $pid, $kw);
                        }
                    }
                }
            }
        }

        $this->manageDataForDisplay($pid, false);

        Log::info('Successfully completed projection calculation for PID: '.$pid);

        return true;
    }

    public function syncSubroutineProcessData($pid = '')
    {
        // For backward compatibility and direct processing of a single PID
        if (! empty($pid)) {
            try {
                // Start transaction for atomic operation
                \DB::beginTransaction();

                // Clean up existing projection data for this specific PID
                ProjectionUserCommission::where('pid', $pid)->delete();
                ProjectionUserOverrides::where('pid', $pid)
                    ->where('type', '!=', 'One Time')
                    ->delete();

                $sale = SalesMaster::with([
                    'salesMasterProcess.closer1Detail',
                    'salesMasterProcess.closer2Detail',
                    'salesMasterProcess.setter1Detail',
                    'salesMasterProcess.setter2Detail',
                    'salesProductMaster',
                ])
                    ->whereHas('salesProductMaster', function ($q) {
                        $q->whereNull('milestone_date')->where('is_last_date', '1');
                    })
                    ->whereNotNull('customer_signoff')
                    ->whereNull('date_cancelled')
                    ->where('pid', $pid)
                    ->orderBy('customer_signoff', 'ASC')
                    ->first();

                // if (!$sale) {
                //     \DB::rollBack();
                //     return ['success' => true, 'message' => 'Data Not Found!!'];
                // }

                if ($sale) {
                    $this->syncIndividualSaleProjection($sale);
                }

                \DB::commit();

                return ['success' => true, 'message' => 'Commission Projection Command Success for PID: '.$pid];
            } catch (\Exception $e) {
                \DB::rollBack();
                \Log::error('Error processing single PID in sales projection sync', [
                    'pid' => $pid,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return ['success' => false, 'message' => 'Error: '.$e->getMessage()];
            }
        }

        // This will be obsolete as we're now using chunked processing in the command
        // We'll keep it for backward compatibility but just return a message
        return ['success' => true, 'message' => 'Please use the chunked command implementation for processing all records.'];
    }
}
