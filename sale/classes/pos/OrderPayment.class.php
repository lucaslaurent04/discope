<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pos;
use equal\orm\Model;
use lodging\sale\booking\Booking;
use lodging\sale\booking\Invoice;

class OrderPayment extends Model {

    public static function getColumns() {

        return [

            'order_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pos\Order',
                'description'       => 'The order the line relates to.',
                'onupdate'          => 'onupdateOrderId',
                'ondelete'          => 'cascade'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',      // payment hasn't been validated yet
                    'paid'          // amount has been received (cannot be undone)
                ],
                'description'       => 'Current status of the payment.',
                'default'           => 'pending',
                'onupdate'          => 'onupdateStatus'
            ],

            'has_booking' => [
                'type'              => 'boolean',
                'description'       => 'Mark the payment as done using a booking.',
                'default'           => false,
                'onupdate'          => 'onupdateHasBooking'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'Booking the payment relates to.',
                'visible'           => ['has_booking', '=', true],
                'ondelete'          => 'null'
            ],

            'has_funding' => [
                'type'              => 'boolean',
                'description'       => 'Mark the line as relating to a funding.',
                'default'           => false
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Funding',
                'description'       => 'The funding the line relates to, if any.',
                'visible'           => ['has_funding', '=', true],
                'onupdate'          => 'onupdateFundingId'
            ],

            /*
                #memo - if the payment is attached to a funding, it can have only one line
            */

            'order_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pos\OrderLine',
                'foreign_field'     => 'order_payment_id',
                'ondetach'          => 'null',
                'description'       => 'The order lines selected for the payment.',
                'onupdate'          => 'onupdateOrderLinesIds'
            ],

            'order_payment_parts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pos\OrderPaymentPart',
                'foreign_field'     => 'order_payment_id',
                'description'       => 'The parts that relate to the payment.',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateOrderPaymentPartsIds'
            ],

            'total_paid' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total paid amount from payment parts.',
                'function'          => 'calcTotalPaid'
            ],

            'total_due' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total due amount (tax incl.) from selected lines.',
                'function'          => 'calcTotalDue'
            ],

            'total_change' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total due amount (tax incl.) from selected lines.',
                'function'          => 'calcTotalChange'
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Payment',
                'foreign_field'     => 'order_payment_id',
                'ondetach'          => 'null',
                'description'       => 'The payments relating to the OrderPayment (o2o : list length should be 1 or 0).'
            ],

            'is_exported' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Tells if at least one of the related payments has been exported.',
                'function'          => 'calcIsExported'
            ]

        ];
    }

    /**
     * Sync the payment with the assigned order (fields has_funding and funding_id).
     * This handled is mostly called upon creation and assignation to an order.
     */
    public static function onupdateOrderId($om, $ids, $values, $lang) {
        $payments = $om->read(self::getType(), $ids, ['order_id'], $lang);
        if($payments > 0) {
            foreach($payments as $id => $payment) {
                $orders = $om->read(Order::getType(), $payment['order_id'], ['has_funding', 'funding_id'], $lang);
                if($orders > 0) {
                    $order = reset($orders);
                    $om->update(self::getType(), $id, ['has_funding' => $order['has_funding'], 'funding_id' => $order['funding_id']], $lang);
                }
            }
        }
    }

    /**
     *
     */
    public static function onupdateStatus($om, $ids, $values, $lang) {
        /*
        $payments = $om->read(self::getType(), $ids, ['status', 'order_payment_parts_ids'], $lang);
        if($payments > 0) {
            foreach($payments as $pid => $payment) {
            }
        }
        */
    }

    public static function onupdateFundingId($om, $ids, $values, $lang) {
        $payments = $om->read(self::getType(), $ids, ['order_id', 'funding_id', 'funding_id.booking_id', 'funding_id.invoice_id'], $lang);
        if($payments > 0) {
            $map_bookings_ids = [];
            $map_invoices_ids = [];
            foreach($payments as $pid => $payment) {
                if($payment['funding_id']) {
                    $om->update(self::getType(), $pid, ['has_funding' => ($payment['funding_id'] > 0)], $lang);
                    $om->update(Order::getType(), $payment['order_id'], ['funding_id' => $payment['funding_id']], $lang);
                    if($payment['funding_id.booking_id']) {
                        $map_bookings_ids[$payment['funding_id.booking_id']] = true;
                    }
                    if($payment['funding_id.invoice_id']) {
                        $map_invoices_ids[$payment['funding_id.invoice_id']] = true;
                    }
                }
            }
            $om->update(Booking::getType(), array_keys($map_bookings_ids), ['payment_status' => null, 'paid_amount' => null], $lang);
            $om->update(Invoice::getType(), array_keys($map_invoices_ids), ['payment_status' => null, 'is_paid' => null]);
        }
    }

    public static function onupdateHasBooking($om, $ids, $values, $lang) {
        // upon update, update related order lines accordingly
        $payments = $om->read(self::getType(), $ids, ['has_booking', 'booking_id', 'order_id', 'order_lines_ids'], $lang);
        if($payments > 0) {
            foreach($payments as $oid => $payment) {
                $om->update(OrderLine::getType(), $payment['order_lines_ids'], ['has_booking' => $payment['has_booking']], $lang);
                $om->update(Order::getType(), $payment['order_id'], ['booking_id' => $payment['booking_id']], $lang);
            }
        }
    }

    public static function calcTotalPaid($om, $ids, $lang) {
        $result = [];
        $payments = $om->read(self::getType(), $ids, ['order_payment_parts_ids'], $lang);
        if($payments > 0) {
            foreach($payments as $id => $payment) {
                $result[$id] = 0.0;
                $parts = $om->read(OrderPaymentPart::getType(), $payment['order_payment_parts_ids'], ['status', 'amount'], $lang);
                foreach($parts as $part) {
                    if($part['status'] == 'paid') {
                        $result[$id] += $part['amount'];
                    }
                }
                $result[$id] = round($result[$id], 2);
            }
        }
        return $result;
    }

    public static function calcTotalDue($om, $ids, $lang) {
        $result = [];
        $payments = $om->read(self::getType(), $ids, ['order_lines_ids.price'], $lang);
        if($payments > 0) {
            foreach($payments as $oid => $payment) {
                $result[$oid] = 0.0;
                foreach((array) $payment['order_lines_ids.price'] as $line) {
                    $result[$oid] += $line['price'];
                }
                $result[$oid] = round($result[$oid], 2);
            }
        }
        return $result;
    }

    public static function calcTotalChange($om, $ids, $lang) {
        $result = [];
        $payments = $om->read(self::getType(), $ids, ['total_due', 'total_paid'], $lang);
        if($payments > 0) {
            foreach($payments as $id => $payment) {
                $result[$id] = 0.0;
                if($payment['total_due'] > 0) {
                    $result[$id] = -round($payment['total_paid'] - $payment['total_due'], 2);
                }
            }
        }
        return $result;
    }

    public static function calcIsExported($om, $ids, $lang) {
        $result = [];
        $payments = $om->read(self::getType(), $ids, ['payments_ids.is_exported']);
        foreach($payments as $id => $order_payment) {
            foreach((array) $order_payment['payments_ids.is_exported'] as $pid => $payment) {
                if($payment['is_exported']) {
                    $result[$id] = true;
                    break;
                }
            }
        }
        return $result;
    }

    public static function onupdateOrderPaymentPartsIds($om, $ids, $values, $lang) {
        $om->write(self::getType(), $ids, ['total_paid' => null], $lang);
    }

    public static function onupdateOrderLinesIds($om, $ids, $values, $lang) {
        $om->write(self::getType(), $ids, ['total_due' => null], $lang);
    }

    public static function candelete($om, $ids) {
        $payments = $om->read(self::getType(), $ids, [ 'order_id.status' ]);

        if($payments > 0) {
            foreach($payments as $id => $payment) {
                if($payment['order_id.status'] == 'paid') {
                    return ['status' => ['non_removable' => 'Payments from paid orders cannot be deleted.']];
                }
            }
        }
        // ignore parent `candelete()`
        return [];
    }
}
