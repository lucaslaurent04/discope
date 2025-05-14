<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\catalog;

use equal\orm\Model;

class ProductModelCategory extends Model {

    public function getTable(): string{
        return 'sale_product_rel_productmodel_category';
    }

    public static function getColumns(): array {
        return [

            'productmodel_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'description'       => "Product model part of the link.",
                'required'          => true
            ],

            'category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Category',
                'description'       => "Category part of the link.",
                'required'          => true
            ]

        ];
    }
}
