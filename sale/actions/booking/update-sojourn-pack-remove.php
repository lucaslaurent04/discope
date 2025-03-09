<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\SojournProductModel;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Removes pack associated with sojourn (has_pack), if any. This script is meant to be called by the `booking/services` UI.",
    'params' 		=>	[
        'id' =>  [
            'description'       => 'Identifier of the targeted sojourn.',
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\BookingLineGroup',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response' => [
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


// read BookingLineGroup object
$group = BookingLineGroup::id($params['id'])
    ->read([
        'id', 'is_extra',
        'name',
        'has_pack',
        'booking_id' => ['id', 'status'],
        'booking_lines_ids',
        'sojourn_product_models_ids'
    ])
    ->first(true);

if(!$group) {
    throw new Exception("unknown_sojourn", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$group['has_pack']) {
    throw new Exception("non_pack_sojourn", EQ_ERROR_INVALID_PARAM);
}

if(!in_array($group['booking_id']['status'], ['quote', 'checkedout']) && !$group['is_extra']) {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}


// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();


// remove existing booking_lines

// #memo - removing service may impact activities and should only be done manually or at pack selection
// BookingLine::ids($group['booking_lines_ids'])->delete(true);
// SojournProductModel::ids($group['sojourn_product_models_ids'])->delete(true);

// reset attributes related to pack
BookingLineGroup::id($group['id'])->update(['is_locked' => false, 'has_pack' => false, 'pack_id' => null ]);

// recompute the group price according to pack or new lines
BookingLineGroup::refreshPrice($orm, $group['id']);

// recompute total price of the booking
Booking::refreshPrice($orm, $group['booking_id']['id']);

// #memo - if booking includes a price from an unpublished pricelist, it is marked as ToBeConfirmed (`is_price_tbc`)
Booking::refreshIsTbc($orm, $group['booking_id']['id']);

// restore events in case this controller is chained with others
$orm->enableEvents();

$context->httpResponse()
        ->status(204)
        ->send();
