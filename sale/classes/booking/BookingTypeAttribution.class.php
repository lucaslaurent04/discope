<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use equal\orm\Model;

class BookingTypeAttribution extends Model {

    public static function getDescription(): string {
        return "A sojourn type, a set of rate classes and set of conditions that, if matched, will apply a specific booking type to a booking.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name the the booking type attribution.",
                'required'          => true
            ],

            'booking_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingType',
                'description'       => "The booking type the attribution applies.",
                'required'          => true
            ],

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\SojournType',
                'description'       => "The sojourn type the booking must match for the booking type to be applied.",
                'help'              => "If empty no check is done on the booking's sojourn type."
            ],

            'booking_type_conditions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingTypeCondition',
                'foreign_field'     => 'booking_type_attribution_id',
                'description'       => "The conditions that have to match to apply the booking type.",
                'ondetach'          => 'delete'
            ],

            'rate_classes_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\customer\RateClass',
                'foreign_field'     => 'booking_type_attributions_ids',
                'rel_table'         => 'sale_booking_type_attribution_rel_sale_rate_class',
                'rel_local_key'     => 'booking_type_attribution_id',
                'rel_foreign_key'   => 'rate_class_id',
                'description'       => "The rate classes the booking must match for this type to apply.",
                'help'              => "If empty no check is done on the rate class of the booking's customer."
            ]

        ];
    }
}

