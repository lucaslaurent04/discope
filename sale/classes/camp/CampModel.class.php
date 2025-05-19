<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class CampModel extends Model {

    public static function getDescription(): string {
        return "Model that acts as a creation base for new camps.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the camp model.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Description of the camp model.",
                'usage'             => 'text/plain'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product targeted by the line.",
                'required'          => true,
                'domain'            => ['is_camp', '=', true]
            ],

            'camp_type' => [
                'type'              => 'string',
                'selection'         => [
                    'sport',
                    'circus',
                    'culture',
                    'environment',
                    'horse-riding'
                ],
                'description'       => "Type of camp.",
                'default'           => 'sport'
            ],

            'is_clsh' => [
                'type'              => 'boolean',
                'description'       => "Is \"Centre loisir sans hébergement\".",
                'help'              => "If CLSH, the enrollments are per day.",
                'default'           => false
            ],

            'clsh_type' => [
                'type'              => 'string',
                'selection'         => [
                    '5-days',
                    '4-days'
                ],
                'description'       => "Is it a camp of 5 or 4 days duration.",
                'default'           => '5-days',
                'visible'           => ['is_clsh', '=', true]
            ],

            'day_product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product targeted by the line.",
                'domain'            => ['is_camp', '=', true],
                'visible'           => ['is_clsh', '=', true]
            ],

            'employee_ratio' => [
                'type'              => 'integer',
                'usage'             => 'number/integer{1,50}',
                'description'       => "The quantity of children one employee can handle alone, max_children for one camp group.",
                'default'           => 12
            ],

            'need_license_ffe' => [
                'type'              => 'boolean',
                'description'       => "Does the camp requires to child to have a 'licence fédération française équitation'.",
                'default'           => false
            ],

            'ase_quota' => [
                'type'              => 'integer',
                'description'       => "Max quantity of children, using financial help \"Aide sociale à l'enfance\", that can take part to the camp.",
                'default'           => 4
            ],

            'required_skills_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Skill',
                'foreign_field'     => 'camp_models_ids',
                'rel_table'         => 'sale_camp_rel_campmodel_skill',
                'rel_foreign_key'   => 'skill_id',
                'rel_local_key'     => 'camp_model_id',
                'description'       => "Skills needed to participate to the camp."
            ],

            'required_documents_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Document',
                'foreign_field'     => 'camp_models_ids',
                'rel_table'         => 'sale_camp_rel_campmodel_document',
                'rel_foreign_key'   => 'document_id',
                'rel_local_key'     => 'camp_model_id',
                'description'       => "Documents needed to participate to the camp."
            ],

            'camps_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Camp',
                'foreign_field'     => 'camp_model_id',
                'description'       => "The camps based on the model."
            ]

        ];
    }
}

