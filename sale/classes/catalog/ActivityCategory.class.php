<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\catalog;

use equal\orm\Model;

class ActivityCategory extends Model {

    public static function getName(): string {
        return "Activity Category";
    }

    public static function getDescription(): string {
        return "Activity categories allow to group activities products in arbitrary ways.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the product category (used for all variants).",
                'multilang'         => true,
                'required'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Unique code of the category (to ease searching).",
                'required'          => true,
                'unique'            => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'multilang'         => true,
                'description'       => "Short string describing the purpose and usage of the category."
            ],

            'products_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\Product',
                'foreign_field'     => 'activity_category_id',
                'description'       => "Products that are part of the category."
            ]

        ];
    }
}
