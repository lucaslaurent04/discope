<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\catalog;

class PosPriceList extends \sale\price\PriceList {

    public static function getColumns() {
        return [
            'prices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\PosPrice',
                'foreign_field'     => 'price_list_id',
                'description'       => "Prices that are related to this list, if any.",
            ]
        ];
    }

}
