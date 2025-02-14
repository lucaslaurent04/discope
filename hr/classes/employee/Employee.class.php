<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace hr\employee;

class Employee extends \identity\Partner {

    public static function getName() {
        return 'Employee';
    }

    public static function getDescription() {
        return "An employee is relationship relating to contract that has been made between an identity and a company.";
    }

    public static function getColumns() {
        return [

            'role_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\employee\Role',
                'description'       => 'Role assigned to the employee.'
                // #memo - might not be assigned at creation
                // 'required'          => true
            ],

            'relationship' => [
                'type'              => 'string',
                'default'           => 'employee',
                'description'       => 'Force relationship to Employee'
            ],

            'date_start' => [
                'type'              => 'date',
                'description'       => 'Date of the first day of work.',
                'required'          => true
            ],

            'date_end' => [
                'type'              => 'date',
                'description'       => 'Date of the last day of work.',
                'help'              => 'Date at which the contract ends (known in advance for fixed-term or unknown for permanent).'
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => 'Marks the employee as currently active within the organisation.',
                'default'           => true
            ],

            'absences_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\absence\Absence',
                'foreign_field'     => 'employee_id',
                'description'       => 'Absences relating to the employee.',
            ],

            'booking_lines_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'employees_ids',
                'rel_table'         => 'sale_booking_line_rel_hr_employee',
                'rel_foreign_key'   => 'booking_line_id',
                'rel_local_key'     => 'employee_id'
            ],

            'consumptions_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\booking\Consumption',
                'foreign_field'     => 'employees_ids',
                'rel_table'         => 'sale_booking_consumption_rel_hr_employee',
                'rel_foreign_key'   => 'consumption_id',
                'rel_local_key'     => 'employee_id'
            ]

        ];
    }

    public function getUnique() {
        return [
            ['owner_identity_id', 'partner_identity_id', 'role_id']
        ];
    }
}
