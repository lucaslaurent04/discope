<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Enrollment;
use sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => "Cancels the enrollment.",
    'params'        => [

        'id' => [
            'type'          => 'integer',
            'description'   => "Identifier of the enrollment we want to validate.",
            'required'      => true
        ],

        'reason' => [
            'type'          => 'string',
            'selection'     => [
                'other',
                'overbooking',
                'duplicate',
                'internal_impediment',
                'external_impediment',
                'health_impediment'
            ],
            'description'   => "The reason of the cancellation of the enrollment.",
            'default'       => 'other'
        ],

        'fee' => [
            'type'          => 'float',
            'usage'         => 'amount/money:2',
            'description'   => "Amount of the cancellation fee.",
            'default'       => function($id): float {
                $enrollment = Enrollment::id($id)->read(['price', 'camp_id' => ['date_from']])->first();
                if(is_null($enrollment)) {
                    return 0;
                }

                $date_from = $enrollment['camp_id']['date_from'];

                $now = time();
                $thirty_days_before = (new \DateTime())->setTimestamp($date_from)->modify('-30 days');
                $fifteen_days_before = (new \DateTime())->setTimestamp($date_from)->modify('-15 days');
                $seven_days_before = (new \DateTime())->setTimestamp($date_from)->modify('-7 days');

                $default_fee = 0;
                if($now < $thirty_days_before) {
                    $default_fee = $enrollment['price'] * 0.75;
                }
                elseif($now < $fifteen_days_before) {
                    $default_fee = $enrollment['price'] * 0.50;
                }
                elseif($now < $seven_days_before) {
                    $default_fee = $enrollment['price'] * 0.25;
                }

                return $default_fee;
            }
        ]

    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.administrator', 'camp.default.user'],
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$enrollment = Enrollment::id($params['id'])
    ->read(['status', 'price', 'price_adapters_ids' => ['origin_type', 'value']])
    ->first();

if(is_null($enrollment)) {
    throw new Exception("unknown_enrollment", EQ_ERROR_UNKNOWN_OBJECT);
}

if($enrollment['status'] === 'cancelled') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

$customer_price = $enrollment['price'];
foreach($enrollment['price_adapters_ids'] as $price_adapter) {
    if(in_array($price_adapter['origin_type'], ['commune', 'community-of-communes', 'department-caf', 'department-msa'])) {
        $customer_price -= $price_adapter['value'];
    }
}

if($params['fee'] > $customer_price) {
    throw new Exception("invalid_fee", EQ_ERROR_INVALID_PARAM);
}

/*
    cancel enrollment
*/

Enrollment::id($enrollment['id'])
    ->update(['cancellation_reason' => $params['reason']])
    ->transition('cancel');

/*
    handle cancellation fee
*/

$enrollment = Enrollment::id($enrollment['id'])
    ->read(['paid_amount', 'center_office_id', 'fundings_ids'])
    ->first();

$funding_data = [];
if($enrollment['paid_amount'] > $params['fee']) {
    $amount_to_reimburse = $enrollment['paid_amount'] - $params['fee'];

    $funding_data = [
        'description'   => "Remboursement annulation",
        'due_amount'    => round(-1 * $amount_to_reimburse, 2)
    ];
}
elseif($enrollment['paid_amount'] < $params['fee']) {
    $missing_amount = $params['fee'] - $enrollment['paid_amount'];

    $funding_data = [
        'description'   => "Frais d'annulation",
        'due_amount'    => round($missing_amount, 2)
    ];
}

if(!empty($funding_data)) {
    Funding::create(array_merge(
        [
            'enrollment_id'     => $enrollment['id'],
            'center_office_id'  => $enrollment['center_office_id'],
            'is_paid'           => false,
            'type'              => 'installment',
            'order'             => count($enrollment['fundings_ids']) + 1,
            'issue_date'        => time(),
            'due_date'          => time()
        ],
        $funding_data
    ));
}

$context->httpResponse()
        ->status(200)
        ->send();
