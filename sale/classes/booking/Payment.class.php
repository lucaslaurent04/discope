<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;

use sale\booking\Booking;
use sale\booking\Funding;
use sale\booking\Invoice;
use sale\camp\Enrollment;

class Payment extends \sale\pay\Payment {

    public static function getColumns() {

        return [

            'booking_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcBookingId',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'The booking the payment relates to, if any (computed).',
                'store'             => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Funding',
                'description'       => 'The funding the payment relates to, if any.',
                'onupdate'          => 'onupdateFundingId'
            ],

            'center_office_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'function'          => 'calcCenterOfficeId',
                'description'       => 'Center office related to the statement (from statement_line_id).',
                'store'             => true
            ],

            'statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BankStatementLine',
                'description'       => 'The bank statement line the payment relates to, if any.',
                'visible'           => [ ['payment_origin', '=', 'bank'] ],
                'onupdate'          => 'onupdateStatementLineId'
            ],

            'order_payment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pos\OrderPayment',
                'description'       => 'The order payment the payment relates to, if any.',
                'visible'           => [ ['payment_origin', '=', 'cashdesk'] ]
            ],

            'payment_origin' => [
                'type'              => 'string',
                'selection'         => [
                    'cashdesk',             // money was received at the cashdesk
                    'bank',                 // money was received on a bank account
                    'online'                // money was received online, through a PSP
                ],
                'description'       => "Origin of the received money.",
                'default'           => 'bank'
            ],

            'payment_method' => [
                'type'              => 'string',
                'selection'         => [
                    'cash',                 // cash money
                    'bank_card',            // electronic payment with bank (or credit) card
                    'booking',              // payment through addition to the final (balance) invoice of a specific booking
                    'voucher',              // gift, coupon, or tour-operator voucher
                    'bank_check',           // physical bank check
                    'wire_transfer',        // transfer between bank accounts
                    'financial_help'        // a financial help will take care of the payment
                ],
                'description'       => "The method used for payment at the cashdesk.",
                'visible'           => [ ['payment_origin', '=', 'cashdesk'] ],
                'default'           => 'cash'
            ],

            'has_psp' => [
                'type'              => 'boolean',
                'description'       => 'Flag to tell payment was done through a Payment Service Provider.',
                'default'           => false
            ],

            'psp_fee_amount' => [
                'type'              => 'float',
                'description'       => 'Amount of the fee of the Service Provider.'
            ],

            'psp_fee_currency' => [
                'type'              => 'string',
                'description'       => 'Currency of the PSP fee amount.',
                'default'           => 'EUR'
            ],

            'psp_type' => [
                'type'              => 'string',
                'description'       => 'Identification string of the payment service provider (ex. \'stripe\').'
            ],

            'psp_ref' => [
                'type'              => 'string',
                'description'       => 'Reference allowing to retrieve the payment details from PSP.'
            ],

            'bank_check_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BankCheck',
                'description'       => 'The BankCheck associated with the payment.'
            ],

            'financial_help_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\FinancialHelp',
                'description'       => "The financial help that takes care of the payment.",
                'visible'           => ['payment_method', '=', 'financial_help']
            ]

        ];
    }


    public static function calcBookingId($om, $ids, $lang) {
        $result = [];
        $payments = $om->read(self::getType(), $ids, ['funding_id.booking_id']);
        foreach($payments as $id => $payment) {
            if(isset($payment['funding_id.booking_id'])) {
                $result[$id] = $payment['funding_id.booking_id'];
            }
        }
        return $result;
    }

    public static function calcCenterOfficeId($om, $ids, $lang) {
        $result = [];
        $payments = $om->read(self::getType(), $ids, ['statement_line_id.center_office_id']);
        if($payments > 0 && count($payments)) {
            foreach($payments as $id => $payment) {
                if(isset($payment['statement_line_id.center_office_id'])) {
                    $result[$id] = $payment['statement_line_id.center_office_id'];
                }
            }
        }
        return $result;
    }

    public static function onupdateStatementLineId($om, $ids, $values, $lang) {
        $payments = $om->read(self::getType(), $ids, ['state', 'statement_line_id.bank_statement_id', 'statement_line_id.remaining_amount']);
        if($payments > 0 && count($payments)) {
            foreach($payments as $id => $payment) {
                $om->update(self::getType(), $id, ['state' => $payment['state'], 'amount' => $payment['statement_line_id.remaining_amount']]);
                // #memo - status of BankStatement is computed from statement lines, and status of BankStatementLine depends on payments
                $om->update(\sale\booking\BankStatement::getType(), $payment['statement_line_id.bank_statement_id'], ['status' => null]);
            }
        }
    }

    /**
     * Check newly assigned funding and create an invoice for long term downpayments.
     * From an accounting perspective, if a downpayment has been received and is not related to an invoice yet,
     * it must relate to a service that will be delivered within the current year.
     * If the service will be delivered the downpayment is converted into an invoice.
     *
     * #memo - This cannot be undone.
     */
    public static function onupdateFundingId($om, $ids, $values, $lang) {
        $payments = $om->read(self::getType(), $ids, ['funding_id', 'funding_id.booking_id', 'funding_id.enrollment_id', 'funding_id.invoice_id', 'funding_id.booking_id.date_from', 'funding_id.type']);

        if($payments > 0) {
            $map_bookings_ids = [];
            $map_enrollments_ids = [];
            $map_invoices_ids = [];
            foreach($payments as $pid => $payment) {
                if($payment['funding_id']) {
                    if($payment['funding_id.booking_id']) {
                        $map_bookings_ids[$payment['funding_id.booking_id']] = true;
                        $current_year_last_day = mktime(0, 0, 0, 12, 31, date('Y'));
                        if($payment['funding_id.type'] != 'invoice' && $payment['funding_id.booking_id.date_from'] > $current_year_last_day) {
                            // if payment relates to a funding attached to a booking that will occur after the 31st of december of current year, convert the funding to an invoice
                            // #memo #waiting - to be confirmed
                            // $om->callonce(Funding::getType(), '_convertToInvoice', $payment['funding_id']);
                        }
                        // update booking_id
                        $om->update(self::getType(), $pid, ['booking_id' => $payment['funding_id.booking_id']]);
                    }
                    elseif($payment['funding_id.enrollment_id']) {
                        $map_enrollments_ids[$payment['funding_id.enrollment_id']] = true;
                        // update enrollment_id
                        $om->update(self::getType(), $pid, ['enrollment_id' => $payment['funding_id.enrollment_id']]);
                    }
                    if($payment['funding_id.invoice_id']) {
                        $map_invoices_ids[$payment['funding_id.invoice_id']] = true;
                    }
                    $om->update(Funding::getType(), $payment['funding_id'], ['paid_amount' => null, 'is_paid' => null], $lang);
                }
                else {
                    // void booking_id, enrollment_id
                    $om->update(self::getType(), $ids, ['booking_id' => null, 'enrollment_id' => null]);
                }
            }
            $bookings_ids = array_keys($map_bookings_ids);
            if(!empty($bookings_ids)) {
                $om->callonce(Booking::getType(), 'updateStatusFromFundings', $bookings_ids, [], $lang);
                $om->update(Booking::getType(), $bookings_ids, ['payment_status' => null, 'paid_amount' => null], $lang);
            }
            $enrollments_ids = array_keys($map_enrollments_ids);
            if(!empty($enrollments_ids)) {
                $om->update(Enrollment::getType(), $enrollments_ids, ['payment_status' => null, 'paid_amount' => null], $lang);
            }
            $invoices_ids = array_keys($map_invoices_ids);
            if(!empty($invoices_ids)) {
                $om->update(Invoice::getType(), $invoices_ids, ['is_paid' => null]);
            }
        }
    }

    /**
     * Signature for single object change from views.
     *
     * @param  Object   $om        Object Manager instance.
     * @param  array    $event     Associative array holding changed fields as keys, and their related new values.
     * @param  array    $values    Copy of the current (partial) state of the object.
     * @param  string   $lang      Language (char 2) in which multilang field are to be processed.
     * @return array    Associative array mapping fields with their resulting values.
     */
    public static function onchange($om, $event, $values, $lang='en') {
        $result = [];
        if(isset($event['funding_id'])) {
            $fundings = $om->read(Funding::getType(), $event['funding_id'],
                [
                    'type',
                    'due_amount',
                    'booking_id',
                    'booking_id.name',
                    'booking_id.customer_id.id',
                    'booking_id.customer_id.name',
                    'enrollment_id',
                    'enrollment_id.name',
                    'invoice_id.partner_id.id',
                    'invoice_id.partner_id.name'
                ],
                $lang
            );

            if($fundings > 0) {
                $funding = reset($fundings);

                if($funding['booking_id']) {
                    $result['booking_id'] = [ 'id' => $funding['booking_id'], 'name' => $funding['booking_id.name'] ];
                    $result['enrollment_id'] = null;
                }
                elseif($funding['enrollment_id']) {
                    $result['enrollment_id'] = [ 'id' => $funding['enrollment_id'], 'name' => $funding['enrollment_id.name'] ];
                    $result['booking_id'] = null;
                }
                else {
                    $result['booking_id'] = null;
                    $result['enrollment_id'] = null;
                }

                if($funding['type'] == 'invoice')  {
                    $result['partner_id'] = [ 'id' => $funding['invoice_id.partner_id.id'], 'name' => $funding['invoice_id.partner_id.name'] ];
                }
                else {
                    $result['partner_id'] = [ 'id' => $funding['booking_id.customer_id.id'], 'name' => $funding['booking_id.customer_id.name'] ];
                }

                // set the amount according to the funding due_amount (the maximum assignable)
                $max = $funding['due_amount'];
                if(isset($values['amount']) && $values['amount'] < $max ) {
                    $max = $values['amount'];
                }
                $result['amount'] = $max;
            }
        }
        return $result;
    }


    public static function getConstraints() {
        return parent::getConstraints();
    }

    /**
     * Check whether the payment can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  Object   $om         ObjectManager instance.
     * @param  array    $ids        List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang='en') {
        /*
        // #memo - we cannot prevent payment assignment based on the expected amount of the funding: if amount received is higher, it will be accounted to the amount paid and regulated at the invoicing of the booking.
        if(isset($values['funding_id'])) {
            $fundings = $om->read(Funding::getType(), $values['funding_id'], ['due_amount'], $lang);
            if($fundings > 0 && count(($fundings))) {
                $funding = reset($fundings);
                if(isset($values['amount'])) {
                    if($values['amount'] > $funding['due_amount']) {
                        return ['amount' => ['excessive_amount' => 'Payment amount cannot be higher than selected funding\'s amount.']];
                    }
                }
                else {
                    $payments = $om->read(self::getType(), $ids, ['amount'], $lang);
                    foreach($payments as $pid => $payment) {
                        if($payment['amount'] > $funding['due_amount']) {
                            return ['amount' => ['excessive_amount' => 'Payment amount cannot be higher than selected funding\'s amount.']];
                        }
                    }
                }
            }
        }
        else if(isset($values['amount'])) {
            $payments = $om->read(self::getType(), $ids, ['amount', 'funding_id', 'funding_id.due_amount'], $lang);
            foreach($payments as $pid => $payment) {
                if($payment['funding_id'] && $payment['amount'] > $payment['funding_id.due_amount']) {
                    return ['amount' => ['excessive_amount' => 'Payment amount cannot be higher than selected funding\'s amount.']];
                }
            }

        }
        */

        // assigning the payment to another funding is allowed at all time
        if(count($values) == 1 && isset($values['funding_id'])) {
            return [];
        }

        return parent::canupdate($om, $ids, $values, $lang);
    }
}
