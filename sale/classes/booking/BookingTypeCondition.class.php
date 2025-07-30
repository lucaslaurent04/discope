<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use equal\orm\Model;

class BookingTypeCondition extends Model {

    public static function getDescription(): string {
        return "A condition part of a booking type attribution, allows to check a booking or a group.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name the the booking type attribution condition.",
                'store'             => true,
                'function'          => 'calcName'
            ],

            'booking_type_attribution_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingTypeAttribution',
                'description'       => "The booking type attribution the condition is part of.",
                'required'          => true
            ],

            'operand' => [
                'type'              => 'string',
                'selection'         => [
                    'nb_pers',
                    'nb_children',
                    'nb_adults',
                    'is_from_channelmanager',
                    'is_sojourn'
                ],
                'description'       => "The field name on which the condition check is done.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'operator' => [
                'type'              => 'string',
                'selection'         => ['=', '>', '>=', '<', '<='],
                'description'       => "The operand used the check the condition.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'value' => [
                'type'              => 'string',
                'description'       => "The value with which the condition check is done.",
                'required'          => true,
                'dependents'        => ['name']
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['operand', 'operator', 'value']);
        foreach($self as $id => $condition) {
            if(!empty($condition['operand']) || !empty($condition['operator']) || !empty($condition['value'])) {
                $name = sprintf(
                    '%s %s %s',
                    $condition['operand'] ?? '',
                    $condition['operator'] ?? '',
                    $condition['value'] ?? ''
                );

                $result[$id] = trim($name);
            }
        }

        return $result;
    }
}

