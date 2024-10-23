<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pos;
use equal\orm\Model;

class OrderLine extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'The name of the good or service being sold.'
            ],

            'order_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pos\Order',
                'description'       => 'The operation the payment relates to.',
                'onupdate'          => 'onupdateOrderId',
                'ondelete'          => 'cascade'
            ],

            'order_payment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pos\OrderPayment',
                'description'       => 'The payment the line relates to.',
                'default'           => 0,
                'ondelete'          => 'null'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'The product (SKU) the line relates to.',
                'onupdate'          => 'onupdateProductId'
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price the line relates to (retrieved by price list).'
            ],

            'has_funding' => [
                'type'              => 'boolean',
                'description'       => 'Mark the line as relating to a funding.',
                'default'           => false
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Funding',
                'description'       => 'The funding the line relates to, if any.',
                'onupdate'          => 'onupdateFundingId',
                'visible'           => ['has_funding', '=', true]
            ],

            'has_booking' => [
                'type'              => 'boolean',
                'description'       => 'Mark the line as paid using a booking.',
                'default'           => false
            ],

            'unit_price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Tax-excluded unit price (with automated discounts applied).',
                'onupdate'          => '_resetPrice'
            ],

            'vat_rate' => [
                'type'              => 'float',
                'usage'             => 'amount/percent',
                'description'       => 'VAT rate that applies to this line.',
                'default'           => 0.0,
                'onupdate'          => '_resetPrice'
            ],

            'discount' => [
                'type'              => 'float',
                'usage'             => 'amount/percent',
                'description'       => 'Discount rate to apply on the unit price.',
                'default'           => 0.0,
                'onupdate'          => '_resetPrice'
            ],

            'qty' => [
                'type'              => 'integer',
                'usage'             => 'numeric/integer',
                'description'       => 'Amount of units in this line.',
                'default'           => 0.0,
                'onupdate'          => '_resetPrice'
            ],

            'free_qty' => [
                'type'              => 'integer',
                'usage'             => 'numeric/integer',
                'description'       => 'Amount of freebies in this line.',
                'default'           => 0.0,
                'onupdate'          => '_resetPrice'
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the line (computed).',
                'function'          => 'calcTotal',
                'store'             => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money',
                'description'       => 'Final tax-included price of the line (computed).',
                'function'          => 'calcPrice',
                'store'             => true
            ]

        ];
    }

    /**
     * Update parent Order for lines referring to a funding.
     */
    public static function onupdateOrderId($om, $ids, $values, $lang) {
        $lines = $om->read(self::getType(), $ids, ['order_id', 'funding_id'], $lang);
        if($lines > 0) {
            foreach($lines as $id => $line) {
                // #memo - this triggers Order::onupdateFundingId (on which depends has_funding)
                $om->update(Order::getType(), $line['order_id'], ['funding_id' => $line['funding_id']], $lang);
            }
        }
    }

    public static function onupdateProductId($om, $ids, $values, $lang) {
        $lines = $om->read(self::getType(), $ids, ['product_id', 'order_id.center_id.price_list_category_id']);

        foreach($lines as $lid => $line) {
            /*
                Find the first Price List that matches the criteria from the order with (shortest duration first)
            */
            $price_lists_ids = $om->search(
                'sale\price\PriceList', [
                ['price_list_category_id', '=', $line['order_id.center_id.price_list_category_id']],
                ['date_from', '<=', time()],
                ['date_to', '>=', time()],
                ['status', '=', 'published'],
                ['is_active', '=', true]
            ],
                ['date_from' => 'desc', 'duration' => 'asc']
            );

            $found = false;

            if($price_lists_ids > 0 && count($price_lists_ids)) {
                /*
                    Search for a matching Price within the found Price List
                */
                foreach($price_lists_ids as $price_list_id) {
                    // there should be one or zero matching pricelist with status 'published', if none of the found pricelist
                    $prices_ids = $om->search(\sale\price\Price::getType(), [ ['price_list_id', '=', $price_list_id], ['product_id', '=', $line['product_id']] ]);
                    if($prices_ids > 0 && count($prices_ids)) {
                        /*
                            Assign found Price to current line
                        */
                        $prices = $om->read(\sale\price\Price::getType(), $prices_ids, ['id', 'price', 'vat_rate']);
                        $price = reset($prices);
                        // set unit_price and vat_rate from found price
                        $om->update(self::getType(), $lid, ['price_id' => $price['id'], 'unit_price' => $price['price'], 'vat_rate' => $price['vat_rate']]);
                        $found = true;
                        break;
                    }
                }
            }
            if(!$found) {
                $date = date('Y-m-d', time());
                trigger_error("ORM::no matching price list found for product {$line['product_id']} for date {$date}", QN_REPORT_WARNING);
            }
        }
    }

    public static function onupdateFundingId($om, $ids, $values, $lang) {
        $lines = $om->read(self::getType(), $ids, ['order_id', 'has_funding', 'funding_id', 'funding_id.booking_id.customer_id'], $lang);
        if($lines > 0) {
            foreach($lines as $id => $line) {
                // #memo - this triggers Order::onupdateFundingId (on which depends has_funding)
                $om->update(Order::getType(), $line['order_id'], ['funding_id' => $line['funding_id'], 'customer_id' => $line['funding_id.booking_id.customer_id']], $lang);
                $om->update(self::getType(), $id, ['has_funding' => ($line['funding_id'] > 0)], $lang);
            }
        }
    }

    public static function candelete($om, $ids) {
        $lines = $om->read(self::getType(), $ids, [ 'order_id.status' ]);

        if($lines > 0) {
            foreach($lines as $id => $line) {
                if($line['order_id.status'] == 'paid') {
                    return ['status' => ['non_removable' => 'Lines from paid orders cannot be deleted.']];
                }
            }
        }
        // ignore parent `candelete()`
        return [];
    }

    public static function calcTotal($om, $ids, $lang) {
        $result = [];
        $lines = $om->read(self::getType(), $ids, ['unit_price', 'qty', 'free_qty', 'discount']);
        if($lines > 0) {
            foreach($lines as $lid => $line) {
                $result[$lid] = round(($line['unit_price'] * (1 - $line['discount'])) * ($line['qty'] - $line['free_qty']), 4);
            }
        }
        return $result;
    }

    public static function calcPrice($om, $ids, $lang) {
        $result = [];
        $lines = $om->read(self::getType(), $ids, ['total', 'vat_rate']);
        if($lines > 0) {
            foreach($lines as $lid => $line) {
                $result[$lid] = round($line['total'] * (1 + $line['vat_rate']), 2);
            }
        }
        return $result;
    }

    /**
     * Recompute the vat_incl and vat_excl prices of the line.
     * This method is used as onupdate handler for all fields impacting the price.
     */
    public static function _resetPrice($om, $ids, $values, $lang) {
        $lines = $om->read(self::getType(), $ids, ['order_id'], $lang);
        if($lines > 0) {
            $orders_ids = array_map(function ($a) {return $a['order_id'];}, $lines);
            $om->write(Order::getType(), $orders_ids, ['total' => null, 'price' => null], $lang);
        }
        $om->write(self::getType(), $ids, ['total' => null, 'price' => null], $lang);
    }
}
