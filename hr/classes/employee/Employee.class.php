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

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => 'The center to which belongs the employee.',
                'default'           => 1
            ],

            'absences_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\absence\Absence',
                'foreign_field'     => 'employee_id',
                'description'       => 'Absences relating to the employee.',
            ],

            'activity_product_models_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'foreign_field'     => 'activity_employees_ids',
                'rel_table'         => 'sale_catalog_product_model_rel_hr_employee',
                'rel_foreign_key'   => 'activity_id',
                'rel_local_key'     => 'employee_id',
                'description'       => "Activity product models that the employee can be assigned to.",
                'domain'            => ['is_activity', '=', true]
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\PartnerPlanningMail',
                'foreign_field'     => 'object_id',
                'description'       => "Mails related to the employee.",
                'domain'            => ['object_class', '=', 'hr\employee\Employee']
            ],

            'extref_employee' => [
                'type'              => 'string',
                'default'           => 'employee',
                'description'       => 'External reference of the Employee.'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'default'           => 'employee',
                'description'       => 'Short description of the Employee (diplomas).'
            ],

            'activity_type' => [
                'type'              => 'string',
                'default'           => 'employee',
                'description'       => 'Activity assigned to the employee.'
            ]

        ];
    }

    public function getUnique() {
        return [
            ['owner_identity_id', 'partner_identity_id', 'role_id']
        ];
    }
}
