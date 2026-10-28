<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use discope\setting\Setting;
use sale\booking\Booking;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Update a booking when its status is `option` and has reached expiry. This script is meant to be scheduled by `do-option` controller.",
    'params' 		=>	[
        'id' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        'free_rental_units' =>  [
            'description'   => 'Flag for marking reserved rental units to be release immediately, if any.',
            'type'          => 'boolean',
            'default'       => false
        ],
        'expected_date_expiry' => [
            'description'   => 'Option expiry date known when the deprecation was scheduled.',
            'type'          => 'integer',
            // keep compatibility with tasks scheduled before this parameter existed
            'default'       => 0
        ]
    ],
    'access'        => [
        'visibility' => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\cron\Scheduler       $cron
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
['context' => $context, 'cron' => $cron, 'dispatch' => $dispatch] = $providers;


// read booking object
$booking = Booking::id($params['id'])
                  ->read(['id', 'name', 'status', 'is_noexpiry', 'date_expiry', 'center_office_id'])
                  ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$date_expiry = strtotime(date('Y-m-d', $booking['date_expiry']));
if(
    $booking['status'] !== 'option'
    || $booking['is_noexpiry']
    || $date_expiry > time()
    || ($params['expected_date_expiry'] && $date_expiry !== $params['expected_date_expiry'])
) {
    $context->httpResponse()->status(204)->send();
    return;
}

$cron->cancel("booking.option.reminder.{$params['id']}");

// customer notification is best effort and must never block option deprecation
try {
    eQual::run('do', 'sale_booking_notify-option-expired', [
        'id'                    => $params['id'],
        'expected_date_expiry'  => $date_expiry
    ]);
}
catch(Throwable $e) {
    trigger_error("APP::option expiration notification failed: {$e->getMessage()}", QN_REPORT_WARNING);
}

$back_to_quote = Setting::get_value('sale', 'features', 'booking.expired_option.revert_to_quote', true);
if($back_to_quote) {
    // revert to quote
    eQual::run('do', 'sale_booking_do-quote', [
        'id'                    => $params['id'],
        'free_rental_units'     => $params['free_rental_units']
    ]);

    if($params['free_rental_units']) {
        // send an alert saying that the option has expired and the booking was reverted to quote with a release of the blocked rental units
        $dispatch->dispatch('lodging.booking.option.expired', 'sale\booking\Booking', $params['id'], 'important', null, [], [], null, $booking['center_office_id']);
    }
    else {
        // send an alert saying that the option has expired and the booking was reverted to quote
        $dispatch->dispatch('lodging.booking.option.expired.reverted_to_quote', 'sale\booking\Booking', $params['id'], 'important', null, [], [], null, $booking['center_office_id']);

        // check quote for blocked rental units (might raise alert lodging.booking.quote.blocking)
        eQual::run('do', 'sale_booking_check-quote', ['id' => $params['id']]);
    }
}
else {
    // send an alert saying that the option has expired
    $dispatch->dispatch('lodging.booking.option.expired.expired_only', 'sale\booking\Booking', $params['id'], 'important', null, [], [], null, $booking['center_office_id']);
}

$context->httpResponse()
        ->status(204)
        ->send();
