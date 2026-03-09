<?php

namespace Database\Seeders;

use App\Models\SequiDocsTemplateCategories;
use Illuminate\Database\Seeder;

class TemplateCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Timestamps removed - Eloquent handles them automatically for true idempotency
        $category_array = [
            [
                'id' => 1,
                'categories' => 'Offer Letter',
                'category_type' => 'system_fixed',
            ],
            [
                'id' => 2,
                'categories' => 'Agreements',
                'category_type' => 'system_fixed',
            ],
            [
                'id' => 3,
                'categories' => 'Email Templates',
                'category_type' => 'system_fixed',
            ],
            [
                'id' => 101,
                'categories' => 'Smart Text Template',
                'category_type' => 'system_fixed',
            ],
        ];
        foreach ($category_array as $template) {
            SequiDocsTemplateCategories::updateOrCreate(
                ['id' => $template['id']],
                $template
            );
        }

    }
}
