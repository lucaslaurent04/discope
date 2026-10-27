<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Enrollment extends Model {

    public static function getDescription(): string {
        return "The enrollment of a child to a camp group.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the enrollment.',
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description of the enrollment."
            ],

            'is_foster' => [
                'type'              => 'boolean',
                'description'       => "Is the child living in a foster family/home.",
                'default'           => false
            ],

            'camp_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "The camp the child is enrolled to.",
                'required'          => true
            ],

            'camp_remaining_qty' => [
                'type'              => 'integer',
                'description'       => "Camp remaining enrollment quantity.",
                'help'              => "Used for UX when creating a new enrollment."
            ],

            'date_from' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => "Start date of the camp.",
                'store'             => true,
                'relation'          => ['camp_id' => 'date_from']
            ],

            'date_to' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => "End date of the camp.",
                'store'             => true,
                'relation'          => ['camp_id' => 'date_to']
            ],

            'center_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center to which the enrollment relates to.",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['camp_id' => 'center_id']
            ],

            'center_office_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => "Office the enrollment relates to (for center management).",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['center_id' => 'center_office_id']
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'waitlisted',
                    'confirmed',
                    'validated',
                    'cancelled'
                ],
                'description'       => "The status of the enrollment.",
                'default'           => 'pending'
            ],

            'cancellation_reason' => [
                'type'              => 'string',
                'selection'         => [
                    'other',                    // customer cancelled for a non-listed reason or without mentioning the reason (cancellation fees might apply)
                    'overbooking',              // the booking was cancelled due to failure in delivery of the service
                    'duplicate',                // several contacts of the same group made distinct bookings for the same sojourn
                    'internal_impediment',      // cancellation due to an incident impacting the rental units
                    'external_impediment',      // cancellation due to external delivery failure (organisation, means of transport, ...)
                    'health_impediment'         // cancellation for medical or mourning reason
                ],
                'description'       => "The reason at the origin of the enrollment's cancellation.",
                'default'           => 'other',
                'visible'           => ['status', '=', 'cancelled']
            ],

            'payment_status' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'due',
                    'paid'
                ],
                'function'          => 'calcPaymentStatus',
                'store'             => true,
                'description'       => "Current status of the payments. Depends on the status of the enrollment.",
                'help'              => "'Due' means we are expecting some money for the enrollment (at the moment, at least one due funding has not been fully received). 'Paid' means that everything expected (all payments) has been received."
            ],

            'paid_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that has been received so far.",
                'function'          => 'calcPaidAmount',
                'store'             => true
            ],

            'all_documents_received' => [
                'type'              => 'boolean',
                'description'       => "Have all required documents been received?",
                'default'           => true
            ],

            'is_external' => [
                'type'              => 'boolean',
                'description'       => "Does the enrollment comes from an external source, not Discope.",
                'default'           => false
            ],

            'external_ref' => [
                'type'              => 'string',
                'description'       => "External reference for enrollment, if any."
            ],

            'external_data' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => "External data given to create enrollment."
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Funding',
                'foreign_field'     => 'enrollment_id',
                'description'       => 'Fundings that relate to the enrollment.',
                'ondetach'          => 'delete'
            ],

            'tasks_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'enrollment_id',
                'foreign_object'    => 'sale\camp\followup\Task',
                'description'       => "Follow up tasks that are associated with the enrollment."
            ]

        ];
    }

    protected static function calcPaymentStatus($self): array {
        $result = [];
        $self->read(['status', 'fundings_ids' => ['due_date', 'is_paid']]);
        foreach($self as $id => $enrollment) {
            $payment_status = 'paid';
            // if there is at least one overdue funding: a payment is 'due', otherwise enrollment is 'paid'
            foreach($enrollment['fundings_ids'] as $funding) {
                if(!$funding['is_paid'] && $funding['due_date'] > time()) {
                    $payment_status = 'due';
                    break;
                }
            }

            $result[$id] = $payment_status;
        }

        return $result;
    }

    protected static function calcPaidAmount($self): array {
        $result = [];
        $self->read(['fundings_ids' => ['paid_amount']]);
        foreach($self as $id => $enrollment) {
            $paid_amount = 0.0;
            foreach($enrollment['fundings_ids'] as $funding) {
                $paid_amount += $funding['paid_amount'];
            }
            $result[$id] = $paid_amount;
        }

        return $result;
    }
}
