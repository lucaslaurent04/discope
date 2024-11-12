<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\customer;
use equal\orm\Model;

class RateClass extends Model {

    public static function getName() {
        return "Fare Class";
    }

    public static function getDescription() {
        return "Fare classes are assigned to customers and allow to assign prices adapters on products booked by those.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'required'          => true,
                'unique'            => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the rate class.",
                'multilang'         => true
            ]
        ];
    }

}