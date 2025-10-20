<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use core\setting\Setting;
use equal\orm\Model;
use sale\camp\catalog\Product;
use sale\pay\Funding;
use sale\pay\Payment;

class Enrollment extends Model {

    public static function getDescription(): string {
        return "The enrollment of a child to a camp group.";
    }

    public static function getLink(): string {
        return "/camp/#/enrollment/object.id";
    }

    public static function getColumns(): array {
        return [

            'date_created' => [
                'type'              => 'computed',
                'result_type'       => 'datetime',
                'description'       => "Creation date, to save the creation date when enrollment imported from external source.",
                'help'              => "Needed because cannot 'created' field cannot be updated to specific value.",
                'store'             => true,
                'function'          => 'calcDateCreated'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['child_id' => 'name']
            ],

            'child_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Child',
                'description'       => "The child that is enrolled.",
                'required'          => true,
                'dependents'        => ['name', 'main_guardian_id', 'child_age', 'is_foster', 'institution_id']
            ],

            'child_firstname' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Firstname of the child.",
                'store'             => false,
                'relation'          => ['child_id' => 'firstname']
            ],

            'child_lastname' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Lastname of the child.",
                'store'             => false,
                'relation'          => ['child_id' => 'lastname']
            ],

            'child_gender' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'F',
                    'M'
                ],
                'store'             => false,
                'relation'          => ['child_id' => 'gender']
            ],

            'child_birthdate' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => "Birthdate of the child.",
                'store'             => false,
                'relation'          => ['child_id' => 'birthdate']
            ],

            'has_camp_birthday' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Does the child has her/his birthday during the camp.",
                'store'             => false,
                'function'          => 'calcHasCampBirthday'
            ],

            'child_remarks' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Remarks about the child's health at the time of the enrollment."
            ],

            'child_age' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The age of the child during the camp.",
                'store'             => true,
                'function'          => 'calcChildAge'
            ],

            'main_guardian_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\camp\Guardian',
                'store'             => true,
                'instant'           => true,
                'relation'          => ['child_id' => ['main_guardian_id']]
            ],

            'main_guardian_mobile' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'phone',
                'description'       => "Mobile phone number of the main guardian of the child.",
                'store'             => false,
                'relation'          => ['main_guardian_id' => ['mobile']]
            ],

            'main_guardian_phone' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'phone',
                'description'       => "Phone number of the main guardian of the child.",
                'store'             => false,
                'relation'          => ['main_guardian_id' => ['phone']]
            ],

            'is_foster' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Is the child living in a forster family/home.",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['child_id' => ['is_foster']]
            ],

            'institution_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\camp\Institution',
                'store'             => true,
                'instant'           => true,
                'relation'          => ['child_id' => ['institution_id']]
            ],

            'family_quotient' => [
                'type'              => 'integer',
                'usage'             => 'number/integer{0,5000}',
                'description'       => "Indicator for measuring the child's family monthly resources.",
                'help'              => "Used to select the price to pay for a CLSH camp.",
                'default'           => 0,
                'onupdate'          => 'onupdateFamilyQuotient',
                'visible'           => ['is_clsh', '=', true]
            ],

            'camp_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "The camp the child is enrolled to.",
                'required'          => true,
                'onupdate'          => 'onupdateCampId',
                'dependents'        => ['date_from', 'date_to', 'is_clsh', 'clsh_type', 'child_age']
            ],

            'camp_age_range' => [
                'type'              => 'computed',
                'selection'         => [
                    '6-to-9',
                    '10-to-12',
                    '13-to-16'
                ],
                'description'       => "Age range of the camp.",
                'store'             => false,
                'relation'          => ['camp_id' => 'age_range']
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
                'relation'          => ['camp_id' => 'center_id']
            ],

            'center_office_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => "Office the enrollment relates to (for center management).",
                'store'             => true,
                'relation'          => ['center_id' => 'center_office_id']
            ],

            'product_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product that will be added to the enrollment lines if the child enroll for the full camp.",
                'domain'            => ['is_camp', '=', true],
                'store'             => true,
                'relation'          => ['camp_id' => 'product_id']
            ],

            'weekend_extra' => [
                'type'              => 'string',
                'selection'         => [
                    'none',             // No weekend extra
                    'full',             // The child stays on the weekend to attend another camp the following week
                    'saturday-morning'  // The parents come on Saturday morning to fetch their child
                ],
                'description'       => "Does the child stays the weekend after the camp.",
                'help'              => "If child stays full weekend it usually means that he is enrolled to another camp the following week. If child stays saturday morning it means that its guardian cannot pick him/her up on Friday.",
                'default'           => 'none',
                'onupdate'          => 'onupdateWeekendExtra',
                'visible'           => ['is_clsh', '=', false]
            ],

            'following_camp' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the camp the child is enrolled to the following week if he/she stays the full weekend.",
                'store'             => false,
                'function'          => 'calcFollowingCamp'
            ],

            'is_clsh' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Is the enrollment for a CLSH camp?",
                'store'             => true,
                'relation'          => ['camp_id' => 'is_clsh'],
            ],

            'clsh_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    '5-days',
                    '4-days'
                ],
                'description'       => "Is it a camp of 5 or 4 days duration.",
                'store'             => true,
                'relation'          => ['camp_id' => 'clsh_type'],
                'visible'           => ['is_clsh', '=', true]
            ],

            'presence_day_1' => [
                'type'              => 'boolean',
                'description'       => "Will the child be present on the first day of the day of the camp.",
                'default'           => false,
                'visible'           => ['is_clsh', '=', true],
                'onupdate'          => 'onupdatePresentDay'
            ],

            'presence_day_2' => [
                'type'              => 'boolean',
                'description'       => "Will the child be present on the second day of the day of the camp.",
                'default'           => false,
                'visible'           => ['is_clsh', '=', true],
                'onupdate'          => 'onupdatePresentDay'
            ],

            'presence_day_3' => [
                'type'              => 'boolean',
                'description'       => "Will the child be present on the third day of the day of the camp.",
                'default'           => false,
                'visible'           => ['is_clsh', '=', true],
                'onupdate'          => 'onupdatePresentDay'
            ],

            'presence_day_4' => [
                'type'              => 'boolean',
                'description'       => "Will the child be present on the fourth day of the day of the camp.",
                'default'           => false,
                'visible'           => ['is_clsh', '=', true],
                'onupdate'          => 'onupdatePresentDay'
            ],

            'presence_day_5' => [
                'type'              => 'boolean',
                'description'       => "Will the child be present on the fifth day of the day of the camp.",
                'default'           => false,
                'visible'           => [
                    ['is_clsh', '=', true],
                    ['clsh_type', '=', '5-days']
                ],
                'onupdate'          => 'onupdatePresentDay'
            ],

            'daycare_day_1' => [
                'type'              => 'string',
                'selection'         => [
                    'none',
                    'am',
                    'pm',
                    'full'
                ],
                'default'           => 'none',
                'visible'           => [
                    ['is_clsh', '=', true],
                    ['presence_day_1', '=', true]
                ]
            ],

            'daycare_day_2' => [
                'type'              => 'string',
                'selection'         => [
                    'none',
                    'am',
                    'pm',
                    'full'
                ],
                'default'           => 'none',
                'visible'           => [
                    ['is_clsh', '=', true],
                    ['presence_day_2', '=', true]
                ]
            ],

            'daycare_day_3' => [
                'type'              => 'string',
                'selection'         => [
                    'none',
                    'am',
                    'pm',
                    'full'
                ],
                'default'           => 'none',
                'visible'           => [
                    ['is_clsh', '=', true],
                    ['presence_day_3', '=', true]
                ]
            ],

            'daycare_day_4' => [
                'type'              => 'string',
                'selection'         => [
                    'none',
                    'am',
                    'pm',
                    'full'
                ],
                'default'           => 'none',
                'visible'           => [
                    ['is_clsh', '=', true],
                    ['presence_day_4', '=', true]
                ]
            ],

            'daycare_day_5' => [
                'type'              => 'string',
                'selection'         => [
                    'none',
                    'am',
                    'pm',
                    'full'
                ],
                'default'           => 'none',
                'visible'           => [
                    ['is_clsh', '=', true],
                    ['presence_day_5', '=', true]
                ]
            ],

            'camp_class' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'other',
                    'member',
                    'close-member'
                ],
                'description'       => "The camp class of the child for this enrollment, to know which price to apply.",
                'store'             => true,
                'function'          => 'calcCampClass',
                'onupdate'          => 'onupdateCampClass'
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

            'preregistration_sent' => [
                'type'              => 'boolean',
                'description'       => "The preregistration mail asking for documents was sent.",
                'default'           => false,
                'visible'           => ['status', 'in', ['confirmed', 'validated']]
            ],

            'confirmation_sent' => [
                'type'              => 'boolean',
                'description'       => "The enrollment confirmation mail was sent.",
                'default'           => false,
                'visible'           => ['status', '=', 'validated']
            ],

            'cancellation_date' => [
                'type'              => 'date',
                'description'       => "Date of cancellation."
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

            'is_ase' => [
                'type'              => 'boolean',
                'description'       => "Is \"aide sociale à l'enfance\".",
                'default'           => false
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Total price of the enrollment (VTA excluded).",
                'store'             => true,
                'function'          => 'calcTotal'
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total price of the enrollment (VTA included).",
                'store'             => true,
                'function'          => 'calcPrice'
            ],

            'all_documents_received' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Have all required documents been received?",
                'store'             => true,
                'function'          => 'calcAllDocumentsReceived'
            ],

            'is_locked' => [
                'type'              => 'boolean',
                'description'       => "Can the enrollment be modified or not?",
                'default'           => false
            ],

            'works_council_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\WorksCouncil',
                'description'       => "The works council that will enhance the camp class by one level.",
                'dependents'        => ['camp_class'],
                'visible'           => ['is_clsh', '=', false],
                'onupdate'          => 'onupdateWorksCouncilId'
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

            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Structured reference for identifying payments relating to the enrollment.",
                'store'             => true,
                'function'          => 'calcPaymentReference'
            ],

            'paid_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that has been received so far.",
                'function'          => 'calcPaidAmount',
                'store'             => true
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

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Funding',
                'foreign_field'     => 'enrollment_id',
                'description'       => 'Fundings that relate to the enrollment.',
                'ondetach'          => 'delete'
            ],

            'enrollment_lines_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'enrollment_id',
                'foreign_object'    => 'sale\camp\EnrollmentLine',
                'description'       => "The lines who list the products of the child's enrollment.",
                'ondetach'          => 'delete'
            ],

            'price_adapters_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'enrollment_id',
                'foreign_object'    => 'sale\camp\price\PriceAdapter',
                'description'       => "The adapters of price for reductions.",
                'ondetach'          => 'delete'
            ],

            'enrollment_mails_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\EnrollmentMail',
                'foreign_field'     => 'enrollments_ids',
                'rel_table'         => 'sale_camp_rel_enrollment_mail',
                'rel_foreign_key'   => 'enrollment_mail_id',
                'rel_local_key'     => 'enrollment_id',
                'description'       => "The mails that are linked to this enrollment."
            ],

            'enrollment_documents_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\EnrollmentDocument',
                'foreign_field'     => 'enrollment_id',
                'description'       => "The documents that have been received.",
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

    public static function onchange($event, $values): array {
        $result = [];
        $is_clsh = null;
        if(isset($event['camp_id'])) {
            $camp = Camp::id($event['camp_id'])
                ->read(['is_clsh'])
                ->first();

            $result['is_clsh'] = $camp['is_clsh'];
            $is_clsh = $camp['is_clsh'];
        }

        if(!is_null($is_clsh)) {
            if($is_clsh) {
                if(isset($event['child_id']) || (isset($values['child_id']) && !isset($values['camp_class']))) {
                    $child = Child::id($event['child_id'] ?? $values['child_id'])
                        ->read(['main_guardian_id' => ['is_ccvg']])
                        ->first();

                    if($child['main_guardian_id']['is_ccvg']) {
                        $result['camp_class'] = 'close-member';
                    }
                    else {
                        $result['camp_class'] = 'other';
                    }
                }
            }
            else {
                if(isset($event['child_id'])|| (isset($values['child_id']) && !isset($values['camp_class']))) {
                    $child = Child::id($event['child_id'] ?? $values['child_id'])
                        ->read(['camp_class'])
                        ->first();

                    $result['camp_class'] = $child['camp_class'];
                }
                elseif(isset($event['works_council_id']) && isset($values['child_id'])) {
                    $child = Child::id($values['child_id'])
                        ->read(['camp_class'])
                        ->first();

                    $camp_class = $child['camp_class'];
                    if($camp_class === 'other') {
                        $camp_class = 'member';
                    }
                    elseif($camp_class === 'member') {
                        $camp_class = 'close-member';
                    }

                    $result['camp_class'] = $camp_class;
                }
            }
        }

        if(
            (isset($event['child_id']) && isset($values['camp_id']))
            || (isset($event['camp_id']) && isset($values['child_id']))
        ) {
            $child_id = $event['child_id'] ?? $values['child_id'];
            $child = Child::id($child_id)
                ->read(['birthdate'])
                ->first();

            $camp_id = $event['camp_id'] ?? $values['camp_id'];
            $camp = Camp::id($camp_id)
                ->read(['date_from'])
                ->first();

            $birthdate = (new \DateTime())->setTimestamp($child['birthdate']);
            $date_from = (new \DateTime())->setTimestamp($camp['date_from']);
            $result['child_age'] = $birthdate->diff($date_from)->y;
        }

        return $result;
    }

    public static function calcDateCreated($self) {
        $result = [];
        $self->read(['created']);
        foreach($self as $id => $enrollment) {
            $result[$id] = $enrollment['created'];
        }

        return $result;
    }

    public static function calcCampClass($self): array {
        $result = [];
        $self->read(['is_clsh', 'works_council_id', 'child_id' => ['camp_class', 'main_guardian_id' => ['is_ccvg']]]);
        foreach($self as $id => $enrollment) {
            $camp_class = $enrollment['child_id']['camp_class'];
            if($enrollment['is_clsh']) {
                if($enrollment['main_guardian_id']['is_ccvg']) {
                    $camp_class = 'close-member';
                }
                else {
                    $camp_class = 'other';
                }
            }
            else {
                if(!is_null($enrollment['works_council_id'])) {
                    if($camp_class === 'other') {
                        $camp_class = 'member';
                    }
                    elseif($camp_class === 'member') {
                        $camp_class = 'close-member';
                    }
                }
            }
            $result[$id] = $camp_class;
        }

        return $result;
    }

    public static function calcChildAge($self): array {
        $result = [];
        $self->read(['date_from', 'child_id'  => ['birthdate']]);
        foreach($self as $id => $enrollment) {
            if(!isset($enrollment['date_from'], $enrollment['child_id']['birthdate'])) {
                continue;
            }

            $date_from = (new \DateTime())->setTimestamp($enrollment['date_from']);
            $birthdate = (new \DateTime())->setTimestamp($enrollment['child_id']['birthdate']);
            $result[$id] = $birthdate->diff($date_from)->y;
        }

        return $result;
    }

    public static function calcFollowingCamp($self): array {
        $result = [];
        $self->read(['weekend_extra', 'child_id', 'camp_id' => ['date_from']]);
        foreach($self as $id => $enrollment) {
            if($enrollment['weekend_extra'] === 'full') {
                $following_enrollment = Enrollment::search([
                    ['child_id', '=', $enrollment['child_id']],
                    ['is_clsh', '=', false],
                    ['date_from', '=', $enrollment['camp_id']['date_from'] + (84600 * 8)],
                ])
                    ->read(['camp_id' => ['name']])
                    ->first(true);

                if(!is_null($following_enrollment)) {
                    $result[$id] = $following_enrollment['camp_id']['name'];
                }
            }
        }

        return $result;
    }

    public static function calcHasCampBirthday($self): array {
        $result = [];
        $self->read(['date_from', 'date_to', 'child_id'  => ['birthdate']]);
        foreach($self as $id => $enrollment) {
            if(!isset($enrollment['date_from'], $enrollment['date_to'], $enrollment['child_id']['birthdate'])) {
                continue;
            }

            $month_day = date('m-d', $enrollment['child_id']['birthdate']);
            $year_birthday = \DateTime::createFromFormat('Y-m-d', date('Y').'-'.$month_day);

            $result[$id] = $enrollment['date_from'] >= $year_birthday && $enrollment['date_to'] <= $year_birthday;
        }

        return $result;
    }

    public static function calcTotal($self): array {
        $result = [];
        $self->read([
            'camp_id'               => ['product_id', 'day_product_id'],
            'enrollment_lines_ids'  => ['product_id', 'total'],
            'price_adapters_ids'    => ['price_adapter_type', 'origin_type', 'value']
        ]);
        foreach($self as $id => $enrollment) {
            $total = 0.0;
            $camp_product_line = null;
            foreach($enrollment['enrollment_lines_ids'] as $enrollment_line) {
                $total += $enrollment_line['total'];

                if(in_array($enrollment_line['product_id'], [$enrollment['camp_id']['product_id'], $enrollment['camp_id']['day_product_id']])) {
                    $camp_product_line = $enrollment_line;
                }
            }

            // #memo - the percentage price-adapter only applies on camp price
            if(!is_null($camp_product_line)) {
                $percent_price_adapter = null;
                foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                    if ($price_adapter['price_adapter_type'] === 'percent') {
                        $percent_price_adapter = $price_adapter;
                        break;
                    }
                }

                if(!is_null($percent_price_adapter)) {
                    $total -= ($camp_product_line['total'] / 100 * $percent_price_adapter['value']);
                }
                if($total < 0) {
                    $total = 0;
                }
            }

            // #memo - apply other and loyalty discount price adapters when type is amount
            foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                if(!in_array($price_adapter['origin_type'], ['other', 'loyalty-discount']) || $price_adapter['price_adapter_type'] !== 'amount') {
                    continue;
                }

                $total -= $price_adapter['value'];
                if($total < 0) {
                    $total = 0;
                }
            }

            $result[$id] = $total;
        }

        return $result;
    }

    public static function calcPrice($self): array {
        $result = [];
        $self->read([
            'camp_id'               => ['product_id', 'day_product_id'],
            'enrollment_lines_ids'  => ['product_id', 'price'],
            'price_adapters_ids'    => ['price_adapter_type', 'origin_type', 'value']
        ]);
        foreach($self as $id => $enrollment) {
            $price = 0.0;
            $camp_product_line = null;
            foreach($enrollment['enrollment_lines_ids'] as $enrollment_line) {
                $price += $enrollment_line['price'];

                if(in_array($enrollment_line['product_id'], [$enrollment['camp_id']['product_id'], $enrollment['camp_id']['day_product_id']])) {
                    $camp_product_line = $enrollment_line;
                }
            }

            // #memo - the percentage price-adapter only applies on camp price
            if(!is_null($camp_product_line)) {
                $percent_price_adapter = null;
                foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                    if ($price_adapter['price_adapter_type'] === 'percent') {
                        $percent_price_adapter = $price_adapter;
                        break;
                    }
                }

                if(!is_null($percent_price_adapter)) {
                    $price -= ($camp_product_line['price'] / 100 * $percent_price_adapter['value']);
                }
                if($price < 0) {
                    $price = 0;
                }
            }

            // #memo - apply other and loyalty discount price adapters when type is amount
            foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                if(!in_array($price_adapter['origin_type'], ['other', 'loyalty-discount']) || $price_adapter['price_adapter_type'] !== 'amount') {
                    continue;
                }

                $price -= $price_adapter['value'];
                if($price < 0) {
                    $price = 0;
                }
            }

            $result[$id] = $price;
        }

        return $result;
    }

    public static function calcAllDocumentsReceived($self): array {
        $result = [];
        $self->read([
            'enrollment_documents_ids'  => ['document_id', 'received'],
            'camp_id'                   => ['required_documents_ids']
        ]);
        foreach($self as $id => $enrollment) {
            $doc_received = true;
            foreach($enrollment['camp_id']['required_documents_ids'] as $required_documents_id) {
                foreach($enrollment['enrollment_documents_ids'] as $en_doc) {
                    if($en_doc['document_id'] === $required_documents_id && !$en_doc['received']) {
                        $doc_received = false;
                        break;
                    }
                }
            }

            $result[$id] = $doc_received;
        }

        return $result;
    }

    public static function calcPaymentStatus($self): array {
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

    public static function calcPaymentReference($self): array {
        $result = [];
        $self->read(['main_guardian_id']);
        foreach($self as $id => $enrollment) {
            // #memo - arbitrary value: used in the accounting software for identifying payments with a temporary account entry counterpart
            $code_ref =  Setting::get_value('sale', 'organization', 'camp.reference.code', 151);
            $reference_type =  Setting::get_value('sale', 'organization', 'camp.reference.type', 'VCS');

            $enrollment_code = $enrollment['id'];

            $reference_value = null;
            switch($reference_type) {
                // use main Guardian id as reference
                case 'main_guardian_id':
                    if(isset($enrollment['child_id']['main_guardian_id'])) {
                        $reference_value = sprintf('%05d', $enrollment['child_id']['main_guardian_id']);
                    }
                    break;
                // ISO-11649
                case 'RF':
                    // structure: RFcc nnn... (up to 25 alpha-num chars) (we omit the 'RF' part in the result)
                    // build a numeric reference
                    $ref_base = sprintf('%03d%07d', $code_ref, $enrollment_code);
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
                    // structure: RNccxxxxxxxxxxxxxxxxxxxx + key + up to 20 digits (we omit the 'RN' part in the result)
                    $ref_body = sprintf('%013d%07d', $code_ref, $enrollment_code);
                    $control = 97 - intval(bcmod($ref_body, '97'));
                    $reference_value = str_pad($control, 2, '0', STR_PAD_LEFT) . $ref_body;
                    break;
                // BE specific
                case 'VCS':
                    // structure: +++xxx/xxxx/xxxcc+++ where cc is the control result (we omit the '+' and '/' chars in the result)
                default:
                $control = ((76 * intval($code_ref)) + $enrollment_code) % 97;
                $control = ($control == 0) ? 97 : $control;
                $reference_value = sprintf('%3d%04d%03d%02d', $code_ref, $enrollment_code / 1000, $enrollment_code % 1000, $control);
                    break;
            }

            $result[$id] = $reference_value;
        }

        return $result;
    }

    public static function calcPaidAmount($self): array {
        $result = [];
        $self->read(['fundings_ids' => ['due_amount', 'is_paid', 'paid_amount']]);
        foreach($self as $id => $enrollment) {
            $paid_amount = 0.0;
            foreach($enrollment['fundings_ids'] as $funding) {
                if($funding['is_paid']) {
                    $paid_amount += $funding['due_amount'];
                }
                elseif($funding['paid_amount'] > 0) {
                    $paid_amount += $funding['paid_amount'];
                }
            }
            $result[$id] = $paid_amount;
        }
        return $result;
    }

    public static function policyConfirm($self): array {
        $result = [];
        $self->read([
            'is_ase',
            'camp_id' => [
                'max_children',
                'ase_quota',
                'enrollments_ids' => ['status', 'is_ase']
            ]
        ]);
        foreach($self as $enrollment) {
            $confirmed_enrollments_qty = 0;
            $confirmed_ase_enrollments = 0;
            foreach($enrollment['camp_id']['enrollments_ids'] as $en) {
                if(in_array($en['status'], ['confirmed', 'validated'])) {
                    $confirmed_enrollments_qty++;

                    if($en['is_ase']) {
                        $confirmed_ase_enrollments++;
                    }
                }
            }

            if($confirmed_enrollments_qty >= $enrollment['camp_id']['max_children']) {
                return ['camp_id' => ['camp_full' => "The camp is full."]];
            }

            if($confirmed_ase_enrollments >= $enrollment['camp_id']['ase_quota']) {
                return ['camp_id' => ['camp_ase_full' => "The camp ASE quota is reached."]];
            }
        }

        return $result;
    }

    public static function policyValidate($self): array {
        $result = [];
        $self->read(['all_documents_received']);
        foreach($self as $enrollment) {
            if(!$enrollment['all_documents_received']) {
                return ['camp_id' => ['missing_document' => "At least one document is missing for the enrollment to this camp."]];
            }
        }

        return $result;
    }

    public static function getPolicies(): array {
        return [

            'confirm' => [
                'description'   => "Checks if the camp isn't full yet and ASE quota.",
                'function'      => "policyConfirm"
            ],

            'validate' => [
                'description'   => "Checks if the enrollment can be validated, if the required documents have already been received and the enrollment has been paid.",
                'function'      => "policyValidate"
            ]

        ];
    }

    /**
     * After status confirm: reset the enrollments qty
     */
    protected static function onafterConfirm($self) {
        $self
            ->do('reset-camp-enrollments-qty')
            ->do('generate_funding')
            ->do('generate_presences');
    }

    /**
     * After status validate: lock enrollment and generate presences
     */
    protected static function onafterValidate($self) {
        $self->update(['is_locked' => true])
             ->do('generate_presences');
    }

    /**
     * After status cancel: unlock enrollment and remove presences
     */
    protected static function onafterCancel($self) {
        $self->update([
            'is_locked'         => false,
            'cancellation_date' => time(),
            'status'            => 'cancelled'   // #todo - find why needed and fix error
        ]);

        // handle fundings and payments
        $self->do('remove_financial_help_payments')
            ->do('delete_unpaid_fundings')
            ->do('update_fundings_due_to_paid')
            ->update(['paid_amount' => null]);

        $self->do('reset-camp-enrollments-qty')
            ->do('remove_presences');
    }

    public static function getWorkflow(): array {
        return [

            'pending' => [
                'description' => "The enrollment is pending/being created, it doesn't block other enrollments.",
                'transitions' => [
                    'waitlist' => [
                        'status'        => 'waitlisted',
                        'description'   => "Add enrollment to waiting list."
                    ],
                    'confirm' => [
                        'status'        => 'confirmed',
                        'description'   => "Reserves the spot (not all documents have necessarily been received).",
                        'policies'      => ['confirm'],
                        'onafter'       => 'onafterConfirm'
                    ],
                    'cancel' => [
                        'status'        => 'cancelled',
                        'description'   => "Cancel the pending enrollment.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'waitlisted' => [
                'description' => "The enrollment is on the waiting list, waiting for a new camp group to be created or to be transferred.",
                'transitions' => [
                    'confirm' => [
                        'status'        => 'confirmed',
                        'description'   => "Reserves the spot (not all documents have necessarily been received).",
                        'policies'      => ['confirm'],
                        'onafter'       => 'onafterConfirm'
                    ],
                    'cancel' => [
                        'status'        => 'cancelled',
                        'description'   => "Cancel the waiting enrollment.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'confirmed' => [
                'description' => "The enrollment is confirmed, the spot is reserved but not all documents have necessarily been received.",
                'transitions' => [
                    'validate' => [
                        'status'        => 'validated',
                        'onafter'       => 'onafterValidate',
                        'help'          => "This step is mandatory for all enrollments (guardians have 10 days to return the documents for web enrollments).",
                        'description'   => "Mark the enrollment as validated (all docs and payments received).",
                        'policies'      => ['validate']
                    ],
                    'cancel' => [
                        'status'        => 'cancelled',
                        'description'   => "Cancel the pending enrollment.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'validated' => [
                'description' => "The enrollment is validated, the required documents have been received.",
                'transitions' => [
                    'cancel' => [
                        'status'        => 'cancelled',
                        'description'   => "Cancel the enrollment.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'cancelled' => [
                'description' => "The enrollment has been cancelled."
            ]

        ];
    }

    public static function canupdate($self, $values): array {
        $self->read([
            'is_external', 'is_locked', 'status', 'child_id', 'camp_id',
            'presence_day_1', 'presence_day_2', 'presence_day_3', 'presence_day_4', 'presence_day_5'
        ]);

        // If is_external some fields cannot be modified
        foreach($self as $enrollment) {
            if($enrollment['is_external']) {
                foreach(array_keys($values) as $column) {
                    // weekend_extra can be modified to alter presences, but it'll not affect lines
                    if(!in_array($column, ['is_locked', 'status', 'cancellation_date', 'enrollment_mails_ids', 'weekend_extra'])) {
                        return ['is_external' => ['external_enrollment' => "Cannot modify an external enrollment."]];
                    }
                }
            }
        }

        // If is_locked cannot be modified
        foreach($self as $enrollment) {
            if($enrollment['is_locked']) {
                foreach(array_keys($values) as $column) {
                    if(!in_array($column, ['is_locked', 'status', 'cancellation_date', 'enrollment_mails_ids'])) {
                        return ['is_locked' => ['locked_enrollment' => "Cannot modify a locked enrollment."]];
                    }
                }
            }
        }

        // Check that camp isn't cancelled
        if(isset($values['camp_id'])) {
            $camp = Camp::id($values['camp_id'])
                ->read(['status'])
                ->first();

            if($camp['status'] === 'cancelled') {
                return ['camp_id' => ['cancelled_camp' => "Cannot enroll to a cancelled camp."]];
            }
        }

        // Check that camp is not already full and that the child hasn't been enrolled yet
        if(
            isset($values['camp_id'])
            || (isset($values['status']) && in_array($values['status'], ['pending', 'validated']))
            || isset($values['presence_day_1'])
            || isset($values['presence_day_2'])
            || isset($values['presence_day_3'])
            || isset($values['presence_day_4'])
            || isset($values['presence_day_5'])
        ) {
            foreach($self as $enrollment) {
                $status = $values['status'] ?? $enrollment['status'];

                if(in_array($status, ['pending', 'validated'])) {
                    $camp = Camp::id($values['camp_id'] ?? $enrollment['camp_id'])
                        ->read([
                            'is_clsh',
                            'clsh_type',
                            'max_children',
                            'enrollments_ids' => [
                                'status',
                                'presence_day_1',
                                'presence_day_2',
                                'presence_day_3',
                                'presence_day_4',
                                'presence_day_5'
                            ]
                        ])
                        ->first();

                    if($camp['is_clsh']) {
                        $days = $camp['clsh_type'] === '5-days' ? [1, 2, 3, 4, 5] : [1, 2, 3, 4];

                        $present_days = [
                            1 => $values['presence_day_1'] ?? $enrollment['presence_day_1'],
                            2 => $values['presence_day_2'] ?? $enrollment['presence_day_2'],
                            3 => $values['presence_day_3'] ?? $enrollment['presence_day_3'],
                            4 => $values['presence_day_4'] ?? $enrollment['presence_day_4'],
                            5 => $values['presence_day_5'] ?? $enrollment['presence_day_5']
                        ];

                        foreach($days as $day) {
                            if(!$present_days[$day]) {
                                continue;
                            }

                            $day_confirmed_enrollments_qty = 0;
                            foreach($camp['enrollments_ids'] as $en) {
                                if($en['presence_day_'.$day] && in_array($en['status'], ['confirmed', 'validated']) && $en['id'] !== $enrollment['id']) {
                                    $day_confirmed_enrollments_qty++;
                                }
                            }

                            if($day_confirmed_enrollments_qty >= $camp['max_children']) {
                                if($day === 1) {
                                    return ['camp_id' => ['day_1_full' => "The 1st day of the camp is full."]];
                                }
                                elseif($day === 2) {
                                    return ['camp_id' => ['day_2_full' => "The 2nd day of the camp is full."]];
                                }
                                elseif($day === 3) {
                                    return ['camp_id' => ['day_3_full' => "The 3rd day of the camp is full."]];
                                }
                                elseif($day === 4) {
                                    return ['camp_id' => ['day_4_full' => "The 4th day of the camp is full."]];
                                }
                                else {
                                    return ['camp_id' => ['day_5_full' => "The 5th day of the camp is full."]];
                                }
                            }
                        }
                    }
                    else {
                        $confirmed_enrollments_qty = 0;

                        foreach($camp['enrollments_ids'] as $en) {
                            if(in_array($en['status'], ['confirmed', 'validated']) && $en['id'] !== $enrollment['id']) {
                                $confirmed_enrollments_qty++;
                            }
                        }
                        if($confirmed_enrollments_qty >= $camp['max_children']) {
                            return ['camp_id' => ['full' => "The camp is full."]];
                        }
                    }
                }
            }
        }

        // Check that child has main guardian or institution + Check that child not already enrolled to camp
        if(isset($values['child_id'])) {
            $child = Child::id($values['child_id'])
                ->read(['main_guardian_id', 'is_foster', 'institution_id'])
                ->first();

            if($child['is_foster']) {
                if(is_null($child['institution_id'])) {
                    return ['child_id' => ['missing_institution' => "Missing institution."]];
                }
            }
            else {
                if(is_null($child['main_guardian_id'])) {
                    return ['child_id' => ['missing_main_guardian' => "Missing main guardian."]];
                }
            }

            foreach($self as $enrollment) {
                $camp = Camp::id($values['camp_id'] ?? $enrollment['camp_id'])
                    ->read(['enrollments_ids' => ['child_id']])
                    ->first();

                foreach($camp['enrollments_ids'] as $en) {
                    if($en['child_id'] === $values['child_id'] && $en['id'] !== $enrollment['id']) {
                        return ['child_id' => ['already_enrolled' => "The child is already enrolled in this camp."]];
                    }
                }
            }
        }

        // Check max quota ase
        if(isset($values['is_ase']) && $values['is_ase']) {
            foreach($self as $enrollment) {
                $camp = Camp::id($values['camp_id'] ?? $enrollment['camp_id'])
                    ->read(['ase_quota', 'camp_group_qty', 'enrollments_ids' => ['is_ase']])
                    ->first();

                $ase_children_qty = 1;
                foreach($camp['enrollments_ids'] as $en) {
                    if($en['is_ase'] && $en['id'] !== $enrollment['id']) {
                        $ase_children_qty++;
                    }
                }

                if($ase_children_qty > ($camp['ase_quota'] * $camp['camp_group_qty'])) {
                    return ['is_ase' => ['too_many_ase_children' => "The ase children quota is full."]];
                }
            }
        }

        // Check that the child has license ffe if needed
        if(isset($values['camp_id']) || isset($values['child_id'])) {
            foreach($self as $enrollment) {
                $camp = Camp::id($values['camp_id'] ?? $enrollment['camp_id'])
                    ->read(['need_license_ffe'])
                    ->first();

                $child = Child::id($values['child_id'] ?? $enrollment['child_id'])
                    ->read(['has_license_ffe'])
                    ->first();

                if($camp['need_license_ffe'] && !$child['has_license_ffe']) {
                    return ['child_id' => ['need_license_ffe' => "The child need a FFE license to enroll to the camp."]];
                }
            }
        }

        // Check that the child has the required age
        if(isset($values['camp_id']) || isset($values['child_id'])) {
            foreach($self as $enrollment) {
                $camp = Camp::id($values['camp_id'] ?? $enrollment['camp_id'])
                    ->read(['min_age', 'max_age', 'date_from'])
                    ->first();

                $child = Child::id($values['child_id'] ?? $enrollment['child_id'])
                    ->read(['birthdate'])
                    ->first();

                $date_from = (new \DateTime())->setTimestamp($camp['date_from']);
                $birthdate = (new \DateTime())->setTimestamp($child['birthdate']);
                $child_age = $birthdate->diff($date_from)->y;

                // allow some flexibility with -1 and +1 (a warning will be dispatched if age doesn't exactly meet requirements of min_age and max_age)
                if($child_age < ($camp['min_age'] - 1) || $child_age > ($camp['max_age'] + 1)) {
                    return ['child_id' => ['birthdate' => "The child does not fit the camp age requirements."]];
                }
            }
        }

        // Check that the child is not already enrolled to another camp at the same time
        if(isset($values['camp_id']) || isset($values['child_id'])) {
            foreach($self as $enrollment) {
                $camp = Camp::id($values['camp_id'] ?? $enrollment['camp_id'])
                    ->read([
                        'required_skills_ids',
                        'date_from',
                        'date_to'
                    ])
                    ->first();

                $child = Child::id($values['child_id'] ?? $enrollment['child_id'])
                    ->read([
                        'skills_ids',
                        'enrollments_ids' => [
                            'status',
                            'camp_id' => [
                                'date_from',
                                'date_to'
                            ]
                        ]
                    ])
                    ->first();

                foreach($camp['required_skills_ids'] as $required_skill_id) {
                    if(!in_array($required_skill_id, $child['skills_ids'])){
                        return ['child_id' => ['missing_skill' => "Child does not have the required skills for this camp."]];
                    }
                }

                foreach($child['enrollments_ids'] as $en) {
                    if(
                        $enrollment['id'] !== $en['id']
                        && $camp['date_from'] <= $en['camp_id']['date_to']
                        && $camp['date_to'] >= $en['camp_id']['date_from']
                        && in_array($en['status'], ['pending', 'validated'])
                    ) {
                        return ['child_id' => ['already_enrolled_to_other_camp' => "Child has already been enrolled to another camp during this period."]];
                    }
                }
            }
        }

        // Check that if clsh the child needs to be present at least one day
        if(isset($values['presence_day_1']) || isset($values['presence_day_2']) || isset($values['presence_day_3']) || isset($values['presence_day_4']) || isset($values['presence_day_5'])) {
            $self->read(['is_clsh', 'presence_day_1', 'presence_day_2', 'presence_day_3', 'presence_day_4', 'presence_day_5']);
            foreach($self as $enrollment) {
                if(!$enrollment['is_clsh']) {
                    continue;
                }

                $present_days = [
                    $values['presence_day_1'] ?? $enrollment['presence_day_1'],
                    $values['presence_day_2'] ?? $enrollment['presence_day_2'],
                    $values['presence_day_3'] ?? $enrollment['presence_day_3'],
                    $values['presence_day_4'] ?? $enrollment['presence_day_4'],
                    $values['presence_day_5'] ?? $enrollment['presence_day_5']
                ];

                if(!in_array(true, $present_days)) {
                    return ['is_clsh' => ['needs_at_least_one_present_day' => "The child need at least one present day."]];
                }
            }
        }

        return parent::cancreate($self, $values);
    }

    public static function onupdateCampId($self) {
        // prevent state to pass to instance when camp selected and state is "draft"
        $self->read(['state']);
        foreach($self as $enrollment) {
            if($enrollment['state'] === 'draft') {
                return;
            }
        }

        $providers = \eQual::inject(['dispatch']);

        /** @var \equal\dispatch\Dispatcher $dispatch */
        $dispatch = $providers['dispatch'];

        $self->do('refresh_camp_product_line');
        $self->do('reset-camp-enrollments-qty');
        $self->do('refresh_required_documents');

        // remove previously generated presences of pending enrollment
        $self->read(['status', 'child_id']);
        foreach($self as $enrollment) {
            if($enrollment['status'] !== 'pending' && $enrollment['status'] !== 'validated') {
                continue;
            }

            Child::id($enrollment['child_id'])->do('remove-unnecessary-presences');
            Enrollment::id($enrollment['id'])->do('generate_presences');
        }

        // check child age
        $self->read(['child_age', 'camp_id' => ['min_age', 'max_age', 'center_office_id']]);
        foreach($self as $id => $enrollment) {
            if($enrollment['child_age'] < $enrollment['camp_id']['min_age'] || $enrollment['child_age'] > $enrollment['camp_id']['max_age']) {
                $dispatch->dispatch('lodging.camp.enrollment.age_mismatch', 'sale\camp\Enrollment', $id, 'warning', null, [], [], null, $enrollment['camp_id']['center_office_id']);
            }
        }
    }

    public static function onupdateFamilyQuotient($self) {
        $self->do('refresh_camp_product_line');
    }

    public static function onupdateWeekendExtra($self) {
        $self->read([
            'weekend_extra', 'is_external',
            'camp_id'               => ['weekend_product_id', 'saturday_morning_product_id', 'date_from', 'date_to', 'center_office_id'],
            'enrollment_lines_ids'  => ['product_id']
        ]);
        foreach($self as $id => $enrollment) {
            if($enrollment['is_external']) {
                // If external we can modify weekend_extra to affect presences generation, but it shouldn't modify the enrollment lines
                continue;
            }

            switch($enrollment['weekend_extra']) {
                case 'none':
                    EnrollmentLine::search([
                        ['enrollment_id', '=', $id],
                        ['product_id', 'in', [$enrollment['camp_id']['weekend_product_id'], $enrollment['camp_id']['saturday_morning_product_id']]]
                    ])
                        ->delete(true);
                    break;
                case 'full':
                    EnrollmentLine::search([
                        ['enrollment_id', '=', $id],
                        ['product_id', '=', $enrollment['camp_id']['saturday_morning_product_id']]
                    ])
                        ->delete(true);

                    $we_lines_ids = EnrollmentLine::search([
                        ['enrollment_id', '=', $id],
                        ['product_id', '=', $enrollment['camp_id']['weekend_product_id']]
                    ])
                        ->ids();

                    if(empty($we_lines_ids)) {
                        $we_product = Product::id($enrollment['camp_id']['weekend_product_id'])
                            ->read(['prices_ids' => ['price_list_id' => ['date_from', 'date_to']]])
                            ->first();

                        $product_price = null;
                        foreach($we_product['prices_ids'] as $price) {
                            if(
                                $enrollment['camp_id']['date_from'] >= $price['price_list_id']['date_from']
                                && $enrollment['camp_id']['date_from'] <= $price['price_list_id']['date_to']
                            ) {
                                $product_price = $price;
                                break;
                            }
                        }

                        if(!is_null($product_price)) {
                            EnrollmentLine::create([
                                'enrollment_id' => $id,
                                'product_id'    => $enrollment['camp_id']['weekend_product_id'],
                                'price_id'      => $product_price['id']
                            ]);
                        }
                    }

                    $providers = \eQual::inject(['dispatch']);

                    /** @var \equal\dispatch\Dispatcher $dispatch */
                    $dispatch = $providers['dispatch'];

                    $dispatch->dispatch('lodging.camp.enrollment.weekend', 'sale\camp\Enrollment', $id, 'warning', null, [], [], null, $enrollment['camp_id']['center_office_id']);
                    break;
                case 'saturday-morning':
                    EnrollmentLine::search([
                        ['enrollment_id', '=', $id],
                        ['product_id', '=', $enrollment['camp_id']['weekend_product_id']]
                    ])
                        ->delete(true);

                    $sat_lines_ids = EnrollmentLine::search([
                        ['enrollment_id', '=', $id],
                        ['product_id', '=', $enrollment['camp_id']['saturday_morning_product_id']]
                    ])
                        ->ids();

                    if(empty($sat_lines_ids)) {
                        $sat_product = Product::id($enrollment['camp_id']['saturday_morning_product_id'])
                            ->read(['prices_ids' => ['price_list_id' => ['date_from', 'date_to']]])
                            ->first();

                        $product_price = null;
                        foreach($sat_product['prices_ids'] as $price) {
                            if(
                                $enrollment['camp_id']['date_from'] >= $price['price_list_id']['date_from']
                                && $enrollment['camp_id']['date_from'] <= $price['price_list_id']['date_to']
                            ) {
                                $product_price = $price;
                                break;
                            }
                        }

                        if(!is_null($product_price)) {
                            EnrollmentLine::create([
                                'enrollment_id' => $id,
                                'product_id'    => $enrollment['camp_id']['saturday_morning_product_id'],
                                'price_id'      => $product_price['id']
                            ]);
                        }
                    }
                    break;
            }
        }
    }

    public static function onupdatePresentDay($self) {
        $self->do('refresh_camp_product_line');
    }

    public static function onupdateWorksCouncilId($self) {
        $self->do('refresh_camp_product_line');
    }

    public static function onupdateCampClass($self) {
        $self->do('refresh_camp_product_line');
    }

    public static function onupdate($self, $values) {
        if(isset($values['status']) || (isset($values['state']) && $values['state'] === 'instance')) {
            $self->do('reset-camp-enrollments-qty');

            foreach($self as $id => $enrollment) {
                \eQual::run('do', 'sale_camp_followup_generate-task-status-change', ['enrollment_id' => $id]);
            }
        }
    }

    public static function doResetCampEnrollmentsQty($self) {
        $self->read(['camp_id']);

        $map_camps_ids = [];
        foreach($self as $enrollment) {
            $map_camps_ids[$enrollment['camp_id']] = true;
        }

        Camp::ids(array_keys($map_camps_ids))->update(['enrollments_qty' => null]);
    }

    public static function doDeleteLines($self) {
        $lines_to_del = [];
        $self->read(['enrollment_lines_ids']);
        foreach($self as $enrollment) {
            $lines_to_del = [...$lines_to_del, ...$enrollment['enrollment_lines_ids']];
        }

        if(!empty($lines_to_del)) {
            EnrollmentLine::ids($lines_to_del)->delete(true);
        }
    }

    public static function doGeneratePresences($self) {
        $self->read([
            'status',
            'is_clsh', 'camp_id', 'child_id', 'date_from', 'date_to', 'weekend_extra',
            'presence_day_1', 'presence_day_2', 'presence_day_3', 'presence_day_4', 'presence_day_5',
            'daycare_day_1', 'daycare_day_2', 'daycare_day_3', 'daycare_day_4', 'daycare_day_5'
        ]);
        foreach($self as $enrollment) {
            if(!in_array($enrollment['status'], ['confirmed', 'validated'])) {
                continue;
            }
            $day_index = 1;
            $date = $enrollment['date_from'];
            while($date <= $enrollment['date_to']) {
                if(!$enrollment['is_clsh'] || $enrollment['presence_day_'.$day_index]) {
                    $am_daycare = false;
                    $pm_daycare = false;
                    if($enrollment['is_clsh']) {
                        $daycare_day = $enrollment['daycare_day_'.$day_index];
                        if($daycare_day === 'am' || $daycare_day === 'full') {
                            $am_daycare = true;
                        }
                        if($daycare_day === 'pm' || $daycare_day === 'full') {
                            $pm_daycare = true;
                        }
                    }

                    Presence::create([
                        'presence_date' => $date,
                        'camp_id'       => $enrollment['camp_id'],
                        'child_id'      => $enrollment['child_id'],
                        'am_daycare'    => $am_daycare,
                        'pm_daycare'    => $pm_daycare
                    ]);
                }

                $day_index++;
                $date += 60 * 60 * 24;
            }

            if(!$enrollment['is_clsh'] && $enrollment['weekend_extra'] !== 'none') {
                // add Saturday presence
                Presence::create([
                    'presence_date' => $date,
                    'camp_id'       => $enrollment['camp_id'],
                    'child_id'      => $enrollment['child_id']
                ]);

                if($enrollment['weekend_extra'] === 'full') {
                    $date += 60 * 60 * 24;

                    // add Sunday presence
                    Presence::create([
                        'presence_date' => $date,
                        'camp_id'       => $enrollment['camp_id'],
                        'child_id'      => $enrollment['child_id']
                    ]);
                }
            }
        }
    }

    public static function doRemovePresences($self) {
        $self->read(['camp_id', 'child_id']);
        foreach($self as $enrollment) {
            Presence::search([
                ['camp_id', '=', $enrollment['camp_id']],
                ['child_id', '=', $enrollment['child_id']]
            ])
                ->delete(true);
        }
    }

    public static function doRefreshCampProductLine($self) {
        $self->read([
            'is_clsh',
            'clsh_type',
            'presence_day_1',
            'presence_day_2',
            'presence_day_3',
            'presence_day_4',
            'presence_day_5',
            'family_quotient',
            'camp_class',
            'child_id',
            'camp_id'   => [
                'date_from',
                'date_to',
                'product_id',
                'day_product_id',
            ]
        ]);
        foreach($self as $id => $enrollment) {
            if(is_null($enrollment['camp_class']) || is_null($enrollment['child_id'])) {
                continue;
            }

            if($enrollment['is_clsh']) {
                $day_products_ids = Product::search(['camp_product_type', '=', 'day'])->ids();

                $camp_product_line = EnrollmentLine::search([
                    ['product_id', 'in', $day_products_ids],
                    ['enrollment_id', '=', $id]
                ])
                    ->read(['product_id'])
                    ->first();

                $day_product_id = $enrollment['camp_id']['day_product_id'];
                if(!is_null($camp_product_line)) {
                    $day_product_id = $camp_product_line['product_id'];
                }

                $day_product = Product::id($day_product_id)
                    ->read([
                        'prices_ids' => [
                            'camp_class',
                            'family_quotient_min',
                            'family_quotient_max',
                            'price_list_id' => ['date_from', 'date_to']
                        ]
                    ])
                    ->first();

                $present_days = [
                    $enrollment['presence_day_1'],
                    $enrollment['presence_day_2'],
                    $enrollment['presence_day_3'],
                    $enrollment['presence_day_4'],
                    $enrollment['presence_day_5']
                ];

                $qty = 0;
                foreach($present_days as $present_day) {
                    if($present_day) {
                        $qty++;
                    }
                }

                $camp_classes = ['other'];
                if($enrollment['camp_class'] === 'close-member') {
                    $camp_classes = ['close-member', 'member', 'other'];
                }
                elseif($enrollment['camp_class'] === 'member') {
                    $camp_classes = ['member', 'other'];
                }

                $camp_price = null;
                foreach($camp_classes as $camp_class) {
                    foreach($day_product['prices_ids'] as $price) {
                        if(
                            $camp_class === $price['camp_class']
                            && $enrollment['camp_id']['date_from'] >= $price['price_list_id']['date_from']
                            && $enrollment['camp_id']['date_from'] <= $price['price_list_id']['date_to']
                            && $enrollment['family_quotient'] >= $price['family_quotient_min']
                            && $enrollment['family_quotient'] <= $price['family_quotient_max']
                        ) {
                            $camp_price = $price;
                            break 2;
                        }
                    }
                }

                if(!is_null($camp_price)) {
                    if(is_null($camp_product_line)) {
                        EnrollmentLine::create([
                            'enrollment_id' => $id,
                            'product_id'    => $day_product['id'],
                            'price_id'      => $camp_price['id'],
                            'qty'           => $qty
                        ]);
                    }
                    else {
                        EnrollmentLine::id($camp_product_line['id'])
                            ->update([
                                'price_id'  => $camp_price['id'],
                                'qty'       => $qty
                            ]);
                    }
                }
            }
            else {
                $products_ids = Product::search(['camp_product_type', '=', 'full'])->ids();

                $camp_product_line = EnrollmentLine::search([
                    ['product_id', 'in', $products_ids],
                    ['enrollment_id', '=', $id]
                ])
                    ->read(['product_id'])
                    ->first();

                $product_id = $enrollment['camp_id']['product_id'];
                if(!is_null($camp_product_line)) {
                    $product_id = $camp_product_line['product_id'];
                }

                $product = Product::id($product_id)
                    ->read([
                        'prices_ids' => [
                            'camp_class',
                            'price_list_id' => ['date_from', 'date_to']
                        ]
                    ])
                    ->first();

                $camp_classes = ['other'];
                if($enrollment['camp_class'] === 'close-member') {
                    $camp_classes = ['close-member', 'member', 'other'];
                }
                elseif($enrollment['camp_class'] === 'member') {
                    $camp_classes = ['member', 'other'];
                }

                $camp_price = null;
                foreach($camp_classes as $camp_class) {
                    foreach($product['prices_ids'] as $price) {
                        if(
                            $camp_class === $price['camp_class']
                            && $enrollment['camp_id']['date_from'] >= $price['price_list_id']['date_from']
                            && $enrollment['camp_id']['date_from'] <= $price['price_list_id']['date_to']
                        ) {
                            $camp_price = $price;
                            break 2;
                        }
                    }
                }

                if(!is_null($camp_price)) {
                    if(is_null($camp_product_line)) {
                        EnrollmentLine::create([
                            'enrollment_id' => $id,
                            'product_id'    => $product['id'],
                            'price_id'      => $camp_price['id'] ?? null,
                            'qty'           => 1
                        ]);
                    }
                    else {
                        EnrollmentLine::id($camp_product_line['id'])
                            ->update(['price_id' => $camp_price['id']]);
                    }
                }
            }
        }
    }

    public static function doRefreshRequiredDocuments($self) {
        $self->read([
            'enrollment_documents_ids'  => ['document_id'],
            'camp_id'                   => ['required_documents_ids']
        ]);

        foreach($self as $id => $enrollment) {
            $needed_documents_ids = [];
            foreach($enrollment['camp_id']['required_documents_ids'] as $required_document_id) {
                $already_added = false;
                foreach($enrollment['enrollment_documents_ids'] as $enrollment_document) {
                    if($enrollment_document['document_id'] === $required_document_id) {
                        $already_added = true;
                        break;
                    }
                }

                if(!$already_added) {
                    $needed_documents_ids[] = $required_document_id;
                }
            }

            foreach($needed_documents_ids as $document_id) {
                EnrollmentDocument::create([
                    'enrollment_id' => $id,
                    'document_id'   => $document_id
                ]);
            }
        }

        // reset all documents received computed value
        $self->update(['all_documents_received' => null]);
    }

    public static function doGenerateFunding($self) {
        $self->read([
            'price',
            'price_adapters_ids'    => ['value', 'origin_type'],
            'fundings_ids'          => ['amount'],
            'camp_id'               => ['date_from', 'center_id' => ['center_office_id']]
        ]);

        foreach($self as $id => $enrollment) {
            $remaining_amount = $enrollment['price'];

            $fundings_amount = 0.0;
            foreach($enrollment['fundings_ids'] as $funding) {
                $fundings_amount += $funding['amount'];
            }

            $remaining_amount -= $fundings_amount;
            if($remaining_amount <= 0) {
                continue;
            }

            $due_date = $enrollment['camp_id']['date_from'];
            $one_month_before_camp = (new \DateTime())->setTimestamp($enrollment['camp_id']['date_from'])->modify('-30 days')->getTimestamp();
            if(time() < $one_month_before_camp) {
                $due_date = $one_month_before_camp;
            }

            $funding = Funding::create([
                'enrollment_id'     => $id,
                'due_amount'        => $remaining_amount,
                'due_date'          => $due_date,
                'center_office_id'  => $enrollment['camp_id']['center_id']['center_office_id']
            ])
                ->read(['center_office_id'])
                ->first();

            foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                // #memo - the other price-adapters are already removed from price
                if(!in_array($price_adapter['origin_type'], ['commune', 'community-of-communes', 'department-caf', 'department-msa'])) {
                    continue;
                }

                Payment::create([
                    'enrollment_id'     => $id,
                    'amount'            => $price_adapter['value'],
                    'payment_origin'    => 'cashdesk',
                    'payment_method'    => 'camp_financial_help',
                    'funding_id'        => $funding['id'],
                    'center_office_id'  => $funding['center_office_id']
                ]);
            }

            self::id($id)->update(['payment_status' => null]);
        }
    }

    protected static function doRemoveFinancialHelpPayments($self) {
        $self->read(['fundings_ids' => ['payments_ids' => ['payment_method']]]);
        foreach($self as $enrollment) {
            $map_funding_to_reset_ids = [];
            $external_payments_ids = [];
            foreach($enrollment['fundings_ids'] as $fid => $funding) {
                foreach($funding['payments_ids'] as $pid => $payment) {
                    if($payment['payment_method'] === 'camp_financial_help') {
                        $external_payments_ids[] = $pid;
                        $map_funding_to_reset_ids[$fid] = true;
                    }
                }
            }

            Payment::ids($external_payments_ids)->delete();

            Funding::ids(array_keys($map_funding_to_reset_ids))->update([
                'paid_amount'   => null,
                'is_paid'       => null,
                'status'        => 'pending'
            ])
                ->read(['paid_amount', 'is_paid']);
        }
    }

    protected static function doDeleteUnpaidFundings($self) {
        $self->read(['fundings_ids' => ['is_paid', 'paid_amount']]);
        foreach($self as $enrollment) {
            foreach($enrollment['fundings_ids'] as $funding_id => $funding) {
                if($funding['paid_amount'] > 0 || $funding['is_paid']) {
                    continue;
                }

                Funding::id($funding_id)->delete(true);
            }
        }
    }

    protected static function doUpdateFundingsDueToPaid($self) {
        $self->read(['fundings_ids' => ['due_amount', 'paid_amount']]);
        foreach($self as $enrollment) {
            foreach($enrollment['fundings_ids'] as $funding_id => $funding) {
                if($funding['due_amount'] <= 0) {
                    continue;
                }

                Funding::id($funding_id)->update(['due_amount' => $funding['paid_amount']]);
            }
        }
    }

    public static function getActions(): array {
        return [

            'reset-camp-enrollments-qty' => [
                'description'   => "Reset the enrollments prices fields values so they can be re-calculated.",
                'policies'      => [],
                'function'      => 'doResetCampEnrollmentsQty'
            ],

            'delete-lines' => [
                'description'   => "Remove all enrollment lines.",
                'policies'      => [],
                'function'      => 'doDeleteLines'
            ],

            'generate_presences' => [
                'description'   => "Generate the child day presences to the camp.",
                'policies'      => [],
                'function'      => 'doGeneratePresences'
            ],

            'remove_presences' => [
                'description'   => "Remove the child day presences to the camp.",
                'policies'      => [],
                'function'      => 'doRemovePresences'
            ],

            'refresh_camp_product_line' => [
                'description'   => "Creates/updates the enrollment line that concerns the product_id or day_product_id.",
                'policies'      => [],
                'function'      => 'doRefreshCampProductLine'
            ],

            'refresh_required_documents' => [
                'description'   => "Creates/updates the enrollment documents that are required depending on the camp.",
                'policies'      => [],
                'function'      => 'doRefreshRequiredDocuments'
            ],

            'generate_funding' => [
                'description'   => "Creates the enrollment funding if is does not already exists.",
                'policies'      => [],
                'function'      => 'doGenerateFunding'
            ],

            'remove_financial_help_payments' => [
                'description'   => "Removes funding's payments that are related for financial helps.",
                'help'          => "Used when a enrollment is cancelled.",
                'policies'      => [],
                'function'      => 'doRemoveFinancialHelpPayments'
            ],

            'delete_unpaid_fundings' => [
                'description'   => "Removes enrollment's fundings that haven't received any payment.",
                'help'          => "Used when a enrollment is cancelled.",
                'policies'      => [],
                'function'      => 'doDeleteUnpaidFundings'
            ],

            'update_fundings_due_to_paid' => [
                'description'   => "Sets partially paid fundings due_amount to the value of paid_amount.",
                'help'          => "Used when a enrollment is cancelled.",
                'policies'      => [],
                'function'      => 'doUpdateFundingsDueToPaid'
            ]

        ];
    }

    public static function ondelete($self): void {
        $self->do('delete-lines');
        $self->do('reset-camp-enrollments-qty');
        $self->do('remove_presences');

        parent::ondelete($self);
    }
}
