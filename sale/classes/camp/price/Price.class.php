<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp\price;

class Price extends \sale\price\Price {

    public static function getDescription(): string {
        return "Price for a camp.";
    }

    public static function getColumns(): array {
        return [

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The Product (sku) the price applies to.",
                'required'          => true,
                'onupdate'          => 'onupdateProductId'
            ],

            'camp_class' => [
                'type'              => 'string',
                'selection'         => [
                    'other',
                    'member',
                    'close-member'
                ],
                'description'       => "The class of the price, to know which price to use depending on data given for child enrollment factors.",
                'default'           => 'other'
            ],

            'is_clsh' => [
                'type'              => 'boolean',
                'description'       => "Is it related to CLSH camps \"Centre loisir sans hébergement\".",
                'default'           => false
            ],

            'family_quotient_min' => [
                'type'              => 'integer',
                'description'       => "Min family quotient to apply the price to CLSH camp enrolment.",
                'visible'           => ['is_clsh', '=', true]
            ],

            'family_quotient_max' => [
                'type'              => 'integer',
                'description'       => "Max family quotient to apply the price to CLSH camp enrolment.",
                'visible'           => ['is_clsh', '=', true]
            ]

        ];
    }
}
