<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;

use sale\booking\Booking;

class BookingInspection extends \equal\orm\Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The name is composed of the booking name and type inspection.',
                'function'          => 'calcName',
                'store'             => true,
                'readonly'          => true,
                'onupdate'          => 'onupdateName'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'The booking inspection relates to inspecting the condition of the booking, such as meter index or remarks.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'date_inspection' => [
                'type'              => 'date',
                'description'       => 'The date of the manager conducts the inspection.',
                'default'           => time()
            ],

            'type_inspection' => [
                'type'              => 'string',
                'selection'         => [
                    'checkedin',
                    'checkedout'
                ],
                'description'       => 'The type of inspection relates to the status of the booking.',
                'required'          => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'submitted',
                    'billed'
                ],
                'description'       => 'Status of the booking inspection.',
                'default'           => 'pending'
            ],

            'has_alert' => [
                'type'              => 'boolean',
                'description'       => 'Mark the booking inspection as  an alert.',
                'default'           => false
            ],

            'has_signature' => [
                'type'              => 'boolean',
                'description'       => 'Mark that the booking inspection has been signed by the client.',
                'default'           => false
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Reason or comments about the booking inspection, if any (for internal use)."
            ],

            'consumptions_meters_readings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\ConsumptionMeterReading',
                'foreign_field'     => 'booking_inspection_id',
                'description'       => 'List of consumption meter readings related to the booking inspection.',
                'ondetach'          => 'delete'
            ],

        ];
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];
        $meters = $om->read(self::getType(), $oids, ['booking_id.name' , 'type_inspection'], $lang);
        if($meters > 0){
            foreach($meters as $id => $meter) {
                $result[$id] = $meter['booking_id.name']. ' ('. $meter['type_inspection'].')';
            }
        }
        return $result;
    }

    public static function onupdateName($om, $ids, $values, $lang) {
        $res = $om->read(self::getType(), $ids, ['name'], $lang);
        if($res > 0 && count($res)) {
            foreach($res as $id => $meter) {
                $om->update(self::getType(), $id, ['name' => null]);
            }
        }
    }

    public static function onchange($om, $event, $values, $lang='fr') {
        $result = [];
        if(isset($event['booking_id'])) {
            $booking = Booking::id($event['booking_id'])->read(['status'])->first(true);
            $result['type_inspection'] = $booking['status'];
        }
        return $result;
    }

    public static function canupdate($om, $oids, $values, $lang) {
        return self::validateBookingInspection($values, function() use ($om, $oids, $values, $lang) {
            return parent::canupdate($om, $oids, $values, $lang);
        });
    }

    public static function cancreate($om, $values, $lang) {
        return self::validateBookingInspection($values, function() use ($om, $values, $lang) {
            return parent::cancreate($om, $values, $lang);
        });
    }

    private static function validateBookingInspection($values, $callback) {
        if (isset($values['booking_id'])) {
            $booking = Booking::id($values['booking_id'])->read(['status', 'bookings_inspections_ids' => ['id', 'type_inspection']])->first(true);
            if ($booking > 0) {
                if (!in_array($booking['status'], ['confirmed', 'validated', 'checkedin', 'checkedout'])) {
                    return ['booking_id' => ['invalid_status_booking' => 'The booking must be in the status of confirmed, validated, check-in or check-out.']];
                }
                if ($values['type_inspection'] == 'checkedout') {
                    foreach ($booking['bookings_inspections_ids'] as $inspection) {
                        if ($inspection['type_inspection'] == 'checkedin') {
                            return $callback();
                        }
                    }
                    return ['type_inspection' => ['invalid_type_inspection' => 'It is not possible to create an inspection for checkout if there is no inspection for check-in.']];
                }
            }
        }
        return $callback();
    }
}
