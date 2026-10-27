<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Camp extends Model {

    public static function getDescription(): string {
        return "Activity camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the camp with dates and ages.",
                'help'              => "Complete name of the camp.",
                'required'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center to which the camp relates to.",
                'default'           => 1
            ],

            'center_office_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => "Office the camp relates to (for center management).",
                'store'             => true,
                'relation'          => ['center_id' => 'center_office_id']
            ],

            'short_name' => [
                'type'              => 'string',
                'description'       => "Short name of the camp.",
                'required'          => true
            ],

            'sojourn_number' => [
                'type'              => 'string',
                'description'       => "Sojourn number to distinguish camps."
            ],

            'sojourn_code' => [
                'type'              => 'string',
                'description'       => "Sojourn number padded to create a recognisable camp sojourn code."
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'published',
                    'cancelled'
                ],
                'description'       => "Status of the camp.",
                'default'           => 'draft'
            ],

            'remarks' => [
                'type'              => 'string',
                'description'       => "Description of the camp.",
                'usage'             => 'text/plain'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "When the camp starts.",
                'required'          => true,
                'default'           => function() {
                    return strtotime('next Sunday');
                }
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "When the camp ends.",
                'required'          => true,
                'default'           => function() {
                    return strtotime('next Sunday +5 days');
                }
            ],

            'camp_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\CampModel',
                'description'       => "Model that was used as a base to create this camp.",
                'required'          => true
            ],

            'age_range' => [
                'type'              => 'string',
                'description'       => "Age range of the accepted participants."
            ],

            'min_age' => [
                'type'              => 'integer',
                'description'       => "Minimal age of the participants."
            ],

            'max_age' => [
                'type'              => 'integer',
                'description'       => "Maximal age of the participants."
            ],

            'employee_ratio' => [
                'type'              => 'integer',
                'usage'             => 'number/integer{1,50}',
                'description'       => "The quantity of children one employee can handle alone."
            ],

            'max_children' => [
                'type'              => 'integer',
                'description'       => "Max quantity of children that can take part to the camp."
            ],

            'enrollments_qty' => [
                'type'              => 'integer',
                'description'       => "Quantity of enrollments that aren't cancelled or waitlisted."
            ],

            'camp_group_qty' => [
                'type'              => 'integer',
                'description'       => "The quantity of camp groups.",
                'default'           => 1
            ],

            'camp_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\CampGroup',
                'foreign_field'     => 'camp_id',
                'description'       => "The groups of children of the camp.",
                'ondetach'          => 'delete'
            ],

            'enrollments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Enrollment',
                'foreign_field'     => 'camp_id',
                'description'       => "All the enrollments linked to camp.",
                'ondetach'          => 'delete'
            ],

            'booking_activities_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingActivity',
                'foreign_field'     => 'camp_id',
                'description'       => "All Booking Activities this camp relates to.",
                'ondetach'          => 'delete'
            ],

            'booking_meals_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingMeal',
                'foreign_field'     => 'camp_id',
                'description'       => "The children's meals for this camp.",
                'ondetach'          => 'delete'
            ],

            'employees_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'camps_ids',
                'rel_table'         => 'sale_hr_rel_camp_employee',
                'rel_foreign_key'   => 'employee_id',
                'rel_local_key'     => 'camp_id'
            ]

        ];
    }
}
