<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BookingActivity;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingLineGroupAgeRangeAssignment;

list($params, $providers) = announce([
    'description'   => "Clone a specific booking line group.",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the booking line group to clone.",
            'required'      => true
        ]

    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['booking.default.user']
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

/**
 * Methods
 */

$createGroupUniqName = function($group_name, $groups) {
    $name_uniq = false;
    $i = 1;

    preg_match("/\((\d)\)$/", $group_name, $matches);
    if(isset($matches[1])) {
        $i = (int) $matches[1];
        $group_name = substr($group_name, 0, -4);
    }

    $name = $group_name.' ('.$i.')';

    while(!$name_uniq) {
        $name_uniq = true;
        foreach($groups as $g) {
            if($g['name'] === $name) {
                $name_uniq = false;
                break;
            }
        }

        if(!$name_uniq) {
            $name = $group_name.' ('.++$i.')';
        }
    }

    return $name;
};

/**
 * Action
 */

$group = BookingLineGroup::id($params['id'])
    ->read([
        'name',
        'order',
        'date_from',
        'date_to',
        'time_from',
        'time_to',
        'group_type',
        'sojourn_type_id',
        'is_sojourn',
        'is_event',
        'is_extra',
        'is_autosale',
        'nb_pers',
        'booking_id' => [
            'id',
            'status',
            'booking_lines_groups_ids' => [
                'name'
            ]
        ],
        'age_range_assignments_ids' => [
            'booking_id',
            'qty',
            'age_range_id',
            'age_from',
            'age_to'
        ],
        'booking_lines_ids' => [
            'booking_id',
            'booking_activity_id',
            'order',
            'qty',
            'unit_price',
            'vat_rate',
            'qty_vars',
            'description',
            'product_id',
            'product_model_id',
            'service_date',
            'time_slot_id',
            'is_activity',
            'price_id',
            'total',
            'price',
            'meal_location'
        ],
        'booking_activities_ids' => [
            'booking_id',
            'activity_booking_line_id',
            'providers_ids',
            'counter',
            'total',
            'price',
            'is_virtual',
            'activity_date',
            'time_slot_id'
        ]
    ])
    ->first(true);

if(is_null($group)) {
    throw new Exception("unknown_group", EQ_ERROR_UNKNOWN_OBJECT);
}

if($group['booking_id']['status'] !== 'quote') {
    throw new Exception("only_group_of_quote_booking_can_be_cloned", EQ_ERROR_NOT_ALLOWED);
}

$orm->disableEvents();

// Create group
$cloned_group = BookingLineGroup::create([
    'name'              => $createGroupUniqName($group['name'], $group['booking_id']['booking_lines_groups_ids']),
    'order'             => max(array_column($group['booking_id']['booking_lines_groups_ids'], 'order')) + 1,
    'booking_id'        => $group['booking_id']['id'],
    'group_type'        => $group['group_type'],
    'date_from'         => $group['date_from'],
    'date_to'           => $group['date_to'],
    'time_from'         => $group['time_from'],
    'time_to'           => $group['time_to'],
    'sojourn_type_id'   => $group['sojourn_type_id'],
    'is_sojourn'        => $group['is_sojourn'],
    'is_event'          => $group['is_event'],
    'is_extra'          => $group['is_extra'],
    'is_autosale'       => $group['is_autosale'],
    'nb_pers'           => $group['nb_pers']
])
    ->read(['id'])
    ->first();

// Create age range assignments
foreach($group['age_range_assignments_ids'] as $assignment) {
    BookingLineGroupAgeRangeAssignment::create([
        'booking_line_group_id' => $cloned_group['id'],
        'booking_id'            => $assignment['booking_id'],
        'qty'                   => $assignment['qty'],
        'age_range_id'          => $assignment['age_range_id'],
        'age_from'              => $assignment['age_from'],
        'age_to'                => $assignment['age_to']
    ]);
}

// Create lines
$map_old_booking_line_id_to_new = [];
$map_old_booking_activity_new_lines_ids = [];
foreach($group['booking_lines_ids'] as $line) {
    $cloned_line = BookingLine::create([
        'booking_line_group_id' => $cloned_group['id'],
        'booking_id'            => $line['booking_id'],
        'order'                 => $line['order'],
        'qty'                   => $line['qty'],
        'unit_price'            => $line['unit_price'],
        'vat_rate'              => $line['vat_rate'],
        'qty_vars'              => $line['qty_vars'],
        'description'           => $line['description'],
        'product_id'            => $line['product_id'],
        'product_model_id'      => $line['product_model_id'],
        'service_date'          => $line['service_date'],
        'time_slot_id'          => $line['time_slot_id'],
        'is_activity'           => $line['is_activity'],
        'price_id'              => $line['price_id'],
        'total'                 => $line['total'],
        'price'                 => $line['price'],
        'meal_location'         => $line['meal_location']
    ])
        ->read(['id'])
        ->first();

    $map_old_booking_line_id_to_new[$line['id']] = $cloned_line['id'];

    if(!isset($map_old_booking_activity_new_lines_ids[$line['booking_activity_id']])) {
        $map_old_booking_activity_new_lines_ids[$line['booking_activity_id']] = [];
    }
    $map_old_booking_activity_new_lines_ids[$line['booking_activity_id']][] = $cloned_line['id'];
}

// Create activities
foreach($group['booking_activities_ids'] as $activity) {
    $cloned_activity = BookingActivity::create([
        'booking_line_group_id'     => $cloned_group['id'],
        'booking_id'                => $activity['booking_id'],
        'activity_booking_line_id'  => $map_old_booking_line_id_to_new[$activity['activity_booking_line_id']],
        'providers_ids'             => $activity['providers_ids'],
        'counter'                   => $activity['counter'],
        'total'                     => $activity['total'],
        'price'                     => $activity['price'],
        'is_virtual'                => $activity['is_virtual'],
        'activity_date'             => $activity['activity_date'],
        'time_slot_id'              => $activity['time_slot_id']
    ])
        ->read(['id'])
        ->first();

    // Link booking lines to newly created activity
    BookingLine::ids($map_old_booking_activity_new_lines_ids[$activity['id']])
        ->update(['booking_activity_id' => $cloned_activity['id']]);
}

$orm->enableEvents();

$context->httpResponse()
        ->status(201)
        ->send();
