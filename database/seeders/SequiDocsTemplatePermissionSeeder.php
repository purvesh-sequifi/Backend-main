<?php

namespace Database\Seeders;

use App\Models\SequiDocsTemplatePermissions;
use Illuminate\Database\Seeder;

class SequiDocsTemplatePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sequi_docs_email_templates = [
            [
                'template_id' => '1',
                'category_id' => '1',
                'position_id' => '2',
                'position_type' => 'receipient',
            ],
            [
                'template_id' => '1',
                'category_id' => '1',
                'position_id' => '3',
                'position_type' => 'receipient',
            ],
            [
                'template_id' => '1',
                'category_id' => '1',
                'position_id' => '2',
                'position_type' => 'permission',
            ],
            [
                'template_id' => '1',
                'category_id' => '1',
                'position_id' => '3',
                'position_type' => 'permission',
            ],
        ];

        foreach ($sequi_docs_email_templates as $template) {
            SequiDocsTemplatePermissions::create($template);
        }
    }
}
