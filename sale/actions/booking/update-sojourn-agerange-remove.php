<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;
use sale\booking\BookingLineGroup;
use sale\booking\BookingLineGroupAgeRangeAssignment;
use sale\customer\AgeRange;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Create an empty additional age range. This script is meant to be called by the `booking/services` UI.",
    'params' 		=>	[
        'id' =>  [
            'description'       => 'Identifier of the targeted sojourn.',
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\BookingLineGroup',
            'required'          => true
        ],
        'age_range_assignment_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\BookingLineGroupAgeRangeAssignment',
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
        'has_pack',
        'nb_pers',
        'date_from',
        'booking_id' => ['id', 'status'],
        'age_range_assignments_ids'
    ])
    ->first(true);

// read Age range object
$age_range_assignment = BookingLineGroupAgeRangeAssignment::id($params['age_range_assignment_id'])
    ->read([
        'id',
        'name',
        'qty'
    ])
    ->first(true);

if(!$group) {
    throw new Exception("unknown_sojourn", EQ_ERROR_UNKNOWN_OBJECT);
}

if(count($group['age_range_assignments_ids']) < 2) {
    throw new Exception("mandatory_age_range_assignment", EQ_ERROR_INVALID_PARAM);
}

if(!in_array($group['booking_id']['status'], ['quote', 'checkedout']) && !$group['is_extra']) {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}

if(!$age_range_assignment) {
    throw new Exception("unknown_age_range_assignment", EQ_ERROR_UNKNOWN_OBJECT);
}


// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();

BookingLineGroupAgeRangeAssignment::id($params['age_range_assignment_id'])->delete(true);

BookingLineGroup::id($group['id'])
    ->update([
        'nb_pers' => $group['nb_pers'] - $age_range_assignment['qty']
    ]);

// #memo - this impacts autosales at booking level
Booking::refreshNbPers($orm, $group['booking_id']['id']);

// #memo - this might create new groups
Booking::refreshAutosaleProducts($orm, $group['booking_id']['id']);

if($group['has_pack']) {
    // append/refresh lines based on pack configuration
    BookingLineGroup::refreshPack($orm, $group['id']);
}

// #memo - this might create new lines
BookingLineGroup::refreshAutosaleProducts($orm, $group['id']);

// #memo - here we don't refresh Age Range assignments

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

BookingLineGroup::refreshPrice($orm, $group['id']);
Booking::refreshPrice($orm, $group['booking_id']['id']);


// restore events in case this controller is chained with others
$orm->enableEvents();

$context->httpResponse()
        ->status(204)
        ->send();
