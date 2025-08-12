<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\BookingLineGroup;

[$params, $providers] = eQual::announce([
    'description'	=> "Refresh all meals of a specific booking.",
    'params' 		=> [

        'id' => [
            'type'              => 'integer',
            'description'       => "Identifier of the targeted booking.",
            'min'               => 1,
            'required'          => true
        ]

    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$booking = Booking::id($params['id'])
    ->read(['status', 'booking_lines_groups_ids'])
    ->first(true);

if(is_null($booking)) {
    throw new Exception("unknown_booking", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($booking['status'], ['quote', 'option', 'confirmed', 'validated'])) {
    throw new Exception("wrong_booking_status", EQ_ERROR_INVALID_PARAM);
}

$orm->disableEvents();

foreach($booking['booking_lines_groups_ids'] as $group_id) {
    BookingLineGroup::refreshMeals($orm, $group_id);
}

$orm->enableEvents();

$context->httpResponse()
        ->status(204)
        ->send();
