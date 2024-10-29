<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Consumption;

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

// #memo - this is a workaround to handle the change of logic between 'adapt' as DataAdapter (equal1.0) or DataAdapterProvider (equal2.0)
if(is_a($dap, 'equal\data\DataAdapter')) {
    $adapter = $dap;
}
else {
    /** @var \equal\data\adapt\DataAdapter */
    $adapter = $dap->get('json');
}
$adaptIn = function($value, $type) use (&$adapter) {
    if(is_a($adapter, 'equal\data\DataAdapter')) {
        return $adapter->adapt($value, $type);
    }
    return $adapter->adaptIn($value, $type);
};
$adaptOut = function($value, $type) use (&$adapter) {
    if(is_a($adapter, 'equal\data\DataAdapter')) {
        return $adapter->adapt($value, $type, 'txt', 'php');
    }
    return $adapter->adaptOut($value, $type);
};


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

// read additional fields for the view
$consumptions = Consumption::ids($consumptions_ids)
    ->read([
        'date',
        'schedule_from',
        'schedule_to',
        'is_rental_unit',
        'qty',
        'type',
        'customer_id'       => ['id', 'name'],
        'rental_unit_id'    => ['id', 'name'],
        'booking_id'        => ['id', 'name', 'status', 'description', 'payment_status'],
        'repairing_id'      => ['id', 'name', 'description']
    ])
    ->adapt('json')
    ->get();

// enrich and adapt result
foreach($result as $rental_unit_id => $dates) {
    foreach($dates as $date_index => $consumptions_list) {
        foreach($consumptions_list as $c_index => $consumption) {
            // retrieve consumption's data and adapt dates and times
            $odata = $consumptions[$consumption['id']];
            if($consumption instanceof \equal\orm\Model){
                $consumption = $consumption->toArray();
            }
            $result[$rental_unit_id][$date_index][$c_index] = array_merge((array) $consumption, $odata, [
                'date_from'     => $adaptOut($consumption['date_from'], 'date'),
                'date_to'       => $adaptOut($consumption['date_to'], 'date'),
                'schedule_from' => $adaptOut($consumption['schedule_from'], 'time'),
                'schedule_to'   => $adaptOut($consumption['schedule_to'], 'time')
            ]);
        }
    }
}

$context->httpResponse()
        ->body($result)
        ->send();
