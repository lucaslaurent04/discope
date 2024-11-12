<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;
use sale\booking\BookingLineGroup;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Updates a sojourn based on partial patch of the main product. This script is meant to be called by the `booking/services` UI.",
    'params' 		=>	[
        'id' =>  [
            'description'       => 'Identifier of the targeted sojourn.',
            'type'              => 'many2one',
            'foreign_object'    => BookingLineGroup::getType(),
            'required'          => true
        ],
        'date_from' =>  [
            'type'              => 'date',
            'description'       => 'New first date of the sojourn.',
            'required'          => true
        ],
        'date_to' =>  [
            'type'              => 'date',
            'description'       => 'New last date of the sojourn.',
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
list($context, $orm) = [ $providers['context'], $providers['orm']];

// read BookingLineGroup object
$group = BookingLineGroup::id($params['id'])
    ->read([
        'id', 'is_extra',
        'booking_id' => ['id', 'status']
    ])
    ->first(true);

if(!$group) {
    throw new Exception("unknown_booking", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($group['booking_id']['status'], ['quote', 'checkedout']) && !$group['is_extra']) {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}

// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();

BookingLineGroup::id($group['id'])
    ->update([
        'date_from' => $params['date_from'],
        'date_to'   => $params['date_to']
    ]);

// reset nb nights
BookingLineGroup::refreshNbNights($orm, $group['id']);
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

// refresh price_id, qty and price for all lines
BookingLineGroup::refreshLines($orm, $group['id']);

// handle auto assignment of rental units (depending on center office prefs)
BookingLineGroup::refreshRentalUnitsAssignments($orm, $group['id']);

BookingLineGroup::refreshPrice($orm, $group['id']);

Booking::refreshPrice($orm, $group['booking_id']['id']);

// #memo - for booking date_from and date_to respectively match the first and the last date of all sojourns
Booking::refreshDate($orm, $group['booking_id']['id']);

// restore events in case this controller is chained with others
$orm->enableEvents();

$context->httpResponse()
        ->status(204)
        ->send();
