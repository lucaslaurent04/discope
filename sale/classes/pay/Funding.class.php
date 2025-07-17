<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pay;
use equal\orm\Model;
use core\setting\Setting;
use sale\camp\Enrollment;

class Funding extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Optional description to identify the funding."
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

            'enrollment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => "Enrollment the funding relates to.",
                'ondelete'          => 'cascade',        // delete funding when parent enrollment is deleted
                'required'          => true,
                'dependents'        => ['enrollment_id' => ['payment_status', 'paid_amount']],
                'onupdate'          => 'onupdateEnrollmentId'
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => [
                    'installment',
                    'invoice'
                ],
                'default'           => 'installment',
                'description'       => "Deadlines are installment except for last one: final invoice."
            ],

            'order' => [
                'type'              => 'integer',
                'description'       => 'Order by which the funding have to be sorted when presented.',
                'default'           => 0
            ],

            'due_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Amount expected for the funding (computed based on VAT incl. price).",
                'required'          => true,
                'onupdate'          => 'onupdateDueAmount'
            ],

            'due_date' => [
                'type'              => 'date',
                'description'       => "Deadline before which the funding is expected.",
                'default'           => time()
            ],

            'issue_date' => [
                'type'              => 'date',
                'description'       => "Date at which the request for payment has to be issued.",
                'default'           => time()
            ],

            'paid_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that has been received (can be greater than due_amount).",
                'function'          => 'calcPaidAmount',
                'store'             => true,
                'instant'           => true
            ],

            'is_paid' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Has the full payment been received?",
                'function'          => 'calcIsPaid',
                'store'             => true,
                'onupdate'          => 'onupdateIsPaid'
            ],

            'amount_share' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/percent',
                'function'          => 'calcAmountShare',
                'store'             => true,
                'description'       => "Share of the payment over the total due amount (enrollment)."
            ],

            'payment_deadline_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\PaymentDeadline',
                'description'       => "The deadline model used for creating the funding, if any.",
                'onupdate'          => 'onupdatePaymentDeadlineId'
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Invoice',
                'ondelete'          => 'null',
                'description'       => 'The invoice targeted by the funding, if any.',
                'help'              => 'As a convention, this field is set when a funding relates to an invoice: either because the funding has been invoiced (downpayment or balance invoice), or because it is an installment (deduced from the due amount)',
                'visible'           => [ ['type', '=', 'invoice'] ]
            ],

            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPaymentReference',
                'description'       => "Message for identifying the purpose of the transaction.",
                'store'             => true
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => "The center office the booking relates to.",
                'required'          => true
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Payment',
                'foreign_field'     => 'funding_id'
            ],

            'bank_check_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\BankCheck',
                'foreign_field'     => 'funding_id'
            ],

        ];
    }


    public static function calcName($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(get_called_class(), $oids, ['payment_deadline_id.name', 'due_amount', 'enrollment_id.name', 'enrollment_id.camp_id.sojourn_code'], $lang);

        if($fundings > 0) {
            foreach($fundings as $oid => $funding) {
                if(isset($funding['enrollment_id.name'])) {
                    $result[$oid] = $funding['enrollment_id.camp_id.sojourn_code'].' '.$funding['enrollment_id.name'].'    '.Setting::format_number_currency($funding['due_amount']);
                }
                else {
                    $result[$oid] = Setting::format_number_currency($funding['due_amount']).'    '.$funding['payment_deadline_id.name'];
                }
            }
        }
        return $result;
    }

    public static function calcPaidAmount($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(self::getType(), $oids, ['enrollment_id', 'payments_ids.amount'], $lang);
        if($fundings > 0) {
            $map_enrollments_ids = [];
            foreach($fundings as $fid => $funding) {
                if(isset($funding['enrollment_id'])) {
                    $map_enrollments_ids[$funding['enrollment_id']] = true;
                }

                $result[$fid] = array_reduce((array) $funding['payments_ids.amount'], function ($c, $funding) {
                    return $c + $funding['amount'];
                }, 0);
            }

            if(!empty($map_enrollments_ids)) {
                // force recompute computed fields for impacted enrollments
                $om->update(Enrollment::getType(), array_keys($map_enrollments_ids), ['payment_status' => null, 'paid_amount' => null]);
            }
        }
        return $result;
    }

    public static function calcIsPaid($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(self::getType(), $oids, ['due_amount', 'paid_amount'], $lang);
        if($fundings > 0) {
            foreach($fundings as $fid => $funding) {
                $result[$fid] = false;
                if(abs(round($funding['due_amount'], 2)) > 0) {
                    $sign_paid = intval($funding['paid_amount'] > 0) - intval($funding['paid_amount'] < 0);
                    $sign_due  = intval($funding['due_amount'] > 0) - intval($funding['due_amount'] < 0);
                    if($sign_paid == $sign_due && abs(round($funding['paid_amount'], 2)) >= abs(round($funding['due_amount'], 2))) {
                        $result[$fid] = true;
                        $om->update(Funding::getType(), $fid, ['status' => 'paid']);
                    }
                }
            }
        }
        return $result;
    }

    public static function calcAmountShare($om, $ids, $lang) {
        $result = [];
        $fundings = $om->read(self::getType(), $ids, ['enrollment_id.price', 'due_amount'], $lang);

        if($fundings > 0) {
            foreach($fundings as $id => $funding) {
                if(!isset($funding['enrollment_id.price'])) {
                    continue;
                }

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
            if(isset($funding['enrollment_id.payment_reference'])) {
                $result[$id] = $funding['enrollment_id.payment_reference'];
            }
        }
        return $result;
    }

    /**
     * Hook invoked before object update for performing object-specific additional operations.
     * Update the scheduled tasks related to the fundings.
     *
     * @param  \equal\orm\ObjectManager    $om         ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @param  array                       $values     Associative array holding the new values that have been assigned.
     * @param  string                      $lang       Language in which multilang fields are being updated.
     * @return void
     */
    public static function onupdate($om, $ids, $values, $lang) {
        $cron = $om->getContainer()->get('cron');

        if(isset($values['due_date'])) {
            foreach($ids as $fid) {
                // remove any previously scheduled task
                $cron->cancel("booking.funding.overdue.{$fid}");
                // setup a scheduled job upon funding overdue
                $cron->schedule(
                // assign a reproducible unique name
                    "booking.funding.overdue.{$fid}",
                    // remind on day following due_date
                    $values['due_date'] + 86400,
                    'sale_booking_funding_check-payment',
                    [ 'id' => $fid ]
                );
            }
        }
        parent::onupdate($om, $ids, $values, $lang);
    }

    public static function onupdateDueAmount($om, $oids, $values, $lang) {
        $fundings = $om->read(self::getType(), $oids, ['enrollment_id']);
        if($fundings > 0 && count($fundings)) {
            $map_enrollments_ids = [];
            $map_other_ids = [];
            foreach($fundings as $fid => $funding) {
                if(isset($funding['enrollment_id'])) {
                    $map_enrollments_ids[$funding['enrollment_id']] = true;
                }
                else {
                    $map_other_ids[$fid] = true;
                }
            }
            if(!empty($map_enrollments_ids)) {
                $fundings_ids = $om->search(self::getType(), ['enrollment_id', 'in', array_keys($map_enrollments_ids)]);
                $om->update(self::getType(), $fundings_ids, ['name' => null, 'amount_share' => null]);
            }
            if(!empty($map_other_ids)) {
                $om->update(self::getType(), array_keys($map_other_ids), ['name' => null]);
            }
        }
    }

    public static function onupdateIsPaid($orm, $oids, $values, $lang) {
        $fundings = $orm->read(self::getType(), $oids, ['enrollment_id']);
        if($fundings > 0 && count($fundings)) {
            $map_enrollments_ids = [];
            foreach($fundings as $fid => $funding) {
                if(isset($funding['enrollment_id'])) {
                    $map_enrollments_ids[$funding['enrollment_id']] = true;
                }
            }
            if(!empty($map_enrollments_ids)) {
                $orm->update(Enrollment::getType(), array_keys($map_enrollments_ids), ['payment_status' => null, 'paid_amount' => null]);
            }
        }
    }

    /**
     * Update the description according to the deadline, when set.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @param  array                        $values     Associative array holding the new values to be assigned.
     * @param  string                       $lang       Language in which multilang fields are being updated.
     */
    public static function onupdatePaymentDeadlineId($om, $oids, $values, $lang) {
        $fundings = $om->read(self::getType(), $oids, ['payment_deadline_id.name'], $lang);
        if($fundings > 0) {
            foreach($fundings as $oid => $funding) {
                if($funding['payment_deadline_id.name'] && strlen($funding['payment_deadline_id.name']) > 0) {
                    $om->update(self::getType(), $oid, ['description' => $funding['payment_deadline_id.name']], $lang);
                }
            }
        }
    }

    public static function onupdateEnrollmentId($self, $values) {
        $self->read(['enrollment_id' => ['camp_id' => ['center_id' => ['center_office_id']]]]);
        foreach($self as $funding) {
            if(is_null($funding['enrollment_id'])) {
                continue;
            }

            Funding::id($funding['id'])
                ->update(['center_office_id' => $funding['enrollment_id']['camp_id']['center_id']['center_office_id']]);
        }
    }

    /**
     * Check whether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @param  array                        $values     Associative array holding the new values to be assigned.
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang) {
        // handle exceptions for fields that can always be updated
        $allowed = ['is_paid', 'invoice_id'];
        $count_non_allowed = 0;

        foreach($values as $field => $value) {
            if(!in_array($field, $allowed)) {
                ++$count_non_allowed;
            }
        }

        if($count_non_allowed > 0) {
            $fundings = $om->read(self::getType(), $oids, ['is_paid', 'due_amount', 'paid_amount', 'payments_ids'], $lang);
            if($fundings > 0) {
                foreach($fundings as $funding) {
                    if($funding['is_paid'] && $funding['due_amount'] == $funding['paid_amount'] && count($funding['payments_ids'])) {
                        return ['is_paid' => ['non_editable' => 'No change is allowed once the funding has been fully paid.']];
                    }
                }
            }
        }
        return parent::canupdate($om, $oids, $values, $lang);
    }

    /**
     * Check whether the identity can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om        ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $fundings = $om->read(self::getType(), $ids, [ 'is_paid', 'due_amount', 'paid_amount', 'type', 'invoice_id', 'invoice_id.status', 'invoice_id.type', 'payments_ids' ]);

        if($fundings > 0) {
            foreach($fundings as $id => $funding) {
                if( $funding['is_paid'] || $funding['paid_amount'] != 0 || ($funding['payments_ids'] && count($funding['payments_ids']) > 0) ) {
                    return ['payments_ids' => ['non_removable_funding' => 'Funding paid or partially paid cannot be deleted.']];
                }
                if( $funding['due_amount'] > 0 && $funding['type'] == 'invoice' && is_null($funding['invoice_id']) && $funding['invoice_id.status'] == 'invoice' && $funding['invoice_id.type'] == 'invoice') {
                    return ['invoice_id' => ['non_removable_funding' => 'Funding relating to an invoice cannot be deleted.']];
                }
            }
        }

        return [];
    }

    /**
     * Hook invoked before object deletion for performing object-specific additional operations.
     * Remove the scheduled tasks related to the deleted fundings.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function ondelete($om, $oids) {
        $cron = $om->getContainer()->get('cron');

        foreach($oids as $fid) {
            // remove any previously scheduled task
            $cron->cancel("booking.funding.overdue.{$fid}");
        }
        parent::ondelete($om, $oids);
    }

    /**
     * Signature for single object change from views.
     *
     * @param  \equal\orm\ObjectManager     $om        Object Manager instance.
     * @param  array                        $event     Associative array holding changed fields as keys, and their related new values.
     * @param  array                        $values    Copy of the current (partial) state of the object (fields depend on the view).
     * @param  string                       $lang      Language (char 2) in which multilang field are to be processed.
     * @return array                        Associative array mapping fields with their resulting values.
     */
    public static function onchange($om, $event, $values, $lang='en') {
        $result = [];

        // if 'is_paid' is set manually, adapt 'paid_mount' consequently
        if(isset($event['is_paid'])) {
            $result['paid_amount'] = $values['due_amount'];
        }

        return $result;
    }

    /**
     * Compute a Structured Reference using belgian SCOR (StructuredCommunicationReference) reference format.
     *
     * Note:
     *  format is aaa-bbbbbbb-XX
     *  where Xaaa is the prefix, bbbbbbb is the suffix, and XX is the control number, that must verify (aaa * 10000000 + bbbbbbb) % 97
     *  as 10000000 % 97 = 76
     *  we do (aaa * 76 + bbbbbbb) % 97
     */
    public static function _get_payment_reference($prefix, $suffix) {
        $a = intval($prefix);
        $b = intval($suffix);
        $control = ((76*$a) + $b ) % 97;
        $control = ($control == 0)?97:$control;
        return sprintf("%3d%04d%03d%02d", $a, $b / 1000, $b % 1000, $control);
    }
}
