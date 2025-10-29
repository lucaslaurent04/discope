<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp\price;

class PriceList extends \sale\price\PriceList {

    public static function getColumns(): array {
        return [

            'prices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\price\Price',
                'foreign_field'     => 'price_list_id',
                'description'       => "Prices that are related to this list, if any.",
            ]

        ];
    }
}
