<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use hr\employee\Employee;
use sale\booking\Booking;
use sale\booking\BookingActivity;
use sale\catalog\ProductModel;
use sale\customer\Customer;

[$params, $providers] = eQual::announce([
    'description'   => "Retrieve the consumptions assigned to specified employees and return an associative array mapping employees and ate indexes with related activities (this controller is used for the planning).",
    'params'        => [
        'employees_ids' =>  [
            'description'   => 'Identifiers of the employees for which the activities are requested.',
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
    'access' => [
        'groups'            => ['booking.default.user', 'booking.infra.user']
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


$domain = [
        ['activity_date', '>=', $params['date_from']],
        ['activity_date', '<=', $params['date_to']]
];

if(!empty($params['employees_ids'])) {
    $domain[] = ['employee_id', 'in', $params['employees_ids']];
}

$activities_ids = $orm->search(BookingActivity::getType(), $domain);

// #memo - we use the ORM to prevent recursion and bypass permission check
$activities = $orm->read(BookingActivity::getType(), $activities_ids, [
        'id',
        'name',
        'employee_id',
        'activity_date',
        'time_slot_id',
        'booking_id',
        'product_model_id',
        'activity_booking_line_id',
        'group_num'
    ]);

// read additional fields for the view
$map_bookings = [];
$map_employees = [];
$map_product_models = [];

// retrieve all foreign objects identifiers
foreach($activities as $id => $activity) {
    $map_bookings[$activity['booking_id']] = true;
    $map_employees[$activity['employee_id']] = true;
    $map_product_models[$activity['product_model_id']] = true;
}

// load all foreign objects at once
$product_models = $orm->read(ProductModel::getType(), array_keys($map_product_models), ['id', 'name', 'description']);
$bookings = $orm->read(Booking::getType(), array_keys($map_bookings), ['id', 'name', 'description', 'status', 'payment_status', 'customer_id']);
$employees = $orm->read(Employee::getType(), array_keys($map_employees), ['id', 'name']);

$map_customers = [];
foreach($bookings as $id => $booking) {
    $map_customers[$booking['customer_id']] = true;
}

$customers = $orm->read(Customer::getType(), array_keys($map_customers), ['id', 'name']);

$result = [];
// build result: enrich and adapt consumptions
foreach($activities as $id => $activity) {

    // #memo - we use employee_id 0 for unassigned activities
    $employee_id = intval($activity['employee_id']);
    $date_index = date('Y-m-d', $activity['activity_date']);
    $time_slot = [1 => 'AM', 3 => 'PM', 6 => 'EV'][$activity['time_slot_id']];

    $booking = $activity['booking_id'] ? $bookings[$activity['booking_id']]->toArray() : null;
    $employee = $activity['employee_id'] ? $employees[$activity['employee_id']]->toArray() : 0;
    $product_model = $activity['product_model_id'] ? $product_models[$activity['product_model_id']]->toArray() : null;
    $customer = isset($booking['customer_id'], $customers[$booking['customer_id']]) ? $customers[$booking['customer_id']]->toArray() : null;

    $result[$employee_id][$date_index][$time_slot][] = array_merge($activity->toArray(), [
            'booking_id'        => $booking,
            'employee_id'       => $employee,
            'product_model_id'  => $product_model,
            'customer_id'       => $customer,
            'activity_date'     => date('c', $activity['activity_date']),
            'time_slot'         => $time_slot
        ]);
}

$context->httpResponse()
        ->body($result)
        ->send();
