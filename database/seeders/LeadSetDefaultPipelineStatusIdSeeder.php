<?php

namespace Database\Seeders;

use App\Models\Lead;
use Illuminate\Database\Seeder;

class LeadSetDefaultPipelineStatusIdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leadIds = Lead::whereNull('pipeline_status_id')->pluck('id')->toArray();

        Lead::whereIn('id', $leadIds)
            ->update([
                'pipeline_status_id' => 1,
            ]);
    }
}
