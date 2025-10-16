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
                'default'           => true,
                'visible'           => ['sponsor_id', '=', null]
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
                    'commune',
                    'community-of-communes',
                    'department-caf',
                    'department-msa',
                    'loyalty-discount'
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
                'type'              => 'float',
                'description'       => "Amount/percentage to remove to the enrollment price.",
                'onupdate'          => 'onupdateValue'
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
        if(array_key_exists('sponsor_id', $event)) {
            if(is_null($event['sponsor_id'])) {
                $result['is_manual_discount'] = true;
            }
            else {
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

    public static function onupdateValue($self) {
        $self->do('reset-enrollments-prices');
    }

    public static function canupdate($self, $values) {
        $self->read(['sponsor_id', 'price_adapter_type', 'is_manual_discount', 'origin_type', 'enrollment_id']);

        foreach($self as $price_adapter) {
            $enrollment_id = $values['enrollment_id'] ?? $price_adapter['enrollment_id'];

            $enrollment = Enrollment::id($enrollment_id)
                ->read(['status'])
                ->first();

            if($enrollment['status'] !== 'pending') {
                return ['enrollment_id' => ['invalid_enrollment_status' => "Enrollment must be pending to add/modify price adapters."]];
            }
        }

        if(isset($values['price_adapter_type']) || isset($values['origin_type'])) {
            foreach($self as $price_adapter) {
                $origin_type = $values['origin_type'] ?? $price_adapter['origin_type'];
                $price_adapter_type = $values['price_adapter_type'] ?? $price_adapter['price_adapter_type'];
                if(in_array($origin_type, ['commune', 'community-of-communes', 'department-caf', 'department-msa']) && $price_adapter_type !== 'amount') {
                    return ['price_adapter_type' => ['must_be_amount_financial_help' => "Must be amount when origin is financial help."]];
                }
            }
        }

        if(isset($values['sponsor_id']) || isset($values['price_adapter_type']) || isset($values['is_manual_discount']) || isset($values['origin_type'])) {
            foreach($self as $price_adapter) {
                $origin_type = $values['origin_type'] ?? $price_adapter['origin_type'];
                $price_adapter_type = $values['price_adapter_type'] ?? $price_adapter['price_adapter_type'];
                if(!in_array($origin_type, ['other', 'loyalty-discount']) && $price_adapter_type  === 'percent') {
                    return ['price_adapter_type' => ['must_be_loyalty_discount' => "Must be loyalty discount when percent type."]];
                }
            }

            foreach($self as $price_adapter) {
                $sponsor_id = array_key_exists('sponsor_id', $values) ? $values['sponsor_id'] : $price_adapter['sponsor_id'];
                if(is_null($sponsor_id)) {
                    continue;
                }

                $price_adapter_type = $values['price_adapter_type'] ?? $price_adapter['price_adapter_type'];
                if($price_adapter_type !== 'amount') {
                    return ['price_adapter_type' => ['must_be_amount' => "Must be amount when from a sponsor."]];
                }

                $is_manual_discount = $values['is_manual_discount'] ?? $price_adapter['is_manual_discount'];
                if($is_manual_discount !== false) {
                    return ['is_manual_discount' => ['cannot_be_manual' => "A price adapter from a sponsor cannot be manual."]];
                }

                $sponsor = Sponsor::id($sponsor_id)
                    ->read(['sponsor_type'])
                    ->first();

                $origin_type = $values['origin_type'] ?? $price_adapter['origin_type'];
                if($origin_type !== $sponsor['sponsor_type']) {
                    return ['origin_type' => ['same_as_sponsor' => "Type must be the same as the linked sponsor."]];
                }
            }
        }

        if(isset($values['price_adapter_type']) && $values['price_adapter_type'] === 'percent') {
            foreach($self as $id => $price_adapter) {
                $enrollment_id = $values['enrollment_id'] ?? $price_adapter['enrollment_id'];

                $other_percent_adapter = PriceAdapter::search([
                    ['enrollment_id', '=', $enrollment_id],
                    ['price_adapter_type', '=', 'percent'],
                    ['id', '<>', $id]
                ])
                    ->read(['id'])
                    ->first();

                if(!is_null($other_percent_adapter)) {
                    return ['price_adapter_type' => ['already_percent' => "Only one 'percent' price adapter is allowed for an enrollment."]];
                }
            }
        }

        return parent::canupdate($self, $values);
    }

    public static function ondelete($self) {
        $self->do('reset-enrollments-prices');
    }
}
