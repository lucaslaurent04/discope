<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Checks if given booking is balanced and, if so, removes all checks relating to it.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking the check against emptyness.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

$booking = Booking::search([
        ['id', '=', $params['id']],
        ['status' , '=', 'balanced']
    ])
    ->read( ['id'])
    ->first(true);

if($booking) {
    $dispatch->cancel('lodging.booking.composition', 'sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.consistency', 'sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.rental_units_assignment', 'sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.overbooking', 'sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.sojourns_accomodations', 'sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.date.checkin', 'sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.payments', 'sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.prices_assignment', 'sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.payments', 'sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.debtor_customer', 'sale\booking\Booking', $booking['id']);
}

$context->httpResponse()
        ->status(204)
        ->send();
