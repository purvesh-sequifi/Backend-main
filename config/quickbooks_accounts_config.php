<?php

return [
    [
        'Name' => 'Payroll Expense Account',
        'AccountType' => 'Expense',
        'Description' => 'Account for recording payroll expenses',
        'AccountSubType' => 'PayrollExpenses',
    ],
    [
        'Name' => 'Taxes Account',
        'AccountType' => 'Expense',
        'Description' => 'Total Taxes',
        'AccountSubType' => 'TaxesPaid',
    ],
    [
        'Name' => 'External Payment Account',
        'AccountType' => 'Other Current Liability',
        'Description' => 'Account for External Payment',
        'AccountSubType' => 'DirectDepositPayable',
    ],
    [
        'Name' => 'Bank Account',
        'AccountType' => 'Bank',
        'Description' => 'Cash in hand Account',
        'AccountSubType' => 'CashOnHand',
    ],
    [
        'Name' => 'Reimbursement Account',
        'AccountType' => 'Expense',
        'Description' => 'Reimbursement Paid',
        'AccountSubType' => 'TaxesPaid',
    ],
];
