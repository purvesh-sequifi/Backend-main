<?php

namespace Database\Seeders;

use App\Models\TicketFaq;
use App\Models\TicketFaqCategory;
use Illuminate\Database\Seeder;

class TicketFaqDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $faqCategories = [
        //     [
        //         'name' => 'Company Settings - Setup',
        //         'order' => '1'
        //     ],
        //     [
        //         'name' => 'Hiring Settings',
        //         'order' => '2'
        //     ],
        //     [
        //         'name' => 'Other Settings',
        //         'order' => '3'
        //     ],
        //     [
        //         'name' => 'Compensation Settings',
        //         'order' => '4'
        //     ],
        //     [
        //         'name' => 'Payroll',
        //         'order' => '5'
        //     ],
        //     [
        //         'name' => 'Reports',
        //         'order' => '6'
        //     ]
        // ];

        // foreach ($faqCategories as $faqCategory) {
        //     TicketFaqCategory::query()->updateOrCreate(['name' => $faqCategory['name']], $faqCategory);
        // }

        // $cat1 = TicketFaqCategory::where('name', $faqCategories[0]['name'])->first()->id;
        // $cat2 = TicketFaqCategory::where('name', $faqCategories[1]['name'])->first()->id;
        // $cat3 = TicketFaqCategory::where('name', $faqCategories[2]['name'])->first()->id;
        // $cat4 = TicketFaqCategory::where('name', $faqCategories[3]['name'])->first()->id;
        // $cat5 = TicketFaqCategory::where('name', $faqCategories[4]['name'])->first()->id;
        // $cat6 = TicketFaqCategory::where('name', $faqCategories[5]['name'])->first()->id;

        // $faqs = [
        //     [
        //         'faq_category_id' => $cat1,
        //         'question' => 'Edit Company Margin',
        //         'answer' => 'Edit Company Margin'
        //     ],
        //     [
        //         'faq_category_id' => $cat1,
        //         'question' => 'Setup Reconciliations',
        //         'answer' => 'Setup Reconciliations'
        //     ],
        //     [
        //         'faq_category_id' => $cat1,
        //         'question' => 'Setup Pay Frequency',
        //         'answer' => 'Setup Pay Frequency'
        //     ],
        //     [
        //         'faq_category_id' => $cat1,
        //         'question' => 'How to allow all email Domains.',
        //         'answer' => 'How to allow all email Domains.'
        //     ],
        //     [
        //         'faq_category_id' => $cat1,
        //         'question' => 'Pay Highest Override only setting',
        //         'answer' => 'Pay Highest Override only setting'
        //     ],
        //     [
        //         'faq_category_id' => $cat2,
        //         'question' => 'Configuring Employee IDs',
        //         'answer' => 'Configuring Employee IDs'
        //     ],
        //     [
        //         'faq_category_id' => $cat2,
        //         'question' => 'Configure employee onboarding questions',
        //         'answer' => 'Configure employee onboarding questions'
        //     ],
        //     [
        //         'faq_category_id' => $cat2,
        //         'question' => 'Add a document upload for Employee onboarding process',
        //         'answer' => 'Add a document upload for Employee onboarding process'
        //     ],
        //     [
        //         'faq_category_id' => $cat3,
        //         'question' => 'Update Company Profile/Logo',
        //         'answer' => 'Update Company Profile/Logo'
        //     ],
        //     [
        //         'faq_category_id' => $cat3,
        //         'question' => 'How to make someone an Admin',
        //         'answer' => 'How to make someone an Admin'
        //     ],
        //     [
        //         'faq_category_id' => $cat4,
        //         'question' => 'Create departments',
        //         'answer' => 'Create departments'
        //     ],
        //     [
        //         'faq_category_id' => $cat4,
        //         'question' => 'How to setup new position and compensation structure.',
        //         'answer' => 'How to setup new position and compensation structure.'
        //     ],
        //     [
        //         'faq_category_id' => $cat4,
        //         'question' => 'Company Ord Chart',
        //         'answer' => 'Company Ord Chart'
        //     ],
        //     [
        //         'faq_category_id' => $cat4,
        //         'question' => 'Add a company location with redline',
        //         'answer' => 'Add a company location with redline'
        //     ],
        //     [
        //         'faq_category_id' => $cat5,
        //         'question' => 'How to view and finalize payroll',
        //         'answer' => 'How to view and finalize payroll'
        //     ],
        //     [
        //         'faq_category_id' => $cat5,
        //         'question' => 'Add advance requests to Payroll',
        //         'answer' => 'Add advance requests to Payroll'
        //     ],
        //     [
        //         'faq_category_id' => $cat5,
        //         'question' => 'View and add payment requests to Payroll.',
        //         'answer' => 'View and add payment requests to Payroll.'
        //     ],
        //     [
        //         'faq_category_id' => $cat5,
        //         'question' => 'One Time Payments',
        //         'answer' => 'One Time Payments'
        //     ],
        //     [
        //         'faq_category_id' => $cat5,
        //         'question' => 'Submit a Payroll Dispute/Advance etc.',
        //         'answer' => 'Submit a Payroll Dispute/Advance etc.'
        //     ],
        //     [
        //         'faq_category_id' => $cat5,
        //         'question' => 'Move payroll to next pay period.',
        //         'answer' => 'Move payroll to next pay period.'
        //     ],
        //     [
        //         'faq_category_id' => $cat5,
        //         'question' => 'Reconcile outside Sequifi',
        //         'answer' => 'Reconcile outside Sequifi'
        //     ],
        //     [
        //         'faq_category_id' => $cat6,
        //         'question' => 'Extract all sales report',
        //         'answer' => 'Extract all sales report'
        //     ],
        //     [
        //         'faq_category_id' => $cat6,
        //         'question' => 'Extract User Report',
        //         'answer' => 'Extract User Report'
        //     ],
        //     [
        //         'faq_category_id' => $cat6,
        //         'question' => 'Export Payroll Report',
        //         'answer' => 'Export Payroll Report'
        //     ],
        //     [
        //         'faq_category_id' => $cat6,
        //         'question' => 'Export Clawback Report',
        //         'answer' => 'Export Clawback Report'
        //     ],
        //     [
        //         'faq_category_id' => $cat6,
        //         'question' => 'Search for sales report by User',
        //         'answer' => 'Search for sales report by User'
        //     ],
        //     [
        //         'faq_category_id' => $cat6,
        //         'question' => 'View Sales Report and Account Summary',
        //         'answer' => 'View Sales Report and Account Summary'
        //     ],
        //     [
        //         'faq_category_id' => $cat6,
        //         'question' => 'View current payroll summary',
        //         'answer' => 'View current payroll summary'
        //     ]
        // ];

        // foreach ($faqs as $faq) {
        //     TicketFaq::query()->updateOrCreate(['faq_category_id' => $faq['faq_category_id'], 'question' => $faq['question']], $faq);
        // }
    }
}
