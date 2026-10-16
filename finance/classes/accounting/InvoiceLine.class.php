<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace finance\accounting;

use equal\orm\Model;

class InvoiceLine extends Model {

    public static function getName() {
        return "Invoice line";
    }

    public static function getDescription() {
        return "Invoice lines describe the products and quantities that are part of an invoice.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Default label of the line, based on product.",
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Complementary description of the line (independent from product)."
            ],

            'invoice_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\InvoiceLineGroup',
                'description'       => "Group the line relates to (in turn, groups relate to their invoice).",
                'ondelete'          => 'cascade'
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Invoice',
                'description'       => "Invoice the line is related to.",
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => "The product (SKU) the line relates to.",
                'required'          => true
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => "The price the line relates to (assigned at line creation).",
                'onupdate'          => 'onupdatePriceId'
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Unit price of the product related to the line.",
                'function'          => 'finance\accounting\InvoiceLine::calcUnitPrice',
                'store'             => true
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/rate',
                'description'       => "VAT rate to be applied.",
                'function'          => 'calcVatRate',
                'store'             => true,
                'default'           => 0.0,
                'onupdate'          => 'onupdateVatRate'
            ],

            'qty' => [
                'type'              => 'float',
                'description'       => "Quantity of product.",
                'default'           => 0,
                'onupdate'          => 'onupdateQty'
            ],

            // #memo - important: to allow the maximum flexibility, percent values can hold 4 decimal digits (must not be rounded, except for display)
            'discount' => [
                'type'              => 'float',
                'usage'             => 'amount/rate',
                'description'       => "Total amount of discount to apply, if any.",
                'default'           => 0.0,
                'onupdate'          => 'onupdateDiscount'
            ],

            'total_no_discount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total tax-excluded price of the line without the discount applied.",
                'function'          => 'calcTotalNoDiscount',
                'store'             => false
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Total tax-excluded price of the line.",
                'function'          => 'calcTotal',
                'store'             => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Final tax-included price of the line.",
                'function'          => 'calcPrice',
                'store'             => true
            ],

            'downpayment_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Invoice',
                'description'       => "Downpayment invoice (set when the line refers to an invoiced downpayment.)"
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['product_id' => ['name']]);
        foreach($self as $id => $line) {
            $result[$id] = $line['product_id']['name'];
        }

        return $result;
    }

    public static function calcUnitPrice($self): array {
        $result = [];
        $self->read(['price_id' => ['price']]);
        foreach($self as $id => $line) {
            $result[$id] = $line['price_id']['price'];
        }

        return $result;
    }

    public static function calcVatRate($self): array {
        $result = [];
        $self->read(['price_id' => ['accounting_rule_id' => ['vat_rule_id' => ['rate']]]]);
        foreach($self as $id => $line) {
            $result[$id] = floatval($line['price_id']['accounting_rule_id']['vat_rule_id']['rate'] ?? 0);
        }

        return $result;
    }

    /**
     * Get total tax-excluded price of the line before the discount is applied.
     */
    public static function calcTotalNoDiscount($self): array {
        $result = [];
        $self->read(['qty', 'unit_price']);
        foreach($self as $id => $line) {
            // #memo - total_no_discount of a line must be rounded to 2 decimals
            $result[$id] = round($line['qty'] * $line['unit_price'], 2);
        }

        return $result;
    }

    /**
     * Get total tax-excluded price of the line.
     */
    public static function calcTotal($self): array {
        $result = [];
        $self->read(['total_no_discount', 'discount']);
        foreach($self as $id => $line) {
            // #memo - total of a line must be rounded to 2 decimals
            $result[$id] = round($line['total_no_discount'] * (1.0 - $line['discount']), 2);
        }

        return $result;
    }

    /**
     * Get final tax-included price of the line.
     */
    public static function calcPrice($self): array {
        $result = [];
        $self->read(['total', 'vat_rate']);
        foreach($self as $id => $line) {
            $result[$id] = round($line['total'] * (1.0 + $line['vat_rate']), 2);
        }

        return $result;
    }

    public static function onupdatePriceId($self, $values) {
        $self
            ->update(['vat_rate' => null, 'unit_price' => null, 'total' => null, 'price' => null])
            ->do('reset-invoice-prices');
    }

    public static function onupdateVatRate($self) {
        $self
            ->update(['price' => null])
            ->do('reset-invoice-prices');
    }

    public static function onupdateQty($self) {
        $self
            ->update(['price' => null, 'total' => null])
            ->do('reset-invoice-prices');
    }

    public static function onupdateDiscount($self) {
        $self
            ->update(['price' => null, 'total' => null])
            ->do('reset-invoice-prices');
    }

    protected static function doResetInvoicePrices($self) {
        $self->read(['invoice_id']);

        $map_invoices_ids = [];
        foreach($self as $line) {
            $map_invoices_ids[$line['invoice_id']] = true;
        }

        Invoice::ids(array_keys($map_invoices_ids))
            ->update([
                'price' => null,
                'total' => null
            ]);
    }

    public static function getActions() {
        return [
            'reset-invoice-prices' => [
                'description'   => "Reset the prices of the invoices.",
                'policies'      => [],
                'function'      => 'doResetInvoicePrices'
            ]
        ];
    }
}
