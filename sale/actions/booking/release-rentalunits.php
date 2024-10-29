<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use sale\booking\Booking;
use sale\booking\Consumption;

[$params, $providers] = eQual::announce([
    'description'   => "Release any previously assigned rental unit, by removing all consumptions.",
    'params'        => [
        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the targeted booking.",
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'cron']
]);

/**
 * @var \equal\php\Context      $context
 * @var \equal\cron\Scheduler   $cron
 */
['context' => $context, 'cron' => $cron] = $providers;

$booking = Booking::id($params['id'])
    ->read(['id', 'status'])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'quote') {
    throw new Exception("invalid_status", QN_ERROR_INVALID_PARAM);
}

$channelmanager_enabled = Setting::get_value('sale', 'channelmanager', 'enabled', false);
if($channelmanager_enabled) {
    // rental units were released: check if consistency must be maintained with channel manager (if booking impacts a rental unit that is linked to a channelmanager room type)
    $map_rental_units_ids = [];

    $booking = Booking::id($params['id'])
        ->read(['date_from', 'date_to', 'consumptions_ids' => ['is_accomodation', 'rental_unit_id']])
        ->first(true);

    foreach($booking['consumptions_ids'] as $consumption) {
        if($consumption['is_accomodation']) {
            $map_rental_units_ids[$consumption['rental_unit_id']] = true;
        }
    }

    if(count($map_rental_units_ids)) {
        $cron->schedule(
            "channelmanager.check-contingencies.{$params['id']}",
            time(),
            'sale_booking_check-contingencies',
            [
                'date_from'         => date('c', $booking['date_from']),
                'date_to'           => date('c', $booking['date_to']),
                'rental_units_ids'  => array_keys($map_rental_units_ids)
            ]
        );
    }
}

Consumption::search(['booking_id', '=', $params['id']])
    ->delete(true);

$context->httpResponse()
        ->status(204)
        ->send();
