<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;

[$params, $providers] = eQual::announce([
    'description'   => "Update hook for Booking creation: init values and makes additional checks.",
    'extends'       => 'core_model_update',
    'params'        => [
       'entity' =>  [
            'description'   => 'Full name (including namespace) of the class to return (e.g. \'core\\User\').',
            'type'          => 'string',
            'required'      => true
        ],
        'id' =>  [
            'description'   => 'Unique identifier of the object to update.',
            'type'          => 'integer',
            'default'       => 0
        ],
        'ids' =>  [
            'description'   => 'List of Unique identifiers of the objects to update.',
            'type'          => 'array',
            'default'       => []
        ],
        'fields' =>  [
            'description'   => 'Associative array mapping fields to be updated with their related values.',
            'type'          => 'array',
            'default'       => []
        ],
        'force' =>  [
            'description'   => 'Flag for forcing update in case a concurrent change is detected.',
            'type'          => 'boolean',
            'default'       => false
        ],
        'lang' => [
            'description '  => 'Specific language for multilang field.',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ]
    ],
    'constants'     => ['DEFAULT_LANG'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context, 'orm' => $orm] = $providers;

if(isset($params['id']) && $params['id'] > 0) {
    $booking_id = $params['id'];
}
elseif(isset($params['ids']) && count($params['ids'])) {
    $booking_id = $params['ids'][0];
}
elseif(isset($params['fields']['id'])) {
    $booking_id = $params['fields']['id'];
}
else {
    throw new Exception("missing_object_identifier", QN_ERROR_INVALID_PARAM);
}


// This controller is meant to intercept booking creation.
// we run a series of checks: each of those raises an Exception not passing.


// 1) update the booking according to the received data

// At this stage only basic information has been given: prevent onupdate events

// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();

$orm->update(Booking::getType(), $booking_id, [
        'customer_nature_id'    => $params['fields']['customer_nature_id'],
        'center_id'             => $params['fields']['center_id'],
        'organisation_id'       => $params['fields']['organisation_id'],
        'date_from'             => strtotime($params['fields']['date_from']),
        'date_to'               => strtotime($params['fields']['date_to']),
        'has_tour_operator'     => ($params['fields']['has_tour_operator'] ?? '') === 'true',
        'tour_operator_id'      => $params['fields']['tour_operator_id'] ?? null,
        'tour_operator_ref'     => $params['fields']['tour_operator_ref'] ?? ''
    ]);


// restore events in case this controller is chained with others
$orm->enableEvents();

Booking::id($booking_id)
    // assign identity & sync with customer
    ->update(['customer_identity_id'  => $params['fields']['customer_identity_id']])
    // re-create contacts
    ->do('import_contacts');

// 2) check customer history

// check customer's previous bookings for remaining unpaid amount
eQual::run('do', 'sale_booking_check-customer-debtor', ['id' => $booking_id]);

// check customer's history for damages, slow payment or harm caused during previous bookings
eQual::run('do', 'sale_booking_check-customer-history', ['id' => $booking_id]);


$context->httpResponse()
        ->body($result)
        ->send();
