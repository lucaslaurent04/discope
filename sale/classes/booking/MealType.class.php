<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;

class MealType extends Model {

    public static function getName() {
        return "Meal type";
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'multilang'         => true,
                'description'       => 'Age range assigned to the preference.'
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Text identifier of the meal type.',
                'unique'            => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'multilang'         => true,
                'description'       => 'Short description of the meal type.'
            ]

        ];
    }


}