<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingLineGroupAgeRangeAssignment;
use sale\booking\Contact;
use sale\booking\MealPreference;
use sale\booking\SojournProductModel;
use sale\customer\Customer;

[$params, $providers] = eQual::announce([
    'description'   => "Clone a specific booking.",
    'params'        => [

        'id' => [
            'type'          => 'integer',
            'description'   => "Identifier of the booking to clone.",
            'required'      => true
        ],

        'customer_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\Customer',
            'description'       => "Customer of the cloned booking.",
            'required'          => true
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "Start date of the time interval.",
            'required'          => true
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

$fields_to_clone = [
    'center_id',
    'center_office_id',
    'description', // with mention that it's cloned
    'type_id',
    'customer_nature_id',
    // 'payment_plan_id',   TODO: ????
    'contacts_ids' => [
        'owner_identity_id',
        'partner_identity_id',
        'relationship',
        'type',
        'origin',
        'is_direct_contact'
    ],
    'booking_lines_groups_ids' => [
        'name',
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
        'is_locked',
        'has_pack',
        'pack_id',
        'rate_class_id',
        // 'booking_activities_ids', TODO: handle activities
        'activity_group_num',
        'has_person_with_disability',
        'person_disability_description',
        'bed_linens',
        'make_beds',
        'qty',
        'meal_preferences_ids' => [
            'age_range_id',
            'type',
            'pref',
            'qty'
        ],
        'age_range_assignments_ids' => [
            'qty',
            'age_range_id',
            'age_from',
            'age_to'
        ],
        'sojourn_product_models_ids' => [
            'product_model_id'
        ],
        'booking_lines_ids' => [
            'description', // with mention that it's cloned
            'product_id',
            // 'price_adapters_ids',        TODO: ????
            // 'manual_discounts_ids',      TODO: ????
            // 'auto_discounts_ids',        TODO: ????
            'qty',
            'has_own_qty',
            'has_own_duration',
            'own_duration',
            'payment_mode',
            'is_contractual',
            'has_manual_unit_price',        // TODO: force unit price
            'has_manual_vat_rate',          // TODO: force vat rate
            'qty_vars',
            'is_autosale',
            // 'booking_activity_id',       TODO: ?????
            // 'service_date',              TODO: adjust depending on dates
            // 'time_slot_id',              TODO: add when service_date handled
            'meal_location',
            // 'activity_rental_unit_id',   TODO: ?????
        ]
    ]
];

$booking = Booking::id($params['id'])
    ->read(array_merge($fields_to_clone, ['date_from', 'date_to', 'customer_id']))
    ->first();

if(is_null($booking)) {
    throw new Exception("unknown_booking", EQ_ERROR_UNKNOWN);
}

if($params['customer_id'] <= 0) {
    throw new Exception("invalid_customer_id", EQ_ERROR_INVALID_PARAM);
}

$center = Center::id($booking['center_id'])
    ->read(['name'])
    ->first();

if(is_null($center)) {
    throw new Exception("unknown_center", EQ_ERROR_UNKNOWN);
}

$target_customer_id = $params['customer_id'] ?? $booking['customer_id'];
$same_customer = $target_customer_id === $booking['customer_id'];

$customer = Customer::id($target_customer_id)
    ->read(['partner_identity_id', 'customer_nature_id', 'rate_class_id'])
    ->first();

if(is_null($customer)) {
    throw new Exception("unknown_customer", EQ_ERROR_UNKNOWN);
}

if($params['date_from'] < time()) {
    throw new Exception("invalid_date_from", EQ_ERROR_INVALID_PARAM);
}

$date_to = $params['date_from'] + ($booking['date_to'] - $booking['date_from']);

$data = [
    'status'                    => 'quote',
    'date_from'                 => $params['date_from'],
    'date_to'                   => $date_to,
    'customer_id'               => $customer['id'],
    'customer_identity_id'      => $customer['partner_identity_id'],
    'customer_nature_id'        => $customer['customer_nature_id'] ?? $booking['customer_nature_id'],
    'customer_rate_class_id'    => $customer['rate_class_id'] ?? $booking['customer_rate_class_id']
];

foreach($fields_to_clone as $booking_field) {
    // ignore booking_lines_groups_ids, it's handled below
    // ignore customer_nature_id and customer_rate_class_id, they're handled above
    if(is_string($booking_field) && !in_array($booking_field, ['customer_nature_id', 'customer_rate_class_id'])) {
        $data[$booking_field] = $booking[$booking_field];
    }
}

$event_mask = $orm->disableEvents();

$cloned_booking = Booking::create($data)
    ->read(['id'])
    ->first();

foreach($booking['contacts_ids'] as $contact) {
    $contact_data = [
        'booking_id' => $cloned_booking['id']
    ];
    foreach($fields_to_clone['contacts_ids'] as $contact_field) {
        $contact_data[$contact_field] = $contact[$contact_field];
    }

    Contact::create($contact_data);
}

foreach($booking['booking_lines_groups_ids'] as $group) {
    $diff_date_from = $group['date_from'] - $booking['date_from'];
    $diff_date_to = $booking['date_to'] - $group['date_to'];

    $group_data = [
        'booking_id'    => $cloned_booking['id'],
        'date_from'     => $params['date_from'] + $diff_date_from,
        'date_to'       => $date_to - $diff_date_to
    ];
    foreach($fields_to_clone['booking_lines_groups_ids'] as $group_field) {
        // ignore booking_lines_ids, age_range_assignments_ids and sojourn_product_models_ids, they're handled below
        // ignore date_from and date_to, must be shifted
        if(is_string($group_field) && !in_array($group_field, ['date_from', 'date_to', 'pack_id', 'has_pack', 'is_locked'])) {
            $group_data[$group_field] = $group[$group_field];
        }
    }

    $cloned_group = BookingLineGroup::create($group_data)
        ->read(['id'])
        ->first();

    foreach($group['age_range_assignments_ids'] as $age_range_assign) {
        $age_range_data = [];
        foreach($fields_to_clone['booking_lines_groups_ids']['age_range_assignments_ids'] as $age_range_field) {
            $age_range_data[$age_range_field] = $age_range_assign[$age_range_field];
        }

        $existing_age_range = BookingLineGroupAgeRangeAssignment::search([
            ['booking_line_group_id', '=', $cloned_group['id']],
            ['age_range_id', '=', $age_range_assign['age_range_id']]
        ])
            ->read(['id'])
            ->first();

        // update existing age range
        if(!is_null($existing_age_range)) {
            unset($age_range_data['age_range_id']);

            BookingLineGroupAgeRangeAssignment::id($existing_age_range['id'])->update($age_range_data);
        }
        // create a new age range
        else {
            BookingLineGroupAgeRangeAssignment::create(array_merge(
                $age_range_data,
                ['booking_id' => $cloned_booking['id'], 'booking_line_group_id' => $cloned_group['id']]
            ));
        }
    }

    foreach($group['sojourn_product_models_ids'] as $sojourn_pm) {
        $sojourn_pm_data = [
            'booking_id'            => $cloned_booking['id'],
            'booking_line_group_id' => $cloned_group['id']
        ];
        foreach($fields_to_clone['booking_lines_groups_ids']['sojourn_product_models_ids'] as $sojourn_pm_field) {
            $sojourn_pm_data[$sojourn_pm_field] = $sojourn_pm[$sojourn_pm_field];
        }

        SojournProductModel::create($sojourn_pm_data);
    }

    $pack_lines = [];
    if($group['has_pack'] && !is_null($group['pack_id'])) {
        \eQual::run('do', 'sale_booking_update-sojourn-pack-set', [
            'id'        => $cloned_group['id'],
            'pack_id'   => $group['pack_id']
        ]);

        $pack_lines = BookingLine::search(['booking_line_group_id', '=', $cloned_group['id']])
            ->read(['product_id'])
            ->get(true);
    }

    foreach($group['booking_lines_ids'] as $line) {
        foreach($pack_lines as $pack_line) {
            if($pack_line['product_id'] === $line['product_id']) {
                continue 2;
            }
        }

        $line_data = [
            'booking_id'            => $cloned_booking['id'],
            'booking_line_group_id' => $cloned_group['id']
        ];
        foreach($fields_to_clone['booking_lines_groups_ids']['booking_lines_ids'] as $line_field) {
            $line_data[$line_field] = $line[$line_field];
        }

        // ignore lines if product id already exists
        $cloned_line = BookingLine::create($line_data)
            ->read(['product_id'])
            ->first();

        $prices = BookingLine::searchPriceId($orm, $cloned_line['id'], $cloned_line['product_id']);
        if(!isset($prices[$cloned_line['id']])) {
            $prices = BookingLine::searchPriceIdUnpublished($orm, $cloned_line['id'], $cloned_line['product_id']);
        }
        if(isset($prices[$cloned_line['id']])) {
            BookingLine::id($cloned_line['id'])->update(['price_id' => $prices[$cloned_line['id']]]);
        }
    }

    BookingLineGroup::refreshMeals($orm, $cloned_group['id']);

    MealPreference::search(['booking_line_group_id', '=', $cloned_group['id']])->delete(true);
    foreach($group['meal_preferences_ids'] as $meal_preference) {
        MealPreference::create([
            'booking_line_group_id' => $cloned_group['id'],
            'age_range_id'          => $meal_preference['age_range_id'],
            'type'                  => $meal_preference['type'],
            'pref'                  => $meal_preference['pref'],
            'qty'                   => $meal_preference['qty']
        ]);
    }

    // TODO: handle activities              sale.features.booking.activity

    if($group['is_locked']) {
        BookingLineGroup::id($cloned_group['id'])->update(['is_locked' => true]);
    }
}

$orm->enableEvents($event_mask);

$context->httpResponse()
        ->status(201)
        ->send();
