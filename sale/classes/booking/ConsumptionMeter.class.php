<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;


class ConsumptionMeter extends \equal\orm\Model {

    public function getTable() {
        return 'lodging_sale_booking_consumptionmeter';
    }

    public static function getColumns() {
        return [

            'description_meter' => [
                'type'              => 'string',
                'description'       => "The short description of the meter.",
                'required'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The name is composed of the center name and description.',
                'function'          => 'calcName',
                'store'             => true,
                'readonly'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center to which the consumption meter to.",
                'required'          => true
            ],

            'date_opening' => [
                'type'              => 'date',
                'description'       => 'The date of the consumption meter opening.',
                'default'           => time()
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => 'Mark the consumption meter as active.',
                'default'           => true
            ],

            'index_value' => [
                'type'              => 'integer',
                'description'       => 'The initial value of the consumption meter.'
            ],

            'coefficient' => [
                'type'              => 'float',
                'description'       => 'The coefficient established in the meter to calculate the consumed consumption',
                'default'           => 1
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'The product relates to consumption meter.'
            ],

            'type_meter' => [
                'type'              => 'string',
                'selection'         => [
                    'water',
                    'gas',
                    'electricity',
                    'gas tank',
                    'oil tank'
                ],
                'description'       => 'The type of meter consumption.'
            ],

            'has_ean' => [
                'type'              => 'boolean',
                'description'       => 'Mark the consumption meter as European Article Numbering.'
            ],

            'meter_number' => [
                'type'              => 'string',
                'description'       => 'The code identifying the factory of the consumption meter.',
                'visible'           => ['has_ean' , '=', false]
            ],

            'meter_ean' => [
                'type'              => 'string',
                'description'       => 'The code identifying  for the European Article Numbering of the consumption meter.',
                'visible'           => ['has_ean' , '=', true]
            ],

            'meter_unit' => [
                'type'              => 'string',
                'selection'         => [
                    'm3',
                    'kWh',
                    'L',
                    '%',
                    'cm'
                ],
                'description'       => 'The unit of the consumption Meter.'
            ],

            'consumptions_meters_readings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\ConsumptionMeterReading',
                'foreign_field'     => 'consumption_meter_id',
                'description'       => 'List of consumptions meters readings of the meter.'
            ]

        ];
    }

    public static function onchange($om, $event, $values, $lang='fr') {
        $result = [];
        if(isset($event['type_meter']) || isset($event['description_meter'])){
            $type_meter = isset($event['type_meter']) ? $event['type_meter'] : $values['type_meter'];
            $description_meter = isset($event['description_meter']) ? $event['description_meter'] : $values['description_meter'];
            $result['name'] = self::computeName($type_meter, $description_meter);
        }
        return $result;
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];
        $meters = $om->read(self::getType(), $oids, ['type_meter' , 'description_meter'], $lang);
        if($meters > 0){
            foreach($meters as $id => $meter) {
                $result[$id] = self::computeName($meter['type_meter'],$meter['description_meter']);
            }
        }
        return $result;
    }

    private static function computeName($type, $description) {
        $meter_map = [
            "water"         => "Eau",
            "gas"           => "Gaz",
            "electricity"   => "Élec",
            "gas tank"      => "Gaz (cit.)",
            "oil tank"      => "Mazout"
        ];
        $result = '[' . ($meter_map[$type] ?? $type) . ']';
        if(strlen($description)) {
            $result .= ' - ' . $description;
        }
        return $result;
    }
}
