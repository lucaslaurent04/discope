<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BookingLine;
use sale\booking\TimeSlot;
use sale\catalog\Product;

[$params, $providers] = eQual::announce([
    'description'	=> "Updates a Booking Line by changing its product.",
    'help'          => "This script is meant to be called by the `booking/services` UI.",
    'params' 		=> [
        'id' => [
            'description'       => 'Identifier of the targeted Booking Line.',
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\BookingLine',
            'required'          => true
        ],
        'product_id' => [
            'type'              => 'many2one',
            'description'       => 'Identifier of the product to assign the line to.',
            'foreign_object'    => 'sale\catalog\Product',
            'default'           => false
        ],
        'service_date' => [
            'type'              => 'date',
            'description'       => 'Date when the activity takes place.',
            'required'          => true
        ],
        'time_slot_id' => [
            'type'              => 'many2one',
            'description'       => 'Identifier of the time slot when the activity takes place.',
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

// check product param
$product = Product::id($params['product_id'])
    ->read(['is_activity'])
    ->first();

if(is_null($product)) {
    throw new Exception("unknown_product", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$product['is_activity']) {
    throw new Exception("product_must_be_activity", EQ_ERROR_INVALID_PARAM);
}

// check service date param
if($params['service_date'] < time()) {
    throw new Exception("service_date_must_be_in_future", EQ_ERROR_INVALID_PARAM);
}

// check time slot
$time_slot = TimeSlot::id($params['time_slot_id'])
    ->read(['name'])
    ->first();

if(is_null($time_slot)) {
    throw new Exception("unknown_time_slot", EQ_ERROR_UNKNOWN_OBJECT);
}


/**
 * Make sure that there is at least one price available (published or unpublished)
 */

$line_id = $params['id'];
$found = false;

// look for published prices
$prices = BookingLine::searchPriceId($orm, $line_id, $params['product_id']);

if(isset($prices[$line_id])) {
    $found = true;
}
// look for unpublished prices
else {
    $prices = BookingLine::searchPriceIdUnpublished($orm, $line_id, $params['product_id']);
    if(isset($prices[$line_id])) {
        $found = true;
    }
}

if(!$found) {
    throw new Exception("missing_price", EQ_ERROR_INVALID_PARAM);
}

/**
 * Set activity product to booking line
 */

$orm->disableEvents();

BookingLine::id($line_id)->do('set-activity-product', [
    'product_id'    => $params['product_id'],
    'service_date'  => $params['service_date'],
    'time_slot_id'  => $params['time_slot_id']
]);

$orm->enableEvents();


/**
 * Response
 */

$context->httpResponse()
        ->status(204)
        ->send();
