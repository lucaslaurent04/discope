<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
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
        'nb_pers',
        'date_from',
        'booking_id' => ['id', 'status']
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

eQual::run('do', 'sale_booking_update-sojourn-nbpers', ['id' => $params['id'], 'nb_pers' => $group['nb_pers'] - $age_range_assignment['qty']]);

// restore events in case this controller is chained with others
$orm->enableEvents();

$context->httpResponse()
        ->status(204)
        ->send();
