<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\customer;
use equal\orm\Model;

class AgeRange extends Model {

    public static function getName() {
        return "Age Range";
    }

    public static function getDescription() {
        return "Age ranges allow to assign consumptions relating to a booking according to the hosts composition of this booking.";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'required'          => true,
                'description'       => 'Name of age range.',
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the age range.",
                'multilang'         => true
            ],

            'age_from' => [
                'type'              => 'integer',
                'description'       => "Age for the lower bound (included).",
                'required'           => true
            ],

            'age_to' => [
                'type'              => 'integer',
                'description'       => "Age for the upper bound (excluded).",
                'required'           => true
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Can the age range be used in bookings?",
                'default'           => true
            ],

            'is_sporty' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the age range relates to higher nutritional needs (athletes).",
                'default'           => false
            ],

            'discounts_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\discount\Discount',
                'foreign_field'     => 'age_ranges_ids',
                'rel_table'         => 'lodging_sale_discount_rel_agerange_discount',
                'rel_foreign_key'   => 'discount_id',
                'rel_local_key'     => 'age_range_id',
                'description'       => 'The conditions that apply to the discount.'
            ],

            'product_models_ids' => [
                'type'            => 'many2many',
                'foreign_object'  => 'sale\catalog\ProductModel',
                'foreign_field'   => 'age_ranges_ids',
                'rel_table'       => 'sale_catalog_product_model_rel_sale_customer_age_ranges',
                'rel_foreign_key' => 'product_model_id',
                'rel_local_key'   => 'age_range_id',
                'description'     => "Specifies the product models associated with particular age ranges, which may influence applicable discounts or eligibility conditions."
            ]
        ];
    }


    public static function getConstraints() {
        return [
            'age_from' =>  [
                'out_of_range' => [
                    'message'       => 'Age must be an integer between 0 and 99.',
                    'function'      => function ($age_from, $values) {
                        return ($age_from >= 0 && $age_from <= 99);
                    }
                ]
            ],
            'age_to' =>  [
                'out_of_range' => [
                    'message'       => 'Age must be an integer between 0 and 99.',
                    'function'      => function ($age_to, $values) {
                        return ($age_to >= 0 && $age_to <= 99);
                    }
                ]
            ]
        ];
    }

    public function getUnique() {
        return [
            ['age_from', 'age_to']
        ];
    }
}
