<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use sale\booking\Booking;
use sale\booking\BookingActivity;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingLineGroupAgeRangeAssignment;
use sale\booking\Contact;
use sale\booking\MealPreference;
use sale\booking\SojournProductModel;
use sale\catalog\Product;
use sale\customer\Customer;
use sale\provider\Provider;

[$params, $providers] = eQual::announce([
    'description'   => "Clone a specific booking.",
    'params'        => [

        'id' => [
            'type'              => 'integer',
            'description'       => "Identifier of the booking to clone.",
            'required'          => true
        ],

        'customer_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\Customer',
            'description'       => "Customer of the cloned booking.",
            'domain'            => ['relationship', '=', 'customer'],
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

$fields = [
    'center_id',
    'center_office_id',
    'organisation_id',
    'description',
    'type_id',
    'date_from',
    'date_to',
    'customer_nature_id',
    'customer_rate_class_id',
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
            'free_qty',
            'age_range_id',
            'age_from',
            'age_to'
        ],
        'sojourn_product_models_ids' => [
            'product_model_id'
        ],
        'booking_lines_ids' => [
            'description',
            'product_id',
            'order',
            'qty',
            'has_own_qty',
            'has_own_duration',
            'own_duration',
            'payment_mode',
            'is_contractual',
            'free_qty',
            'qty_vars',
            'is_autosale',
            'booking_activity_id',
            'service_date',
            'time_slot_id',
            'meal_location',
            'has_manual_unit_price',
            'unit_price',
            'has_manual_vat_rate',
            'vat_rate'
        ],
        'booking_activities_ids' => [
            'description',
            'activity_booking_line_id',
            'is_virtual',
            'supplies_booking_lines_ids',
            'transports_booking_lines_ids',
            'product_id',
            'product_model_id',
            'qty',
            'counter',
            'counter_total',
            'activity_date',
            'time_slot_id',
            'schedule_from',
            'schedule_to'
        ]
    ]
];

$booking = Booking::id($params['id'])
    ->read($fields)
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

$map_products = [];
foreach($booking['booking_lines_groups_ids'] as $group) {
    foreach($group['booking_lines_ids'] as $line) {
        $map_products[$line['product_id']] = true;
    }
}
$products = Product::ids(array_keys($map_products))
    ->read(['id'])
    ->get(true);
$not_deleted_product_id = array_map(fn($product) => $product['id'], $products);


if(count($products) !== count($not_deleted_product_id)) {
    trigger_error("ORM::some booking lines cannot be cloned because some products are deleted.", EQ_REPORT_WARNING);
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

$diff_date_from = $params['date_from'] - $booking['date_from'];

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

foreach($fields as $booking_field) {
    // ignore relationship fields
    // ignore dates/customer data because they depend on the given params
    if(is_string($booking_field) && !in_array($booking_field, ['date_from', 'date_to', 'customer_nature_id', 'customer_rate_class_id'])) {
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
    foreach($fields['contacts_ids'] as $contact_field) {
        $contact_data[$contact_field] = $contact[$contact_field];
    }

    Contact::create($contact_data);
}

$providers = Provider::search()
    ->read(['product_models_ids'])
    ->get();

$cloned_groups_ids = [];
foreach($booking['booking_lines_groups_ids'] as $group) {
    if($group['is_extra']) {
        continue;
    }

    $diff_group_date_from = $group['date_from'] - $booking['date_from'];
    $diff_group_date_to = $booking['date_to'] - $group['date_to'];

    $group_data = [
        'booking_id'    => $cloned_booking['id'],
        'date_from'     => $params['date_from'] + $diff_group_date_from,
        'date_to'       => $date_to - $diff_group_date_to
    ];
    foreach($fields['booking_lines_groups_ids'] as $group_field) {
        // ignore booking_lines_ids, age_range_assignments_ids and sojourn_product_models_ids, they're handled below
        // ignore date_from and date_to, must be shifted
        if(is_string($group_field) && !in_array($group_field, ['date_from', 'date_to', 'is_locked'])) {
            $group_data[$group_field] = $group[$group_field];
        }
    }

    $cloned_group = BookingLineGroup::create($group_data)
        ->read(['id'])
        ->first();

    $cloned_groups_ids[] = $cloned_group['id'];

    // Remove groups and lines that were automatically created
    BookingLineGroup::search([['booking_id', '=', $cloned_booking['id']], ['id', 'not in', $cloned_groups_ids]])->delete(true);
    BookingLine::search([['booking_id', '=', $cloned_booking['id']], ['booking_line_group_id', 'not in', $cloned_groups_ids]])->delete(true);

    // Remove age range assignment that was automatically created
    BookingLineGroupAgeRangeAssignment::search(['booking_line_group_id', '=', $cloned_group['id']])->delete(true);

    foreach($group['age_range_assignments_ids'] as $age_range_assign) {
        $age_range_data = [
            'booking_id'            => $cloned_booking['id'],
            'booking_line_group_id' => $cloned_group['id']
        ];
        foreach($fields['booking_lines_groups_ids']['age_range_assignments_ids'] as $age_range_field) {
            $age_range_data[$age_range_field] = $age_range_assign[$age_range_field];
        }

        BookingLineGroupAgeRangeAssignment::create($age_range_data);
    }

    foreach($group['sojourn_product_models_ids'] as $sojourn_pm) {
        $sojourn_pm_data = [
            'booking_id'            => $cloned_booking['id'],
            'booking_line_group_id' => $cloned_group['id']
        ];
        foreach($fields['booking_lines_groups_ids']['sojourn_product_models_ids'] as $sojourn_pm_field) {
            $sojourn_pm_data[$sojourn_pm_field] = $sojourn_pm[$sojourn_pm_field];
        }

        SojournProductModel::create($sojourn_pm_data);
    }

    $map_activities_lines = [];
    $map_old_to_new_lines = [];
    foreach($group['booking_lines_ids'] as $line) {
        if(!in_array($line['product_id'], $not_deleted_product_id)) {
            continue;
        }

        $line_data = [
            'booking_id'            => $cloned_booking['id'],
            'booking_line_group_id' => $cloned_group['id']
        ];
        if(!is_null($line['service_date'])) {
            $line_data['service_date'] = $line['service_date'] + $diff_date_from;
        }
        foreach($fields['booking_lines_groups_ids']['booking_lines_ids'] as $line_field) {
            if(!in_array($line_field, ['booking_activity_id', 'service_date', 'unit_price', 'vat_rate'])) {
                $line_data[$line_field] = $line[$line_field];
            }
        }

        // ignore lines if product id already exists
        $cloned_line = BookingLine::create($line_data)
            ->read(['product_id'])
            ->first();

        $price_data = [];
        if($line['has_manual_unit_price']) {
            $price_data['unit_price'] = $line['unit_price'];
        }
        if($line['has_manual_vat_rate']) {
            $price_data['vat_rate'] = $line['vat_rate'];
        }
        if(!empty($price_data)) {
            BookingLine::id($cloned_line['id'])->update($price_data);
        }

        $prices = BookingLine::searchPriceId($orm, $cloned_line['id'], $cloned_line['product_id']);
        if(!isset($prices[$cloned_line['id']])) {
            $prices = BookingLine::searchPriceIdUnpublished($orm, $cloned_line['id'], $cloned_line['product_id']);
        }
        if(isset($prices[$cloned_line['id']])) {
            BookingLine::id($cloned_line['id'])->update(['price_id' => $prices[$cloned_line['id']]]);
        }

        if(!is_null($line['booking_activity_id'])) {
            $map_activities_lines[$line['booking_activity_id']][] = $cloned_line['id'];
        }

        $map_old_to_new_lines[$line['id']] = $cloned_line['id'];
    }

    foreach($group['booking_activities_ids'] as $activity) {
        if(!in_array($activity['product_id'], $not_deleted_product_id)) {
            continue;
        }

        $supplies_ids = array_map(fn($supply_id) => $map_old_to_new_lines[$supply_id], $activity['supplies_booking_lines_ids']);
        $transports_ids = array_map(fn($transport_id) => $map_old_to_new_lines[$transport_id], $activity['transports_booking_lines_ids']);

        $providers_ids = [];
        foreach($providers as $provider) {
            if(in_array($activity['product_model_id'], $provider['product_models_ids'])) {
                $providers_ids[] = $provider['id'];
            }
        }
        // only set providers_ids if only one is available
        if(count($providers_ids) !== 1) {
            $providers_ids = [];
        }

        $activity_data = [
            'activity_booking_line_id'      => $map_old_to_new_lines[$activity['activity_booking_line_id']],
            'activity_date'                 => $activity['activity_date'] + $diff_date_from,
            'supplies_booking_lines_ids'    => $supplies_ids,
            'transports_booking_lines_ids'  => $transports_ids,
            'providers_ids'                 => $providers_ids
        ];

        foreach($fields['booking_activities_ids'] as $activity_field) {
            if(!in_array($activity_field, ['activity_booking_line_id', 'activity_date', 'supplies_booking_lines_ids', 'transports_booking_lines_ids', 'product_model_id'])) {
                $activity_data[$activity_field] = $activity[$activity_field];
            }
        }

        $cloned_activity = BookingActivity::create($activity_data)
            ->read(['id'])
            ->first();

        $cloned_lines_ids = $map_activities_lines[$activity['id']];
        if(!empty($cloned_lines_ids)) {
            BookingLine::search(['id', 'in', $cloned_lines_ids])
                ->update(['booking_activity_id' => $cloned_activity['id']]);
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

    if($group['is_locked']) {
        BookingLineGroup::id($cloned_group['id'])->update(['is_locked' => true]);
    }
}

$orm->enableEvents($event_mask);

$context->httpResponse()
        ->status(201)
        ->send();
