<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\autosale;
use equal\orm\Model;

class Condition extends Model {

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string'
            ],

            'operand' => [
                'type'              => 'string',
                'selection'         => [
                    'nb_pers',
                    'nb_nights',
                    'count_booking_12'
                ],
                'required'          => true
            ],

            'operator' => [
                'type'              => 'string',
                'required'          => true
            ],

            'value' => [
                'type'              => 'string',
                'required'          => true
            ],

            'autosale_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\autosale\AutosaleLine',
                'description'       => 'The autosale line the condition belongs to.',
                'required'          => true
            ]

        ];
    }

}