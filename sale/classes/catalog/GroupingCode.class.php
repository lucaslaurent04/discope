<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\catalog;
use equal\orm\Model;

class GroupingCode extends Model {

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Grouping Code name.',
                'required'          => true
            ],


            'code' => [
                'type'              => 'string',
                /*
                'selection'         => [
                    'Accommodation',
                    'Activity',
                    'Transport',
                    'Supply',
                ],
                */
                'description'       => 'Represents the code associated with the ID of the group code',
                'help'              => 'User must be free to create arbitrary codes'
            ],

            'has_age_range' => [
                'type'        => 'boolean',
                'description' => 'Indicates whether the grouping code is assigned to a specific age range.',
                'default'     => false
            ],

            'age_range_id' => [
                'type'             => 'many2one',
                'foreign_object'   => 'sale\customer\AgeRange',
                'description'      => 'The age range linked to the grouping code.',
                'visible'          => ["has_age_range", "=", true]
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "The description providing additional context for the group code."
            ],


            'product_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'foreign_field'     => 'grouping_code_id',
                'description'       => 'Product models associated with the grouping code.'
            ],

            'products_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\Product',
                'foreign_field'     => 'grouping_code_id',
                'description'       => 'Products associated with the grouping code.'
            ]



        ];
    }
}