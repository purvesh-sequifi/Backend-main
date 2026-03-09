<?php

namespace Database\Seeders;

use App\Models\SequiDocsDocumentComment;
use Illuminate\Database\Seeder;

class SequiDocsDocumentCommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SequiDocsDocumentComment::create([
            'document_id' => 329,
            'category_id' => 1,
            'template_id' => 57,
            'document_name' => 'Offer Letter',
            'user_id_from' => 'onboarding_employees',
            'comment_user_id_from' => 'onboarding_employees',
            'document_send_to_user_id' => 166,
            'comment_by_id' => 166,
            'comment_type' => null,
            'comment' => 'Hi,\r\nMy last name is suppose to be spelled like Raley not Ramey.',
        ]);
    }
}
