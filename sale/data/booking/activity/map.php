<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use hr\employee\Employee;
use identity\Identity;
use identity\Partner;
use sale\booking\Booking;
use sale\booking\BookingActivity;
use sale\booking\BookingLineGroupAgeRangeAssignment;
use sale\booking\channelmanager\BookingLineGroup;
use sale\catalog\ProductModel;
use sale\customer\Customer;
use sale\provider\Provider;

[$params, $providers] = eQual::announce([
    'description'   => "Return an associative array mapping partners, date indexes and time slots with related activities (this controller is used for the employee planning).",
    'params'        => [
        'partners_ids' =>  [
            'description'   => 'Identifiers of the partners for which the activities are requested.',
            'type'          => 'array',
            'default'       => []
        ],
        'date_from' => [
            'description'   => 'Start of time-range for the lookup.',
            'type'          => 'date',
            'required'      => true
        ],
        'date_to' => [
            'description'   => 'End of time-range for the lookup.',
            'type'          => 'date',
            'required'      => true
        ]
    ],
    'access'        => [
        'groups'        => ['booking.default.user', 'booking.infra.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context                   $context
 * @var \equal\orm\ObjectManager             $orm
 * @var \equal\auth\AuthenticationManager    $auth
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

// #memo - processing of this controller might be heavy, so we make sure AC does not check permissions for each single consumption
$auth->su();

$activities_ids = $orm->search(BookingActivity::getType(), [
    ['activity_date', '>=', $params['date_from']],
    ['activity_date', '<=', $params['date_to']]
]);

$domain = [];
if(!empty($params['partners_ids'])) {
    $partners_ids = $orm->search(Partner::getType(), [
        ['id', 'in', $params['partners_ids']],
        ['relationship', 'in', ['employee', 'provider']]
    ]);

    $partners = $orm->read(Partner::getType(), $partners_ids, ['id', 'name', 'relationship']);

    $employees_ids = [];
    $providers_ids = [];
    foreach($partners as $partner) {
        if($partner['relationship'] === 'employee') {
            $employees_ids[] = $partner['id'];
        }
        else {
            $providers_ids[] = $partner['id'];
        }
    }

    if(!empty($employees_ids)) {
        $domain[] = [
            ['id', 'in', $activities_ids],
            ['employee_id', 'in', $params['employees_ids']]
        ];
    }
    if(!empty($providers_ids)) {
        $activities = $orm->read(BookingActivity::getType(), $activities_ids, ['id', 'providers_ids']);

        $providers_activities_ids = [];
        foreach($activities as $activity) {
            if(count(array_intersect($activity['providers_ids'], $providers_ids)) > 0) {
                $providers_activities_ids = $activity['id'];
            }
        }

        $domain[] = [
            ['id', 'in', $providers_activities_ids]
        ];
    }
}

if(!empty($domain)) {
    $activities_ids = $orm->search(BookingActivity::getType(), $domain);
}

// #memo - we use the ORM to prevent recursion and bypass permission check
$activities = $orm->read(BookingActivity::getType(), $activities_ids, [
        'id',
        'name',
        'has_staff_required',
        'employee_id',
        'providers_ids',
        'activity_date',
        'time_slot_id',
        'booking_id',
        'booking_line_group_id',
        'product_model_id',
        'activity_booking_line_id',
        'group_num',
        'counter',
        'counter_total'
    ]);

// read additional fields for the view
$map_bookings = [];
$map_groups = [];
$map_employees = [];
$map_providers = [];
$map_product_models = [];

// retrieve all foreign objects identifiers
foreach($activities as $id => $activity) {
    $map_bookings[$activity['booking_id']] = true;
    $map_groups[$activity['booking_line_group_id']] = true;
    $map_employees[$activity['employee_id']] = true;
    foreach($activity['providers_ids'] as $provider_id) {
        $map_providers[$provider_id] = true;
    }
    $map_product_models[$activity['product_model_id']] = true;
}

// load all foreign objects at once
$bookings = $orm->read(Booking::getType(), array_keys($map_bookings), ['id', 'name', 'description', 'status', 'payment_status', 'customer_id', 'date_from', 'date_to', 'nb_pers']);
$booking_groups = $orm->read(BookingLineGroup::getType(), array_keys($map_groups), ['id', 'nb_pers', 'age_range_assignments_ids', 'has_person_with_disability']);
$employees = $orm->read(Employee::getType(), array_keys($map_employees), ['id', 'name', 'relationship']);
$providers = $orm->read(Provider::getType(), array_keys($map_providers), ['id', 'name', 'relationship']);
$product_models = $orm->read(ProductModel::getType(), array_keys($map_product_models), ['id', 'name', 'description']);

$map_customers = [];
foreach($bookings as $id => $booking) {
    $map_customers[$booking['customer_id']] = true;
}
$customers = $orm->read(Customer::getType(), array_keys($map_customers), ['id', 'name', 'partner_identity_id']);

$map_identities = [];
foreach($customers as $id => $customer) {
    $map_identities[$customer['partner_identity_id']] = true;
}
$identities = $orm->read(Identity::getType(), array_keys($map_identities), ['id', 'name', 'address_city']);

$age_range_assignments_ids = [];
foreach($booking_groups as $group) {
    $age_range_assignments_ids = array_merge($age_range_assignments_ids, $group['age_range_assignments_ids']);
}
$age_range_assignments = $orm->read(BookingLineGroupAgeRangeAssignment::getType(), array_unique($age_range_assignments_ids), ['id', 'booking_line_group_id', 'age_from', 'age_to', 'qty']);

$result = [];
// build result: enrich and adapt consumptions
foreach($activities as $id => $activity) {
    $date_index = date('Y-m-d', $activity['activity_date']);
    $time_slot = [1 => 'AM', 3 => 'PM', 6 => 'EV'][$activity['time_slot_id']];

    $booking = isset($activity['booking_id'], $bookings[$activity['booking_id']]) ? $bookings[$activity['booking_id']]->toArray() : null;
    $booking['date_from'] = date('d/m/y', $booking['date_from']);
    $booking['date_to'] = date('d/m/y', $booking['date_to']);

    $booking_group = isset($activity['booking_line_group_id'], $booking_groups[$activity['booking_line_group_id']]) ? $booking_groups[$activity['booking_line_group_id']]->toArray() : null;

    $group_age_range_assignments = [];
    foreach($age_range_assignments as $age_range_assignment) {
        if($age_range_assignment['booking_line_group_id'] === $booking_group['id']) {
            $group_age_range_assignments[] = $age_range_assignment->toArray();
        }
    }

    $product_model = isset($activity['product_model_id'], $product_models[$activity['product_model_id']]) ? $product_models[$activity['product_model_id']]->toArray() : null;
    $customer = isset($booking['customer_id'], $customers[$booking['customer_id']]) ? $customers[$booking['customer_id']]->toArray() : null;
    $identity = isset($customer['partner_identity_id'], $identities[$customer['partner_identity_id']]) ? $identities[$customer['partner_identity_id']]->toArray() : null;

    $partner_id = null;
    if($activity['has_staff_required']) {
        // #memo - we use employee_id 0 for unassigned activities
        $partner_id = intval($activity['employee_id']);
        $employee = isset($activity['employee_id'], $employees[$activity['employee_id']]) ? $employees[$activity['employee_id']]->toArray() : null;

        $result[$partner_id][$date_index][$time_slot][] = array_merge($activity->toArray(), [
            'activity_date'             => date('c', $activity['activity_date']),
            'time_slot'                 => $time_slot,
            'booking_id'                => $booking,
            'booking_line_group_id'     => $booking_group,
            'product_model_id'          => $product_model,
            'customer_id'               => $customer,
            'partner_id'                => $employee,
            'partner_identity_id'       => $identity,
            'age_range_assignments_ids' => $group_age_range_assignments,
        ]);
    }
    else {
        foreach($activity['providers_ids'] as $provider_id) {
            $provider = isset($providers[$provider_id]) ? $providers[$provider_id]->toArray() : null;

            $result[$provider_id][$date_index][$time_slot][] = array_merge($activity->toArray(), [
                'activity_date'             => date('c', $activity['activity_date']),
                'time_slot'                 => $time_slot,
                'booking_id'                => $booking,
                'booking_line_group_id'     => $booking_group,
                'product_model_id'          => $product_model,
                'customer_id'               => $customer,
                'partner_id'                => $provider,
                'partner_identity_id'       => $identity,
                'age_range_assignments_ids' => $group_age_range_assignments
            ]);
        }
    }
}

$context->httpResponse()
        ->body($result)
        ->send();
