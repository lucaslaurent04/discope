<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Payment;
use sale\booking\Funding;

[$params, $providers] = eQual::announce([
    'description'   => "Creates an arbitrary payment and associate it with a funding record.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted funding.',
            'type'              => 'integer',
            'min'               => 1,
            'required'          => true
        ],

        'date' => [
            'type'              => 'date',
            'description'       => "Date at which the payment was received.",
            'default'           => time()
        ],

        'payment_origin' => [
            'type'              => 'string',
            'selection'         => [
                'cashdesk',             // money was received at the cashdesk
                'bank',                 // money was received on a bank account
                'online'                // money was received online, through a PSP
            ],
            'description'       => "Origin of the received money.",
            'default'           => 'bank'
        ],

        'payment_method' => [
            'type'              => 'string',
            'selection'         => [
                'cash',                 // cash money
                'bank_card',            // electronic payment with bank (or credit) card
                'booking',              // payment through addition to the final (balance) invoice of a specific booking
                'voucher',              // gift, coupon, or tour-operator voucher
                'bank_check',           // physical bank check
                'wire_transfer',        // transfer between bank accounts
                'financial_help'        // a financial help will take care of the payment
            ],
            'description'       => "The method used for payment at the cashdesk.",
            'default'           => 'wire_transfer'
        ],

        'amount' => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => 'The monetary value of the bank check.',
            'default'           => function($id = null) {
                $funding = Funding::id($id)->read(['due_amount', 'paid_amount'])->first(true);
                if(!$funding) {
                    return null;
                }
                $remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);
                return  $remaining_amount ;
            }
        ],

    ],
    'access' => [
        'groups'            => ['booking.default.user', 'finance.default.administrator', 'finance.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;


if($params['amount'] < 0) {
    throw new Exception("invalid_amount", EQ_ERROR_INVALID_PARAM);
}

$funding = Funding::id($params['id'])
            ->read([
                'paid_amount',
                'due_amount',
                'booking_id' => ['customer_id'],
                'center_office_id'
            ])
            ->first(true);

if(!$funding) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

$sign = ($funding['due_amount'] >= 0) ? 1 : -1;
$remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);

if($remaining_amount <= 0) {
    throw new Exception("nothing_to_pay", EQ_ERROR_INVALID_PARAM);
}

$maps_origins_allowed_payment_methods = [
    'cashdesk'  => ['cash', 'bank_card', 'voucher', 'bank_check', 'financial_help', 'booking'],
    'bank'      => ['wire_transfer'],
    'online'    => ['bank_card']
];

if(!in_array($params['payment_method'], $maps_origins_allowed_payment_methods[$params['payment_origin']])) {
    throw new Exception("invalid_payment_method", EQ_ERROR_INVALID_PARAM);
}

$payment = Payment ::create([
        'booking_id'        => $funding['booking_id']['id'],
        'partner_id'        => $funding['booking_id']['customer_id'],
        'center_office_id'  => $funding['center_office_id'],
        'amount'            => $params['amount'],
        'payment_origin'    => $params['payment_origin'],
        'payment_method'    => $params['payment_method']
    ])
    // this updated funding paid status
    ->update([
        'funding_id'        => $funding['id']
    ])
    ->read(['id'])
    ->first();

$context->httpResponse()
        ->status(205)
        ->send();
