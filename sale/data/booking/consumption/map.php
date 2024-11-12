<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use realestate\RentalUnit;
use sale\booking\Booking;
use sale\booking\Consumption;
use sale\booking\Repairing;
use sale\customer\Customer;

list($params, $providers) = eQual::announce([
    'description'   => "Retrieve the consumptions attached to rental units of specified centers and return an associative array mapping rental units and ate indexes with related consumptions (this controller is used for the planning).",
    'params'        => [
        'centers_ids' =>  [
            'description'   => 'Identifiers of the centers for which the consumptions are requested.',
            'type'          => 'array',
            'required'      => true
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
    'providers'     => ['context', 'orm', 'auth', 'adapt']
]);


list($context, $orm, $auth, $dap) = [$providers['context'], $providers['orm'], $providers['auth'], $providers['adapt']];

// #memo - processing of this controller might be heavy, so we make sure AC does not check permissions for each single consumption
$auth->su();

// get associative array mapping rental units and dates with consumptions
$result = Consumption::getExistingConsumptions(
    $orm,
    $params['centers_ids'],
    $params['date_from'],
    $params['date_to']
);

$consumptions_ids = [];
foreach($result as $rental_unit_id => $dates) {
    foreach($dates as $date_index => $consumptions) {
        // #memo - there might be several consumptions for a same rental_unit within a same day
        foreach($consumptions as $consumption) {
            $consumptions_ids[] = $consumption['id'];
        }
    }
}

// #memo - we use the ORM to prevent recursion and bypass permission check
$consumptions = $orm->read(Consumption::getType(), $consumptions_ids, [
        'date',
        'schedule_from',
        'schedule_to',
        'is_rental_unit',
        'qty',
        'type',
        'customer_id',
        'rental_unit_id',
        'booking_id',
        'repairing_id'
    ]);

// read additional fields for the view
$map_bookings = [];
$map_repairings = [];
$map_customers = [];
$map_rental_units = [];

// retrieve all foreign objects identifiers
foreach($consumptions as $consumptions_ids => $consumption) {
    $map_bookings[$consumption['booking_id']] = true;
    $map_repairings[$consumption['repairing_id']] = true;
    $map_customers[$consumption['customer_id']] = true;
    $map_rental_units[$consumption['rental_unit_id']] = true;
}

// load all foreign objects at once
$customers = $orm->read(Customer::getType(), array_keys($map_customers), ['id', 'name']);
$repairings = $orm->read(Repairing::getType(), array_keys($map_repairings), ['id', 'name', 'description']);
$bookings = $orm->read(Booking::getType(), array_keys($map_bookings), ['id', 'name', 'description', 'status', 'payment_status']);
$rental_units = $orm->read(RentalUnit::getType(), array_keys($map_rental_units), ['id', 'name']);

// build result: enrich and adapt consumptions
foreach($result as $rental_unit_id => $dates) {
    foreach($dates as $date_index => $consumptions_list) {
        foreach($consumptions_list as $c_index => $item) {
            // retrieve consumption's data and adapt dates and times
            if($item instanceof \equal\orm\Model){
                $result[$rental_unit_id][$date_index][$c_index] = $item->toArray();
            }
            $consumption = $consumptions[$item['id']];

            foreach($consumption as $key => $value) {
                if(strpos($key, '.') > 0) {
                    $parts = explode('.', $key);
                    $key1 = $parts[0];
                    $key2 = $parts[1];
                    if(!isset($result[$rental_unit_id][$date_index][$c_index][$key1])) {
                        $result[$rental_unit_id][$date_index][$c_index][$key1] = [];
                    }
                    else {
                        $result[$rental_unit_id][$date_index][$c_index][$key1] = (array) $result[$rental_unit_id][$date_index][$c_index][$key1];
                    }
                    $result[$rental_unit_id][$date_index][$c_index][$key1][$key2] = $value;
                }
                else {
                    $result[$rental_unit_id][$date_index][$c_index][$key] = $value;
                }
            }
            $result[$rental_unit_id][$date_index][$c_index]['booking_id'] = ($consumption['booking_id']) ? $bookings[$consumption['booking_id']]->toArray() : null;
            $result[$rental_unit_id][$date_index][$c_index]['customer_id'] = ($consumption['customer_id']) ? $customers[$consumption['customer_id']]->toArray() : null;
            $result[$rental_unit_id][$date_index][$c_index]['rental_unit_id'] = ($consumption['rental_unit_id']) ? $rental_units[$consumption['rental_unit_id']]->toArray() : null;
            $result[$rental_unit_id][$date_index][$c_index]['repairing_id'] = ($consumption['repairing_id']) ? $repairings[$consumption['repairing_id']]->toArray() : null;

            $result[$rental_unit_id][$date_index][$c_index]['date'] = date('c', $consumption['date']);
            $result[$rental_unit_id][$date_index][$c_index]['date_from'] = date('c', $item['date_from']);
            $result[$rental_unit_id][$date_index][$c_index]['date_to'] = date('c', $item['date_to']);
            $result[$rental_unit_id][$date_index][$c_index]['schedule_from'] = date('H:i:s', $item['schedule_from']);
            $result[$rental_unit_id][$date_index][$c_index]['schedule_to'] = ($item['schedule_to'] == 86400) ? '24:00:00' : date('H:i:s', $item['schedule_to']);
        }
    }
}

$context->httpResponse()
        ->body($result)
        ->send();
