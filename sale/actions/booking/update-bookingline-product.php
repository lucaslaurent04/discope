<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;

[$params, $providers] = eQual::announce([
    'description'	=> "Updates a Booking Line by changed its product. This script is meant to be called by the `booking/services` UI.",
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

// step-1 - make sure that there is at least one price available (published or unpublished)
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

// step-2 - attempt to update line
BookingLine::id($line_id)->update(['product_id' => $params['product_id']]);

// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();

$line = BookingLine::id($line_id)
    ->read(['booking_line_group_id'])
    ->first();

BookingLineGroup::refreshMeals($orm, $line['booking_line_group_id']);

// restore events in case this controller is chained with others
$orm->enableEvents();

$context->httpResponse()
        ->status(204)
        ->send();
