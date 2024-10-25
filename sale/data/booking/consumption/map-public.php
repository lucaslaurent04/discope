<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Consumption;


list($params, $providers) = announce([
    'description'   => "This controller is meant for the public calendar (allowing visitors to see Centers availability). We expect only centers with a single rental unit (group lodges).",
    'params'        => [
        'centers_ids' =>  [
            'description'   => 'Identifiers of the centers for which the consumptions are requested.',
            'type'          => 'array',
            'required'      => true
        ],
        'rental_unit_id' =>  [
            'description'   => 'Identifiers of the rental unit for which the consumptions are requested.',
            'type'          => 'integer',
            'default'      => 0
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'adapt']
]);


list($context, $orm, $dap) = [$providers['context'], $providers['orm'], $providers['adapt']];

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

$date_from = time();
$date_to = strtotime('+2 years');

// get associative array mapping rental units and dates with consumptions
$result = Consumption::getExistingConsumptions(
        $orm,
        $params['centers_ids'],
        $date_from,
        $date_to
    );


$output = [];
// enrich and adapt result
foreach($result as $rental_unit_id => $dates) {
    foreach($dates as $date_index => $consumptions) {
        // we deal with rental_unit as a binary status (free/busy), so we only consider the first consumption
        $consumption = reset($consumptions);
        if($params['rental_unit_id'] > 0) {
            if($consumption['rental_unit_id'] != $params['rental_unit_id']) {
                continue;
            }
        }
        $output[] = [
            'date_from'         => $adaptOut($consumption['date_from'], 'date'),
            'date_to'           => $adaptOut($consumption['date_to'], 'date'),
            'type_consumption'  => $adaptOut($consumption['type'], 'string'),
            'rental_unit_id'    => $consumption['rental_unit_id']
        ];
    }
}
$context->httpResponse()
        ->body($output)
        ->send();
