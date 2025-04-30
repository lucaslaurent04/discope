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
                'required'          => true,
                'onupdate'          => 'onupdateBirthdate',
                'default'           => function () {
                    return strtotime('now -10 years');
                }
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

            'is_foster' => [
                'type'              => 'boolean',
                'description'       => "Is the child living in a forster family/home.",
                'default'           => false,
                'onupdate'          => 'onupdateIsFoster'
            ],

            'is_cpa_member' => [
                'type'              => 'boolean',
                'description'       => "Is the child member of a CPA club.",
                'default'           => false,
                'onupdate'          => 'onupdateIsCpaNumber'
            ],

            'cpa_club' => [
                'type'              => 'string',
                'description'       => "Name of the 'centre plein air' the child is member of.",
                'visible'           => ['is_cpa_member', '=', true]
            ],

            'licence_ffe' => [
                'type'              => 'string',
                'description'       => "Licence 'fédération française équitation'."
            ],

            'year_licence_ffe' => [
                'type'              => 'integer',
                'usage'             => 'number/integer{2000,'.date('Y').'}',
                'description'       => "Year the licence ffe was acquired."
            ],

            'camp_class' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'other',
                    'member',
                    'close-member'
                ],
                'description'       => "The camp class of the child, to know which price to apply.",
                'store'             => true,
                'function'          => 'calcCampClass'
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

            'institution_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Institution',
                'description'       => "The institution that is taking care or the child.",
                'visible'           => ['is_foster', '=', true]
            ],

            'main_guardian_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Guardian',
                'description'       => "The main guardian responsible of the child.",
                'help'              => "Used to know which address to use for invoicing.",
                'onupdate'          => 'onupdateMainGuardianId'
            ],

            'guardians_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Guardian',
                'foreign_field'     => 'children_ids',
                'rel_table'         => 'sale_camp_rel_child_guardian',
                'rel_foreign_key'   => 'guardian_id',
                'rel_local_key'     => 'child_id',
                'description'       => "Guardians of the child.",
                'onupdate'          => 'onupdateGuardiansIds'
            ],

            'enrollments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Enrollment',
                'foreign_field'     => 'child_id',
                'description'       => "Camp enrollments of child.",
                'ondetach'          => 'delete'
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

    public static function calcCampClass($self): array {
        $result = [];
        $self->read(['camp_class', 'is_cpa_member', 'main_guardian_id' => ['is_vienne', 'is_ccvg']]);
        foreach($self as $id => $child) {
            $new_camp_class = 'other';
            if(isset($child['main_guardian_id'])) {
                if($child['main_guardian_id']['is_ccvg'] || $child['is_cpa_member']) {
                    $new_camp_class = 'close-member';
                }
                elseif($child['main_guardian_id']['is_vienne']) {
                    $new_camp_class = 'member';
                }
            }

            $result[$id] = $new_camp_class;
        }

        return $result;
    }

    /**
     * Reset child age of not locked enrollments of children
     */
    public static function onupdateBirthdate($self) {
        $reset_age_enrollments_ids = [];
        $self->read(['enrollments_ids' => ['is_locked']]);
        foreach($self as $child) {
            foreach($child['enrollments_ids'] as $enrollment) {
                if(!$enrollment['is_locked']) {
                    $reset_age_enrollments_ids[] = $enrollment['id'];
                }
            }
        }

        if(!empty($reset_age_enrollments_ids)) {
            Enrollment::ids($reset_age_enrollments_ids)
                ->update(['child_age' => null]);
        }
    }

    public static function onupdateIsFoster($self) {
        $self->read(['is_foster', 'institution_id']);
        foreach($self as $id => $child) {
            if(!$child['is_foster'] && !is_null($child['institution_id'])) {
                self::id($id)->update(['institution_id' => null]);
            }
        }
    }

    public static function onupdateIsCpaNumber($self) {
        $self->read(['is_cpa_member', 'cpa_club']);
        foreach($self as $id => $child) {
            if(!$child['is_cpa_member'] && !is_null($child['cpa_club'])) {
                self::id($id)->update(['cpa_club' => null]);
            }
            if($child['is_cpa_member']) {
                self::id($id)->update(['camp_class' => 'close-member']);
            }
        }
    }

    public static function onupdateMainGuardianId($self) {
        $self->read(['camp_class']);
        foreach($self as $id => $child) {
            if($child['camp_class'] === 'other') {
                self::id($id)->update(['camp_class' => null]);
            }
        }
    }

    public static function onupdateGuardiansIds($self) {
        $self->read(['main_guardian_id', 'guardians_ids']);
        foreach($self as $id => $child) {
            self::id($id)->update(['main_guardian_id' => $child['guardians_ids'][0] ?? null]);
        }
    }
}
