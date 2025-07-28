<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use equal\orm\Model;

class BookingTypeAssignment extends Model {

    public static function getDescription(): string {
        return "A set of rules that, if matched, will apply a specific booking type to a booking.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name the the booking type assignment.",
                'required'          => true
            ],

            'booking_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingType',
                'description'       => "The booking type the assignment applies.",
                'required'          => true
            ],

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\SojournType',
                'description'       => "The sojourn type the booking must match for the booking type to be applied.",
                'help'              => "If empty no check is done on the booking's sojourn type."
            ],

            'booking_type_assign_rules_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingTypeAssignmentRule',
                'foreign_field'     => 'booking_type_assignment_id',
                'description'       => "The rules that have to match to apply the assignment.",
                'ondetach'          => 'delete'
            ],

            'rate_classes_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\customer\RateClass',
                'foreign_field'     => 'booking_type_assignments_ids',
                'rel_table'         => 'sale_booking_type_assignment_rel_sale_rate_class',
                'rel_local_key'     => 'booking_type_assignment_id',
                'rel_foreign_key'   => 'rate_class_id',
                'description'       => "The rate classes the booking must match for this assignment to apply.",
                'help'              => "If empty no check is done on the rate class of the booking's customer."
            ]

        ];
    }
}

