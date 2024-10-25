<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\ConsumptionMeterReading;

list($params, $providers) = eQual::announce([
    'description'   => 'Provides the summary of the consumption reading by the consumption meter, including the consumption index and total price.',
    'params'        => [
        'booking_id' => [
            'type'                  => 'many2one',
            'foreign_object'        => 'sale\booking\Booking',
            'description'           => 'The booking to which the meter reading will be associated.'
        ],

        'consumption_meter_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\ConsumptionMeter',
            'description'       => 'The meter ID relates to the consumption meter reading in the booking.'
        ],
        'index_initial' => [
            'type'                  => 'integer',
            'description'           => 'The index value in the checkin.'
        ],
        'index_final' => [
            'type'                  => 'integer',
            'description'           => 'The index value in the checkout.'
        ],
        'index_difference' => [
            'type'                  => 'integer',
            'description'           => 'The index value difference between checkout and check-in.'
        ],
        'unit_price' => [
            'type'                  => 'float',
            'usage'                 => 'amount/money:2',
            'description'           => 'The unit price of the consumption meter reading in the checkin.'
        ],
        'total' => [
            'type'                  => 'float',
            'usage'                 => 'amount/money:2',
            'description'           => 'The total price of the consumption meter.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
$context = $providers['context'];

$result = [];
if(isset($params['booking_id'])) {

    $consumptions = ConsumptionMeterReading::search(['booking_id' , "=" , $params['booking_id']])
                    ->read(['consumption_meter_id' => ['id', 'name'], 'booking_inspection_id' => ['id','type_inspection'], 'date_reading', 'index_value' , 'unit_price'])
                    ->get(true);

    $meter_map = [];
    foreach($consumptions as $consumption) {
        $consumption_meter_id = $consumption['consumption_meter_id']['id'];

        if(!isset($meter_map[$consumption_meter_id])) {
            $meter_map[$consumption_meter_id] = [
                'consumption_meter_id'  => $consumption['consumption_meter_id'],
                'index_initial'         => 0,
                'index_final'           => 0,
                'index_difference'      => 0,
                'unit_price'            => 0,
                'total'                 => 0
            ];
        }

        if ($consumption['booking_inspection_id']['type_inspection'] == 'checkedin'){
            $meter_map[$consumption_meter_id]['index_initial'] = $consumption['index_value'];
            $meter_map[$consumption_meter_id]['unit_price'] = $consumption['unit_price'];
        }

        if ($consumption['booking_inspection_id']['type_inspection'] == 'checkedout'){
            $meter_map[$consumption_meter_id]['index_final'] = $consumption['index_value'];
        }

    }


    foreach($meter_map as $meter_id => $meter) {
        $result[] = [
            'consumption_meter_id'   => $meter['consumption_meter_id'],
            'index_final'            => $meter['index_final'],
            'index_initial'          => $meter['index_initial'],
            'index_difference'       => $meter['index_final'] - $meter['index_initial'],
            'unit_price'             => $meter['unit_price'],
            'total'                  => ($meter['index_final'] - $meter['index_initial']) * $meter['unit_price']
        ];
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();
