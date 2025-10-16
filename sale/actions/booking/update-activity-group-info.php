<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BookingLineGroup;
use sale\booking\BookingLineGroupAgeRangeAssignment;

[$params, $providers] = eQual::announce([
    'description'	=> "Update activity group information, even if status is not quote.",
    'help'          => "To only use for activities groups that do not contain products but only activities. To use form activities planning.",
    'params' 		=>	[

        'id' =>  [
            'type'          => 'integer',
            'min'           => 1,
            'description'   => "Identifier of the booking line group.",
            'required'      => true
        ],

        'nb_pers' => [
            'type'          => 'integer',
            'description'   => "Qty of people in the group."
        ],

        'age_from' => [
            'type'          => 'integer',
            'description'   => "Age min of participants of the children activity group."
        ],

        'age_to' => [
            'type'          => 'integer',
            'description'   => "Age max of participants of the children activity group."
        ],

        'has_person_with_disability' => [
            'type'          => 'boolean',
            'description'   => "Has a person with disability."
        ],

        'person_disability_description' => [
            'type'          => 'string',
            'description'   => "Description about group's people disability."
        ]

    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$booking_line_group = BookingLineGroup::id($params['id'])
    ->read(['group_type', 'booking_lines_ids', 'age_range_assignments_ids'])
    ->first();

if(is_null($booking_line_group)) {
    throw new Exception("unknown_group", EQ_ERROR_UNKNOWN_OBJECT);
}

if($booking_line_group['group_type'] !== 'camp') {
    throw new Exception("invalid_type", EQ_ERROR_INVALID_PARAM);
}

if(count($booking_line_group['booking_lines_ids']) > 0) {
    throw new Exception("invalid_group", EQ_ERROR_INVALID_PARAM);
}

if(count($booking_line_group['age_range_assignments_ids']) !== 1) {
    throw new Exception("invalid_age_ranges", EQ_ERROR_INVALID_PARAM);
}

$event_mask = $orm->disableEvents();

if(isset($params['nb_pers']) || isset($params['has_person_with_disability']) || isset($params['person_disability_description'])) {
    $data = [];
    if(isset($params['nb_pers'])) {
        $data['nb_pers'] = $params['nb_pers'];
    }
    if(isset($params['has_person_with_disability'])) {
        $data['has_person_with_disability'] = $params['has_person_with_disability'];
    }
    if(isset($params['person_disability_description'])) {
        $data['person_disability_description'] = $params['person_disability_description'];
    }

    $orm->update(BookingLineGroup::getType(), $booking_line_group['id'], $data);
}

if(isset($params['nb_pers']) || isset($params['age_from']) || isset($params['age_to'])) {
    $data = [];
    if(isset($params['nb_pers'])) {
        $data['qty'] = $params['nb_pers'];
    }
    if(isset($params['age_from'])) {
        $data['age_from'] = $params['age_from'];
    }
    if(isset($params['age_to'])) {
        $data['age_to'] = $params['age_to'];
    }

    $orm->update(BookingLineGroupAgeRangeAssignment::getType(), $booking_line_group['age_range_assignments_ids'], $data);
}

$orm->enableEvents($event_mask);

$context->httpResponse()
        ->status(200)
        ->send();
