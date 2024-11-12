<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\User;
use sale\booking\ConsumptionMeter;
use sale\booking\ConsumptionMeterReading;


list($params, $providers) = announce([
    'description'   => 'Provides data for the mandatory UREBA statistics for the center.',
    'params'        => [
        'all_centers' => [
            'type'              => 'boolean',
            'default'           => false,
            'description'       => "Mark all the centers of the consumption meter."
        ],

        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => "Center for the consumption meter."
        ],

        'type_meter' => [
                'type'              => 'string',
                'selection'         => [
                    'all',
                    'water',
                    'gas',
                    'electricity',
                    'gas tank',
                    'oil tank'
                ],
                'default'           => 'all',
                'description'       => 'The type of meter consumption.'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit (defaults to first day of the current  month ).",
            'default'           => mktime(0, 0, 0, date("m"), 1)
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval upper limit (defaults to last day of the current month).',
            'default'           => mktime(23, 59, 59, date("m") + 1, 0)
        ],

        /* parameters used as properties of virtual entity */
        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center for the consumption meter.'
        ],
        'meter' => [
            'type'              => 'string',
            'description'       => 'Name of the consumption meter.'
        ],
        'meter_type' => [
            'type'              => 'string',
            'description'       => 'The type of consumption meter.'
        ],
        'meter_unit' => [
            'type'              => 'string',
            'description'       => 'The unit of the consumption meter '
        ],
        'index_initial' => [
            'type'              => 'integer',
            'description'       => 'The index initial of the consumption for the meter.'
        ],
        'index_final' => [
            'type'              => 'integer',
            'description'       => 'The index final of the consumption for the meter.'
        ],
        'index_consumption' => [
            'type'              => 'integer',
            'description'       => 'The consumption for the meter is between the final index and the initial index.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'adapt' ,'auth' ]
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\data\DataAdapter     $adapter
 * @var \equal\auth\AuthenticationManager $auth
 */
list($context, $orm, $adapter, $auth) = [ $providers['context'], $providers['orm'], $providers['adapt'], $providers['auth'] ];

$domain = [];

if($params['center_id'] || $params['all_centers']) {
    $domain = [
        ['date_reading', '>=', $params['date_from'] ],
        ['date_reading', '<=', $params['date_to'] ]
    ];
}

$center_ids = [];
if($params['all_centers']) {
    $user_id = $auth->userId();
    if($user_id <= 0) {
        throw new Exception('user_unknown', QN_ERROR_NOT_ALLOWED);
    }
    $user = User::id($user_id)->read(['centers_ids'])->first();
    if(!$user) {
        throw new Exception('unexpected_error', QN_ERROR_INVALID_USER);
    }
    $center_ids = $user['centers_ids'];
    $domain[] = ['center_id', 'in', $user['centers_ids'] ];

}
elseif($params['center_id'] && $params['center_id'] > 0) {
    $center_ids = $params['center_id'];
    $domain[] = [ 'center_id', '=', $params['center_id'] ];
}

if($params['type_meter'] != 'all') {
    $consumption_meter_ids = ConsumptionMeter::search([
            ['type_meter' , '=' , $params['type_meter']],
            ['center_id' , 'in', $center_ids]
        ])->ids();
    $domain[] = ['consumption_meter_id', 'in', $consumption_meter_ids];
}

$consumption_meter_readings = [];
if($domain){
    $consumption_meter_readings = ConsumptionMeterReading::search($domain, ['sort'  => ['date_reading' => 'asc']])
        ->read([
            'id',
            'booking_inspection_id'     => ['id', 'type_inspection'],
            'consumption_meter_id'      => ['id','name','meter_unit', 'type_meter'],
            'center_id'                 => ['id', 'name'],
            'index_value',
            'date_reading'
        ])
        ->get(true);
}

$consumption_meter_reading_map = [];
foreach($consumption_meter_readings as $id => $consumption_meter_reading) {

    $center_id =  $consumption_meter_reading['center_id']['id'];
    $consumption_meter_id = $consumption_meter_reading['consumption_meter_id']['id'];

    if (!isset($consumption_meter_reading_map[$center_id])) {
        $consumption_meter_reading_map[$center_id] = [];
    }

    if (!isset($consumption_meter_reading_map[$center_id][$consumption_meter_id])) {
        $consumption_meter_reading_map[$center_id][$consumption_meter_id] = [];
    }

    $consumption_meter_reading_map[$center_id][$consumption_meter_id][] = $consumption_meter_reading;
}

$map_meter_reading_type = [];
foreach ($consumption_meter_reading_map as $center_id => $meter_map) {
    foreach ($meter_map as $meter_id => $readings) {
        $first_checking = null;
        $last_checkout = null;

        foreach ($readings as $reading) {
            $date_reading = $reading['date_reading'];

            if ($reading['booking_inspection_id']['type_inspection'] == 'checkedin') {
                if ($first_checking == null || $date_reading < $first_checking['date_reading']) {
                    $first_checking = $reading;
                }
            }

            if ($reading['booking_inspection_id']['type_inspection'] == 'checkedout') {
                if ($last_checkout == null || $date_reading > $last_checkout['date_reading']) {
                    $last_checkout = $reading;
                }
            }
        }
        $first_checking_value = $first_checking ? $first_checking['index_value'] : null;
        $last_checkout_value = $last_checkout ? $last_checkout['index_value'] : null;

        $map_meter_reading_type[$center_id][$meter_id] = [
            'center'                   => $reading['center_id']['name'],
            'meter'                    => $reading['consumption_meter_id']['name'],
            'meter_type'               => $reading['consumption_meter_id']['type_meter'],
            'meter_unit'               => $reading['consumption_meter_id']['meter_unit'],
            'index_initial'            => $first_checking_value,
            'index_final'              => $last_checkout_value,
            'index_consumption'        => $last_checkout_value - $first_checking_value
        ];

    }
}

$options_meter_type = [
    "water" => "Eau",
    "gas" => "Gaz",
    "electricity" => "Électricité",
    "gas tank" => "Citerne gaz",
    "oil tank" => "Citerne mazout"
];

$result = [];
foreach ($map_meter_reading_type as $center_id => $meter_map) {
    foreach ($meter_map as $meter_id => $reading) {
        $result[] = [
            'center'                    => $reading['center'],
            'meter'                     => $reading['meter'],
            'meter_type'                => $options_meter_type[$reading['meter_type']],
            'meter_unit'                => $reading['meter_unit'],
            'index_final'               => $reading['index_final'],
            'index_initial'             => $reading['index_initial'],
            'index_consumption'         => $reading['index_consumption']
        ];
    }
}



if($params['all_centers']) {
    usort($result, function($a, $b) {
        $result = strcmp($a['center'], $b['center']);
        if ($result === 0) {
            return strcmp($a['meter'], $b['meter']);
        }
        return $result;
    });
}


$context->httpResponse()
        ->body($result)
        ->send();
