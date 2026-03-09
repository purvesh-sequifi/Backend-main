<?php

namespace App\Http\Controllers\API\Hiring;

use App\Http\Controllers\Controller;
use App\Models\PipelineComment;
use App\Models\PipelineLeadStatus;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineCommentController extends Controller
{
    public function pipelineCommentSave(Request $request): JsonResponse
    {

        $this->validate($request, [
            'pipeline_lead_status_id' => 'required|exists:pipeline_lead_status,id',
            'user_id' => 'required|integer|exists:users,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file',
        ]);

        try {

            $attachments = $request->file('attachments');

            if (! empty($attachments)) {

                foreach ($attachments as $attachment) {

                    $file = $attachment;
                    $img_path = time().$file->getClientOriginalName();

                    $img_path = str_replace(' ', '_', $img_path);
                    $ds_path = 'lead_pipeline_or_bucket_documents/'.$img_path;
                    $awsPath = config('app.domain_name').'/'.$ds_path;
                    // echo $awsPath;die();
                    s3_upload($awsPath, file_get_contents($file), false);
                    // $s3_document_url = s3_getTempUrl(config('app.domain_name').'/'.$ds_path);

                    $commentData = [
                        'pipeline_lead_status_id' => $request->pipeline_lead_status_id,
                        'user_id' => $request->user_id,
                        'path' => $ds_path,
                        'comment_parent_id' => $request->comment_parent_id ?? null,
                        'comment' => $request->comment ?? null,
                    ];

                    PipelineComment::create($commentData);

                }

            } else {

                PipelineComment::create([
                    'pipeline_lead_status_id' => $request->pipeline_lead_status_id,
                    'user_id' => $request->user_id,
                    'comment_parent_id' => $request->comment_parent_id ?? null,
                    'comment' => $request->comment ?? null,
                ]);

            }

            return response()->json([
                'ApiName' => 'pipelineCommentSave',
                'status' => true,
                'message' => 'Comment added successfully',
            ], 200);

        } catch (Exception $e) {

            return response()->json([
                'ApiName' => 'pipelineCommentSave',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);

        }

    }

    public function deletePipeline(Request $request): JsonResponse
    {

        $this->validate($request, [
            'pipeline_id' => 'required|exists:pipeline_lead_status,id',
        ]);

        PipelineLeadStatus::where('id', $request->pipeline_id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted successfully',
        ], 200);

    }

    public function pipelineCommentDelete(Request $request): JsonResponse
    {

        $this->validate($request, [
            'id' => 'required|exists:pipeline_comments,id',
        ]);

        PipelineComment::where('id', $request->id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Comment deleted successfully',
        ], 200);

    }
}
