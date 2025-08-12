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
        'group_type' =>  [
            'type'              => 'string',
            'selection'         => [
                'simple',
                'sojourn',
                'event',
                'camp'
            ],
            'description'       => 'New type the group must be assigned to.',
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
    throw new Exception("unknown_sojourn", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($group['booking_id']['status'], ['quote', 'checkedout']) && !$group['is_extra']) {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}

// Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
// While these callbacks are useful for maintaining data integrity (they and are used in tests),
// they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
$orm->disableEvents();

BookingLineGroup::id($group['id'])->update(['group_type' => $params['group_type']]);

// #memo - adjust is_sojourn & is_event
BookingLineGroup::refreshType($orm, $group['id']);
// #memo - age range assignment might not exist yet
BookingLineGroup::refreshAgeRangeAssignments($orm, $group['id']);
// #memo - nb_nights is actually nb_days for events
BookingLineGroup::refreshNbNights($orm, $group['id']);
// #memo - this might create new lines
BookingLineGroup::refreshAutosaleProducts($orm, $group['id']);

BookingLineGroup::refreshLines($orm, $group['id']);

BookingLineGroup::refreshPrice($orm, $group['id']);
Booking::refreshPrice($orm, $group['booking_id']['id']);
Booking::refreshNbPers($orm, $group['booking_id']['id']);

// restore events in case this controller is chained with others
$orm->enableEvents();

BookingLineGroup::resetActivityGroupNumber($group['booking_id']['id']);

$context->httpResponse()
        ->status(204)
        ->send();
