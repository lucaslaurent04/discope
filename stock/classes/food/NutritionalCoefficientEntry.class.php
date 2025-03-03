<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace stock\food;

use equal\orm\Model;

class NutritionalCoefficientEntry extends Model {

    public static function getDescription(): string {
        return "A configuration of the nutritional coefficient needed to feed a person from a specific age.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the nutritional coefficient.",
                'required'          => true
            ],

            'table_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'stock\food\NutritionalCoefficientTable',
                'description'       => "The configuration to which the nutritional coefficient belongs.",
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'age_from' => [
                'type'              => 'integer',
                'description'       => "The minimum age at which the coefficient is applicable. (included)",
                'default'           => 0,
                'min'               => 0,
                'max'               => 99
            ],

            'age_to' => [
                'type'              => 'integer',
                'description'       => "The maximum age at which the coefficient is applicable. (excluded)",
                'default'           => 19,
                'min'               => 0,
                'max'               => 99
            ],

            'nutritional_coefficient' => [
                'type'              => 'float',
                'description'       => "The nutritional coefficient of the meal needed for the people from age_from to age_to.",
                'default'           => 1
            ],

            'is_sporty' => [
                'type'              => 'boolean',
                'description'       => "Is the nutritional coefficient for sporty people?",
                'default'           => false
            ]

        ];
    }
}
