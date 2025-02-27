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

    public static function getNutritionalCoefficient(int $table_id, int $max_age, bool $is_sporty): float {
        $nutritional_coefficient = 1.0;

        // Sort by age_from asc to get the best match on "Max age"
        $sort = ['age_from' => 'asc'];

        $entries = NutritionalCoefficientEntry::search(['table_id', '=', $table_id], ['sort' => $sort])
            ->read(['age_from', 'age_to', 'is_sporty', 'nutritional_coefficient'])
            ->get();

        foreach($entries as $entry) {
            if($max_age >= $entry['age_from'] && $max_age < $entry['age_to']) {
                // Match on "Age range"
                $nutritional_coefficient = $entry['nutritional_coefficient'];

                if($is_sporty && $entry['is_sporty']) {
                    // Break because complete Match on "Age range" and "Is sporty"
                    break;
                }
            }
            elseif($is_sporty && $entry['is_sporty']) {
                // Match on "Is sporty"
                $nutritional_coefficient = $entry['nutritional_coefficient'];
            }
        }

        return $nutritional_coefficient;
    }
}
