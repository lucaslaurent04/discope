<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\catalog;


class PosPrice extends \sale\price\Price {
    public static function getColumns() {
        return [

            // #memo - we let the user deal with the accounting rule (VAT rate might vary)

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\PosProduct',
                'description'       => "The Product (sku) the price applies to.",
                'required'          => true,
                'onupdate'          => 'sale\price\Price::onupdateProductId'
            ],

            'price_list_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\PosPriceList',
                'description'       => "The Price List the price belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade',
                'onupdate'          => 'sale\price\Price::onupdatePriceListId'
            ],


        ];
    }

}
