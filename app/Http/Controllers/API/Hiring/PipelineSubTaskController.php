<?php

namespace App\Http\Controllers\API\Hiring;

use App\Http\Controllers\Controller;
use App\Models\PipelineSubTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineSubTaskController extends Controller
{
    // listing
    public function index(Request $request): JsonResponse
    {
        // dd($request->query('0'));
        $pipeline_id = 0;
        if ($request->pipeline_id) {
            $pipeline_id = $request->pipeline_id;
        } elseif ($request->query('0')) {
            $pipeline_id = $request->query('0');
        }
        $subTasks = PipelineSubTask::where('pipeline_lead_status_id', $pipeline_id)->get();

        return response()->json([
            'data' => $subTasks,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $pipelineSubTasks = $request->pipelineSubTasks;

        $request->validate([
            'pipelineSubTasks' => 'required|array',
            'pipelineSubTasks.*.pipeline_lead_status_id' => 'required|exists:pipeline_lead_status,id',
            'pipelineSubTasks.*.description' => 'required|string|max:255',
        ]);

        foreach ($pipelineSubTasks as $pipelineSubTask) {

            if (isset($pipelineSubTask['id'])) {

                // dd($pipelineSubTask['description']);
                $subTask = PipelineSubTask::withTrashed()->find($pipelineSubTask['id']);

                $subTask->update([
                    'pipeline_lead_status_id' => $pipelineSubTask['pipeline_lead_status_id'],
                    'description' => $pipelineSubTask['description'],
                ]);

                $subTask->restore();

            } else {

                $subTask = PipelineSubTask::create([
                    'pipeline_lead_status_id' => $pipelineSubTask['pipeline_lead_status_id'],
                    'description' => $pipelineSubTask['description'],
                ]);

            }

        }

        return response()->json([
            'status' => true,
            'message' => 'Data Added',
        ], 200);
    }

    // Display the specified resource
    public function show($id): JsonResponse
    {
        $subTask = PipelineSubTask::findOrFail($id);

        return response()->json($subTask);
    }

    // Update the specified resource in storage
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            // 'description' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:0,1',
        ]);

        $subTask = PipelineSubTask::findOrFail($id);

        $subTask->update($request->only(['description', 'status']));

        return response()->json($subTask);
    }

    // Remove the specified resource from storage
    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:pipeline_sub_tasks,id',
        ]);

        $subTask = PipelineSubTask::findOrFail($request->id);
        $subTask->delete();

        return response()->json([
            'status' => true,
            'message' => 'SubTask deleted successfully.',
        ], 200);
    }

    public function getSubTasksOfPipeline($pipeline_lead_status_id): JsonResponse
    {

        $pipelineSubTasks = PipelineSubTask::where('pipeline_lead_status_id', $pipeline_lead_status_id)->get();

        if ($pipelineSubTasks->isNotEmpty()) {

            return response()->json([
                'data' => $pipelineSubTasks,
                'status' => true,
            ], 200);

        }

        return response()->json([
            'data' => [],
            'status' => true,
        ], 200);

    }
}
