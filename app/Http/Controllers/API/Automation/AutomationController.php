<?php

namespace App\Http\Controllers\API\Automation;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAutomationRuleRequest;
use App\Http\Requests\UpdateAutomationRuleRequest;
use App\Models\AutomationRule;
use App\Models\HiringStatus;
use App\Models\PipelineLeadStatus;
use App\Models\PipelineSubTask;
use App\Models\PipelineSubTaskCompleteByLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|integer|in:'.implode(',', AutomationRule::getStatuses()),
            'category' => 'nullable|string|in:'.implode(',', AutomationRule::getCategories()),
        ]);

        // $query = AutomationRule::query();
        // $query->where('status', $request->status);
        // $query->where('category', $request->category);

        $query = AutomationRule::query()->with(['user']);

        if ($request->filled('automation_title')) {
            $query->where('automation_title', 'like', '%'.$request->automation_title.'%');
        }

        if ($request->filled('category')) {
            $query->where('category', ucfirst($request->category));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rules = $query->paginate(10);
        $rules->transform(function ($rule) {
            $modifiedWhen = collect($rule->rule[0]['when'])->transform(function ($when) use ($rule) {

                $from_bucket_names = '';
                $to_bucket_names = '';
                $in_bucket_names = '';

                if (is_array($when)) {

                    if (isset($when['from_buckets'])) {
                        // event_type: Lead Moves
                        foreach ($when['from_buckets'] as $from_bucket) {
                            if ($from_bucket == '0') {
                                $from_bucket_names .= 'Any,';
                            } else {
                                if ($rule->category == 'Lead') {
                                    $status = PipelineLeadStatus::find($from_bucket);
                                    if ($status) {
                                        $from_bucket_names .= $status->status_name.',';
                                    }
                                } else {
                                    $status = HiringStatus::find($from_bucket);
                                    if ($status) {
                                        $from_bucket_names .= $status->status.',';
                                    }
                                }

                            }
                        }
                    }

                    if (isset($when['in_buckets'])) {
                        // event_type: Lead Stays
                        foreach ($when['in_buckets'] as $in_bucket) {
                            if ($in_bucket == '0') {
                                $in_bucket_names .= 'Any,';
                            } else {
                                if ($rule->category == 'Lead') {
                                    $status = PipelineLeadStatus::find($in_bucket);
                                    if ($status) {
                                        $in_bucket_names .= $status->status_name.',';
                                    }
                                } else {
                                    $status = HiringStatus::find($in_bucket);
                                    if ($status) {
                                        $in_bucket_names .= $status->status.',';
                                    }
                                }

                            }
                        }

                    }

                    if (isset($when['to_buckets'])) {
                        // event_type: Lead Stays
                        foreach ($when['to_buckets'] as $to_bucket) {
                            if ($to_bucket == '0') {
                                $to_bucket_names .= 'Any,';
                            } else {
                                if ($rule->category == 'Lead') {
                                    $status = PipelineLeadStatus::find($to_bucket);
                                    if ($status) {
                                        $to_bucket_names .= $status->status_name.',';
                                    }
                                } else {
                                    $status = HiringStatus::find($to_bucket);
                                    if ($status) {
                                        $to_bucket_names .= $status->status.',';
                                    }
                                }

                            }
                        }

                    }

                    if (isset($when['sub_task_id'])) {

                        $subTask = PipelineSubTask::find($when['sub_task_id']);
                        if ($subTask) {
                            $when['sub_task_name'] = $subTask->description;
                        } else {
                            $when['sub_task_name'] = '';
                        }

                    }

                    if (isset($when['sub_task_id']) && isset($when['from_bucket'])) {

                        $completed = PipelineSubTaskCompleteByLead::where([
                            'pipeline_sub_task_id' => $when['sub_task_id'],
                        ])->where('pipeline_lead_status_id', $when['from_bucket'])->first();

                        if ($completed) {
                            $when['sub_task_status'] = 'completed';
                        } else {
                            $when['sub_task_status'] = '';
                        }

                    }

                    $when['from_bucket_names'] = $from_bucket_names;
                    $when['to_bucket_names'] = $to_bucket_names;
                    $when['in_bucket_names'] = $in_bucket_names;
                }

                return $when;
            });
            $rule->whenWithMeta = $modifiedWhen;
            $modifiedThen = collect($rule->rule[0]['then'])->transform(function ($then) {
                // $then['emails'] = ['sdfdf@sdf.com','sfs@ss.com'];
                return $then;
            });
            $rule->thenWithMeta = $modifiedThen;

            return $rule;
        });

        return response()->json([
            'status' => true,
            'data' => $rules,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAutomationRuleRequest $request): JsonResponse
    {
        $rule = AutomationRule::create([
            'automation_title' => $request->automation_title,
            'rule' => $request->rule,
            'status' => AutomationRule::STATUS_ACTIVE,
            'category' => $request->category,
            'user_id' => $request->user_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Automation Rule created successfully.',
            'data' => $rule,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $automationRule = AutomationRule::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $automationRule,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     */
    public function update(UpdateAutomationRuleRequest $request): JsonResponse
    {
        $automationRule = AutomationRule::where('id', $request->id);

        $automationRule->update([
            'automation_title' => $request->automation_title,
            'rule' => $request->rule,
            'status' => AutomationRule::STATUS_ACTIVE,
            // 'category' => AutomationRule::CATEGORY_LEAD,
            'category' => $request->category,
            'user_id' => $request->user_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Automation Rule updated successfully.',
            'data' => $automationRule,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $automationRule = AutomationRule::where('id', $id);
        $automationRule->delete();

        return response()->json([
            'status' => true,
            'message' => 'Automation Rule deleted successfully.',
        ], 200);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'automation_title' => 'nullable|string|max:255',
            'category' => [
                'nullable',
                'string',
                Rule::in(AutomationRule::getCategories()),
            ],
        ]);

        $automationRules = AutomationRule::query();

        if ($request->filled('automation_title')) {
            $automationRules->where('automation_title', 'like', '%'.$request->automation_title.'%');
        }

        if ($request->filled('category')) {
            $automationRules->where('category', $request->category);
        }

        $automationRules = $automationRules->get();

        if ($automationRules->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No automation rules found with the given title.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Automation rules retrieved successfully.',
            'data' => $automationRules,
        ], 200);
    }

    public function deactivate($id): JsonResponse
    {
        $rule = AutomationRule::find($id);

        if (! $rule) {
            return response()->json([
                'status' => false,
                'message' => 'Automation rule not found',
            ], 404);
        }

        $rule->update(['status' => AutomationRule::STATUS_INACTIVE]);

        return response()->json([
            'status' => true,
            'message' => 'Automation rule deactivated successfully',
        ], 200);
    }

    public function activate($id): JsonResponse
    {
        $rule = AutomationRule::find($id);

        if (! $rule) {
            return response()->json([
                'status' => false,
                'message' => 'Automation rule not found',
            ], 404);
        }

        $rule->update(['status' => AutomationRule::STATUS_ACTIVE]);

        return response()->json([
            'status' => true,
            'message' => 'Automation rule activated successfully',
        ], 200);
    }

    public function categories()
    {

        return AutomationRule::getCategories();

    }

    public function events($category)
    {

        return AutomationRule::getEvents(ucfirst(strtolower($category)));

    }

    public function getEventActions($category)
    {

        return AutomationRule::getEventActions(ucfirst(strtolower($category)));

    }
}
