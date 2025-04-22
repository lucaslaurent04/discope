<?php

namespace sale\camp;

use equal\orm\Model;

class EnrollmentLine extends Model {

    public static function getDescription(): string {
        return "One product bought for the enrollment of a child to a camp.";
    }

    public static function getColumns(): array {
        return [

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
                'required'          => true
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => "The price the line relates to (retrieved by price list).",
                'required'          => true,
                'dependencies'      => ['unit_price']
            ],

            'qty' => [
                'type'              => 'integer',
                'description'       => "Quantity of the product that is purchased.",
                'default'           => 1,
                'dependencies'      => ['total']
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Tax-excluded unit price (with automated discounts applied).",
                'store'             => true,
                'function'          => 'calcUnitPrice',
                'dependencies'      => ['total']
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Total tax-excluded price of the line (computed).",
                'store'             => true,
                'function'          => 'calcTotal',
                'dependencies'      => ['price']
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "VAT rate that applies to this line.",
                'store'             => true,
                'function'          => 'calcVatRate',
                'dependencies'      => ['price']
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Final tax-included price (computed).",
                'store'             => true,
                'function'          => 'calcPrice'
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
}
