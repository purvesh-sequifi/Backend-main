<?php

namespace Database\Seeders;

use App\Models\Lead;
use Illuminate\Database\Seeder;

class LeadSetPipelineStatusIdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leadIds = Lead::whereNull('pipeline_status_id')->orWhere('pipeline_status_id', 0)->pluck('id')->toArray();

        Lead::whereIn('id', $leadIds)
            ->update([
                'pipeline_status_id' => 1,
            ]);
    }
}
