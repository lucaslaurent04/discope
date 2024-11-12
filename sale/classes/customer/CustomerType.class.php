<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\customer;

class CustomerType extends \identity\IdentityType {

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'alias',
                'alias'             => 'description'
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Mnemonic of the customer type.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the customer type.",
                "multilang"         => true
            ],

            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The rate class that applies to this type of customer.",
                // when using Natures, the rate class to apply is set in CustomerNature
                'required'          => false
            ],
        ];
    }

}