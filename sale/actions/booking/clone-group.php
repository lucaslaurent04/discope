<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;

list($params, $providers) = announce([
    'description'   => "Clone a specific booking line group.",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the booking line group to clone.",
            'required'      => true
        ]

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
                'name',
                'order'
            ]
        ],
        'booking_lines_ids' => [
            'description',
            'product_id',
            'service_date',
            'time_slot_id',
            'booking_activity_id' => [
                'activity_booking_line_id'
            ]
        ]
    ])
    ->first(true);

if(is_null($group)) {
    throw new Exception("unknown_group", EQ_ERROR_UNKNOWN_OBJECT);
}

if($group['booking_id']['status'] !== 'quote') {
    throw new Exception("only_group_of_quote_booking_can_be_cloned", EQ_ERROR_NOT_ALLOWED);
}

$clone = BookingLineGroup::create([
    'name'              => $createGroupUniqName($group['name'], $group['booking_id']['booking_lines_groups_ids']),
    'order'             => max(array_column($group['booking_id']['booking_lines_groups_ids'], 'order')) + 1,
    'date_from'         => $group['date_from'],
    'date_to'           => $group['date_to'],
    'time_from'         => $group['time_from'],
    'time_to'           => $group['time_to'],
    'group_type'        => $group['group_type'],
    'sojourn_type_id'   => $group['sojourn_type_id'],
    'is_sojourn'        => $group['is_sojourn'],
    'is_event'          => $group['is_event'],
    'is_extra'          => $group['is_extra'],
    'is_autosale'       => $group['is_autosale'],
    'booking_id'        => $group['booking_id']['id'],
    'nb_pers'           => $group['nb_pers']
])
    ->read(['id'])
    ->first(true);

$order = 1;
foreach($group['booking_lines_ids'] as $line) {
    if(isset($line['booking_activity_id']['activity_booking_line_id']) && $line['booking_activity_id']['activity_booking_line_id'] !== $line['id']) {
        // Skip because is transport or supply of an activity and will be automatically added
        continue;
    }

    $clone_line = BookingLine::create([
        'order'                 => $order++,
        'booking_id'            => $group['booking_id']['id'],
        'booking_line_group_id' => $clone['id'],
        'service_date'          => $line['service_date'],
        'time_slot_id'          => $line['time_slot_id']
    ])
        ->read(['id'])
        ->first();

    \eQual::run('do', 'sale_booking_update-bookingline-product', [
        'id'            => $clone_line['id'],
        'product_id'    => $line['product_id']
    ]);

    // BookingLine::id($clone_line['id'])->update(['qty' => '']);
}

$context->httpResponse()
        ->status(201)
        ->send();
