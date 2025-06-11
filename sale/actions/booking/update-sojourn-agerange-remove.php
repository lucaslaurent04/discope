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
    ->first();

if(!$group) {
    throw new Exception("unknown_sojourn", EQ_ERROR_UNKNOWN_OBJECT);
}

if(count($group['age_range_assignments_ids']) < 2) {
    throw new Exception("mandatory_age_range_assignment", EQ_ERROR_INVALID_PARAM);
}

if(!in_array($group['booking_id']['status'], ['quote', 'checkedout']) && !$group['is_extra']) {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}

// read Age range object
$ageRangeAssignment = BookingLineGroupAgeRangeAssignment::id($params['age_range_assignment_id'])
    ->read([
        'id',
        'name',
        'qty',
        'age_range_id'
    ])
    ->first();

if(!$ageRangeAssignment) {
    throw new Exception("unknown_age_range_assignment", EQ_ERROR_UNKNOWN_OBJECT);
}


/*
    #todo #temp #kaleo - remember BookingLines with specific days having qty manually assigned through qty_vars
    #memo - qty_vars only applies for booking lines with accounting_method == 'person'
    For each line, remember initial resulting qty_vars (independent from nb_pers and qty), false means "no change"
*/
$map_booking_lines_qty_vars = [];
$bookingLineGroup = BookingLineGroup::id($params['id'])->read(['nb_pers', 'booking_lines_ids' => ['qty_accounting_method', 'qty', 'qty_vars']])->first();
$original_nb_pers = $bookingLineGroup['nb_pers'];
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
            $qty_vars[$i] = $original_nb_pers + $qty_var;
        }
    }
    $map_booking_lines_qty_vars[$booking_line_id] = $qty_vars;
}
/**/


// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();

BookingLineGroupAgeRangeAssignment::id($ageRangeAssignment['id'])->delete(true);

BookingLineGroup::id($group['id'])
    ->update([
        'nb_pers' => $group['nb_pers'] - $ageRangeAssignment['qty']
    ]);

// #memo - this impacts autosales at booking level
Booking::refreshNbPers($orm, $group['booking_id']['id']);

// #memo - this might create new groups
Booking::refreshAutosaleProducts($orm, $group['booking_id']['id']);

// #memo - whether we have a pack or not, the lines relating to the deleted age range are no longer relevant
/*
if($group['has_pack']) {
    // append/refresh lines based on pack configuration
    BookingLineGroup::refreshPack($orm, $group['id']);
}
*/
$bookingLines = BookingLine::search(['booking_line_group_id', '=', $group['id']])->read(['product_id' => ['has_age_range', 'age_range_id']]);
foreach($bookingLines as $booking_line_id => $bookingLine) {
    if($bookingLine['product_id']['has_age_range'] && $bookingLine['product_id']['age_range_id'] === $ageRangeAssignment['age_range_id']) {
        BookingLine::id($booking_line_id)->delete(true);
    }
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


/*
    #todo #temp #kaleo - remember BookingLines with specific days having qty manually assigned through qty_vars
    #memo - qty_vars only applies for booking lines with accounting_method == 'person'
    For each line, force specific days to previously manually set value, if any
*/
$bookingLineGroup = BookingLineGroup::id($params['id'])->read(['booking_lines_ids' => ['qty', 'qty_vars']])->first();
foreach($bookingLineGroup['booking_lines_ids'] as $booking_line_id => $bookingLine) {
    if(!isset($map_booking_lines_qty_vars[$booking_line_id])) {
        continue;
    }
    $new_qty_vars = json_decode($bookingLine['qty_vars']);
    $qty_vars = $map_booking_lines_qty_vars[$booking_line_id];
    foreach($qty_vars as $i => $qty_var) {
        if($qty_var !== false) {
            $new_qty_vars[$i] = $qty_var - $original_nb_pers;
        }
    }
    BookingLine::id($booking_line_id)->update(['qty_vars' => json_encode($new_qty_vars)]);
}
/**/


$context->httpResponse()
        ->status(204)
        ->send();
