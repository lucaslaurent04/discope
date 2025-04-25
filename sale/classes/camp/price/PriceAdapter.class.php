<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp\price;

use equal\orm\Model;
use sale\camp\Enrollment;
use sale\camp\Sponsor;

class PriceAdapter extends Model {

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "The name of the price adapter.",
                'required'          => true
            ],

            'enrollment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => "Enrollment the line is part of.",
                'required'          => true
            ],

            'is_manual_discount' => [
                'type'              => 'boolean',
                'description'       => "Flag to set the adapter as manual or related to a discount.",
                'default'           => true
            ],

            'price_adapter_type' => [
                'type'              => 'string',
                'selection'         => [
                    'percent',
                    'amount'
                ],
                'description'       => "Type of manual discount (fixed amount or percentage of the price).",
                'visible'           => ['is_manual_discount', '=', true],
                'default'           => 'amount'
            ],

            'origin_type' => [
                'type'              => 'string',
                'selection'         => [
                    'other',
                    'help-commune',
                    'help-community-of-communes',
                    'help-department-caf',
                    'help-department-msa',
                    'loyalty-discount',
                    'ce'
                ],
                'description'       => "Type of the price adapter.",
                'default'           => 'other'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Additional information about the price adapter."
            ],

            'value' => [
                'type'              => 'compute',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Amount/percentage to remove to the enrollment price.",
                'store'             => true,
                'function'          => 'calcAmount',
                'onupdate'          => 'onupdateAmount'
            ],

            'sponsor_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Sponsor',
                'description'       => "The origin of the price adapter."
            ]

        ];
    }

    /**
     * Apply sponsor data to price adapter.
     */
    public static function onchange($event, $values): array {
        $result = [];
        if(isset($event['sponsor_id'])) {
            $sponsor = Sponsor::id($event['sponsor_id'])
                ->read(['name', 'amount', 'sponsor_type'])
                ->first();

            $result['value'] = $sponsor['amount'];
            $result['origin_type'] = $sponsor['sponsor_type'];
            $result['price_adapter_type'] = 'amount';
            $result['is_manual_discount'] = false;
            if(empty($values['name'])) {
                $result['name'] = $sponsor['name'];
            }
        }

        return $result;
    }

    public static function getActions(): array {
        return [

            'reset-enrollments-prices' => [
                'description'   => "Reset the enrollments prices fields values so they can be re-calculated.",
                'policies'      => [],
                'function'      => 'doResetEnrollmentsPrices'
            ]

        ];
    }

    public static function doResetEnrollmentsPrices($self) {
        $self->read(['enrollment_id']);

        $map_enrollment_ids = [];
        foreach($self as $enrollment_line) {
            $map_enrollment_ids[$enrollment_line['enrollment_id']] = true;
        }

        Enrollment::ids(array_keys($map_enrollment_ids))
            ->update([
                'total' => null,
                'price' => null
            ]);
    }

    public static function onupdateAmount($self) {
        $self->do('reset-enrollments-prices');
    }

    public static function calcAmount($self): array {
        $result = [];
        $self->read(['sponsor_id' => ['amount']]);
        foreach($self as $id => $price_adapter) {
            if(isset($price_adapter['sponsor_id']['amount'])) {
                $result[$id] = $price_adapter['sponsor_id']['amount'];
            }
        }

        return $result;
    }
}
