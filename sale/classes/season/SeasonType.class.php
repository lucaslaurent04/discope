<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\season;
use equal\orm\Model;

class SeasonType extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Short code of the type."
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Explanation on when to use the type and its specifics.",
                'default'           => '',
                'multilang'         => true
            ]            
            
        ];
    }

}