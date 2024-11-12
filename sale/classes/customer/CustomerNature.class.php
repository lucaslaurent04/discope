<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\customer;
use equal\orm\Model;

class CustomerNature extends Model {

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'alias',
                'alias'             => 'description'
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Mnemonic of the customer nature.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the customer nature.",
                'multilang'         => true
            ],
            
            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The rate class that applies to customers of this nature.",
                'required'          => true
            ],

            'customer_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerType',
                'description'       => "The customer type the nature relates to.",
                'required'          => true
            ],            
        ];
    }

}