<?php

return [
    'priorities' => ['normal' => 'Normal', 'emergency' => 'Emergency'],
    'payment_accounts' => ['cash' => 'Cash', 'easypaisa' => 'Easypaisa', 'jazzcash' => 'JazzCash', 'bank' => 'Bank account', 'other' => 'Other account'],
    'pay_methods' => [
        'salary' => 'Monthly salary (PKR)',
        'percentage' => 'Percentage of completed order price',
        'per_order' => 'Rupees per completed order',
        'per_suit' => 'Rupees per completed shalwar kameez suit',
    ],
    'styles' => [
        'daman' => ['label' => 'Daman / دامن', 'options' => ['straight' => 'Straight / Seedha', 'round' => 'Round / Gol', 'side_cut' => 'Side-cut', 'custom' => 'Custom — write instructions']],
        'collar' => ['label' => 'Collar / گلا', 'options' => ['ban' => 'Ban / band collar', 'shirt' => 'Shirt collar', 'plain' => 'Plain round neck', 'custom' => 'Custom — write instructions']],
        'cuff' => ['label' => 'Sleeve finish / کف', 'options' => ['plain' => 'Plain sleeve', 'single' => 'Single cuff', 'double' => 'Double cuff', 'custom' => 'Custom — write instructions']],
        'shalwar' => ['label' => 'Shalwar style / شلوار', 'options' => ['traditional' => 'Traditional roomy shalwar', 'narrow' => 'Narrow shalwar', 'custom' => 'Custom — write instructions']],
    ],
];
