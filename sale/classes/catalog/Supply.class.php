<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\catalog;

class Supply extends \sale\catalog\ProductModel{

    public static function getName() {
        return 'Supply';
    }


    public static function getColumns() {

        return [

            'product_models_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'foreign_field'     => 'supplies_ids',
                'rel_table'         => 'sale_catalog_product_model_rel_sale_catalog_supplies',
                'rel_foreign_key'   => 'product_model_id',
                'rel_local_key'     => 'supply_id',
                'description'       => "The product models that can be supplies."
            ]


        ];
    }

}
