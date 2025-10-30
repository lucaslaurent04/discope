<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use core\setting\Setting;
use equal\data\DataFormatter;
use equal\orm\Model;
use identity\Center;
use sale\customer\Customer;
use sale\customer\CustomerNature;

class Booking extends Model {

    public static function getDescription() {
        return "Bookings group all the information that allow following up of the reservation process of rental units.";
    }

    public static function getLink() {
        return "/booking/#/booking/object.id";
    }

    public static function getColumns() {
        return [
            'creator' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User who created the entry.'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code to serve as reference (should be unique).",
                'function'          => 'calcName',
                'store'             => true,
                'readonly'          => true,
                // #memo - workaround for preventing setting name at creation when coming from filtered view
                'onupdate'          => 'onupdateName',
                'generation'        => 'generateName'
            ],

            'display_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Label to be displayed for identifying the booking.',
                'function'          => 'calcDisplayName',
                'store'             => true,
                'readonly'          => true,
                'generation'        => 'generateDisplayName'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => "Reason or comments about the booking, if any (for internal use).",
                'default'           => ''
            ],

            'customer_reference' => [
                'type'              => 'string',
                'description'       => "Code or short string given by the customer as own reference, if any.",
                'default'           => ''
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => "The customer whom the booking relates to (depends on selected identity).",
                'onupdate'          => 'onupdateCustomerId'
            ],

            'customer_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The identity of the customer whom the booking relates to.",
                'onupdate'          => 'onupdateCustomerIdentityId',
                'required'          => true,
                'dependents'        => ['name', 'display_name']
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The organisation the establishment belongs to."
            ],

            'customer_nature_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerNature',
                'description'       => 'Nature of the customer (synced with customer) for views convenience.',
                'onupdate'          => 'onupdateCustomerNatureId',
                'required'          => true
            ],

            'customer_rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => 'Rate class that applies to the customer.',
                'onupdate'          => 'onupdateCustomerRateClassId',
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center to which the booking relates to.",
                'required'          => true,
                'dependents'        => ['name', 'display_name', 'center_office_id']
            ],

            'center_office_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => 'Office the invoice relates to (for center management).',
                'store'             => true,
                'instant'           => true,
                'relation'          => ['center_id' => 'center_office_id']
            ],

            'checkedout_revert_to_quote' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Allow to revert the status from checkedin/checkedout to quote.",
                'store'             => false,
                'relation'          => ['center_office_id' => 'checkedout_revert_to_quote']
            ],

            'status_before_revert_to_quote' => [
                'type'              => 'string',
                'selection'         => ['checkedin', 'checkedout'],
                'description'       => "The status of the booking before it was reverted to quote from checkedin or checkedout status."
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'calcTotal',
                'description'       => 'Total tax-excluded price of the booking.',
                'store'             => true
            ],

            'is_price_tbc' => [
                'type'              => 'boolean',
                'description'       => 'The booking contains products with prices to be confirmed.',
                'default'           => false
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'function'          => 'calcPrice',
                'description'       => 'Final tax-included price of the booking.',
                'store'             => true
            ],

            // #todo - add origin ID (internal, OTA, TO)

            // A booking can have several contacts (extending identity\Partner)
            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Contact',
                'foreign_field'     => 'booking_id',
                'description'       => 'List of contacts related to the booking, if any.',
                'ondetach'          => 'delete'
            ],

            'has_contract' => [
                'type'              => 'boolean',
                'description'       => "Has a contract been generated yet? Flag is reset in case of changes before the sojourn.",
                'default'           => false
            ],

            'contracts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Contract',
                'foreign_field'     => 'booking_id',
                'order'             => 'created',
                'sort'              => 'desc',
                'description'       => 'List of contracts relating to the booking, if any.'
            ],

            'current_contract_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\booking\Contract',
                'function'          => 'calcCurrentContractId',
                'store'             => false,
                'description'       => 'Most recent contract, if any.'
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_id',
                'description'       => 'Detailed consumptions of the booking.'
            ],

            'booking_lines_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'foreign_field'     => 'booking_id',
                'description'       => 'Grouped consumptions of the booking.',
                'order'             => 'order',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateBookingLinesGroupsIds'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Consumption',
                'foreign_field'     => 'booking_id',
                'description'       => 'Consumptions relating to the booking.',
                'ondetach'          => 'delete'
            ],

            'composition_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Composition',
                'description'       => 'The composition that relates to the booking.'
            ],

            'composition_items_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\CompositionItem',
                'foreign_field'     => 'booking_id',
                'description'       => "The items that refer to the composition.",
                'ondetach'          => 'delete'
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingType',
                'description'       => "The kind of booking it is about.",
                'default'           => 1                // default to 'general public'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'quote',                    // booking is just informative: nothing has been booked in the planning
                    'option',                   // booking has been placed in the planning for 10 days
                    'confirmed',                // booking has been placed in the planning without time limit
                    'validated',                // signed contract and first installment have been received
                    'checkedin',                // host is currently occupying the booked rental unit
                    'checkedout',               // host has left the booked rental unit
                    'proforma',                 // balance invoice created but not emitted yet
                    'invoiced',                 // balance invoice emitted
                    'debit_balance',            // customer still has to pay something
                    'credit_balance',           // a reimbursement to customer is required
                    'balanced',                 // booking is over and balance is cleared
                    'cancelled'                 // booking is over because cancelled without fees, nothing to invoice to customer
                ],
                'description'       => 'Status of the booking.',
                'default'           => 'quote',
                'onupdate'          => 'onupdateStatus'
            ],

            'is_cancelled' => [
                'type'              => 'boolean',
                'description'       => "Flag marking the booking as cancelled (impacts status).",
                'default'           => false
            ],

            'is_from_channelmanager' => [
                'type'              => 'boolean',
                'description'       => 'Used to distinguish bookings created from channel manager.',
                'default'           => false
            ],

            'extref_reservation_id' => [
                'type'              => 'string',
                'description'       => 'Identifier of the related reservation at channel manager side.',
                'visible'           => ['is_from_channelmanager', '=', true]
            ],

            'guarantee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Guarantee',
                'description'       => 'The guarantee given by the customer, if any.',
                'visible'           => ['is_from_channelmanager', '=', true]
            ],

            'date_expiry' => [
                'type'              => 'date',
                'description'       => 'Reservation expiration date in Option',
                'visible'           => [["status", "=", "option"],["is_noexpiry", "=", false]],
                'default'           => time()
            ],

            'is_noexpiry' => [
                'type'              => 'boolean',
                'description'       => "Flag marking an option as never expiring.",
                'default'           => false,
                'visible'           => ['status', '=', 'option']
            ],

            'cancellation_reason' => [
                'type'              => 'string',
                'selection'         => [
                    'other',                    // customer cancelled for a non-listed reason or without mentioning the reason (cancellation fees might apply)
                    'overbooking',              // the booking was cancelled due to failure in delivery of the service
                    'duplicate',                // several contacts of the same group made distinct bookings for the same sojourn
                    'internal_impediment',      // cancellation due to an incident impacting the rental units
                    'external_impediment',      // cancellation due to external delivery failure (organisation, means of transport, ...)
                    'health_impediment',        // cancellation for medical or mourning reason
                    'ota'                       // cancellation was made through the channel manager
                ],
                'description'       => "Reason for which the customer cancelled the booking.",
                'default'           => 'other'
            ],

            'payment_status' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'icon',
                'selection'         => [
                    'due',
                    'paid'
                ],
                'function'          => 'calcPaymentStatus',
                'store'             => true,
                'description'       => "Current status of the payments. Depends on the status of the booking.",
                'help'              => "'Due' means we are expecting some money for the booking (at the moment, at least one due funding has not been fully received). 'Paid' means that everything expected (all payments) has been received."
            ],

            'payment_plan_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\PaymentPlan',
                'description'       => 'The payment plan that has been automatically assigned.'
            ],

            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPaymentReference',
                'description'       => 'Structured reference for identifying payments relating to the Booking.',
                'store'             => true
            ],

            'display_payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcDisplayPaymentReference',
                'description'       => 'Formatted Structured reference as shown in Bank Statements.',
            ],

            // date fields are based on dates from booking line groups
            'date_from' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'function'          => 'calcDateFrom',
                'store'             => true,
                'default'           => time()
            ],

            'date_to' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'function'          => 'calcDateTo',
                'store'             => true,
                'default'           => time()
            ],

            // time fields are based on dates from booking line groups
            'time_from' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'function'          => 'calcTimeFrom',
                'store'             => true
            ],

            'time_to' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'function'          => 'calcTimeTo',
                'store'             => true
            ],

            'nb_pers' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Approx. amount of persons involved in the booking.',
                'function'          => 'calcNbPers',
                'store'             => true
            ],

            'has_payer_organisation' => [
                'type'              => 'boolean',
                'description'       => "Flag to know if invoice must be sent to another Identity.",
                'default'           => false
            ],

            'payer_organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'visible'           => [ 'has_payer_organisation', '=', true ],
                'domain'            => [ ['owner_identity_id', '=', 'object.customer_identity_id'], ['relationship', '=', 'payer'] ],
                'description'       => "The partner whom the invoices have to be sent to, if any."
            ],

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\SojournType',
                'description'       => 'Default sojourn type of the booking (set according to booking center).'
            ],

            'sojourn_product_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\SojournProductModel',
                'foreign_field'     => 'booking_id',
                'description'       => "The product models groups assigned to the booking (from groups).",
                'ondetach'          => 'delete'
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Funding',
                'foreign_field'     => 'booking_id',
                'description'       => 'Fundings that relate to the booking.',
                'ondetach'          => 'delete'
            ],

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Invoice',
                'foreign_field'     => 'booking_id',
                'description'       => 'Invoices that relate to the booking.',
                'ondetach'          => 'delete'
            ],

            'is_invoiced' => [
                "type"              => "boolean",
                "description"       => "Marks the booking has having a non-cancelled balance invoice.",
                "default"           => false,
                'onupdate'          => 'onupdateIsInvoiced'
            ],

            'has_transport' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Mark the booking as completed by a Tour Operator.',
                'store'             => true,
                'function'          => 'calcHasTransport'
            ],

            'has_tour_operator' => [
                'type'              => 'boolean',
                'description'       => 'Mark the booking as completed by a Tour Operator.',
                'default'           => false
            ],

            'tour_operator_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\TourOperator',
                'domain'            => ['is_tour_operator', '=', true],
                'description'       => 'Tour Operator that completed the booking.',
                'visible'           => ['has_tour_operator', '=', true]
            ],

            'tour_operator_ref' => [
                'type'              => 'string',
                'description'       => 'Specific reference, voucher code, or booking ID for the TO.',
                'visible'           => ['has_tour_operator', '=', true]
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', 'sale\booking\Booking']
            ],

            'rental_unit_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\SojournProductModelRentalUnitAssignement',
                'foreign_field'     => 'booking_id',
                'description'       => "The rental units assigned to the group (from lines)."
            ],

            'is_locked' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'A booking can be locked to prevent updating the sale price of contracted products.',
                'function'          => 'calcIsLocked'
            ],

            'alert' => [
                'type'              => 'computed',
                'usage'             => 'icon',
                'result_type'       => 'string',
                'function'          => 'calcAlert'
            ],

            'paid_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that has been received so far.",
                'function'          => 'calcPaidAmount',
                'store'             => true
            ],

            'guest_list_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\GuestList',
                'description'       => 'Guest List associated to booking'
            ],

            'guest_list_items_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\GuestListItem',
                'foreign_field'     => 'booking_id',
                'description'       => "The guest items that refer to the booking."
            ],

            'bookings_inspections_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingInspection',
                'foreign_field'     => 'booking_id',
                'description'       => "The booking inspection relates to Booking.."
            ],

            'consumptions_meter_reading_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\ConsumptionMeterReading',
                'foreign_field'     => 'booking_id',
                'description'       => "The consumptions meter readings  relates to Booking.."
            ],

            'booking_activities_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingActivity',
                'foreign_field'     => 'booking_id',
                'description'       => "The booking activities that refer to the booking."
            ],

            'activity_weeks_descriptions' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => "JSON description of activities per week, for week planning document."
            ],

            'tasks_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'booking_id',
                'foreign_object'    => 'sale\booking\followup\Task',
                'description'       => "Follow up tasks that are associated with the booking."
            ],

            'booking_points_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'booking_id',
                'foreign_object'    => 'sale\booking\BookingPoint',
                'description'       => "Loyalty points that are generated by the booking."
            ],

            'booking_applied_points_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'booking_apply_id',
                'foreign_object'    => 'sale\booking\BookingPoint',
                'description'       => "Loyalty points that are applied on the booking."
            ]

        ];
    }

    public static function getActions(): array {
        return [
            'import_contacts' => [
                'description'   => 'Import contacts from customer identity.',
                'policies'      => [],
                'function'      => 'doImportContacts'
            ],

            'delete_unpaid_fundings' => [
                'description'   => 'Removes booking\'s fundings that haven\'t received any payment.',
                'help'          => 'Used when a booking is cancelled.',
                'policies'      => [],
                'function'      => 'doDeleteUnpaidFundings'
            ],

            'update_fundings_due_to_paid' => [
                'description'   => 'Sets partially paid fundings due_amount to the value of paid_amount.',
                'help'          => 'Used when a booking is cancelled.',
                'policies'      => [],
                'function'      => 'doUpdateFundingsDueToPaid'
            ],

            'create_negative_funding_for_reimbursement' => [
                'description'   => 'Creates a negative funding entry for reimbursement.',
                'help'          => 'Used when a booking is cancelled.',
                'policies'      => [],
                'function'      => 'doCreateNegativeFundingForReimbursement'
            ],

            'refresh_groups_activity_number' => [
                'description'   => 'Refreshes the booking line groups activity number.',
                'help'          => '',
                'policies'      => [],
                'function'      => 'doRefreshGroupsActivityNumber'
            ]
        ];
    }

    /**
     * #memo - this is necessary when a Booking is created at once using ::create
     *
     */
    public static function oncreate($self) {
        $self->do('import_contacts');
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];

        $bookings = $om->read(self::getType(), $oids, ['center_id.center_office_id.code'], $lang);
        $format = Setting::get_value('sale', 'organization', 'booking.sequence_format', '%05d{sequence}');

        foreach($bookings as $oid => $booking) {
            $sequence_name = 'booking.sequence.'.$booking['center_id.center_office_id.code'];
            $sequence_value = Setting::get_value('sale', 'organization', $sequence_name);

            if($sequence_value) {
                Setting::set_value('sale', 'organization', $sequence_name, $sequence_value + 1);
                $result[$oid] = Setting::parse_format($format, [
                    'center'    => $booking['center_id.center_office_id.code'],
                    'sequence'  => $sequence_value
                ]);
            }

        }
        return $result;
    }

    public static function calcDisplayName($self) {
        $result = [];
        $self->read(['state', 'name', 'customer_identity_id' => ['id', 'name', 'accounting_account', 'address_city']]);
        foreach($self as $id => $booking) {
            if($booking['state'] === 'draft') {
                continue;
            }

            $format = Setting::get_value('sale', 'booking', 'booking.name_format', '%s{name} - %s{customer_name} (%s{customer_identity_id})');

            $result[$id] = Setting::parse_format($format, [
                'name'                          => $booking['name'],
                'customer_identity_id'          => $booking['customer_identity_id']['id'],
                'customer_name'                 => $booking['customer_identity_id']['name'],
                'customer_accounting_account'   => $booking['customer_identity_id']['accounting_account'] ?? '',
                'customer_address_city'         => $booking['customer_identity_id']['address_city'] ?? ''
            ]);
        }
        return $result;
    }

    public static function calcDateFrom($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['booking_lines_groups_ids']);

        foreach($bookings as $bid => $booking) {
            $min_date = PHP_INT_MAX;
            $booking_line_groups = $om->read('sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['date_from', 'is_sojourn', 'is_event']);
            if($booking_line_groups > 0 && count($booking_line_groups)) {
                foreach($booking_line_groups as $gid => $group) {
                    if( ($group['is_sojourn']  || $group['is_event'] ) && $group['date_from'] < $min_date) {
                        $min_date = $group['date_from'];
                    }
                }
                $result[$bid] = ($min_date == PHP_INT_MAX) ? time() : $min_date;
            }
        }

        return $result;
    }

    public static function calcDateTo($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['booking_lines_groups_ids']);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $max_date = 0;
                $booking_line_groups = $om->read('sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['date_to', 'is_sojourn', 'is_event']);
                if($booking_line_groups > 0 && count($booking_line_groups)) {
                    foreach($booking_line_groups as $gid => $group) {
                        if( ($group['is_sojourn']  || $group['is_event'] ) && $group['date_to'] > $max_date) {
                            $max_date = $group['date_to'];
                        }
                    }
                    $result[$bid] = ($max_date == 0) ? time() : $max_date;
                }
            }
        }

        return $result;
    }

    public static function calcTimeFrom($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(__CLASS__, $oids, ['booking_lines_groups_ids']);

        foreach($bookings as $bid => $booking) {
            $min_date = PHP_INT_MAX;
            $time_from = Setting::get_value('sale', 'features',  'booking.checkin.default', 14 * 3600);
            $booking_line_groups = $om->read('sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['date_from', 'time_from', 'is_sojourn', 'is_event']);
            if($booking_line_groups > 0 && count($booking_line_groups)) {
                foreach($booking_line_groups as $gid => $group) {
                    if(($group['is_sojourn']  || $group['is_event'] ) && $group['date_from'] < $min_date) {
                        $min_date = $group['date_from'];
                        $time_from = $group['time_from'];
                    }
                }
            }
            $result[$bid] = $time_from;
        }

        return $result;
    }

    public static function calcTimeTo($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(__CLASS__, $oids, ['booking_lines_groups_ids']);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $max_date = 0;
                $time_to =  Setting::get_value('sale', 'features',  'booking.checkout.default' ,  10 * 3600);
                $booking_line_groups = $om->read('sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['date_to', 'time_to', 'is_sojourn', 'is_event']);
                if($booking_line_groups > 0 && count($booking_line_groups)) {
                    foreach($booking_line_groups as $gid => $group) {
                        if(($group['is_sojourn']  || $group['is_event'] ) && $group['date_to'] > $max_date) {
                            $max_date = $group['date_to'];
                            $time_to = $group['time_to'];
                        }
                    }
                }
                $result[$bid] = $time_to;
            }
        }

        return $result;
    }

    public static function calcNbPers($self) {
        $result = [];
        $self->read(['booking_lines_groups_ids' => ['nb_pers', 'is_autosale', 'is_extra', 'group_type']]);
        foreach($self as $id => $booking) {
            $nb_pers = 0;

            // pass-1 - consider only sojourns
            foreach($booking['booking_lines_groups_ids'] as $group) {
                if($group['is_autosale'] || $group['is_extra']) {
                    continue;
                }
                if($group['group_type'] === 'sojourn') {
                    $nb_pers += $group['nb_pers'];
                }
            }

            // pass-2 - no sojourn, consider other types (event, camp, simple)
            if($nb_pers === 0) {
                foreach($booking['booking_lines_groups_ids'] as $group) {
                    if($group['is_autosale'] || $group['is_extra']) {
                        continue;
                    }
                    $nb_pers += $group['nb_pers'];
                }
            }

            // pass-3 - no match, fall back to any group available
            if($nb_pers === 0) {
                foreach($booking['booking_lines_groups_ids'] as $group) {
                    $nb_pers += $group['nb_pers'];
                }
            }

            $result[$id] = $nb_pers;
        }

        return $result;
    }

    public static function calcHasTransport($self): array {
        $result = [];
        $self->read(['booking_lines_ids' => ['product_model_id' => ['is_transport']]]);
        foreach($self as $id => $booking) {
            $has_transport = false;
            foreach($booking['booking_lines_ids'] as $line) {
                if($line['product_model_id']['is_transport'] ?? false) {
                    $has_transport = true;
                    break;
                }
            }

            $result[$id] = $has_transport;
        }

        return $result;
    }

    /**
     * Computes the paid_amount property based on the bookings fundings.
     *
     */
    public static function calcPaidAmount($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['fundings_ids'], $lang);

        if($bookings > 0) {
            foreach($bookings as $oid => $booking) {
                $fundings = $om->read(\sale\booking\Funding::getType(), $booking['fundings_ids'], ['due_amount', 'is_paid', 'paid_amount'], $lang);
                $paid_amount = 0.0;
                if($fundings > 0) {
                    foreach($fundings as $fid => $funding) {
                        if($funding['is_paid']) {
                            $paid_amount += $funding['due_amount'];
                        }
                        elseif($funding['paid_amount'] > 0) {
                            $paid_amount += $funding['paid_amount'];
                        }
                    }
                }
                $result[$oid] = $paid_amount;
            }
        }
        return $result;
    }

    /**
     * Payment status tells if a given booking is currently expecting money.
     */
    public static function calcPaymentStatus($om, $ids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $ids, ['status', 'fundings_ids', 'contracts_ids'], $lang);
        if($bookings > 0 && count($bookings)) {
            foreach($bookings as $id => $booking) {
                $result[$id] = 'paid';
                $fundings = $om->read(\sale\booking\Funding::getType(), $booking['fundings_ids'], ['due_date', 'is_paid'], $lang);
                // if there is at least one overdue funding : a payment is 'due', otherwise booking is 'paid'
                if($fundings > 0 && count($fundings)) {
                    $has_one_paid = false;
                    foreach($fundings as $funding) {
                        if($funding['is_paid']) {
                            $has_one_paid = true;
                        }
                        if(!$funding['is_paid'] && $funding['due_date'] > time()) {
                            $result[$id] = 'due';
                            break;
                        }
                    }
                }
            }
        }
        return $result;
    }

    public static function calcPrice($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(get_called_class(), $oids, ['booking_lines_groups_ids.price']);
        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $price = array_reduce((array) $booking['booking_lines_groups_ids.price'], function ($c, $group) {
                    return $c + $group['price'];
                }, 0.0);
                $result[$bid] = round($price, 2);
            }
        }
        return $result;
    }

    public static function calcTotal($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(get_called_class(), $oids, ['booking_lines_groups_ids.total']);
        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $total = array_reduce((array) $booking['booking_lines_groups_ids.total'], function ($c, $a) {
                    return $c + round($a['total'], 2);
                }, 0.0);
                $result[$bid] = round($total, 4);
            }
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
    public static function calcPaymentReference($self) {
        $result = [];
        $self->read(['name', 'customer_identity_id' => ['name', 'id', 'accounting_account']]);
        foreach($self as $id => $booking) {
            // #memo - arbitrary value : used in the accounting software for identifying payments with a temporary account entry counterpart
            $code_ref =  Setting::get_value('sale', 'organization', 'booking.reference.code', 150);
            $reference_type =  Setting::get_value('sale', 'organization', 'booking.reference.type', 'VCS');

            $booking_code = intval($booking['name']);

            switch($reference_type) {
                // use Customer accounting_account as reference (from Identity)
                case 'accounting_account':
                    $reference_value = $booking['customer_identity_id']['accounting_account'];
                    break;
                // ISO-11649
                case 'RF':
                    // structure: RFcc nnn... (up to 25 alpha-num chars) (we omit the 'RF' part in the result)
                    // build a numeric reference
                    $ref_base = sprintf('%03d%07d', $code_ref, $booking_code);
                    // append 'RF00' to compute the check
                    $tmp = $ref_base . 'RF00';
                    // replace letters with digits
                    $converted = '';
                    foreach(str_split($tmp) as $char) {
                        // #memo - in ISO-11649 'A' = 10
                        $converted .= ctype_alpha($char) ? (ord($char) - 55) : $char;
                    }
                    $mod97 = intval(bcmod($converted, '97'));
                    $control = str_pad(98 - $mod97, 2, '0', STR_PAD_LEFT);
                    $reference_value = $control . $ref_base;
                    break;
                // FR specific
                case 'RN':
                    // structure : RNccxxxxxxxxxxxxxxxxxxxx + key + up to 20 digits (we omit the 'RN' part in the result)
                    $ref_body = sprintf('%013d%07d', $code_ref, $booking_code);
                    $control = 97 - intval(bcmod($ref_body, '97'));
                    $reference_value = str_pad($control, 2, '0', STR_PAD_LEFT) . $ref_body;
                    break;
                // BE specific
                case 'VCS':
                    // structure: +++xxx/xxxx/xxxcc+++ where cc is the control result (we omit the '+' and '/' chars in the result)
                default:
                    $control = ((76 * intval($code_ref)) + $booking_code) % 97;
                    $control = ($control == 0) ? 97 : $control;
                    $reference_value = sprintf('%3d%04d%03d%02d', $code_ref, $booking_code / 1000, $booking_code % 1000, $control);
            }

            $result[$id] = $reference_value;
        }
        return $result;
    }

    public static function calcDisplayPaymentReference($om, $ids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $ids, ['payment_reference'], $lang);
        $reference_type = Setting::get_value('sale', 'organization', 'booking.reference.type', 'VCS');
        foreach($bookings as $id => $booking) {
            $reference = $booking['payment_reference'];
            if(in_array($reference, ['RN', 'RF', 'VCS'])) {
                DataFormatter::format($booking['payment_reference'], $reference_type);
            }
            $result[$id] = $reference;
        }
        return $result;
    }

    public static function calcIsLocked($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['contracts_ids'], $lang);
        foreach($bookings as $oid => $booking) {
            $result[$oid] = false;
            if(count($booking['contracts_ids'])) {
                $contracts = $om->read(Contract::getType(), $booking['contracts_ids'], ['is_locked', 'status'], $lang);
                foreach($contracts as $contract) {
                    if($contract['status'] != 'cancelled' && $contract['is_locked']) {
                        $result[$oid] = true;
                    }
                }
            }
        }
        return $result;
    }

    public static function calcAlert($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['contracts_ids'], $lang);
        foreach($bookings as $oid => $booking) {

            $messages_ids = $om->search('core\alert\Message',[ ['object_class', '=', 'sale\booking\Booking'], ['object_id', '=', $oid]]);
            if($messages_ids > 0 && count($messages_ids)) {
                $max_alert = 0;
                $map_alert = array_flip([
                    'notice',           // weight = 1, might lead to a warning
                    'warning',          // weight = 2, might be important, might require an action
                    'important',        // weight = 3, requires an action
                    'error'             // weight = 4, requires immediate action
                ]);
                $messages = $om->read(\core\alert\Message::getType(), $messages_ids, ['severity']);
                foreach($messages as $mid => $message){
                    $weight = $map_alert[$message['severity']];
                    if($weight > $max_alert) {
                        $max_alert = $weight;
                    }
                }
                switch($max_alert) {
                    case 0:
                        $result[$oid] = 'info';
                        break;
                    case 1:
                        $result[$oid] = 'warn';
                        break;
                    case 2:
                        $result[$oid] = 'major';
                        break;
                    case 3:
                    default:
                        $result[$oid] = 'error';
                        break;
                }
            }
            else {
                $result[$oid] = 'success';
            }
        }
        return $result;
    }

    public static function calcCurrentContractId($self) {
        $result = [];
        $self->read(['contracts_ids']);
        foreach($self as $id => $booking) {
            if(count($booking['contracts_ids'])) {
                $result[$id] = current($booking['contracts_ids']);
            }
            else {
                $result[$id] = null;
            }
        }
        return $result;
    }

    /**
     * This method is used to adjust the final status of a Booking according to the payment status of its fundings.
     * #memo - this method differs from the parent one.
     */
    public static function updateStatusFromFundings($om, $ids, $values=[], $lang='en') {
        $bookings = Booking::ids($ids)->read(['status', 'price', 'fundings_ids'])->get();
        if($bookings > 0 && count($bookings)) {
            foreach($bookings as $id => $booking) {
                if($booking['status'] == 'confirmed') {
                    $contracts_ids = $om->search(\sale\booking\Contract::getType(), [
                        ['booking_id', '=', $id],
                        ['status', '=', 'signed']
                    ]);
                    // discard booking with non-signed contract
                    if(!$contracts_ids && count($contracts_ids) <= 0) {
                        continue;
                    }
                    if(count($booking['fundings_ids']) == 0) {
                        // contract signed & no funding : booking is validated
                        $om->update(self::getType(), $id, ['status' => 'validated'], $lang);
                    }
                    // there is at least one funding
                    else {
                        $today = time();
                        $fundings_ids = $om->search(\sale\booking\Funding::getType(), [
                            ['booking_id', '=', $id],
                            ['due_date', '<', $today]
                        ]);
                        // if there are fundings with passed due_date
                        if($fundings_ids && count($fundings_ids) > 0) {
                            $all_paid = true;
                            $fundings = $om->read(\sale\booking\Funding::getType(), $fundings_ids, ['is_paid'], $lang);
                            foreach($fundings as $fid => $funding) {
                                if(!$funding['is_paid']) {
                                    $all_paid = false;
                                    break;
                                }
                            }
                            if($all_paid) {
                                // if all fundings with passed due date are paid : booking is validated
                                $om->update(self::getType(), $id, ['status' => 'validated'], $lang);
                            }
                        }
                        // all fundings have a due_date in the future
                        else {
                            $fundings_ids = $om->search(\sale\booking\Funding::getType(), [
                                ['booking_id', '=', $id],
                                ['is_paid', '=', true]
                            ]);
                            // there is at least one funding marked as paid : mark booking as validated
                            if($fundings_ids && count($fundings_ids) > 0) {
                                $om->update(self::getType(), $id, ['status' => 'validated'], $lang);
                            }
                        }
                    }
                }
                elseif(in_array($booking['status'], ['invoiced', 'balanced', 'debit_balance', 'credit_balance'])) {
                    // booking is candidate to status change only if it has a non-cancelled balance invoice
                    $invoices_ids = $om->search(\sale\booking\Invoice::getType(), [['booking_id', '=', $id], ['type', '=', 'invoice'], ['is_deposit', '=', false], ['status', '=', 'invoice']]);
                    if($invoices_ids < 0 || !count($invoices_ids)) {
                        // target booking has not yet been invoiced : ignore match
                        continue;
                    }
                    $sum = 0.0;
                    $fundings = $om->read(\sale\booking\Funding::gettype(), $booking['fundings_ids'], ['due_amount', 'paid_amount'], $lang);
                    foreach($fundings as $fid => $funding) {
                        $sum += $funding['paid_amount'];
                    }
                    if(round($sum, 2) < round($booking['price'], 2)) {
                        // an unpaid amount remains
                        $om->update(self::getType(), $id, ['status' => 'debit_balance']);
                    }
                    elseif(round($sum, 2) > round($booking['price'], 2)) {
                        // a reimbursement is due
                        $om->update(self::getType(), $id, ['status' => 'credit_balance']);
                    }
                    else {
                        // everything has been paid : booking can be archived
                        $om->update(self::getType(), $id, ['status' => 'balanced']);
                    }
                }
            }
        }
    }

    // #todo - this should be part of the onupdate() hook
    public static function _resetPrices($om, $oids, $values, $lang) {
        $om->update(__CLASS__, $oids, ['total' => null, 'price' => null]);
    }

    public static function onupdateBookingLinesGroupsIds($om, $oids, $values, $lang) {
        $om->callonce(self::getType(), '_resetPrices', $oids, [], $lang);
    }

    /**
     * Maintain sync of has_contract flag and with customer count_booking_12 and count_booking_24
     */
    public static function onupdateStatus($om, $ids, $values, $lang) {
        $bookings = $om->read(self::getType(), $ids, ['status', 'customer_id'], $lang);
        if($bookings > 0) {
            foreach($bookings as $id => $booking) {
                if($booking['status'] == 'confirmed') {
                    $om->update(self::getType(), $id, ['has_contract' => true], $lang);
                }

                $loyalty_points_feature = Setting::get_value('sale', 'features', 'booking.loyalty_points', false);

                if($loyalty_points_feature) {
                    $bookingPoint = BookingPoint::search(['booking_id', '=', $id])->read(['id', 'booking_apply_id'])->first();

                    if(!$bookingPoint) {
                        $bookingPoint = BookingPoint::create(['booking_id' => $id])->read(['id', 'booking_apply_id'])->first();
                    }

                    if(!isset($bookingPoint['booking_apply_id'])) {
                        // call a refresh
                        BookingPoint::id($bookingPoint['id'])->do('refresh_points');
                    }
                }

                \eQual::run('do', 'sale_booking_followup_generate-task-status-change', ['booking_id' => $id]);
            }
        }
    }

    /**
     * Handler for updating values relating the customer.
     * Customer and Identity are synced : only the identity can be changed through views, customer always derives from the selected identity.
     * This handler is always triggered by the onupdateCustomerIdentityId method.
     *
     * @param  \equal\orm\ObjectManager     $om        Object Manager instance.
     * @param  array                        $oids      List of objects identifiers.
     * @param  array                        $values    Associative array mapping fields names with new values tha thave been assigned.
     * @param  string                       $lang      Language (char 2) in which multilang field are to be processed.
     */
    public static function onupdateCustomerId($om, $oids, $values, $lang) {

        /*
        // #memo - this has been removed to allow manual assignment of rat_class_id
        // update rate_class, based on customer
        $bookings = $om->read(self::getType(), $oids, [
            'booking_lines_groups_ids',
            'customer_id.rate_class_id',
        ], $lang);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                // update bookingline group rate_class_id   (triggers resetPrices and updatePriceAdapters)
                if($booking['booking_lines_groups_ids'] && count($booking['booking_lines_groups_ids'])) {
                    if($booking['customer_id.rate_class_id']) {
                        $om->update(\sale\booking\BookingLineGroup::getType(), $booking['booking_lines_groups_ids'], ['rate_class_id' => $booking['customer_id.rate_class_id']], $lang);
                    }
                }
            }
        }
        */

        // update auto sale products
        $om->callonce(self::getType(), 'updateAutosaleProducts', $oids, [], $lang);
    }

    /**
     * Generate one or more groups for products sold automatically.
     * We generate services groups related to autosales when the following fields are updated:
     * customer, date_from, date_to, center_id
     *
     */
    public static function updateAutosaleProducts($om, $ids, $values, $lang) {
        foreach($ids as $id) {
            self::refreshAutosaleProducts($om, $id);
        }
    }

    /**
     * Maintain sync with Customer
     */
    public static function onupdateCustomerNatureId($om, $oids, $values, $lang) {
        $bookings = $om->read(self::getType(), $oids, ['customer_id', 'customer_rate_class_id', 'customer_nature_id', 'customer_nature_id.rate_class_id'], $lang);

        if($bookings > 0) {
            foreach($bookings as $id => $booking) {
                if($booking['customer_nature_id.rate_class_id'] && $booking['customer_id']) {
                    $om->update('sale\customer\Customer', $booking['customer_id'], [
                        'customer_nature_id'    => $booking['customer_nature_id']
                    ]);
                    if(!$booking['customer_rate_class_id']) {
                        $om->update('sale\customer\Customer', $booking['customer_id'], [
                            'rate_class_id'         => $booking['customer_nature_id.rate_class_id']
                        ]);
                    }
                }
            }
        }
    }

    public static function onupdateCustomerRateClassId($om, $oids, $values, $lang) {
        $bookings = $om->read(self::getType(), $oids, ['customer_id', 'customer_rate_class_id'], $lang);

        if($bookings > 0) {
            foreach($bookings as $id => $booking) {
                if($booking['customer_rate_class_id'] && $booking['customer_id']) {
                    $om->update('sale\customer\Customer', $booking['customer_id'], [
                        'rate_class_id'         => $booking['customer_rate_class_id']
                    ]);
                }
            }
        }
    }

    /**
     * #workaround - Prevent name update by other mean than calculation.
     */
    public static function onupdateName($om, $ids, $values, $lang) {
        $res = $om->read(self::getType(), $ids, ['name'], $lang);
        if($res > 0 && count($res)) {
            foreach($res as $id => $booking) {
                // reset name set with a filter value
                if(strpos($booking['name'], '%') !== false) {
                    $om->update(self::getType(), $id, ['name' => null]);
                }
            }
        }
    }

    /**
     * Maintain sync with Customer when assigning a new customer by selecting a customer_identity_id
     * Customer is always selected by picking up an identity (there should always be only one 'customer' partner for a given identity for current organisation).
     * If the identity has a parent identity (department or subsidiary), the customer is based on that parent identity.
     *
     * @param  \equal\orm\ObjectManager     $om        Object Manager instance.
     * @param  array                        $oids      List of objects identifiers.
     * @param  array                        $values    Associative array mapping fields names with new values that have been assigned.
     * @param  string                       $lang      Language (char 2) in which multilang field are to be processed.
     */
    public static function onupdateCustomerIdentityId($om, $oids, $values, $lang) {

        $bookings = $om->read(self::getType(), $oids, [
            'description',
            'customer_identity_id',
            'customer_identity_id.description',
            'customer_identity_id.has_parent',
            'customer_identity_id.parent_id',
            'customer_nature_id',
            'customer_nature_id.rate_class_id',
            'customer_rate_class_id'
        ]);

        if($bookings > 0) {
            foreach($bookings as $oid => $booking) {
                $partner_id = null;
                $identity_id = $booking['customer_identity_id'];
                if($booking['customer_identity_id.has_parent'] && $booking['customer_identity_id.parent_id']) {
                    $identity_id = $booking['customer_identity_id.parent_id'];
                }
                // find the partner that relates to the target identity, if any
                $partners_ids = $om->search('sale\customer\Customer', [
                    ['relationship', '=', 'customer'],
                    ['owner_identity_id', '=', 1],
                    ['partner_identity_id', '=', $identity_id]
                ]);
                if(count($partners_ids)) {
                    $partner_id = reset($partners_ids);
                }
                else {
                    // create a new customer for the selected identity
                    $identities = $om->read('identity\Identity', $identity_id, ['type_id']);
                    if($identities > 0 && count($identities)) {
                        $identity = reset($identities);
                        $partner_id = $om->create('sale\customer\Customer', [
                            'partner_identity_id'   => $identity_id,
                            'customer_type_id'      => $identity['type_id'],
                            'rate_class_id'         => $booking['customer_rate_class_id'] ?? $booking['customer_nature_id.rate_class_id'],
                            'customer_nature_id'    => $booking['customer_nature_id']
                        ]);
                    }
                }

                $values = [];

                // if description is empty, replace it with assigned customer's description
                if(strlen((string) $booking['description']) == 0) {
                    $values['description'] = $booking['customer_identity_id.description'];
                }

                if($partner_id && $partner_id > 0) {
                    // will trigger an update of the rate_class for existing booking_lines
                    $values['customer_id'] = $partner_id;
                }
                $om->update(self::getType(), $oid, $values);
            }

        }
    }

    public static function doImportContacts($self, $om) {
        $self->read(['customer_identity_id', 'contacts_ids']);

        foreach($self as $id => $booking) {
            if(is_null($booking['customer_identity_id']) || $booking['customer_identity_id'] <= 0) {
                continue;
            }
            // ignore special case where customer is the organisation itself
            if($booking['customer_identity_id'] == 1) {
                continue;
            }

            // remove all previously auto created contacts
            $previous_contacts_ids = $om->search(\sale\booking\Contact::getType(), [ ['booking_id', '=', $id], ['origin', '=', 'auto'] ] );
            if($previous_contacts_ids > 0) {
                $om->delete(\sale\booking\Contact::getType(), $previous_contacts_ids, true);
            }

            $partners_ids = [];
            $existing_partners_ids = [];
            // read all contacts (to prevent importing contacts twice)
            if($booking['contacts_ids'] && count($booking['contacts_ids'] )) {
                // #memo - we don't remove previously added contacts to keep user's work
                // $om->delete(Contact::getType(), $booking['contacts_ids'], true);
                $contacts = $om->read(\sale\booking\Contact::getType(), $booking['contacts_ids'], ['partner_identity_id']);
                $existing_partners_ids = array_map(function($a) { return $a['partner_identity_id'];}, $contacts);
            }
            // if customer has contacts assigned to its identity, import those
            $identity_contacts_ids = $om->search(\identity\Contact::getType(), [
                ['owner_identity_id', '=', $booking['customer_identity_id']],
                ['relationship', '=', 'contact']
            ]);
            if($identity_contacts_ids > 0 && count($identity_contacts_ids) > 0) {
                $contacts = $om->read(\identity\Contact::getType(), $identity_contacts_ids, ['partner_identity_id']);
                foreach($contacts as $cid => $contact) {
                    if($contact['partner_identity_id']) {
                        $partners_ids[] = $contact['partner_identity_id'];
                    }
                }
            }
            // append customer identity's own contact
            $partners_ids[] = $booking['customer_identity_id'];
            // keep only partners_ids not present yet - limit to max 5 contacts
            // #todo - store MAX value in Settings
            $partners_ids = array_slice(array_diff($partners_ids, $existing_partners_ids), 0, 5);
            // create booking contacts
            foreach($partners_ids as $partner_id) {
                if(!$partner_id) {
                    continue;
                }
                $om->create(\sale\booking\Contact::getType(), [
                    'booking_id'            => $id,
                    'owner_identity_id'     => $booking['customer_identity_id'],
                    'partner_identity_id'   => $partner_id,
                    'origin'                => 'auto'
                ]);
            }
        }
    }

    public static function doDeleteUnpaidFundings($self) {
        $self->read(['fundings_ids' => ['is_paid', 'paid_amount']]);
        foreach($self as $booking) {
            foreach($booking['fundings_ids'] as $funding_id => $funding) {
                if($funding['paid_amount'] > 0 || $funding['is_paid']) {
                    continue;
                }

                Funding::id($funding_id)->delete(true);
            }
        }
    }

    public static function doUpdateFundingsDueToPaid($self) {
        $self->read(['fundings_ids' => ['due_amount', 'paid_amount']]);
        foreach($self as $booking) {
            foreach($booking['fundings_ids'] as $funding_id => $funding) {
                if($funding['due_amount'] <= 0) {
                    continue;
                }

                Funding::id($funding_id)
                    ->update(['due_amount' => $funding['paid_amount']]);
            }
        }
    }

    public static function doCreateNegativeFundingForReimbursement($self) {
        $self->read(['center_office_id', 'fundings_ids' => ['paid_amount']]);
        foreach($self as $id => $booking) {
            $total_paid_amount = 0;
            foreach($booking['fundings_ids'] as $funding) {
                $total_paid_amount += $funding['paid_amount'];
            }

            if($total_paid_amount > 0) {
                Funding::create([
                    'description'       => "Remboursement annulation",
                    'booking_id'        => $id,
                    'center_office_id'  => $booking['center_office_id'],
                    'due_amount'        => round(-$total_paid_amount, 2),
                    'is_paid'           => false,
                    'type'              => 'installment',
                    'order'             => count($booking['fundings_ids']) + 1,
                    'issue_date'        => time(),
                    'due_date'          => time()
                ]);
            }
        }
    }

    public static function doRefreshGroupsActivityNumber($self) {
        $self->read(['booking_lines_groups_ids' => ['order', 'group_type']]);
        foreach($self as $booking) {
            $groups_ids = [];
            $map_order_groups_ids = [];
            foreach($booking['booking_lines_groups_ids'] as $group) {
                $groups_ids[] = $group['id'];
                if($group['group_type'] === 'camp') {
                    $map_order_groups_ids[$group['order']] = $group['id'];
                }
            }

            BookingLineGroup::ids($groups_ids)->update([
                'activity_group_num' => null
            ]);

            $group_camp_name_format = Setting::get_value('sale', 'booking', 'group.camp.name_format');

            $group_ids = array_values($map_order_groups_ids);
            foreach($group_ids as $index => $group_id) {
                $activity_group_num = $index + 1;

                $data = compact('activity_group_num');
                if(!is_null($group_camp_name_format)) {
                    $data['name'] = Setting::parse_format($group_camp_name_format, [
                        'center_name'           => $booking['center_id']['name'],
                        'activity_group_num'    => $activity_group_num
                    ]);
                }

                BookingLineGroup::id($group_id)->update($data);
            }
        }
    }

    public static function onupdateIsInvoiced($om, $oids, $values, $lang) {
        $bookings = $om->read(self::getType(), $oids, ['is_invoiced', 'booking_lines_ids']);
        foreach($bookings as $id => $booking) {
            // update all booking lines accordingly to their parent booking
            $om->update(BookingLine::getType(), $booking['booking_lines_ids'], ['is_invoiced' => $booking['is_invoiced']]);
        }
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

        if(isset($event['date_from'])) {
            if($values['date_to'] < $event['date_from']) {
                $result['date_to'] = $event['date_from'];
            }
        }
        // try to retrieve nature from an identity
        if(isset($event['center_id'])) {
            $center = Center::id($event['center_id'])->read(['organisation_id' => ['id', 'name'], 'center_office_id' => ['id', 'name']])->first();
            if($center) {
                $result['organisation_id'] = ['id' => $center['organisation_id']['id'], 'name' => $center['organisation_id']['name']];
                $result['center_office_id'] = ['id' => $center['center_office_id']['id'], 'name' => $center['center_office_id']['name']];
            }
        }

        // try to retrieve nature from an identity
        if(isset($event['customer_identity_id'])) {
            $partner = Customer::search([
                    ['relationship', '=', 'customer'],
                    ['owner_identity_id', '=', 1],
                    ['partner_identity_id', '=', $event['customer_identity_id']]
                ])
                ->read(['id', 'name', 'customer_nature_id' => ['id', 'name'], 'rate_class_id' => ['id', 'name']])
                ->first();

            $result['customer_id'] = [
                    'id' => $partner['id'],
                    'name' => $partner['name']
                ];
            if(isset($partner['customer_nature_id'])) {
                $result['customer_nature_id'] = [
                        'id'    => $partner['customer_nature_id']['id'],
                        'name'  => $partner['customer_nature_id']['name']
                    ];
            }
            if(isset($partner['rate_class_id'])) {
                $result['customer_rate_class_id'] = [
                        'id'    => $partner['rate_class_id']['id'],
                        'name'  => $partner['rate_class_id']['name']
                    ];
            }

        }
        elseif(isset($event['customer_nature_id'])) {
            $customer_nature = CustomerNature::id($event['customer_nature_id'])->read(['rate_class_id' => ['id', 'name']])->first(true);
            $result['customer_rate_class_id'] = [
                'id'    => $customer_nature['rate_class_id']['id'],
                'name'  => $customer_nature['rate_class_id']['name']
            ];
        }

        return $result;
    }

    public static function canclone($orm, $oids) {
        // prevent cloning bookings
        return ['status' => ['not_allowed' => 'Booking cannot be cloned.']];
    }

    /**
     * Check whether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang) {

        $bookings = $om->read(self::getType(), $oids, ['state', 'status', 'customer_id', 'customer_identity_id', 'center_id', 'booking_lines_ids'], $lang);

        // fields that can always be updated
        $allowed_fields = ['status', 'description', 'is_invoiced', 'payment_status', 'activity_weeks_descriptions'];

        if(isset($values['status'])) {
            foreach($bookings as $booking) {
                if($values['status'] === $booking['status']) {
                    continue;
                }

                $lines = $om->read(BookingLine::getType(), $booking['booking_lines_ids'], ['product_id']);
                foreach($lines as $line) {
                    if(!$line['product_id']) {
                        return ['status' => ['undefined_product_id' => "Status cannot be updated if a booking line has no product."]];
                    }
                }
            }
        }

        if(isset($values['center_id'])) {
            $has_booking_lines = false;
            foreach($bookings as $bid => $booking) {
                // if there are services and the center is already defined (otherwise this is the first assignation and some auto-services might just have been created)
                if($booking['center_id'] && count($booking['booking_lines_ids'])) {
                    $has_booking_lines = true;
                    break;
                }
            }
            if($has_booking_lines) {
                return ['center_id' => ['non_editable' => 'Center cannot be changed once services are attached to the booking.']];
            }
        }

        // identity cannot be changed once the contract has been emitted
        if(isset($values['customer_identity_id'])) {
            foreach($bookings as $bid => $booking) {
                if(!in_array($booking['status'], ['quote', 'option'])) {
                    return ['customer_identity_id' => ['non_editable' => 'Customer cannot be changed once a contract has been emitted.']];
                }
            }
        }

        // if customer nature is missing, make sure the selected customer has one already
        if(isset($values['customer_id']) && !isset($values['customer_nature_id'])) {
            // if we received a customer id, its customer_nature_id must be set
            $customers = $om->read('sale\customer\Customer', $values['customer_id'], [ 'customer_nature_id']);
            if($customers) {
                $customer = reset($customers);
                if(is_null($customer['customer_nature_id'])) {
                    return ['customer_nature_id' => ['missing_mandatory' => 'Unknown nature for this customer.']];
                }
            }
        }

        if(isset($values['booking_lines_ids'])) {
            // trying to add or remove booking lines
            // (lines cannot be assigned to more than one booking)
            $booking = reset($bookings);
            if(!in_array($booking['status'], ['quote'])) {
                $lines = $om->read('sale\booking\BookingLine', $values['booking_lines_ids'], [ 'booking_line_group_id.is_extra']);
                foreach($lines as $line) {
                    if(!$line['booking_line_group_id.is_extra']) {
                        return ['status' => ['non_editable' => 'Non-extra services cannot be changed for non-quote bookings.']];
                    }
                }
            }
        }

        if(isset($values['booking_lines_groups_ids'])) {
            // trying to add or remove booking line groups
            // groups cannot be assigned to more than one booking
            $booking = reset($bookings);
            if(!in_array($booking['status'], ['quote'])) {
                $booking_lines_groups_ids = array_map( function($id) { return abs($id); }, $values['booking_lines_groups_ids']);
                $groups = $om->read('sale\booking\BookingLineGroup', $booking_lines_groups_ids, [ 'is_extra']);
                foreach($groups as $group) {
                    if(!$group['is_extra']) {
                        return ['status' => ['non_editable' => 'Non-extra service groups cannot be changed for non-quote bookings.']];
                    }
                }
            }
        }

        // check for accepted changes based on status
        foreach($bookings as $id => $booking) {
            if($booking['state'] === 'draft') {
                continue;
            }
            if(in_array($booking['status'], ['proforma', 'invoiced', 'debit_balance', 'credit_balance', 'balanced', 'cancelled'])) {
                if(count(array_diff(array_keys($values), $allowed_fields))) {
                    return ['status' => ['non_editable' => 'Invoiced bookings edition is limited.']];
                }
            }
            if( !$booking['customer_id'] && !$booking['customer_identity_id'] && !isset($values['customer_id']) && !isset($values['customer_identity_id']) ) {
                return ['customer_id' => ['missing_mandatory' => 'Customer is mandatory.']];
            }
        }

        return [];
        // ignore parent method
        return parent::canupdate($om, $oids, $values, $lang);
    }

    public static function candelete($om, $oids, $lang='en') {
        $res = $om->read(self::getType(), $oids, [ 'status', 'is_from_channelmanager' ]);

        if($res > 0) {
            foreach($res as $oids => $odata) {
                if($odata['status'] != 'quote') {
                    return ['status' => ['non_editable' => 'Non-quote bookings cannot be deleted manually.']];
                }
                if($odata['is_from_channelmanager']) {
                    return ['is_from_channelmanager' => ['non_removable' => 'Bookings from channel manager cannot be removed.']];
                }
            }
        }
        return parent::candelete($om, $oids, $lang);
    }

    /**
     * Hook invoked after object deletion for performing object-specific additional operations.
     *
     * @param  \equal\orm\ObjectManager     $orm       ObjectManager instance.
     * @param  array                        $ids       List of objects identifiers.
     * @return void
     */
    public static function onafterdelete($orm, $ids) {
        // #memo - we do this to handle case where auto products (by groups) are re-created during the delete cycle
        $groups_ids = $orm->search(BookingLineGroup::getType(), ['booking_id', 'in', $ids]);
        $orm->delete(BookingLineGroup::getType(), $groups_ids, true);
    }

    /**
     * Resets and recreates booking consumptions from booking lines and rental units assignments.
     * This method is called upon setting booking status to 'option' or 'confirmed' (#see `option.php`)
     * #memo - consumptions are used in the planning.
     *
     */
    public static function createConsumptions($om, $ids, $values, $lang) {
        $bookings = $om->read(self::getType(), $ids, ['consumptions_ids'], $lang);

        // get in-memory list of consumptions for all lines
        $consumptions = $om->call(self::getType(), 'getResultingConsumptions', $ids, [], $lang);

        // recreate consumptions objects
        foreach($consumptions as $consumption) {
            $om->create(Consumption::getType(), $consumption, $lang);
        }

        // if everything went well, remove old consumptions
        foreach($bookings as $id => $booking) {
            $om->delete(Consumption::getType(), $booking['consumptions_ids'], true);
        }

    }


    /**
     * Process BookingLines to create an in-memory list of consumptions objects.
     *
     */
    public static function getResultingConsumptions($om, $oids, $values, $lang) {
        // resulting consumptions objects
        $consumptions = [];

        $bookings = $om->read(self::getType(), $oids, ['booking_lines_groups_ids'], $lang);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $consumptions = array_merge($consumptions, \sale\booking\BookingLineGroup::getResultingConsumptions($om, $booking['booking_lines_groups_ids'], [], $lang));
            }
        }

        return $consumptions;
    }

    public static function generateName() {
        return null;
    }

    public static function generateDisplayName() {
        return null;
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets `total` and `price` computed fields.
     */
    public static function refreshPrice($om, $id) {
        $om->update(self::getType(), $id, ['total' => null, 'price' => null]);
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets `date_from`, `date_to`, `time_from` and `time_to` computed fields.
     */
    public static function refreshDate($om, $id) {
        $bookings = $om->read(self::getType(), $id, ['booking_lines_groups_ids.group_type']);

        if($bookings <= 0) {
            return;
        }

        $booking = reset($bookings);

        $has_sojourn = false;
        foreach($booking['booking_lines_groups_ids.group_type'] as $gid => $group) {
            if($group['group_type'] !== 'simple') {
                $has_sojourn = true;
                break;
            }
        }

        // #memo - do not refresh Booking if there are no sojourns (so that last set dates remain)
        if(!$has_sojourn) {
            return;
        }

        $om->update(self::getType(), $id, ['date_from' => null, 'date_to' => null, 'time_from' => null, 'time_to' => null]);
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets `time_from` and `time_to` computed fields.
     */
    public static function refreshTime($om, $id) {
        $om->update(self::getType(), $id, ['time_from' => null, 'time_to' => null]);
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets `nb_pers` field.
     */
    public static function refreshNbPers($om, $id) {
        $om->update(self::getType(), $id, ['nb_pers' => null]);
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Recomputes `type_id`.
     */
    public static function refreshBookingType($om, $id) {
        $attributions_ids = $om->search(BookingTypeAttribution::getType(), []);
        if(!empty($attributions_ids)) {
            self::refreshBookingTypeWithAttributions($om, $id);
            return;
        }

        // If no attributions configured then use old algorithm to attribute a booking type
        // #todo - delete below when the attributions rules are defined for Kaleo

        $bookings = $om->read(self::getType(), $id, [
            'id',
            'booking_lines_groups_ids',
            'booking_lines_ids'
        ]);

        if($bookings <= 0) {
            return;
        }

        $booking = reset($bookings);

        // within checks, compare with currently assigned type
        $type_id = 1;


        // pass-1 - check at group level
        $groups = $om->read(BookingLineGroup::getType(), $booking['booking_lines_groups_ids'], [
            'id',
            'booking_id',
            'is_sojourn',
            'nb_pers',
            'pack_id.product_model_id.booking_type_id',
            'rate_class_id.name'
        ]);

        foreach($groups as $gid => $group) {
            // if model of chosen product has a specific (non-generic) booking type, update the booking of the group accordingly
            if(isset($group['pack_id.product_model_id.booking_type_id']) && $group['pack_id.product_model_id.booking_type_id'] != 1) {
                $type_id = $group['pack_id.product_model_id.booking_type_id'];
            }
            // handle rate classes targeting school trips
            elseif($group['rate_class_id.name'] == 'T5' || $group['rate_class_id.name'] == 'T7') {
                $type_id = 4;
            }
            // handle individual and large groups
            elseif($group['is_sojourn'] && $group['rate_class_id.name'] == 'T4') {
                if($group['nb_pers'] >= 10) {
                    // booking type 'TPG' (tout public groupe) is for booking with 10 pers. or more
                    $type_id = 6;
                }
                else {
                    // booking type 'TP' (tout public) is for booking with less than 10 pers.
                    $type_id = 1;
                }
            }

            if($type_id != 1) {
                break;
            }

        }

        // pass-2 - check at lines level (if no new type found so far)
        if($type_id == 1) {
            $lines = $om->read(BookingLine::getType(), $booking['booking_lines_ids'], [
                'id',
                'product_id.product_model_id.booking_type_id'
            ]);

            foreach($lines as $lid => $line) {
                // if model of chosen product has a non-generic booking type, update the booking of the line accordingly
                if(isset($line['product_id.product_model_id.booking_type_id'])) {
                    $type_id = $line['product_id.product_model_id.booking_type_id'];
                }

                if($type_id != 1) {
                    break;
                }
            }
        }

        $om->update(self::getType(), $id, ['type_id' => $type_id]);
    }

    public static function refreshBookingTypeWithAttributions($om, $id) {
        $bookings = $om->read(self::getType(), $id, [
            'id',
            'is_from_channelmanager',
            'booking_lines_groups_ids',
            'booking_lines_ids'
        ]);

        if($bookings <= 0) {
            return null;
        }

        $booking = reset($bookings);

        $attributions_ids = $om->search(BookingTypeAttribution::getType(), []);
        if(empty($attributions_ids)) {
            return null;
        }

        $attributions = $om->read(BookingTypeAttribution::getType(), $attributions_ids, [
            'name',
            'booking_type_id',
            'sojourn_type_id',
            'booking_type_conditions_ids',
            'rate_classes_ids'
        ]);

        $groups = $om->read(BookingLineGroup::getType(), $booking['booking_lines_groups_ids'], [
            'id',
            'is_sojourn',
            'nb_pers',
            'nb_adults',
            'nb_children',
            'rate_class_id',
            'sojourn_type_id',
            'pack_id.product_model_id.booking_type_id'
        ]);

        // First, use the pack product model configuration
        foreach($groups as $group) {
            if(isset($group['pack_id.product_model_id.booking_type_id']) && $group['pack_id.product_model_id.booking_type_id'] != 1) {
                $om->update(self::getType(), $id, [
                    'type_id' => $group['pack_id.product_model_id.booking_type_id']
                ]);
                return;
            }
        }

        // Second, use the attributions' conditions
        $default_booking_type_id = 1;
        $matched_attribution = null;
        foreach($attributions as $attribution) {
            $conditions = $om->read(BookingTypeCondition::getType(), $attribution['booking_type_conditions_ids'], [
                'operand',
                'operator',
                'value'
            ]);

            if(!$attribution['sojourn_type_id'] && empty($attribution['rate_classes_ids']) && empty($attribution['booking_type_conditions_ids'])) {
                $default_booking_type_id = $attribution['booking_type_id'];
                continue;
            }

            $on_booking = [
                'is_from_channelmanager'
            ];
            $on_sojourn_group = [
                'is_sojourn',
                'nb_pers',
                'nb_children',
                'nb_adults',
            ];

            // Check conditions on booking
            foreach($conditions as $condition) {
                if(!in_array($condition['operand'], $on_booking)) {
                    continue;
                }

                $operator = $condition['operator'];
                if($operator === '=') {
                    $operator = '==';
                }

                $value = $condition['value'];
                if(!is_numeric($condition['value'])) {
                    $value = "'$value'";
                }

                $operand = $booking[$condition['operand']];
                if(!is_numeric($operand)) {
                    $operand = "'$operand'";
                }

                if(!eval("return $operand $operator $value;")) {
                    continue 2;
                }
            }

            // Check conditions, rate class and sojourn type on groups
            foreach($groups as $group) {
                foreach($conditions as $condition) {
                    if(!in_array($condition['operand'], $on_sojourn_group)) {
                        continue;
                    }

                    $operator = $condition['operator'];
                    if($operator === '=') {
                        $operator = '==';
                    }

                    $value = $condition['value'];
                    if(!is_numeric($condition['value'])) {
                        $value = "'$value'";
                    }

                    if(!empty($attribution['rate_classes_ids']) && !in_array($group['rate_class_id'], $attribution['rate_classes_ids'])) {
                        continue 2;
                    }
                    if($attribution['sojourn_type_id'] && $group['sojourn_type_id'] !== $attribution['sojourn_type_id']) {
                        continue 2;
                    }

                    $operand = $group[$condition['operand']];
                    if(!is_numeric($operand)) {
                        $operand = "'$operand'";
                    }

                    if(!eval("return $operand $operator $value;")) {
                        continue 2;
                    }
                }

                if(!empty($attribution['rate_classes_ids'])) {
                    if(!in_array($group['rate_class_id'], $attribution['rate_classes_ids'])) {
                        continue;
                    }
                }
                elseif($attribution['sojourn_type_id']) {
                    if($group['sojourn_type_id'] !== $attribution['sojourn_type_id']) {
                        continue;
                    }
                }

                $matched_attribution = $attribution;
                break 2;
            }
        }

        $booking_type_id = $default_booking_type_id;
        if(isset($matched_attribution)) {
            // Attribution matched, so use its booking type
            $booking_type_id = $matched_attribution['booking_type_id'];
        }
        elseif($booking_type_id === 1) {
            // As last resort, check the booking lines
            $lines = $om->read(BookingLine::getType(), $booking['booking_lines_ids'], [
                'product_id.product_model_id.booking_type_id'
            ]);

            foreach($lines as $line) {
                if(isset($line['product_id.product_model_id.booking_type_id']) && $line['product_id.product_model_id.booking_type_id'] !== 1) {
                    $booking_type_id = $line['product_id.product_model_id.booking_type_id'];
                    break;
                }
            }
        }

        $om->update(self::getType(), $id, ['type_id' => $booking_type_id]);
    }


    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Updates is_price_tbc according to price lists targeted by packs and lines (if it includes non-published price list, then booking is marked as TBC).
     */
    public static function refreshIsTbc($om, $id) {
        $bookings = $om->read(self::getType(), $id, [
            'id',
            'booking_lines_groups_ids',
            'booking_lines_ids'
        ]);

        if($bookings <= 0) {
            return;
        }

        $booking = reset($bookings);

        $is_tbc = false;

        // pass-1 - check at groups level
        $groups = $om->read(BookingLineGroup::getType(), $booking['booking_lines_groups_ids'], [
            'id',
            'has_pack',
            'price_id.price_list_id.status',
        ]);

        foreach($groups as $gid => $group) {
            if(!$group['has_pack'] || !isset($group['price_id.price_list_id.status'])) {
                continue;
            }
            if($group['price_id.price_list_id.status'] != 'published') {
                $is_tbc = true;
                break;
            }
        }

        // pass-2 - check at lines level
        if(!$is_tbc) {
            $lines = $om->read(BookingLine::getType(), $booking['booking_lines_ids'], [
                'id',
                'price_id.price_list_id.status'
            ]);

            foreach($lines as $lid => $line) {
                if(isset($line['price_id.price_list_id.status']) && $line['price_id.price_list_id.status'] != 'published') {
                    $is_tbc = true;
                    break;
                }
            }
        }

        // mark booking according to found TBC flag
        $om->update(self::getType(), $id, ['is_price_tbc' => $is_tbc]);
    }

    private static function computeCountBookingYearFiscal($booking_id, $customer_id) {
        $date_from = strtotime(Setting::get_value('finance', 'accounting', 'fiscal_year.date_from'));
        return Booking::search([
                ['id', '<>', $booking_id],
                ['customer_id', '=', $customer_id],
                ['date_from', '>=',  $date_from],
                ['is_cancelled', '=', false],
                ['status', 'not in', ['quote', 'option']]
            ])
            ->count();
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Creates auto sale groups (scope 'booking'), with booking lines, based on AutosaleList associated to the Center.
     */
    public static function refreshAutosaleProducts($om, $id) {
        /*
            remove groups related to autosales that already exist
        */

        $bookings = $om->read(self::getType(), $id, [
            'id',
            'customer_id.rate_class_id',
            'customer_id',
            'booking_lines_groups_ids',
            'nb_pers',
            'date_from',
            'date_to',
            'center_id.autosale_list_category_id',
            'center_id.sojourn_type_id',
            'customer_rate_class_id'
        ]);

        if($bookings <= 0) {
            return;
        }

        $booking = reset($bookings);

        $groups_ids_to_delete = [];
        $booking_lines_groups = $om->read('sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['is_autosale']);
        if($booking_lines_groups > 0) {
            foreach($booking_lines_groups as $gid => $group) {
                if($group['is_autosale']) {
                    $groups_ids_to_delete[] = -$gid;
                }
            }
            $om->update(self::getType(), $id, ['booking_lines_groups_ids' => $groups_ids_to_delete]);
        }

        /*
            Find the first Autosale List that matches the booking dates
        */

        $autosale_lists_ids = $om->search('sale\autosale\AutosaleList', [
            ['autosale_list_category_id', '=', $booking['center_id.autosale_list_category_id']],
            ['date_from', '<=', $booking['date_from']],
            ['date_to', '>=', $booking['date_from']]
        ]);

        $autosale_lists = $om->read('sale\autosale\AutosaleList', $autosale_lists_ids, ['id', 'autosale_lines_ids']);
        $autosale_list_id = 0;
        $autosale_list = null;
        if($autosale_lists > 0 && count($autosale_lists)) {
            // use first match (there should always be only one or zero)
            $autosale_list = array_pop($autosale_lists);
            $autosale_list_id = $autosale_list['id'];
            trigger_error("ORM:: match with autosale List {$autosale_list_id}", QN_REPORT_DEBUG);
        }
        else {
            trigger_error("ORM:: no autosale List found", QN_REPORT_DEBUG);
        }
        /*
            Search for matching Autosale products within the found List
        */
        if($autosale_list_id) {
            $operands = [];

            // for now, we only support member cards for customer that haven't booked a service for more thant 12 months
            // $operands['count_booking_12'] = $booking['customer_id.count_booking_12'];

            $bookings_ids = $om->search(self::getType(), [
                [
                    ['is_cancelled', '=', false],
                    ['status', 'not in', ['quote', 'option']],
                    ['id', '<>', $id],
                    ['customer_id', '=', $booking['customer_id']],
                    ['date_from', '>=', $booking['date_from']],
                    ['date_from', '<=', strtotime('+12 months', $booking['date_from'])]
                ],
                [
                    ['is_cancelled', '=', false],
                    ['status', 'not in', ['quote', 'option']],
                    ['id', '<>', $id],
                    ['customer_id', '=', $booking['customer_id']],
                    ['date_from', '<=', $booking['date_from']],
                    ['date_from', '>=', strtotime('-12 months', $booking['date_from'])]
                ]
            ]);

            $operands['count_booking_12'] = count($bookings_ids);

            $operands['count_booking_fiscal_year'] = self::computeCountBookingYearFiscal($id, $booking['customer_id']);
            $operands['nb_pers'] = $booking['nb_pers'];

            $autosales = $om->read('sale\autosale\AutosaleLine', $autosale_list['autosale_lines_ids'], [
                'product_id.id',
                'product_id.name',
                'name',
                'has_own_qty',
                'qty',
                'scope',
                'rate_class_id',
                'conditions_ids'
            ]);

            // filter discounts based on related conditions
            $products_to_apply = [];

            // filter discounts to be applied on whole booking
            foreach($autosales as $autosale_id => $autosale) {
                if($autosale['scope'] != 'booking') {
                    continue;
                }
                if(isset($autosale['rate_class_id']) && $booking['customer_rate_class_id'] !== $autosale['rate_class_id']) {
                    continue;
                }
                $conditions = $om->read('sale\autosale\Condition', $autosale['conditions_ids'], ['operand', 'operator', 'value']);
                $valid = true;
                foreach($conditions as $c_id => $condition) {
                    if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                        // unknown operator
                        continue;
                    }
                    $operator = $condition['operator'];
                    if($operator == '=') {
                        $operator = '==';
                    }
                    if(!isset($operands[$condition['operand']])) {
                        $valid = false;
                        break;
                    }
                    $operand = $operands[$condition['operand']];
                    $value = $condition['value'];
                    if(!is_numeric($operand)) {
                        $operand = "'$operand'";
                    }
                    if(!is_numeric($value)) {
                        $value = "'$value'";
                    }
                    trigger_error("Booking - testing {$autosale['name']} : {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                    $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                    if(!$valid) {
                        break;
                    }
                }
                if($valid) {
                    trigger_error("ORM:: all conditions fullfilled", QN_REPORT_DEBUG);
                    $products_to_apply[$autosale_id] = [
                        'id'            => $autosale['product_id.id'],
                        'name'          => $autosale['product_id.name'],
                        'has_own_qty'   => $autosale['has_own_qty'],
                        'qty'           => $autosale['qty']
                    ];
                }
            }

            // apply all applicable products
            $count = count($products_to_apply);

            if($count) {
                // create a new BookingLine Group dedicated to autosale products
                $group = [
                    'name'          => 'Suppléments obligatoires',
                    'booking_id'    => $id,
                    'rate_class_id' => ($booking['customer_id.rate_class_id']) ? $booking['customer_id.rate_class_id'] : 4,
                    'date_from'     => $booking['date_from'],
                    'date_to'       => $booking['date_to'],
                    'is_autosale'   => true
                ];
                if(isset($booking['center_id.sojourn_type_id'])) {
                    $group['sojourn_type_id'] = $booking['center_id.sojourn_type_id'];
                }
                if($count == 1) {
                    // in case of a single line, overwrite group name
                    foreach($products_to_apply as $autosale_id => $product) {
                        $group['name'] = $product['name'];
                    }
                }
                $gid = $om->create('sale\booking\BookingLineGroup', $group);

                // add all applicable products to the group
                $order = 1;
                foreach($products_to_apply as $autosale_id => $product) {
                    // create a line relating to the product
                    $line = [
                        'order'                     => $order++,
                        'booking_id'                => $id,
                        'booking_line_group_id'     => $gid,
                        'product_id'                => $product['id'],
                        'has_own_qty'               => $product['has_own_qty'],
                        'qty'                       => $product['qty']
                    ];
                    $lid = $om->create('sale\booking\BookingLine', $line);
                    if($lid > 0) {
                        // resolve price
                        BookingLine::refreshPriceId($om, $lid);
                    }
                }
            }
        }
        else {
            $date = date('Y-m-d', $booking['date_from']);
            trigger_error("ORM::no matching autosale list found for date {$date}", QN_REPORT_DEBUG);
        }

    }

}
