<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\pay;

use equal\orm\Model;
use sale\camp\Enrollment;

class Payment extends Model {

    public static function getColumns() {
        return [

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'description'       => "The partner to whom the payment relates."
            ],

            'enrollment_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcEnrollmentId',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => 'The enrollment the payment relates to, if any (computed).',
                'store'             => true
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount paid (whatever the origin).'
            ],

            'communication' => [
                'type'              => 'string',
                'description'       => "Message from the payer.",
            ],

            'receipt_date' => [
                'type'              => 'datetime',
                'description'       => "Time of reception of the payment.",
                'default'           => time()
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
                    'voucher',              // gift, coupon, or tour-operator voucher
                    'cash',                 // cash money
                    'bank_card',            // electronic payment with credit or debit card
                    'bank_check',           // physical bank check
                    'wire_transfer',        // transfer between bank accounts
                    'camp_financial_help'   // camp sponsor financial help
                ],
                'description'       => "The method used for payment at the cashdesk.",
                'visible'           => [ ['payment_origin', '=', 'cashdesk'] ],
                'default'           => 'cash'
            ],

            'is_manual' => [
                'type'              => 'boolean',
                'description'       => 'Payment was created manually at the checkout directly in the booking (not through cashdesk). Can also be a payment from a bank check for enrollments and bookings.',
                'default'           => false
            ],

            'operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pos\Operation',
                'description'       => 'The operation the payment relates to.',
                'visible'           => [ ['payment_origin', '=', 'cashdesk'] ]
            ],

            'statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\BankStatementLine',
                'description'       => 'The bank statement line the payment relates to.',
                'visible'           => [ ['payment_origin', '=', 'bank'] ],
                'onupdate'          => 'onupdateStatementLineId'
            ],

            'voucher_ref' => [
                'type'              => 'string',
                'description'       => 'The reference of the voucher the payment relates to.',
                'visible'           => [ ['payment_method', '=', 'voucher'] ]
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
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

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Invoice',
                'description'       => 'The invoice targeted by the payment, if any.'
            ],

            'is_exported' => [
                'type'              => 'boolean',
                'description'       => 'Mark the payment as exported (part of an export to elsewhere).',
                'default'           => false
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'paid'
                ],
                'description'       => 'Current status of the payment.',
                'default'           => 'paid'
            ],

            'bank_check_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\BankCheck',
                'description'       => 'The BankCheck associated with the payment.'
            ]

        ];
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
                    'enrollment_id',
                    'enrollment_id.name',
                    'invoice_id.partner_id.id',
                    'invoice_id.partner_id.name'
                ],
                $lang
            );

            if($fundings > 0) {
                $funding = reset($fundings);

                if($funding['enrollment_id']) {
                    $result['enrollment_id'] = [ 'id' => $funding['enrollment_id'], 'name' => $funding['enrollment_id.name'] ];
                }
                else {
                    $result['enrollment_id'] = null;
                }

                if($funding['type'] == 'invoice')  {
                    $result['partner_id'] = [ 'id' => $funding['invoice_id.partner_id.id'], 'name' => $funding['invoice_id.partner_id.name'] ];
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
        $payments = $om->read(self::getType(), $ids, ['enrollment_id.camp_id.center_id.center_office_id', 'statement_line_id.center_office_id']);
        if($payments > 0 && count($payments)) {
            foreach($payments as $id => $payment) {
                if(isset($payment['enrollment_id.camp_id.center_id.center_office_id'])) {
                    $result[$id] = $payment['enrollment_id.camp_id.center_id.center_office_id'];
                }
                elseif(isset($payment['statement_line_id.center_office_id'])) {
                    $result[$id] = $payment['statement_line_id.center_office_id'];
                }
            }
        }
        return $result;
    }

    /**
     * Assign partner_id and invoice_id from invoice relating to funding, if any.
     * Force recomputing of target funding computed fields (is_paid and paid_amount).
     *
     */
    public static function onupdateFundingId($om, $ids, $values, $lang) {
        trigger_error("ORM::calling sale\pay\Payment::onupdateFundingId", QN_REPORT_DEBUG);

        $payments = $om->read(self::getType(), $ids, ['funding_id', 'funding_id.invoice_id', 'funding_id.enrollment_id.camp_id.date_from', 'funding_id.type', 'partner_id']);

        if($payments > 0) {
            $map_enrollments_ids = [];
            // $fundings_ids = [];
            foreach($payments as $pid => $payment) {
                if($payment['funding_id']) {
                    if($payment['funding_id.enrollment_id']) {
                        $map_enrollments_ids[$payment['funding_id.enrollment_id']] = true;
                        // update enrollment_id
                        $om->update(self::getType(), $pid, ['enrollment_id' => $payment['funding_id.enrollment_id']]);
                    }
                    elseif(!$payment['partner_id']) {
                        $fundings = $om->read('sale\pay\Funding', $payment['funding_id'], [
                                'type',
                                'due_amount',
                                'invoice_id',
                                'invoice_id.partner_id.id',
                                'invoice_id.partner_id.name'
                            ],
                            $lang);

                        if($fundings > 0 && count($fundings) > 0) {
                            $funding = reset($fundings);
                            if($funding['type'] == 'invoice') {
                                $values['partner_id'] = $funding['invoice_id.partner_id.id'];
                                $values['invoice_id'] = $funding['invoice_id'];
                            }
                            $om->update(self::getType(), $pid, $values);
                        }
                    }

                    $om->update(Funding::getType(), $payment['funding_id'], ['is_paid' => null, 'paid_amount' => null]);
                    // $fundings_ids[] = $payment['funding_id'];
                }
                else {
                    $om->update(self::getType(), $ids, ['enrollment_id' => null]);
                }
            }
            $om->update(Enrollment::getType(), array_keys($map_enrollments_ids), ['payment_status' => null, 'paid_amount' => null], $lang);
            // force immediate re-computing of the is_paid field
            // $om->read('sale\pay\Funding', array_unique($fundings_ids), ['is_paid', 'paid_amount']);
        }
    }

    public static function onupdateStatementLineId($om, $ids, $values, $lang) {
        $payments = $om->read(self::getType(), $ids, ['state', 'statement_line_id.bank_statement_id', 'statement_line_id.remaining_amount']);
        if($payments > 0 && count($payments)) {
            foreach($payments as $id => $payment) {
                $om->update(self::getType(), $id, ['state' => $payment['state'], 'amount' => $payment['statement_line_id.remaining_amount']]);
                // #memo - status of BankStatement is computed from statement lines, and status of BankStatementLine depends on payments
                $om->update(BankStatement::getType(), $payment['statement_line_id.bank_statement_id'], ['status' => null]);
            }
        }
    }

    /**
     * Check wether the payment can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  Object   $om         ObjectManager instance.
     * @param  Array    $ids        List of objects identifiers.
     * @param  Array    $values     Associative array holding the new values to be assigned.
     * @param  String   $lang       Language in which multilang fields are being updated.
     * @return Array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang='en') {
        // assigning the payment to another funding is allowed at all time
        if(count($values) == 1 && isset($values['funding_id'])) {
            return [];
        }

        $payments = $om->read(self::getType(), $ids, ['state', 'is_exported', 'payment_origin', 'amount', 'statement_line_id.amount', 'statement_line_id.remaining_amount'], $lang);
        foreach($payments as $pid => $payment) {
            if($payment['is_exported']) {
                return ['is_exported' => ['non_editable' => 'Once exported a payment can no longer be updated.']];
            }
            if($payment['payment_origin'] == 'bank') {
                if(isset($values['amount'])) {
                    $sign_line = intval($payment['statement_line_id.amount'] > 0) - intval($payment['statement_line_id.amount'] < 0);
                    $sign_payment = intval($values['amount'] > 0) - intval($values['amount'] < 0);
                    // #memo - we prevent creating payment that do not decrease the remaining amount
                    if($sign_line != $sign_payment) {
                        return ['amount' => ['incompatible_sign' => "Payment amount ({$values['amount']}) and statement line amount ({$payment['statement_line_id.amount']}) must have the same sign."]];
                    }
                    // #memo - when state is still draft, it means that reconcile is made manually
                    if($payment['state'] == 'draft') {
                        if(round($payment['statement_line_id.amount'], 2) < 0) {
                            if(round($payment['statement_line_id.remaining_amount'] - $values['amount'], 2) > 0) {
                                return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$payment['statement_line_id.remaining_amount']}) (err#1)."]];
                            }
                        }
                        else {
                            if(round($payment['statement_line_id.remaining_amount'] - $values['amount'], 2) < 0) {
                                return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$payment['statement_line_id.remaining_amount']}) (err#2)."]];
                            }
                        }
                    }
                    else  {
                        if(round($payment['statement_line_id.amount'], 2) < 0) {
                            if(round($payment['statement_line_id.remaining_amount'] + $payment['amount'] - $values['amount'], 2) > 0) {
                                return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$payment['statement_line_id.remaining_amount']}) (err#3)."]];
                            }
                        }
                        else {
                            if(round($payment['statement_line_id.remaining_amount'] + $payment['amount'] - $values['amount'], 2) < 0) {
                                return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$payment['statement_line_id.remaining_amount']}) (err#4)."]];
                            }
                        }
                    }
                }
            }
        }
        return parent::canupdate($om, $ids, $values, $lang);
    }


    /**
     * Hook invoked before object deletion for performing object-specific additional operations.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function ondelete($om, $oids) {
        // set back related statement line status to 'pending'
        $payments = $om->read(__CLASS__, $oids, ['statement_line_id', 'funding_id']);
        if($payments > 0) {
            foreach($payments as $pid => $payment) {
                $om->update('sale\pay\BankStatementLine', $payment['statement_line_id'], ['status' => 'pending']);
                $om->update('sale\pay\Funding', $payment['funding_id'], ['is_paid' => false, 'paid_amount' => null]);
            }
        }
        return parent::ondelete($om, $oids);
    }

    /**
     * Check whether the payments can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om        ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $payments = $om->read(self::getType(), $ids, [ 'payment_origin', 'payment_method', 'status', 'is_exported', 'is_manual', 'statement_line_id.status' ]);

        if($payments > 0) {
            foreach($payments as $id => $payment) {
                if($payment['is_exported']) {
                    return ['is_exported' => ['non_removable' => 'Paid payment cannot be removed.']];
                }
                if($payment['payment_origin'] === 'bank' && $payment['statement_line_id.status'] !== 'pending') {
                    return ['status' => ['non_removable' => 'Payment from reconciled line cannot be removed.']];
                }
                if(!$payment['is_manual'] && $payment['status'] === 'paid' && $payment['payment_method'] !== 'camp_financial_help') {
                    return ['status' => ['non_removable' => 'Non manual paid payment cannot be removed.']];
                }
            }
        }
        return parent::candelete($om, $ids);
    }
}
