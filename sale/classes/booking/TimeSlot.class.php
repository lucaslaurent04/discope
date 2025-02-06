<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;

class TimeSlot extends Model {

    public function getTable() {
        return 'lodging_sale_booking_timeslot';
    }

    public static function getName() {
        return 'Time Slot';
    }

    public static function getDescription() {
        return 'Time slots are used for planning purpose in order to slice a day into several moments.';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Time slot name.',
                'multilang'         => true,
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short description detailing the usage of the slot.',
                'multilang'         => true
            ],

            'order' => [
                'type'              => 'integer',
                'default'           => 1,
                'description'       => 'For sorting the moments within a day.'
            ],

            'code' => [
                'type'              => 'string',
                'selection'         => [
                    'B',
                    'AM',
                    'L',
                    'PM',
                    'D',
                    'EV'
                ],
                'description'       => 'Represents the code associated with the ID of the time slot'
            ],

            'is_meal' => [
                'type'              => 'boolean',
                'description'       => 'Does the time slot relate to a meal?',
                'default'           => false
            ],

            'schedule_from' => [
                'type'              => 'time',
                'required'          => true,
                'description'       => 'Time at which the slot starts (included).'
            ],

            'schedule_to' => [
                'type'              => 'time',
                'required'          => true,
                'description'       => 'Time at which the slots ends (excluded).'
            ],

            'product_models_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'foreign_field'     => 'time_slots_ids',
                'rel_table'         => 'sale_catalog_product_model_rel_sale_booking_timeslot',
                'rel_foreign_key'   => 'product_model_id',
                'rel_local_key'     => 'time_slot_id',
                'description'       => "The product models that can be scheduled during this time slot.",
                'visible'           => [ ['type', '=', 'service'], ['service_type', '=', 'schedulable'] ]
            ]

        ];
    }
}
