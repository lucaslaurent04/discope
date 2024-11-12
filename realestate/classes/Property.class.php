<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate;
use equal\orm\Model;

class Property extends \identity\Establishment {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the property.",
                'required'          => true
            ]

        ];
    }    
}