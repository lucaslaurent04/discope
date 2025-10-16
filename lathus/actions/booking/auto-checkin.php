<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;

[$params, $provider] = eQual::announce([
    'description'   => "Auto checkin all bookings that starts today.",
    'params'        => [],
    'access'        => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $provider;

$bookings_ids = Booking::search([
    ['date_from', '=', time()],
    ['status', '=', 'validated']
])
    ->ids();

$errors = [];

foreach($bookings_ids as $booking_id) {
    try {
        eQual::run('do', 'sale_booking_do-checkin', ['id' => $booking_id]);
    }
    catch(Exception $e) {
        $errors[] = "unable to checkin Booking {$booking_id} : ".$e->getMessage();
    }
}

$context->httpResponse()
        ->status(200)
        ->send();
