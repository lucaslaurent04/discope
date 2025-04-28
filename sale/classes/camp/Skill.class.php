<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Skill extends Model {

    public static function getDescription(): string {
        return "A skill needed to be allowed to take part to a camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the skill.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Description of the skill.",
                'usage'             => 'text/plain'
            ],

            'camp_models_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\CampModel',
                'foreign_field'     => 'required_skills_ids',
                'rel_table'         => 'sale_camp_rel_campmodel_skill',
                'rel_foreign_key'   => 'camp_model_id',
                'rel_local_key'     => 'skill_id',
                'description'       => "Camp models that requires the skill."
            ],

            'camps_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Camp',
                'foreign_field'     => 'required_skills_ids',
                'rel_table'         => 'sale_camp_rel_camp_skill',
                'rel_foreign_key'   => 'camp_id',
                'rel_local_key'     => 'skill_id',
                'description'       => "Camps that requires the skill."
            ],

            'children_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Child',
                'foreign_field'     => 'skills_ids',
                'rel_table'         => 'sale_camp_rel_child_skill',
                'rel_foreign_key'   => 'child_id',
                'rel_local_key'     => 'skill_id',
                'description'       => "Children that have the skill."
            ]

        ];
    }
}
