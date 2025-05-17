<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;
use sale\booking\BookingLineGroup;
use sale\catalog\Product;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Updates a sojourn based on partial patch of the main product. This script is meant to be called by the `booking/services` UI.",
    'params' 		=>	[
        'id' =>  [
            'description'       => 'Identifier of the targeted sojourn.',
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\BookingLineGroup',
            'required'          => true
        ],
        'pack_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\catalog\Product',
            'description'       => 'Pack (product) the group relates to, if any.',
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
        'nb_pers',
        'date_from',
        'booking_id' => ['id', 'status']
    ])
    ->first(true);

// read Pack (Product) object
$pack = Product::id($params['pack_id'])
    ->read([
        'id',
        'product_model_id' => ['id', 'name', 'has_duration', 'duration', 'capacity', 'qty_accounting_method'],
    ])
    ->first(true);

if(!$group) {
    throw new Exception("unknown_sojourn", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($group['booking_id']['status'], ['quote', 'checkedout']) && !$group['is_extra']) {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}

if(!$pack) {
    throw new Exception("unknown_pack", EQ_ERROR_UNKNOWN_OBJECT);
}

// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent deep cycling that can lead to performance issues.
$orm->disableEvents();

BookingLineGroup::id($group['id'])->update([
        'has_pack' => true,
        'pack_id' => $params['pack_id']
    ]);

// if group has default name : update group name based on pack
if(strpos($group['name'], 'Services ') === 0 && isset($pack['product_model_id']['name'])) {
    BookingLineGroup::id($group['id'])->update([
            'name' => $pack['product_model_id']['name']
        ]);
}

// update date_to according to pack duration (if set)
if($pack['product_model_id']['has_duration']) {
    BookingLineGroup::id($group['id'])->update([
            'date_to' =>  $group['date_from'] + ($pack['product_model_id']['duration'] * 60*60*24)
        ]);
    BookingLineGroup::refreshNbNights($orm, $group['id']);
    Booking::refreshDate($orm, $group['booking_id']['id']);
}

if($pack['product_model_id']['qty_accounting_method'] == 'accomodation' && $pack['product_model_id']['capacity'] > $group['nb_pers']) {
        BookingLineGroup::id($group['id'])->update([
            'nb_pers' =>  $pack['product_model_id']['capacity']
        ]);
    Booking::refreshNbPers($orm, $group['booking_id']['id']);
}

// #memo - this must remain out of conditions blocks for supporting various cases
Booking::refreshAutosaleProducts($orm, $group['booking_id']['id']);

// append new lines based on pack configuration
BookingLineGroup::refreshPack($orm, $group['id']);

// #memo - this might create new lines
BookingLineGroup::refreshAutosaleProducts($orm, $group['id']);

/*
    #memo - adapters depend on date_from, nb_nights, nb_pers, nb_children
        rate_class_id,
        center_id.discount_list_category_id,
        center_office_id.freebies_manual_assignment,
    and are applied both on group and each of its lines
*/
BookingLineGroup::refreshPriceAdapters($orm, $group['id']);

BookingLineGroup::refreshMealPreferences($orm, $group['id']);

// refresh price_id, qty and price for all lines
BookingLineGroup::refreshLines($orm, $group['id']);

// handle auto assignment of rental units (depending on center office prefs)
BookingLineGroup::refreshRentalUnitsAssignments($orm, $group['id']);

// #memo - this only affects Groups that relate to a model marked with `has_own_price`
BookingLineGroup::refreshPriceId($orm, $group['id']);

// recompute the group price according to pack or new lines
BookingLineGroup::refreshPrice($orm, $group['id']);

// #memo - new lines have been added, that could be rental units relating to a product model set as schedulable service with its own schedule
BookingLineGroup::refreshTime($orm, $group['id']);

// update meals
BookingLineGroup::refreshMeals($orm, $group['id']);

// recompute time (could be based on product associated with pack or based on product associated with lines)
Booking::refreshTime($orm, $group['booking_id']['id']);

// #memo - booking type might be impacted by the chosen pack or one of its lines
Booking::refreshBookingType($orm, $group['booking_id']['id']);

// recompute total price of the booking
Booking::refreshPrice($orm, $group['booking_id']['id']);

// #memo - if booking includes a price from an unpublished pricelist, it is marked as ToBeConfirmed (`is_price_tbc`)
Booking::refreshIsTbc($orm, $group['booking_id']['id']);

// restore events in case this controller is chained with others
$orm->enableEvents();

$context->httpResponse()
        ->status(204)
        ->send();
