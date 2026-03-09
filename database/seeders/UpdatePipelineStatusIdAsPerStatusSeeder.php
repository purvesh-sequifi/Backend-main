<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\PipelineLeadStatus;
use Illuminate\Database\Seeder;

class UpdatePipelineStatusIdAsPerStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PipelineLeadStatus::truncate();
        $leadStatus = [
            ['status_name' => 'New Lead', 'display_order' => '1', 'hide_status' => 0, 'colour_code' => '#E4E9FF'],
            ['status_name' => 'Interview Scheduled', 'display_order' => '2', 'hide_status' => 0, 'colour_code' => '#E3F4FC'],
            ['status_name' => 'Rejected', 'display_order' => '3', 'hide_status' => 0, 'colour_code' => '#FBE7E4'],
        ];

        foreach ($leadStatus as $key => $status) {
            $pipelineStatus = PipelineLeadStatus::where('status_name', $status['status_name'])->first();
            if ($pipelineStatus == null) {
                $lastOrderBy = PipelineLeadStatus::orderBy('display_order', 'DESC')->first();
                $orderBy = 1;
                if ($lastOrderBy != null) {
                    $orderBy = $lastOrderBy->display_order + 1;
                }

                $pipelineStatus = new PipelineLeadStatus;
                $pipelineStatus->status_name = $status['status_name'];
                $pipelineStatus->display_order = $orderBy;
                $pipelineStatus->hide_status = $status['hide_status'];
                $pipelineStatus->colour_code = $status['colour_code'];
                $pipelineStatus->save();
            }
        }

        $leadStatus = Lead::where('type', 'Lead')->groupBy('status')->pluck('status')->toArray();
        foreach ($leadStatus as $key => $status) {
            $pipelineStatus = PipelineLeadStatus::where('status_name', $status)->first();
            if ($pipelineStatus == null) {
                $lastOrderBy = PipelineLeadStatus::orderBy('display_order', 'DESC')->first();
                $orderBy = 1;
                if ($lastOrderBy != null) {
                    $orderBy = $lastOrderBy->display_order + 1;
                }

                if (! in_array($status, ['Follow Up', 'FollowUp', 'Hired'])) {
                    $pipelineStatus = new PipelineLeadStatus;
                    $pipelineStatus->status_name = $status;
                    $pipelineStatus->display_order = $orderBy;
                    $pipelineStatus->save();
                }
            }
        }

        $leads = Lead::where('type', 'Lead')->whereNotIn('status', ['Hired'])->get();
        foreach ($leads as $key => $lead) {
            $status = $lead->status;
            if (in_array($status, ['Follow Up', 'FollowUp'])) {
                $status = 'New Lead';
            }

            $pipelineStatus = PipelineLeadStatus::where('status_name', $status)->first();

            // $lead->status = $status;
            $lead->pipeline_status_id = $pipelineStatus->id ?? 1;
            $lead->save();
        }

        // Set Rejected display_order
        $lastOrderBy = PipelineLeadStatus::orderBy('display_order', 'DESC')->first();
        $orderBy = 1;
        if ($lastOrderBy != null) {
            $orderBy = $lastOrderBy->display_order + 1;
        }

        $rejectedRecord = PipelineLeadStatus::where('status_name', 'Rejected')->first();
        if ($rejectedRecord != null) {
            $rejectedRecord->display_order = $orderBy;
            $rejectedRecord->save();
        }
    }
}
