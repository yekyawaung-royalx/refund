<?php
return [

    'sender-prepaid' => [
        'columns' => 34,
        'payment_by' => 'sender pay',
        'payment_type' => 'prepaid',
        'checks' => [
            'origin_branch_required' => true,
            'express_income_required' => true,
        ],
    ],

    'sender-postpaid' => [
        'columns' => 34,
        'payment_by' => 'sender pay',
        'payment_type' => 'postpaid',
        'checks' => [
            'delivered_date_required' => true,
            'destination_branch_required' => true,
            'cod_required' => true,
        ],
    ],

    'receiver-postpaid' => [
        'columns' => 33,
        'payment_by' => 'receiver pay',
        'payment_type' => 'postpaid',
        'checks' => [
            'delivered_date_required' => true,
            'destination_branch_required' => true,
            'express_income_required' => true,
        ],
    ],

];