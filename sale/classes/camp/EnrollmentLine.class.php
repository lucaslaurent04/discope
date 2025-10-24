<?php

namespace sale\camp;

use equal\orm\Model;
use sale\camp\catalog\Product;

class EnrollmentLine extends Model {

    public static function getDescription(): string {
        return "One product bought for the enrollment of a child to a camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Line name relates to its product.",
                'store'             => true,
                'relation'          => ['product_id' => 'name']
            ],

            'enrollment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => "Enrollment the line is part of.",
                'required'          => true
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product targeted by the line.",
                'required'          => true,
                'domain'            => ['is_camp', '=', true],
                'onupdate'          => 'onupdateProductId'
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\price\Price',
                'description'       => "The price the line relates to (retrieved by price list).",
                'required'          => true,
                'domain'            => ['product_id', '=', 'object.product_id'],
                'onupdate'          => 'onupdatePriceId'
            ],

            'qty' => [
                'type'              => 'integer',
                'description'       => "Quantity of the product that is purchased.",
                'default'           => 1,
                'onupdate'          => 'onupdateQty'
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Tax-excluded unit price (with automated discounts applied).",
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcUnitPrice',
                'onupdate'          => 'onupdateUnitPrice'
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Total tax-excluded price of the line (computed).",
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcTotal',
                'onupdate'          => 'onupdateTotal'
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "VAT rate that applies to this line.",
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcVatRate',
                'onupdate'          => 'onupdateVatRate'
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Final tax-included price (computed).",
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcPrice',
                'onupdate'          => 'onupdatePrice'
            ]

        ];
    }

    public static function calcUnitPrice($self): array {
        $result = [];
        $self->read(['price_id' => ['price']]);
        foreach($self as $id => $enrollment_line) {
            if(isset($enrollment_line['price_id']['price'])) {
                $result[$id] = $enrollment_line['price_id']['price'];
            }
            else {
                $result[$id] = 0.0;
            }
        }

        return $result;
    }

    public static function calcTotal($self): array {
        $result = [];
        $self->read(['unit_price', 'qty']);
        foreach($self as $id => $enrollment_line) {
            if(isset($enrollment_line['unit_price'], $enrollment_line['qty'])) {
                $result[$id] = round($enrollment_line['unit_price'] * $enrollment_line['qty'], 4);
            }
            else {
                $result[$id] = 0.0;
            }
        }

        return $result;
    }

    public static function calcVatRate($self): array {
        $result = [];
        $self->read(['price_id' => ['accounting_rule_id' => ['vat_rule_id' => ['rate']]]]);
        foreach($self as $id => $enrollment_line) {
            if(isset($enrollment_line['price_id']['accounting_rule_id']['vat_rule_id']['rate'])) {
                $result[$id] = $enrollment_line['price_id']['accounting_rule_id']['vat_rule_id']['rate'];
            }
            else {
                $result[$id] = 0.0;
            }
        }

        return $result;
    }

    public static function calcPrice($self): array {
        $result = [];
        $self->read(['total', 'vat_rate']);
        foreach($self as $id => $enrollment_line) {
            if(isset($enrollment_line['total'], $enrollment_line['vat_rate'])) {
                $result[$id] = round($enrollment_line['total'] * (1.0 + $enrollment_line['vat_rate']), 2);
            }
            else {
                $result[$id] = 0.0;
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

    public static function onupdateQty($self) {
        $self->update(['total' => null, 'price' => null]);

        $self->do('reset-enrollments-prices');
    }

    public static function onupdateProductId($self) {
        $camp_products_enrollments_ids = [];

        $self->read(['enrollment_id', 'product_id' => ['camp_product_type']]);
        foreach($self as $enrollment_line) {
            if(in_array($enrollment_line['product_id']['camp_product_type'], ['full', 'day'])) {
                $camp_products_enrollments_ids[] = $enrollment_line['enrollment_id'];
            }
        }

        if(!empty($camp_products_enrollments_ids)) {
            Enrollment::ids($camp_products_enrollments_ids)->do('refresh_camp_product_line');
        }
    }

    public static function onupdatePriceId($self) {
        $self->update(['unit_price' => null, 'total' => null, 'price' => null]);

        $self->do('reset-enrollments-prices');
    }

    public static function onupdateUnitPrice($self) {
        $self->update(['total' => null, 'price' => null]);

        $self->do('reset-enrollments-prices');
    }

    public static function onupdateTotal($self) {
        $self->update(['price' => null]);

        $self->do('reset-enrollments-prices');
    }

    public static function onupdateVatRate($self) {
        $self->update(['price' => null]);

        $self->do('reset-enrollments-prices');
    }

    public static function onupdatePrice($self) {
        $self->do('reset-enrollments-prices');
    }

    public static function ondelete($self) {
        $self->do('reset-enrollments-prices');
    }

    public static function canupdate($self, $values): array {
        $self->read(['enrollment_id' => ['is_locked']]);
        foreach($self as $enrollment_line) {
            if($enrollment_line['enrollment_id']['is_locked']) {
                return ['enrollment_id' => ['locked_enrollment' => "Cannot modify a line of a locked enrollment."]];
            }
        }

        if(isset($values['product_id'])) {
            $new_product = Product::id($values['product_id'])
                ->read(['camp_product_type'])
                ->first();

            $self->read(['product_id' => ['camp_product_type']]);
            foreach($self as $enrollment_line) {
                if($enrollment_line['product_id']['camp_product_type'] !== $new_product['camp_product_type']) {
                    return ['product_id' => ['invalid_camp_product_type' => "Camp product is not matching the current camp product type."]];
                }
            }
        }

        return parent::canupdate($self, $values);
    }
}
