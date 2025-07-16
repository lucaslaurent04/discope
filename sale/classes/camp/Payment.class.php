<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

class Payment extends \sale\pay\Payment {

    public static function getColumns() {

        return [

            'enrollment_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcEnrollmentId',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => 'The enrollment the payment relates to, if any (computed).',
                'store'             => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Funding',
                'description'       => 'The funding the payment relates to, if any.',
                'onupdate'          => 'onupdateFundingId',
                'dependents'        => ['enrollment_id' => ['payment_status', 'paid_amount']]
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
                'foreign_object'    => 'sale\pay\BankStatementLine',
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
                    // money was received at the cashdesk
                    'cashdesk',
                    // money was received on a bank account
                    'bank'
                ],
                'description'       => "Origin of the received money.",
                'default'           => 'bank'
            ],

            'payment_method' => [
                'type'              => 'string',
                'selection'         => [
                    'cash',                 // cash money
                    'bank_card',            // electronic payment with bank (or credit) card
                    'voucher',              // gift, coupon, or tour-operator voucher
                    'bank_check',           // physical bank check
                    // TODO: handle financial help
                ],
                'description'       => "The method used for payment at the cashdesk.",
                'visible'           => [ ['payment_origin', '=', 'cashdesk'] ],
                'default'           => 'cash'
            ],

            'bank_check_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\BankCheck',
                'description'       => 'The BankCheck associated with the payment.'
            ]

        ];
    }


    public static function calcEnrollmentId($om, $ids, $lang) {
        $result = [];
        $payments = $om->read(self::getType(), $ids, ['funding_id.enrollment_id']);
        foreach($payments as $id => $payment) {
            if(isset($payment['funding_id.enrollment_id'])) {
                $result[$id] = $payment['funding_id.enrollment_id'];
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
        $payments = $om->read(self::getType(), $ids, ['funding_id', 'funding_id.enrollment_id', 'funding_id.invoice_id', 'funding_id.enrollment_id.camp_id.date_from', 'funding_id.type']);

        if($payments > 0) {
            $map_enrollments_ids = [];
            // $map_invoices_ids = [];
            foreach($payments as $pid => $payment) {
                if($payment['funding_id']) {
                    if($payment['funding_id.enrollment_id']) {
                        $map_enrollments_ids[$payment['funding_id.enrollment_id']] = true;
                        $current_year_last_day = mktime(0, 0, 0, 12, 31, date('Y'));
                        if($payment['funding_id.type'] != 'invoice' && $payment['funding_id.enrollment_id.camp_id.date_from'] > $current_year_last_day) {
                            // if payment relates to a funding attached to a booking that will occur after the 31st of december of current year, convert the funding to an invoice
                            // #memo #waiting - to be confirmed
                            // $om->callonce(Funding::getType(), '_convertToInvoice', $payment['funding_id']);
                        }
                        // update enrollment_id
                        $om->update(self::getType(), $pid, ['enrollment_id' => $payment['funding_id.enrollment_id']]);
                    }
                    /*if($payment['funding_id.invoice_id']) {
                        $map_invoices_ids[$payment['funding_id.invoice_id']] = true;
                    }*/
                    $om->update(Funding::getType(), $payment['funding_id'], ['paid_amount' => null, 'is_paid' => null], $lang);
                }
                else {
                    // void enrollment_id
                    $om->update(self::getType(), $ids, ['enrollment_id' => null]);
                }
            }
            // $om->callonce(Enrollment::getType(), 'updateStatusFromFundings', array_keys($map_enrollments_ids), [], $lang);
            // $om->update(Enrollment::getType(), array_keys($map_enrollments_ids), ['payment_status' => null, 'paid_amount' => null], $lang);
            // $om->update(Invoice::getType(), array_keys($map_invoices_ids), ['is_paid' => null]);
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
                    'invoice_id.partner_id.id',
                    'invoice_id.partner_id.name'
                ],
                $lang
            );

            if($fundings > 0) {
                $funding = reset($fundings);
                $result['booking_id'] = [ 'id' => $funding['booking_id'], 'name' => $funding['booking_id.name'] ];
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
