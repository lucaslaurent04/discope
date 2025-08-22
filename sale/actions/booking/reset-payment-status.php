<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;

list($params, $providers) = announce([
    'description'   => "Update the payment status of all non-balanced bookings. This controller is meant to be run by CRON on a daily basis.",
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context    $context
 */
list($context) = [$providers['context']];

/*
    Update booking status for all bookings that are not balanced yet.
*/
Booking::search([['state', '=', 'instance'], ['status', 'not in', ['balanced', 'cancelled']]])->update(['payment_status' => null]);

$context->httpResponse()
        ->status(204)
        ->send();
