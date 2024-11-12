<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\discount;
use equal\orm\Model;

class Discount extends Model {

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Context the discount is meant to be used."
            ],

            'value' => [
                'type'              => 'float',
                // #memo - can be either percent, value or qty
                /*'usage'             => 'amount/percent',*/
                'description'       => "Discount value.",
                'default'           => 0.0
            ],

            'discount_list_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\discount\DiscountList',
                'description'       => 'The discount list the discount belongs to.',
                'required'          => true
            ],

            'discount_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\discount\DiscountCategory',
                'description'       => 'The discount category the discount belongs to.',
                'required'          => true
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => [ 
                    'amount',           // discount is a fixed value
                    'percent',          // discount is a rate to be applied
                    'freebie'           // discount is a count of free products
                ],
                'description'       => 'The kind of contact, based on its responsibilities.'
            ],            

            'conditions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\discount\Condition',
                'foreign_field'     => 'discount_id',
                'description'       => 'The conditions that apply to the discount.'
            ],

            'has_age_ranges' => [
                'type'              => 'boolean',
                'default'           => false
            ],

            'age_ranges_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\customer\AgeRange',
                'foreign_field'     => 'discounts_ids',
                'rel_table'         => 'lodging_sale_discount_rel_agerange_discount',
                'rel_foreign_key'   => 'age_range_id',
                'rel_local_key'     => 'discount_id',
                'visible'           => ['has_age_ranges', '=', true],
                'description'       => 'The conditions that apply to the discount.'
            ],

            'value_max' => [
                'type'              => 'string',
                'selection'         => [
                    'nb_pers',
                    'nb_adults',
                    'nb_children'
                ],
                'visible'           => ['type', '=', 'freebie'],
                'description'       => 'The maximum amount of freebies that can be granted.',
                'help'              => 'This is a reference to maximum freebies that can be granted, according to current sojourn (Booking Line Group). This can only be applied for freebie discounts.',
                'default'           => 'nb_pers'
            ],

        ];
    }

}
