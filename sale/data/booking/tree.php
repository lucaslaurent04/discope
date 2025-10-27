<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\SojournProductModelRentalUnitAssignement;

// announce script and fetch parameters values
[$params, $providers] = eQual::announce([
    'description'	=>	'Provide a fully loaded tree for a given booking.',
    'params' 		=>	[
        'id' => [
            'description'   => 'Identifier of the booking for which the tree is requested.',
            'type'          => 'integer',
            'required'      => true
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
    'providers' => ['context', 'auth']
]);

['context' => $context, 'auth' => $auth] = $providers;

// #memo - processing of this controller might be heavy, so we make sure AC does not check permissions for each single consumption
$auth->su();

$tree = [
    'id', 'name', 'created', 'date_from', 'date_to', 'status', 'total', 'price', 'is_locked', 'nb_pers',
    'customer_id' => [
        'id', 'rate_class_id'
    ],
    'center_id' => [
        'id', 'name', 'sojourn_type_id', 'product_groups_ids'
    ],
    'booking_lines_groups_ids' => [
        'id',
        'name',
        'order',
        'has_pack',
        'total',
        'price',
        'fare_benefit',
        'is_locked',
        'is_autosale',
        'is_extra',
        'has_schedulable_services',
        'has_consumptions',
        'date_from',
        'date_to',
        'time_from',
        'time_to',
        'nb_pers',
        'nb_nights',
        'group_type',
        'is_sojourn',
        'is_event',
        'has_locked_rental_units',
        'booking_id',
        'sojourn_type_id',
        'activity_group_num',
        'has_person_with_disability',
        'person_disability_description',
        'meal_prefs_description',
        'pack_id' => [
            'id',
            'name'
        ],
        'rate_class_id' => [
            'id',
            'name',
            'description'
        ],
        'age_range_assignments_ids' => [
            'age_range_id', 'qty', 'free_qty', 'age_from', 'age_to', 'is_sporty'
        ],
        'sojourn_product_models_ids' => [
            'id',
            'qty',
            'booking_line_group_id',
            'product_model_id' => [
                'id',
                'name',
                'capacity',
                'qty_accounting_method'
            ],
            'rental_unit_assignments_ids' => [
                '@sort' => ['name' => 'asc'],
                'id',
                'name',
                'qty',
                'use_extra',
                'booking_line_group_id',
                'rental_unit_id' => [
                    'id',
                    'name',
                    'description',
                    'is_accomodation',
                    'capacity',
                    'extra'
                ]
            ]
        ],
        'meal_preferences_ids' => [
            'type', 'pref', 'qty'
        ],
        'booking_lines_ids' => [
            'id',
            'name',
            'description',
            'order',
            'qty',
            'vat_rate',
            'unit_price',
            'total',
            'price',
            'free_qty',
            'discount',
            'fare_benefit',
            'qty_vars',
            'qty_accounting_method',
            'is_rental_unit',
            'is_accomodation',
            'is_meal',
            'meal_location',
            'is_activity',
            'is_transport',
            'is_supply',
            'is_fullday',
            'service_date',
            'time_slot_id',
            'booking_activity_id',
            'price_id',
            'product_id' => [
                'name',
                'sku',
                'has_age_range',
                'age_range_id',
                'product_model_id' => [
                    'schedule_offset',
                    'has_duration',
                    'duration',
                    'capacity',
                    'type',
                    'service_type',
                    'is_repeatable',
                    'is_meal',
                    'is_snack',
                    'is_fullday',
                    'has_provider',
                    'has_rental_unit',
                    'providers_ids'             => ['name'],
                    'activity_rental_units_ids' => ['name'],
                    'time_slots_ids'            => ['name', 'code']
                ]
            ],
            'auto_discounts_ids' => [
                'id', 'type', 'value',
                'discount_id' => ['name'],
                'discount_list_id' => [
                    'name',
                    'rate_min',
                    'rate_max'
                ]
            ],
            'manual_discounts_ids' => [
                'id',
                'type',
                'value',
                'discount_id' => ['name']
            ]
        ],
        'booking_activities_ids' => [
            'activity_booking_line_id',
            'booking_line_group_id',
            'supplies_booking_lines_ids',
            'transports_booking_lines_ids',
            'counter',
            'total',
            'price',
            'qty',
            'is_virtual',
            'activity_date',
            'time_slot_id',
            'schedule_from',
            'schedule_to',
            'providers_ids',
            'rental_unit_id',
            'description',
            'product_id' => [
                'name',
                'sku'
            ],
        ],
        'booking_meals_ids' => [
            'date',
            'time_slot_id',
            'is_self_provided',
            'meal_type_id',
            'meal_place_id',
            'booking_lines_ids'
        ]
    ]
];


$bookings = Booking::id($params['id'])
    ->read($tree)
    ->adapt('json')
    ->get(true);

if(!$bookings || !count($bookings)) {
    throw new Exception('unknown_booking', QN_ERROR_UNKNOWN_OBJECT);
}

$booking = reset($bookings);

// adjust capacity according to assignments made in other groups of the same booking, if any
// #memo - this is necessary since a same UL can be assigned for distinct sojourns
foreach($booking['booking_lines_groups_ids'] as $group_index => $group) {
    foreach($group['sojourn_product_models_ids'] as $spm_index => $spm) {
        foreach($spm['rental_unit_assignments_ids'] as $assignment_index => $assignment) {
            // retrieve overlapping groups, if any
            $overlapping_groups_ids = [];
            $date_from = strtotime($group['date_from']);
            $date_to = strtotime($group['date_to']);
            foreach($booking['booking_lines_groups_ids'] as $other_group) {
                if($other_group['id'] == $group['id']) {
                    continue;
                }
                $group_date_from = strtotime($other_group['date_from']);
                $group_date_to = strtotime($other_group['date_to']);
                if(max($date_from, $group_date_from) < min($date_to, $group_date_to)) {
                    $overlapping_groups_ids[] = $other_group['id'];
                }
            }
            if(count($overlapping_groups_ids)) {
                $rental_unit_id = $assignment['rental_unit_id']['id'];
                // retrieve assignments from other groups
                $assignments = SojournProductModelRentalUnitAssignement::search([['rental_unit_id', '=', $rental_unit_id], ['booking_line_group_id', 'in', $overlapping_groups_ids]])->read(['qty'])->get(true);
                if($assignments && count($assignments)) {
                    $sum = array_reduce($assignments, function ($c, $a) {return $c + $a['qty'];}, 0);
                    $booking['booking_lines_groups_ids'][$group_index]['sojourn_product_models_ids'][$spm_index]['rental_unit_assignments_ids'][$assignment_index]['rental_unit_id']['capacity'] -= $sum;
                }
            }
        }
    }
}

$context->httpResponse()
        ->body($booking)
        ->send();
