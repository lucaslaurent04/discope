<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp\catalog;

class ProductModel extends \sale\catalog\ProductModel {

    public static function getDescription(): string {
        return "Product model that allows to sell a camp sojourn.";
    }

    public static function getColumns(): array {
        return [

            'products_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'foreign_field'     => 'product_model_id',
                'description'       => "Product variants that are related to this model.",
            ]

        ];
    }
}
