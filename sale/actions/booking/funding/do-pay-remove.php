<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Funding;
use sale\booking\Booking;
use sale\booking\Payment;

list($params, $providers) = eQual::announce([
    'description'   => "Remove the manual payment attached to the funding, if any, and unmark funding as paid.",
    'help'          => "Manual payments can be undone while the booking is not fully balanced (and invoiced).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted funding.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'groups'            => ['finance.default.administrator', 'finance.default.user']
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
 * @var \equal\orm\ObjectManager    $om
 */
list($context, $om) = [ $providers['context'], $providers['orm'] ];

$funding = Funding::id($params['id'])
            ->read(['booking_id' => ['id', 'status', 'customer_id'], 'invoice_id', 'center_office_id', 'is_paid', 'paid_amount', 'due_amount'])
            ->first(true);

if(!$funding) {
    throw new Exception("unknown_funding", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$funding['is_paid']) {
    throw new Exception("funding_not_paid", QN_ERROR_INVALID_PARAM);
}

if($funding['booking_id']['status'] == 'balanced') {
    throw new Exception("booking_balanced", QN_ERROR_INVALID_PARAM);
}

// remove manuel payments, if any
$payments = Payment::search([
        ['funding_id', '=', $funding['id']],
        ['is_manual', '=', true]
    ])
    ->delete(true);

Funding::id($params['id'])
    ->update(['status' => 'pending'])
    ->update(['paid_amount' => null])
    ->update(['is_paid' => null]);

Booking::updateStatusFromFundings($om, (array) $funding['booking_id']['id'], [], 'en');

$context->httpResponse()
        ->status(205)
        ->send();
