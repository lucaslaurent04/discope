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
use sale\booking\BookingLineGroupAgeRangeAssignment;
use sale\customer\AgeRange;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Update an age range assignment. This script is meant to be called by the `booking/services` UI.",
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
        ],
        'age_range_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\AgeRange',
            'description'       => 'New age range assigned.',
            'required'          => true
        ],
        'qty' =>  [
            'type'              => 'integer',
            'description'       => 'New amount of participants assigned to given ages.',
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
        'booking_id' => ['id', 'status']
    ])
    ->first(true);


if(!$group) {
    throw new Exception("unknown_sojourn", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($group['booking_id']['status'], ['quote', 'checkedout']) && !$group['is_extra']) {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}

// check Age range object
$age_range = AgeRange::id($params['age_range_id'])->first(true);
if(!$age_range) {
    throw new Exception("unknown_age_range", EQ_ERROR_UNKNOWN_OBJECT);
}

// read Age range assignment object
$ageRangeAssignment = BookingLineGroupAgeRangeAssignment::id($params['age_range_assignment_id'])
    ->read([
        'id',
        'name',
        'qty',
        'age_range_id'
    ])
    ->first(true);

if(!$ageRangeAssignment) {
    throw new Exception("unknown_age_range_assignment", EQ_ERROR_UNKNOWN_OBJECT);
}

if($params['qty'] < 0) {
    throw new Exception("negative_value", EQ_ERROR_INVALID_PARAM);
}


/*
    #todo #temp #kaleo - remember BookingLines with specific days having qty manually assigned through qty_vars
    #memo - qty_vars only applies for booking lines with accounting_method == 'person'
    For each line, remember initial resulting qty_vars (independent from nb_pers and qty), false means "no change"
*/
$map_booking_lines_qty_vars = [];
$bookingLineGroup = BookingLineGroup::id($params['id'])->read(['nb_pers', 'booking_lines_ids' => ['qty_accounting_method', 'qty', 'qty_vars']])->first();
foreach($bookingLineGroup['booking_lines_ids'] as $booking_line_id => $bookingLine) {
    if($bookingLine['qty_accounting_method'] !== 'person') {
        continue;
    }
    $qty_vars = json_decode($bookingLine['qty_vars']);
    foreach($qty_vars as $i => $qty_var) {
        $qty_var = intval($qty_var);
        if($qty_var === 0) {
            $qty_vars[$i] = false;
        }
        else {
            $qty_vars[$i] = $bookingLineGroup['nb_pers'] + $qty_var;
        }
    }
    $map_booking_lines_qty_vars[$booking_line_id] = $qty_vars;
}
/**/


// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();


BookingLineGroupAgeRangeAssignment::id($params['age_range_assignment_id'])
    ->update([
        'qty'           => $params['qty'],
        'age_range_id'  => $params['age_range_id']
    ]);

$delta = $params['qty'] - $ageRangeAssignment['qty'];

BookingLineGroup::id($group['id'])
    ->update([
        'nb_pers' => $group['nb_pers'] + $delta
    ]);

// #memo - this impacts autosales at booking level
Booking::refreshNbPers($orm, $group['booking_id']['id']);
// #memo - this might create new groups
Booking::refreshAutosaleProducts($orm, $group['booking_id']['id']);

// #memo - only all-ages lines and lines specific to the changed age range are impacted
/*
if($group['has_pack']) {
    // append/refresh lines based on pack configuration
    BookingLineGroup::refreshPack($orm, $group['id']);
}
*/
$bookingLines = BookingLine::search(['booking_line_group_id', '=', $bookingLineGroup['id']])->read(['product_id' => ['has_age_range', 'age_range_id']]);
foreach($bookingLines as $booking_line_id => $bookingLine) {
    if(!$bookingLine['product_id']['has_age_range'] || $bookingLine['product_id']['age_range_id'] === $ageRangeAssignment['age_range_id']) {
        BookingLine::refreshQty($orm, $booking_line_id);
    }
}

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

BookingLineGroup::refreshPrice($orm, $group['id']);
Booking::refreshPrice($orm, $group['booking_id']['id']);


// restore events in case this controller is chained with others
$orm->enableEvents();


/*
    #todo #temp #kaleo - remember BookingLines with specific days having qty manually assigned through qty_vars
    #memo - qty_vars only applies for booking lines with accounting_method == 'person'
    For each line, force specific days to previously manually set value, if any
*/
$bookingLineGroup = BookingLineGroup::id($params['id'])->read(['nb_pers', 'booking_lines_ids' => ['qty', 'qty_vars']])->first();
foreach($bookingLineGroup['booking_lines_ids'] as $booking_line_id => $bookingLine) {
    if(!isset($map_booking_lines_qty_vars[$booking_line_id])) {
        continue;
    }
    $new_qty_vars = json_decode($bookingLine['qty_vars']);
    $qty_vars = $map_booking_lines_qty_vars[$booking_line_id];
    foreach($qty_vars as $i => $qty_var) {
        $new_qty_var = $new_qty_vars[$i];
        if($qty_var !== false) {
            $new_qty_vars[$i] = $qty_var - $bookingLineGroup['nb_pers'];
        }
    }
    BookingLine::id($booking_line_id)->update(['qty_vars' => json_encode($new_qty_vars)]);
}
/**/


$context->httpResponse()
        ->status(204)
        ->send();
