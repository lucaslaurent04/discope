<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use identity\Center;

class ConsumptionMeterReading extends \equal\orm\Model {

    public static function getColumns() {
        return [

            'booking_inspection_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingInspection',
                'description'       => "The booking inspection corresponds to the consumption meter reading.",
                'required'          => true,
            ],

            'consumption_meter_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\ConsumptionMeter',
                'description'       => 'The meter ID relates to the consumption meter reading in the booking.',
                'required'          => true
            ],

            'booking_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'The booking to which the meter reading will be associated.',
                'function'          => 'calcBooking',
                'store'             => true
            ],

            'center_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center associated with the consumption meter reading.",
                'function'          => 'calcCenter',
                'store'             => true
            ],

            'date_reading' => [
                'type'              => 'date',
                'description'       => 'The day the meter reading is taken.',
                'default'           => time()
            ],

            'index_value' => [
                'type'              => 'integer',
                'description'       => 'The index value of the consumption meter reading.',
                'required'          => true
            ],

            'display_value'=> [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'number/real',
                'description'       => 'The index value is formatted with a comma starting from the third digit',
                'function'          => 'calcDisplayValue',
                'store'             => true
            ],

            'price_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => '\sale\price\Price',
                'description'       => 'The price of the consumption meter reading.',
                'function'          => 'calcPriceId',
                'readonly'          => true,
                'store'             => true
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'The unit price of the consumption meter reading.',
                'function'          => 'calcUnitPrice',
                'store'             => true
            ]
        ];
    }

    public static function calcDisplayValue($om, $oids, $lang) {
        $result = [];
        $meters = $om->read(self::getType(), $oids, ['index_value'], $lang);
        if($meters > 0){
            foreach($meters as $id => $meter) {
                $result[$id] = $meter['index_value']/1000;
            }
        }
        return $result;
    }

    public static function onchange($om, $event, $values, $lang='fr') {
        $result = [];

        if(isset($event['booking_inspection_id'])){
            $booking_inspection = BookingInspection::id($event['booking_inspection_id'])
                ->read(['booking_id' => ['id', 'name', 'center_id' => ['id', 'name']]])
                ->first(true);

            $result['booking_id'] = $booking_inspection['booking_id'];
            $result['center_id'] = $booking_inspection['booking_id']['center_id'];
        }

        if(isset($event['index_value'])){
            $result['display_value'] = $event['index_value']/1000;
        }

        if(isset($event['consumption_meter_id']) || isset($event['date_reading']) || isset($event['center_id']) ){
            $date_reading = $event['date_reading'] ?? $values['date_reading'];
            $meter_id = $event['consumption_meter_id'] ?? $values['consumption_meter_id'];
            $center_id = $event['center_id'] ?? $values['center_id'];

            $meter = ConsumptionMeter::id($meter_id)->read('product_id')->first(true);
            $center = Center::id($center_id)->read('price_list_category_id')->first(true);

            $reading = [
                'date_reading' => $date_reading,
                'consumption_meter_id.product_id' => $meter['product_id'],
                'center_id.price_list_category_id' => $center['price_list_category_id']
            ];

            $price = self::getPrice($om, $reading);
            if ($price !== null) {
                if($price['id'] > 0) {
                    $result['price_id'] = $price;
                }

                if($price['price'] > 0) {
                    $result['unit_price'] = $price['price'];
                }
            }
        }

        return $result;
    }

    public static function canupdate($om, $oids, $values, $lang) {

        $indexValidationResult = self::validateIndex($values);
        if ($indexValidationResult !== true) {
            return $indexValidationResult;
        }

        $unitPriceValidationResult = self::validateUnitPrice($values);
        if ($unitPriceValidationResult !== true) {
            return $unitPriceValidationResult;
        }

        $centerValidationResult = self::validateCenter($values);
        if ($centerValidationResult !== true) {
            return $centerValidationResult;
        }

        $meterReadingValidationResult = self::validateMeterReading($values);
        if ($meterReadingValidationResult !== true) {
            return $meterReadingValidationResult;
        }

        return parent::canUpdate($om, $oids, $values, $lang);
    }

    public static function cancreate($om, $values, $lang) {

        $indexValidationResult = self::validateIndex($values);
        if ($indexValidationResult !== true) {
            return $indexValidationResult;
        }

        $unitPriceValidationResult = self::validateUnitPrice($values);
        if ($unitPriceValidationResult !== true) {
            return $unitPriceValidationResult;
        }


        $centerValidationResult = self::validateCenter($values);
        if ($centerValidationResult !== true) {
            return $centerValidationResult;
        }

        $meterReadingValidationResult = self::validateMeterReading($values);
        if ($meterReadingValidationResult !== true) {
            return $meterReadingValidationResult;
        }

        return parent::canCreate($om, $values, $lang);
    }

    private static function validateIndex($values) {
        if (isset($values['index_value']) && $values['index_value'] <= 0) {
            return ['index_value' => ['invalid_index' => 'The index value must be greater than zero.']];
        }
        return true;
    }

    private static function validateUnitPrice($values) {
        if (isset($values['unit_price']) && $values['unit_price'] <= 0) {
            return ['unit_price' => ['unit_price' => 'The unite price  must be greater than zero.']];
        }
        return true;
    }

    private static function validateCenter($values) {
        if (isset($values['center_id']) && isset($values['consumption_meter_id'])) {
            $meter = ConsumptionMeter::id($values['consumption_meter_id'])->read(['center_id'])->first(true);
            if ($meter['center_id'] != $values['center_id']) {
                return ['consumption_meter_id' => ['invalid_center' => 'It is not possible to create a consumption meter reading for a different center than the one associated with the booking inspection']];
            }
        }
        return true;
    }

    private static function validateMeterReading($values) {
        if (isset($values['booking_id']) && isset($values['consumption_meter_id']) && isset($values['booking_inspection_id'])) {
            $booking_inspection = BookingInspection::id($values['booking_inspection_id'])->read(['type_inspection'])->first(true);
            if ($booking_inspection['type_inspection'] == 'checkedout'){
                $found_meter_reading_checkin = false;
                $meterReadings = ConsumptionMeterReading::search([
                        ['booking_id', '=', $values['booking_id']],
                        ['consumption_meter_id', '=', $values['consumption_meter_id']]
                    ])
                    ->read(['id', 'consumption_meter_id' => ['id', 'name'], 'booking_inspection_id' => ['id', 'type_inspection']])
                    ->get(true);

                foreach ($meterReadings as $meterReading) {
                    if ($meterReading['booking_inspection_id']['type_inspection'] == 'checkedin') {
                        $found_meter_reading_checkin = true;
                        break;
                    }
                }

                if (!$found_meter_reading_checkin) {
                    return ['consumption_meter_id' => ['invalid_consumption_reading' => 'It is not possible to create a consumption meter reading because there is no consumption meter for the check-in']];
                }
            }
        }
        return true;
    }

    public static function calcBooking($om, $oids, $lang) {
        $result = [];
        $consumptions = $om->read(self::getType(), $oids, ['booking_inspection_id.booking_id'], $lang);
        if($consumptions > 0) {
            foreach($consumptions as $id => $consumption) {
                $result[$id] = $consumption['booking_inspection_id.booking_id'] ;
            }
        }
        return $result;
    }

    public static function calcCenter($om, $oids, $lang) {
        $result = [];
        $consumptions = $om->read(self::getType(), $oids, ['booking_id.center_id'], $lang);
        if($consumptions > 0) {
            foreach($consumptions as $id => $consumption) {
                $result[$id] = $consumption['booking_id.center_id'] ;
            }
        }
        return $result;
    }

    public static function calcUnitPrice($om, $oids, $lang) {
        $result = [];
        $readings = $om->read(self::getType(), $oids, [
            'date_reading',
            'consumption_meter_id.product_id',
            'center_id.price_list_category_id'
        ], $lang);

        if($readings > 0) {
            foreach($readings as $id => $reading) {
                $price = self::getPrice($om, $reading);
                if ($price !== null) {
                    if($price['price'] > 0) {
                        $result[$id] = $price['price'];
                    }
                }
            }
        }

        return $result;
    }

    public static function calcPriceId($om, $oids, $lang) {
        $result = [];
        $readings = $om->read(self::getType(), $oids, [
            'date_reading',
            'consumption_meter_id.product_id',
            'center_id.price_list_category_id'
        ], $lang);

        if($readings > 0) {
            foreach($readings as $id => $reading) {
                $price = self::getPrice($om, $reading);
                if ($price !== null) {
                    if($price['id'] > 0) {
                        $result[$id] = $price['id'];
                    }
                }
            }
        }

        return $result;
    }

    private static function getPrice($om, $reading) {
        $price_lists_ids = $om->search(
            \sale\price\PriceList::getType(),
            [
                [
                    ['price_list_category_id', '=', $reading['center_id.price_list_category_id']],
                    ['date_from', '<=', $reading['date_reading']],
                    ['date_to', '>=', $reading['date_reading']],
                    ['status', '=', 'published']
                ]
            ],
            ['duration' => 'asc']
        );

        if ($price_lists_ids > 0 && count($price_lists_ids)) {
            foreach ($price_lists_ids as $price_list_id) {
                $prices_ids = $om->search(\sale\price\Price::getType(), [
                    ['price_list_id', '=', $price_list_id],
                    ['product_id', '=', $reading['consumption_meter_id.product_id']]
                ]);

                if ($prices_ids > 0 && count($prices_ids)) {
                    $price_id = reset($prices_ids);
                    $prices = $om->read(\sale\price\Price::getType(), [$price_id], ['id', 'name', 'price']);
                    if ($prices > 0) {
                        $price = reset($prices);
                        return $price;
                    }
                }
            }
        }

        return null;
    }
}
