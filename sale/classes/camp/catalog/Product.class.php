<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp\catalog;

class Product extends \sale\catalog\Product {

    public static function getDescription(): string {
        return "Product that allows to sell a camp sojourn.";
    }

    public static function getColumns(): array {
        return [

            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\ProductModel',
                'description'       => "Product Model of this variant.",
                'required'          => true,
                'onupdate'          => 'onupdateProductModelId',
                'dependents'        => ['has_own_price', 'is_pack', 'is_rental_unit', 'is_meal', 'is_snack', 'is_activity', 'is_transport', 'is_supply']
            ],

            'prices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\price\Price',
                'foreign_field'     => 'product_id',
                'description'       => "Prices that are related to this product.",
                'ondetach'          => 'delete'
            ]

        ];
    }
}
