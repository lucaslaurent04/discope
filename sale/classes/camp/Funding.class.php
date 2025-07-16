<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use core\setting\Setting;

class Funding extends \sale\pay\Funding {

    public static function getColumns(): array {

        return [

            // override to use local calcName with enrollment_id
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true
            ],

            'enrollment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => "Enrollment the funding relates to.",
                'ondelete'          => 'cascade',        // delete funding when parent enrollment is deleted
                'required'          => true,
                'dependents'        => ['enrollment_id' => ['payment_status', 'paid_amount']],
                'onupdate'          => 'onupdateEnrollmentId'
            ],

            // override to use custom onupdateDueAmount
            'due_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Amount expected for the funding (computed based on VAT incl. price).",
                'required'          => true,
                'onupdate'          => 'onupdateDueAmount'
            ],

            // override to reference enrollment.paid_amount
            'is_paid' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Has the full payment been received?",
                'function'          => 'calcIsPaid',
                'store'             => true,
                'onupdate'          => 'onupdateIsPaid',
            ],

            // override to reference enrollment.paid_amount
            'paid_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that has been received (can be greater than due_amount).",
                'function'          => 'calcPaidAmount',
                'store'             => true,
                'instant'           => true
            ],

            'amount_share' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/percent',
                'function'          => 'calcAmountShare',
                'store'             => true,
                'description'       => "Share of the payment over the total due amount (enrollment)."
            ],

            // override to use local calcPaymentReference with enrollment_id
            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPaymentReference',
                'description'       => 'Message for identifying the purpose of the transaction.',
                'store'             => true
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Payment',
                'foreign_field'     => 'funding_id'
            ],

            'bank_check_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\BankCheck',
                'foreign_field'     => 'funding_id'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'in_process',
                    'paid',
                ],
                'description'       => 'The current processing status of the funding',
                'default'           => 'pending'
            ],

        ];
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(get_called_class(), $oids, ['enrollment_id.name', 'due_amount'], $lang);

        if($fundings > 0) {
            foreach($fundings as $oid => $funding) {
                $result[$oid] = $funding['enrollment_id.name'].'    '.Setting::format_number_currency($funding['due_amount']);
            }
        }
        return $result;
    }

    public static function calcAmountShare($om, $ids, $lang) {
        $result = [];
        $fundings = $om->read(self::getType(), $ids, ['enrollment_id.price', 'due_amount'], $lang);

        if($fundings > 0) {
            foreach($fundings as $id => $funding) {
                $total = round($funding['enrollment_id.price'], 2);
                if($total == 0) {
                    $share = 1;
                }
                else {
                    $share = round(abs($funding['due_amount']) / abs($total), 2);
                }
                $sign = ($funding['due_amount'] < 0)?-1:1;
                $result[$id] = $share * $sign;
            }
        }

        return $result;
    }

    public static function calcPaymentReference($om, $ids, $lang) {
        $result = [];
        $fundings = $om->read(self::getType(), $ids, ['enrollment_id.payment_reference'], $lang);
        foreach($fundings as $id => $funding) {
            $result[$id] = $funding['enrollment_id.payment_reference'];
        }
        return $result;
    }

    public static function calcIsPaid($orm, $ids, $lang) {
        $result = [];
        $fundings = $orm->read(self::getType(), $ids, ['enrollment_id', 'due_amount', 'paid_amount', 'invoice_id'], $lang);
        $map_enrollments_ids = [];
        if($fundings > 0) {
            foreach($fundings as $fid => $funding) {
                $result[$fid] = false;
                $map_enrollments_ids[$funding['enrollment_id']] = true;
                if(abs(round($funding['due_amount'], 2)) > 0) {
                    $sign_paid = intval($funding['paid_amount'] > 0) - intval($funding['paid_amount'] < 0);
                    $sign_due  = intval($funding['due_amount'] > 0) - intval($funding['due_amount'] < 0);
                    if($sign_paid == $sign_due && abs(round($funding['paid_amount'], 2)) >= abs(round($funding['due_amount'], 2))) {
                        $result[$fid] = true;
                        $orm->update(Funding::getType(), $fid, ['status' => 'paid']);
                    }
                }
            }
            // #memo - this handler can result from a payment_status computation : we need callonce to prevent infinite loops
            // $orm->callonce(Enrollment::getType(), 'updateStatusFromFundings', array_keys($map_enrollments_ids), [], $lang);
            /*
            // #memo - we cannot do that in calc, since this might lead to erasing values that have just been set
            Enrollment::updateStatusFromFundings($orm, array_keys($map_enrollments_ids));
            // force recompute paid_amount property for impacted enrollments
            $om->update(Enrollment::getType(), array_keys($map_enrollments_ids), ['payment_status' => null, 'paid_amount' => null]);
            // force recompute is_paid property for impacted invoices
            $om->update(Invoice::getType(), array_keys($map_invoices_ids), ['payment_status' => null, 'is_paid' => null]);
            */
        }
        return $result;
    }

    /**
     * Computes the paid_amount property based on related payments.
     * In addition, also resets the related enrollment paid_amount computed field.
     *
     * #note - this should not be necessary since Payment::onupdateFundingId is necessarily triggered at payment creation
     */
    public static function calcPaidAmount($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(self::getType(), $oids, ['enrollment_id', 'invoice_id', 'payments_ids.amount'], $lang);
        if($fundings > 0) {
            $map_enrollments_ids = [];
            // $map_invoices_ids = [];
            foreach($fundings as $fid => $funding) {
                $map_enrollments_ids[$funding['enrollment_id']] = true;
                // $map_invoices_ids[$funding['invoice_id']] = true;
                $result[$fid] = array_reduce((array) $funding['payments_ids.amount'], function ($c, $funding) {
                    return $c + $funding['amount'];
                }, 0);
            }
            // force recompute computed fields for impacted enrollments and invoices
            $om->update(Enrollment::getType(), array_keys($map_enrollments_ids), ['payment_status' => null, 'paid_amount' => null]);
            // $om->update(Invoice::getType(), array_keys($map_invoices_ids), ['payment_status' => null, 'is_paid' => null]);
        }
        return $result;
    }

    /**
     * When due_amount is updated (funding is assigned to an enrollment), we reset the amount_share of all the fundings of the enrollment.
     */
    public static function onupdateDueAmount($orm, $oids, $values, $lang) {
        $fundings = $orm->read(self::getType(), $oids, ['enrollment_id']);
        $map_enrollments_ids = [];
        if($fundings > 0 && count($fundings)) {
            foreach($fundings as $fid => $funding) {
                $map_enrollments_ids[$funding['enrollment_id']] = true;
            }
            $fundings_ids = $orm->search(self::getType(), ['enrollment_id', 'in', array_keys($map_enrollments_ids)]);
            $orm->update(self::getType(), $fundings_ids, ['name' => null, 'amount_share' => null]);
        }
    }

    public static function onupdateIsPaid($orm, $oids, $values, $lang) {
        $fundings = $orm->read(self::getType(), $oids, ['invoice_id', 'enrollment_id']);
        if($fundings > 0 && count($fundings)) {
            $map_enrollments_ids = [];
            // $map_invoices_ids = [];
            foreach($fundings as $fid => $funding) {
                /*if($funding['invoice_id']) {
                    $map_invoices_ids[$funding['invoice_id']] = true;
                }*/
                if($funding['enrollment_id']) {
                    $map_enrollments_ids[$funding['enrollment_id']] = true;
                }
            }
            // $orm->update(Invoice::getType(), array_keys($map_invoices_ids), ['payment_status' => null, 'is_paid' => null]);
            $orm->update(Enrollment::getType(), array_keys($map_enrollments_ids), ['payment_status' => null, 'paid_amount' => null]);
            $orm->callonce(Enrollment::getType(), 'updateStatusFromFundings', array_keys($map_enrollments_ids), [], $lang);
        }
    }

    public static function onupdateEnrollmentId($self, $values) {
        $self->read([
            'enrollment_id' => ['camp_id' => ['center_id' => ['center_office_id']]]
        ]);
        foreach($self as $funding) {
            if(is_null($funding['enrollment_id'])) {
                continue;
            }

            Funding::id($funding['id'])
                ->update(['center_office_id' => $funding['enrollment_id']['camp_id']['center_id']['center_office_id']]);
        }
    }

    /**
     * Check whether an object can be created.
     * These tests come in addition to the unique constraints returned by method `getUnique()`.
     * Checks if the sum of the enrollment's fundings remains lower than the price of the enrollment itself.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $values     Associative array holding the values to be assigned to the new instance (not all fields might be set).
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array            Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be created.
     */
    public static function cancreate($om, $values, $lang) {
        if(isset($values['enrollment_id']) && isset($values['due_amount'])) {
            $enrollments = $om->read(Enrollment::getType(), $values['enrollment_id'], ['price', 'fundings_ids.due_amount'], $lang);
            if($enrollments > 0 && count($enrollments)) {
                // #memo - we allow creating arbitrary fundings (to ease the handling of all possible client payment scenarios)
                /*
                $enrollment = reset($enrollments);
                $fundings_price = (float) $values['due_amount'];
                foreach((array) $enrollment['fundings_ids.due_amount'] as $fid => $funding) {
                    $fundings_price += (float) $funding['due_amount'];
                }
                if($fundings_price > $enrollment['price'] && abs($enrollment['price']-$fundings_price) >= 0.0001) {
                    return ['status' => ['exceeded_price' => "Sum of the fundings cannot be higher than the enrollment total ({$fundings_price}, {$enrollment['price']})."]];
                }
                */
            }
        }
        // #memo - idem - we allow creating arbitrary fundings (to ease the handling of all possible client payment scenarios)
        /*
        if(isset($values['due_amount']) && $values['due_amount'] < 0) {
            return ['due_amount' => ['invalid' => "Due amount of a funding cannot be negative."]];
        }
        */
        return parent::cancreate($om, $values, $lang);
    }

    /**
     * Check whether an object can be updated.
     * These tests come in addition to the unique constraints returned by method `getUnique()`.
     * Checks if the sum of each enrollment's fundings remains lower than the price of the enrollment itself.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @param  array                        $values     Associative array holding the new values to be assigned.
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array            Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang) {
        $allowed_fields = ['type', 'invoice_id'];

        // prevent setting the amount to a negative value
        // #memo - we allow creating arbitrary fundings (to ease the handling of all possible client payment scenarios)
        // #memo - this should be allowed only when made automatically
        /*
        if(isset($values['due_amount']) && $values['due_amount'] < 0) {
            return ['due_amount' => ['invalid' => "Due amount of a funding cannot be negative."]];
        }
        */

        $fundings = $om->read(self::getType(), $oids, ['is_paid', 'enrollment_id', 'type', 'invoice_id', 'invoice_id.status', 'invoice_id.type', 'due_amount', 'paid_amount'], $lang);

        if($fundings > 0) {
            foreach($fundings as $fid => $funding) {
                // #memo - modifying the funding of an emitted credit note is accepted (in order to re-use previously paid fundings put on first invoice)
                if(isset($values['due_amount']) && $funding['type'] == 'invoice' && $funding['invoice_id'] && isset($funding['invoice_id.status']) && $funding['invoice_id.status'] != 'proforma' && $funding['invoice_id.type'] != 'credit_note') {
                    return ['due_amount' => ['non_editable' => "Invoiced funding cannot be updated."]];
                }
                $enrollments = $om->read(Enrollment::getType(), $funding['enrollment_id'], ['price', 'fundings_ids.due_amount'], $lang);
                // #memo - we allow creating arbitrary fundings independently from related enrollment (to ease the handling of all possible client payment scenarios)
                if($enrollments > 0 && count($enrollments)) {
                    /*
                    $enrollment = reset($enrollments);
                    $fundings_price = 0.0;
                    if(isset($values['due_amount'])) {
                        $fundings_price = (float) $values['due_amount'];
                    }
                    foreach((array) $enrollment['fundings_ids.due_amount'] as $oid => $odata) {
                        if($oid != $fid) {
                            $fundings_price += (float) $odata['due_amount'];
                        }
                    }
                    if($fundings_price > $enrollment['price'] && abs($enrollment['price']-$fundings_price) >= 0.0001) {
                        return ['status' => ['exceeded_price' => "Sum of the fundings cannot be higher than the enrollment total ({$fundings_price}, {$enrollment['price']})."]];
                    }
                    */
                }
                // #memo - we allow creating arbitrary fundings independently from related enrollment (to ease the handling of all possible client payment scenarios)
                // #todo - some situation should probably be prevented
                /*
                if($funding['paid_amount'] > $funding['due_amount']) {
                    if( count(array_diff(array_keys($values), $allowed_fields)) > 0 ) {
                        return ['status' => ['non_editable' => 'Funding can only be updated while marked as non-paid ['.implode(',', array_keys($values)).'].']];
                    }
                }
                */
            }
        }
        return [];
        // ignore parent method
        return parent::canupdate($om, $oids, $values, $lang);
    }
}
