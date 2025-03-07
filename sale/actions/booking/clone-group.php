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
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

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

$cloneAgeRangeAssignments = function($clone_group_id, $clone_age_range_assignments_ids, $age_range_assignments) {
    foreach($age_range_assignments as $index => $age_range_assignment) {
        $assignment_id = null;
        if($index === 0 && isset($clone_age_range_assignments_ids[0])) {
            $assignment_id = $clone_age_range_assignments_ids[0];
        }
        else {
            $clone_age_range_assignment = eQual::run('do', 'sale_booking_update-sojourn-agerange-add', [
                'id' => $clone_group_id
            ]);

            $assignment_id = $clone_age_range_assignment['id'];
        }

        eQual::run('do', 'sale_booking_update-sojourn-agerange-set', [
            'id'                        => $clone_group_id,
            'age_range_assignment_id'   => $assignment_id,
            'age_range_id'              => $age_range_assignment['age_range_id'],
            'qty'                       => $age_range_assignment['qty']
        ]);

        BookingLineGroupAgeRangeAssignment::id($assignment_id)
            ->update([
                'age_from'  => $age_range_assignment['age_from'],
                'age_to'    => $age_range_assignment['age_to']
            ]);
    }
};

$cloneBookingLines = function($clone_group_id, $lines) {
    usort($lines, function($a, $b) {
        return $a['id'] <=> $b['id'];
    });

    $map_old_activity_line_id_new_activity_id = [];
    $lines_orders = [];
    foreach($lines as $line) {
        $activity_line_id = null;
        if(
            isset($line['booking_activity_id']['activity_booking_line_id']['id'])
            && $line['booking_activity_id']['activity_booking_line_id']['id'] !== $line['id']
        ) {
            $product_model = $line['booking_activity_id']['activity_booking_line_id']['product_model_id'];
            if(
                $line['product_id'] === $product_model['transport_product_model_id']
                || in_array($line['product_id'], $product_model['supplies_ids'])
            ) {
                // Find automatically added transport or supply line, then update its qty and unit_price if needed.
                $new_activity_id = $map_old_activity_line_id_new_activity_id[$line['booking_activity_id']['activity_booking_line_id']['id']];
                $activity = BookingActivity::id($new_activity_id)
                    ->read(['booking_lines_ids' => ['product_model_id', 'qty', 'unit_price']])
                    ->first();

                $clone_line = null;
                foreach($activity['booking_lines_ids'] as $l) {
                    if($line['product_model_id'] === $l['product_model_id']) {
                        $clone_line = $l;
                        break;
                    }
                }

                if(!is_null($clone_line)) {
                    if($line['qty'] !== $clone_line['qty']) {
                        BookingLine::id($clone_line['id'])->update(['qty' => $line['qty']]);
                    }
                    if($line['unit_price'] !== $clone_line['unit_price']) {
                        BookingLine::id($clone_line['id'])->update(['unit_price' => $line['unit_price']]);
                    }
                    if($line['vat_rate'] !== $clone_line['vat_rate']) {
                        BookingLine::id($clone_line['id'])->update(['vat_rate' => $line['vat_rate']]);
                    }
                    if(!empty($line['description'])) {
                        BookingLine::id($clone_line['id'])->update(['description' => $line['description']]);
                    }

                    $lines_orders[] = ['order' => $line['order'], 'new_line_id' => $clone_line['id']];
                }

                continue;
            }
            else {
                // Save activity line id to link new line to the new activity
                $activity_line_id = $line['booking_activity_id']['activity_booking_line_id']['id'];
            }
        }

        $clone_line = BookingLine::create([
            'booking_line_group_id' => $clone_group_id,
            'booking_id'            => $line['booking_id'],
            'order'                 => $line['order'],
            'service_date'          => $line['service_date'],
            'time_slot_id'          => $line['time_slot_id'],
            'description'           => $line['description']
        ])
            ->read(['id'])
            ->first();

        \eQual::run('do', 'sale_booking_update-bookingline-product', [
            'id'            => $clone_line['id'],
            'product_id'    => $line['product_id']
        ]);

        $clone_line = BookingLine::id($clone_line['id'])
            ->read(['qty', 'unit_price', 'vat_rate'])
            ->first();

        $lines_orders[] = ['order' => $line['order'], 'new_line_id' => $clone_line['id']];

        // Handle link between BookingLine and BookingActivity
        if($line['is_activity']) {
            $bl = BookingLine::id($clone_line['id'])
                ->read(['booking_activity_id'])
                ->first();

            if(!is_null($bl)) {
                $map_old_activity_line_id_new_activity_id[$line['id']] = $bl['booking_activity_id'];
            }
        }
        elseif(!is_null($activity_line_id) && isset($map_old_activity_line_id_new_activity_id[$activity_line_id])) {
            BookingLine::id($clone_line['id'])
                ->update(['booking_activity_id' => $map_old_activity_line_id_new_activity_id[$activity_line_id]]);
        }

        if($line['qty'] !== $clone_line['qty']) {
            BookingLine::id($clone_line['id'])->update(['qty' => $line['qty']]);
        }
        if($line['unit_price'] !== $clone_line['unit_price']) {
            BookingLine::id($clone_line['id'])->update(['unit_price' => $line['unit_price']]);
        }
        if($line['vat_rate'] !== $clone_line['vat_rate']) {
            BookingLine::id($clone_line['id'])->update(['vat_rate' => $line['vat_rate']]);
        }
    }

    usort($lines_orders, function($a, $b) { return $a <=> $b; });
    foreach($lines_orders as $line_order) {
        BookingLine::id($line_order['new_line_id'])->update(['order' => $line_order['order']]);
    }
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
            'booking_id',
            'order',
            'qty',
            'unit_price',
            'vat_rate',
            'description',
            'product_id',
            'service_date',
            'time_slot_id',
            'is_activity',
            'product_model_id',
            'booking_activity_id' => [
                'activity_booking_line_id' => [
                    'product_model_id' => [
                        'transport_product_model_id',
                        'supplies_ids'
                    ]
                ]
            ]
        ],
        'age_range_assignments_ids' => [
            'qty',
            'age_range_id',
            'age_from',
            'age_to'
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
    ->read(['id', 'age_range_assignments_ids'])
    ->first(true);

$cloneAgeRangeAssignments($clone['id'], $clone['age_range_assignments_ids'], $group['age_range_assignments_ids']);

$cloneBookingLines($clone['id'], $group['booking_lines_ids']);

$context->httpResponse()
        ->status(201)
        ->send();
