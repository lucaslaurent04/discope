<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;
use sale\booking\BookingActivity;
use sale\booking\BookingLineGroup;
use sale\booking\BookingMeal;

list($params, $providers) = eQual::announce([
    'description'   => "Checks if the composition is complete for a given booking.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of targeted booking.',
            'type'          => 'integer',
            'required'      => true
        ],
        'booking_line_group_id' => [
            'description'   => 'Identifier of the targeted group to remove.',
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
['context'=> $context, 'orm' => $orm, 'auth' => $auth, 'dispatch' => $dispatch] = $providers;


// ensure booking object exists and is readable
$booking = Booking::id($params['id'])->read(['id', 'name', 'status'])->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$sojourn = BookingLineGroup::id($params['booking_line_group_id'])->read(['id', 'name', 'booking_id'])->first(true);

if(!$sojourn) {
    throw new Exception("unknown_sojourn", QN_ERROR_UNKNOWN_OBJECT);
}

if($sojourn['booking_id'] != $booking['id']) {
    throw new Exception("mismatch_booking", QN_ERROR_UNKNOWN_OBJECT);
}

// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();

BookingLineGroup::id($params['booking_line_group_id'])->delete(true);

BookingActivity::search(['booking_line_group_id', '=', $params['booking_line_group_id']])->delete(true);

BookingMeal::search(['booking_line_group_id', '=', $params['booking_line_group_id']])->delete(true);

// recompute total price of the booking
Booking::refreshPrice($orm, $booking['id']);

// recompute date_from and date_to according to sojourns
Booking::refreshDate($orm, $booking['id']);

// recompute total nb_pers according to sojourns
Booking::refreshNbPers($orm, $booking['id']);

// #memo - if booking no longer includes a price from an unpublished pricelist, un-mark it as ToBeConfirmed
Booking::refreshIsTbc($orm, $booking['id']);

// #memo - keep order valid (without jumps or multiple with same order)
Booking::refreshOrder($orm, $booking['id']);

// restore events (in case this controller is chained with others)
$orm->enableEvents();

Booking::id($booking['id'])->do('refresh_groups_activity_number');

$context->httpResponse()
        ->status(204)
        ->send();
