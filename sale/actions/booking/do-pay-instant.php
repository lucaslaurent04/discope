<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Create a manual payment to complete the payments of all fundings not related to a deposit invoice.",
    'help'          => "This action is intended for payment with bank card only. Manual payments can be undone while the booking is not fully balanced (and invoiced).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'groups'            => ['booking.default.user', 'sale.default.administrator']
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

// read booking object
$booking = Booking::id($params['id'])
    ->read(['id', 'name', 'status', 'fundings_ids' => ['id', 'type', 'is_paid', 'invoice_id' => ['is_deposit']]])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if(in_array($booking['status'], ['balanced', 'cancelled'])) {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

foreach($booking['fundings_ids'] as $funding) {
    if( $funding['type'] == 'installment' || (isset($funding['invoice_id']['is_deposit']) && $funding['invoice_id']['is_deposit'] === false) ) {
        try {
            eQual::run('do', 'sale_booking_funding_do-pay-append', ['id' => $funding['id']]);
        }
        catch(Exception $e) {
            // ignore errors raised while appending payments
        }
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
