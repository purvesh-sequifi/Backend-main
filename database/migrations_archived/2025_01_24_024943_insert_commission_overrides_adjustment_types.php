<?php

use App\Models\AdjustementType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        // AdjustementType::insert([['name' => 'Commission'], ['name' => 'Overrides']]);  // Single line

        // Array of records to insert or update
        $records = [
            ['name' => 'Payroll Dispute'],
            ['name' => 'Reimbursement'],
            ['name' => 'Bonus'],
            ['name' => 'Advance'],
            ['name' => 'Fine/fee'],
            ['name' => 'Incentive'],
            ['name' => 'Leave(Unpaid)'],
            ['name' => 'PTO'],
            ['name' => 'Time Adjustment'],
            ['name' => 'Commission'],
            ['name' => 'Overrides'],
            ['name' => 'Payroll'],
        ];

        // Loop through each record and use updateOrInsert in a single call
        foreach ($records as $record) {
            AdjustementType::updateOrCreate(
                ['name' => $record['name']], // Check for this condition
                $record                       // Data to insert or update
            );
        }
    }

    public function down()
    {
        AdjustementType::whereIn('name', ['Payroll Dispute', 'Reimbursement', 'Bonus', 'Advance', 'Fine/fee', 'Incentive', 'Leave(Unpaid)', 'PTO', 'Time Adjustment', 'Commission', 'Overrides', 'Payroll'])->delete();  // Single line rollback
    }
};
