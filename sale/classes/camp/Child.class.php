<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Child extends Model {

    public static function getDescription(): string {
        return "Child that participate to a camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Complete name of the child.",
                'store'             => true,
                'function'          => 'calcName'
            ],

            'firstname' => [
                'type'              => 'string',
                'description'       => "First name of the parent of the child.",
                'required'          => true
            ],

            'lastname' => [
                'type'              => 'string',
                'description'       => "Last name of the parent of the child.",
                'required'          => true
            ],

            'birthdate' => [
                'type'              => 'date',
                'description'       => "The child's birthdate.",
                'required'          => true
            ],

            'gender' => [
                'type'              => 'string',
                'description'       => "The child's gender.",
                'selection'         => [
                    'F',
                    'M'
                ],
                'default'           => 'F'
            ],

            'fullname_of_person_in_charge' => [
                'type'              => 'string',
                'description'       => "Full name of the person in charge of the child.",
                'required'          => true
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => "Street and number of the child's guardian.",
                'required'          => true
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => "Optional info for mail dispatch (appartment, box, floor, ...)."
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => "Zip code of the child's guardian.",
                'required'          => true
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => "City of the child's guardian.",
                'required'          => true
            ],

            'cpa_club_id' => [
                'type'              => 'string',
                'description'       => "Identifier of the 'centre plein air' the child is member of."
            ],

            'skills_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Skill',
                'foreign_field'     => 'children_ids',
                'rel_table'         => 'sale_camp_rel_child_skill',
                'rel_foreign_key'   => 'skill_id',
                'rel_local_key'     => 'child_id',
                'description'       => "Skills needed to participate to the camp."
            ],

            'guardians_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Guardian',
                'foreign_field'     => 'children_ids',
                'rel_table'         => 'sale_camp_rel_child_guardian',
                'rel_foreign_key'   => 'guardian_id',
                'rel_local_key'     => 'child_id',
                'description'       => "Guardians of the child."
            ],

            'enrollments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Enrollment',
                'foreign_field'     => 'child_id',
                'description'       => "Camp enrollments of child."
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['firstname', 'lastname']);
        foreach($self as $id => $child) {
            if(isset($child['firstname'], $child['lastname'])) {
                $result[$id] = $child['firstname'].' '.$child['lastname'];
            }
        }

        return $result;
    }
}

