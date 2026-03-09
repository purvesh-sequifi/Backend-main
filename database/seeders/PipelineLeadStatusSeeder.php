<?php

namespace Database\Seeders;

use App\Models\PipelineLeadStatus;
use Illuminate\Database\Seeder;

class PipelineLeadStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PipelineLeadStatus::create([
            'status_name' => 'Leads',
            'display_order' => '1',
            'hide_status' => '1',
            'colour_code' => '#E4E9FF',
        ]);
        PipelineLeadStatus::create([
            'status_name' => 'Interviewing',
            'display_order' => '2',
            'hide_status' => '1',
            'colour_code' => '#E3F4FC',
        ]);
        PipelineLeadStatus::create([
            'status_name' => 'Rejected',
            'display_order' => '3',
            'hide_status' => '1',
            'colour_code' => '#FBE7E4',
        ]);
        PipelineLeadStatus::create([
            'status_name' => 'Follow Up',
            'display_order' => '4',
            'hide_status' => '1',
            'colour_code' => '#EEF8E8',
        ]);
        PipelineLeadStatus::create([
            'status_name' => 'Shortlisted',
            'display_order' => '5',
            'hide_status' => '1',
            'colour_code' => '#E3F4FC',
        ]);
    }
}
