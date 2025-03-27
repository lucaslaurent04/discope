<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;
use sale\booking\BookingLineGroup;


list($params, $providers) = eQual::announce([
    'description'   => "Checks if the composition is complete for a given booking.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'integer',
            'required'      => true
        ],
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $auth, $dispatch) = [ $providers['context'], $providers['orm'], $providers['auth'], $providers['dispatch']];



// ensure booking object exists and is readable
$booking = Booking::id($params['id'])->read([
        'id', 'name', 'status', 'date_from', 'date_to', 'booking_lines_groups_ids', 'center_office_id',
        'customer_id' => ['rate_class_id'],
        'center_id' => ['name', 'sojourn_type_id']]
    )
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();

$values = [
    'booking_id' => $booking['id'],
    'name'       => "Services " . $booking['center_id']['name'],
    'date_from'  => ((int) $booking['date_from'] > 0) ? $booking['date_from'] : time(),
    'date_to'    => ((int) $booking['date_to'] > 0) ? $booking['date_to'] : time() + 86400
];

if($booking['status'] != 'quote') {
    $values['is_extra'] = true;
}

// default rate class is the rate_class of the customer of the booking
if($booking['customer_id']['rate_class_id']) {
    $values['rate_class_id'] = $booking['customer_id']['rate_class_id'];
}

if($booking['center_id']['sojourn_type_id']) {
    $values['sojourn_type_id'] = $booking['center_id']['sojourn_type_id'];
}

if($booking['booking_lines_groups_ids']) {
    $values['order'] = count((array) $booking['booking_lines_groups_ids']) + 1;
}

BookingLineGroup::create($values);

Booking::refreshNbPers($orm, $booking['id']);

// restore events in case this controller is chained with others
$orm->enableEvents();

$context->httpResponse()
        ->status(201)
        ->send();