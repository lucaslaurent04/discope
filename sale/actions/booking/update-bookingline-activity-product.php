<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\BookingActivity;
use sale\booking\BookingLine;
use sale\catalog\Product;

[$params, $providers] = eQual::announce([
    'description'	=> "Updates an Activity's transport/supply Booking Line by changing its product.",
    'params' 		=> [
        'id' => [
            'description'       => "Identifier of the targeted booking line.",
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\BookingLine',
            'required'          => true
        ],
        'product_id' => [
            'description'       => "Identifier of the product to assign the line to.",
            'type'              => 'many2one',
            'foreign_object'    => 'sale\catalog\Product',
            'default'           => false
        ],
        'booking_activity_id' => [
            'description'       => "Identifier of the booking activity this transport/supply should be linked to.",
            'type'              => 'many2one',
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


/**
 * Check given params
 */

// check line param
$line = BookingLine::id($params['id'])
    ->read(['name', 'booking_id' => ['has_transport']])
    ->first();

if(is_null($line)) {
    throw new Exception("unknown_line", EQ_ERROR_UNKNOWN_OBJECT);
}

// check product param
$product = Product::id($params['product_id'])
    ->read(['is_transport', 'is_supply'])
    ->first();

if(is_null($product)) {
    throw new Exception("unknown_product", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$product['is_transport'] && !$product['is_supply']) {
    throw new Exception("product_must_be_transport_or_supply", EQ_ERROR_INVALID_PARAM);
}

// check activity param
$activity = BookingActivity::id($params['booking_activity_id'])
    ->read(['activity_date', 'time_slot_id' => ['name']])
    ->first();

if(is_null($activity)) {
    throw new Exception("unknown_activity", EQ_ERROR_UNKNOWN_OBJECT);
}


/**
 * Update the product of booking line with a transport/supply and attach it to the activity
 */

// create description for transport product
$description = null;
if($product['is_transport']) {
    $description = sprintf('Transport (%s - %s) : %s',
        date('d/m/Y', $activity['service_date']),
        $activity['time_slot_id']['name'],
        $line['name']
    );
}

// disable event and save mask of previously discarded events
$events_mask = $orm->disableEvents();

BookingLine::id($line['id'])
    ->update([
        'product_id'            => $product['id'],
        'booking_activity_id'   => $activity['id'],
        'service_date'          => $activity['activity_date'],
        'time_slot_id'          => $activity['time_slot_id']['id'],
        'description'           => $description
    ])
    ->do('update-price-id')
    ->do('update-qty');

// update booking has_transport field if needed
if($product['is_transport'] && !$line['booking_id']['has_transport']) {
    Booking::id($line['booking_id']['id'])
        ->update(['has_transport' => true]);
}

// re-enable events to previous state
$orm->enableEvents($events_mask);


/**
 * Response
 */

$context->httpResponse()
        ->status(204)
        ->send();
