<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace stock\food;

use equal\orm\Model;

class NutritionalCoefficientTable extends Model {

    public static function getDescription(): string {
        return "Meal nutritional coefficient configurations for specific Center. Is composed of multiple entries that can be configured by an age range.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the nutritional coefficient configuration.",
                'required'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center to which the nutritional coefficient's configuration belongs.",
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'stock\food\NutritionalCoefficientEntry',
                'foreign_field'     => 'table_id',
                'description'       => "The entries composing the nutritional coefficient configuration."
            ]

        ];
    }
}
