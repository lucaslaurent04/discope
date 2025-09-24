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

            // Non CLSH products: 'product_id', 'weekend_product_id' and 'saturday_morning_product_id'

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product that will be added to the enrollment lines if the child enroll for the full camp.",
                'domain'            => ['is_camp', '=', true],
                'visible'           => ['is_clsh', '=', false]
            ],

            'weekend_product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product that will be added to the enrollment lines if the child stays the weekend after the camp.",
                'domain'            => ['is_camp', '=', true],
                'visible'           => ['is_clsh', '=', false]
            ],

            'saturday_morning_product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product that will be added to the enrollment lines if the child stays the until Saturday morning after the camp.",
                'domain'            => ['is_camp', '=', true],
                'visible'           => ['is_clsh', '=', false]
            ],

            // CLSH product: 'day_product_id'

            'day_product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product that will be added to the enrollment lines if the child enroll for specific days of the camp.",
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

    public static function canupdate($self, $values): array {
        $self->read([
            'is_clsh',
            'day_product_id',
            'product_id',
            'weekend_product_id',
            'saturday_morning_product_id',
        ]);

        foreach($self as $camp_model) {
            $is_clsh = $values['is_clsh'] ?? $camp_model['is_clsh'];
            if($is_clsh) {
                $day_product = $values['day_product_id'] ?? $camp_model['day_product_id'];
                if(is_null($day_product)) {
                    return ['day_product_id' => ['required_product' => "Product required."]];
                }
            }
            else {
                $product = $values['product_id'] ?? $camp_model['product_id'];
                if(is_null($product)) {
                    return ['product_id' => ['required_product' => "Product required."]];
                }

                $weekend_product = $values['weekend_product_id'] ?? $camp_model['weekend_product_id'];
                if(is_null($weekend_product)) {
                    return ['weekend_product_id' => ['required_product' => "Product required."]];
                }

                $saturday_morning_product = $values['saturday_morning_product_id'] ?? $camp_model['saturday_morning_product_id'];
                if(is_null($saturday_morning_product)) {
                    return ['saturday_morning_product_id' => ['required_product' => "Product required."]];
                }
            }
        }

        return parent::canupdate($self, $values);
    }
}
