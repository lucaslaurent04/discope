<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;

class MealPlace extends Model {

    public static function getName() {
        return "Meal Place";
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the Meal Place.'
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Text identifier of the meal place.',
                'unique'            => true
            ],

            'place_type' => [
                'type'              => 'string',
                'description'       => 'Type of meal place.',
                'selection'         => [
                    'onsite',
                    'offsite',
                    'mobile'
                ],
                'default'           => 'onsite'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'multilang'         => true,
                'description'       => 'Short description of the meal place.'
            ]

        ];
    }


}