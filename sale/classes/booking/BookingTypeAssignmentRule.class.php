<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use equal\orm\Model;

class BookingTypeAssignmentRule extends Model {

    public static function getDescription(): string {
        return "A set of rules that, if matched, will apply a specific booking type to a booking.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name the the booking type assignment rule.",
                'store'             => true,
                'function'          => 'calcName'
            ],

            'booking_type_assignment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingTypeAssignment',
                'description'       => "The booking type assignment the rule is part of.",
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
                'description'       => "The field name on which the rule check is done.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'operator' => [
                'type'              => 'string',
                'selection'         => ['=', '>', '>=', '<', '<='],
                'description'       => "The operand used the check the rule.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'value' => [
                'type'              => 'string',
                'description'       => "The value with which the rule check is done.",
                'required'          => true,
                'dependents'        => ['name']
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['operand', 'operator', 'value']);
        foreach($self as $id => $rule) {
            if(!empty($rule['operand']) || !empty($rule['operator']) || !empty($rule['value'])) {
                $name = sprintf(
                    '%s %s %s',
                    $rule['operand'] ?? '',
                    $rule['operator'] ?? '',
                    $rule['value'] ?? ''
                );

                $result[$id] = trim($name);
            }
        }

        return $result;
    }
}

