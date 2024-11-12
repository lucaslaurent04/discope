<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\catalog;
use equal\orm\Model;

class Option extends Model {
    public static function getColumns() {
        /**
         */

        return [
            'name' => [
                'type'              => 'alias',
                'alias'             => 'description',
                'description'       => 'The display name of the option (description).'
            ],

            'label' => [
                'type'              => 'string',
                'description'       => 'Unique name of the option.'
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the option.",
                'multilang'         => true
            ],

            'family_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Family',
                'description'       => "Product Family this option belongs to.",
                'required'          => true
            ]
        ];
    }


}