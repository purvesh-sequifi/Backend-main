<?php

namespace Database\Seeders;

use App\Models\SequiDocsTemplate;
use Illuminate\Database\Seeder;

class CreateTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use updateOrCreate to make this seeder idempotent
        // Note: created_at and updated_at are automatically handled by Eloquent
        SequiDocsTemplate::updateOrCreate(
            ['id' => 1],
            [
                'created_by' => 1,
                'categery_id' => 1,
                'template_name' => 'Offer letter',
                'template_description' => 'This will be used to send an offer letter',
                'is_sign_required_for_hire' => 1,
                'template_content' => '<p>Dear [employee_name],<br />&nbsp;</p> <p>We are pleased to offer you the [employee_name] position of [employee_position] at [company_name] with a start date of [company_date], contingent upon You will be reporting directly to [manager_name] at [company_address]</p>',
                'template_agreements' => '',
                'dynamic_value' => '{"Sequifi","Admin"}',
                'recipient_sign_req' => 1,
                'self_sign_req' => 1,
                'add_sign' => 0,
                'template_comment' => '',
                'manager_sign_req' => 0,
                'completed_step' => 4,
                'recruiter_sign_req' => 0,
                'add_recruiter_sign_req' => 0,
                // Timestamps removed - Eloquent handles them automatically
            ]
        );
    }
}
