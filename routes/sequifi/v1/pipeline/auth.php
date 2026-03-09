<?php

use App\Http\Controllers\API\Pipeline\PipelineController;
use Illuminate\Support\Facades\Route;

//  CRM Setting
Route::post('/list_lead_status', [PipelineController::class, 'list_lead_status']);
Route::get('/history_lead_status', [PipelineController::class, 'history_lead_status']);
Route::post('/add_update_lead_status', [PipelineController::class, 'addUpdateLeadStatus']);
Route::post('/update_lead_status', [PipelineController::class, 'update_lead_status']);
Route::post('/add_lead_comment', [PipelineController::class, 'add_lead_comment']);
Route::post('/delete_lead_comment', [PipelineController::class, 'delete_lead_comment']);
Route::get('/list_lead_comment/{lead_id}', [PipelineController::class, 'list_lead_comment']);
Route::post('/update_lead_card', [PipelineController::class, 'update_lead_card']);
Route::post('/list_onboarding_status', [PipelineController::class, 'list_onboarding_status']);
Route::post('/show_hide_onboarding_status', [PipelineController::class, 'show_hide_onboarding_status']);
Route::get('/get_take_interviews_list', [PipelineController::class, 'get_take_interviews_list']);
Route::post('/pipeline-bucket-list', [PipelineController::class, 'pipelineBucketList']);
