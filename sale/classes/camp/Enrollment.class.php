<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;
use sale\camp\price\Price;

class Enrollment extends Model {

    public static function getDescription(): string {
        return "The enrollment of a child to a camp group.";
    }

    public static function getColumns(): array {
        return [

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
                'required'          => true
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
                'onupdate'          => 'onupdateCampId'
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

            'is_clsh' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "",
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
                    'canceled'
                ],
                'description'       => "The status of the enrollment.",
                'default'           => 'pending'
            ],

            'cancellation_date' => [
                'type'              => 'date',
                'description'       => "Date of cancellation."
            ],

            'is_ase' => [
                'type'              => 'boolean',
                'description'       => "Is \"aide sociale à l'enfance\".",
                'default'           => false,
                'onupdate'          => 'onupdateIsAse'
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
                'visible'           => ['is_clsh', '=', false]
            ],

            'documents_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'enrollment_id',
                'foreign_object'    => 'sale\camp\document\Document',
                'description'       => "The documents needed for the child to enroll to the camp."
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

            'sponsors_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Sponsor',
                'foreign_field'     => 'enrollments_ids',
                'rel_table'         => 'sale_rel_enrollment_sponsor',
                'rel_foreign_key'   => 'sponsor_id',
                'rel_local_key'     => 'enrollment_id',
                'description'       => "Sponsors that reduce the price of the enrollment."
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'description'       => "Mails related to the enrollment.",
                'domain'            => ['object_class', '=', 'sale\camp\Enrollment']
            ]

        ];
    }

    public static function onchange($event, $values): array {
        $result = [];
        if($values['is_clsh']) {
            if(isset($event['child_id'])) {
                $child = Child::id($event['child_id'])
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
            if(isset($event['child_id'])) {
                $child = Child::id($event['child_id'])
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
        $self->read([
            'camp_id'   => ['date_from'],
            'child_id'  => ['birthdate']
        ]);
        foreach($self as $id => $enrollment) {
            if(!isset($enrollment['camp_id']['date_from'], $enrollment['child_id']['birthdate'])) {
                continue;
            }

            $date_from = (new \DateTime())->setTimestamp($enrollment['camp_id']['date_from']);
            $birthdate = (new \DateTime())->setTimestamp($enrollment['child_id']['birthdate']);
            $result[$id] = $birthdate->diff($date_from)->y;
        }

        return $result;
    }

    public static function calcTotal($self): array {
        $result = [];
        $self->read([
            'enrollment_lines_ids'  => ['total'],
            'price_adapters_ids'    => ['price_adapter_type', 'value']
        ]);
        foreach($self as $id => $enrollment) {
            $total = 0.0;
            foreach($enrollment['enrollment_lines_ids'] as $enrollment_line) {
                $total += $enrollment_line['total'];
            }

            foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                if($price_adapter['price_adapter_type'] !== 'amount') {
                    continue;
                }
                $total -= $price_adapter['value'];
            }
            if($total < 0) {
                $total = 0;
            }

            $percentage = 0;
            foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                if($price_adapter['price_adapter_type'] !== 'percent') {
                    continue;
                }
                $percentage += $price_adapter['value'];
            }
            if($percentage > 100) {
                $percentage = 100;
            }

            $total -= $total / 100 * $percentage;

            $result[$id] = $total;
        }

        return $result;
    }

    public static function calcPrice($self): array {
        $result = [];
        $self->read([
            'enrollment_lines_ids'  => ['price'],
            'price_adapters_ids'    => ['price_adapter_type', 'value']
        ]);
        foreach($self as $id => $enrollment) {
            $price = 0.0;
            foreach($enrollment['enrollment_lines_ids'] as $enrollment_line) {
                $price += $enrollment_line['price'];
            }

            foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                if($price_adapter['price_adapter_type'] !== 'amount') {
                    continue;
                }
                $price -= $price_adapter['value'];
            }
            if($price < 0) {
                $price = 0;
            }

            $percentage = 0;
            foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                if($price_adapter['price_adapter_type'] !== 'percent') {
                    continue;
                }
                $percentage += $price_adapter['value'];
            }
            if($percentage > 100) {
                $percentage = 100;
            }

            $price -= $price / 100 * $percentage;

            $result[$id] = $price;
        }

        return $result;
    }

    public static function policyRemoveFromWaitlist($self): array {
        $result = [];
        $self->read([
            'camp_id' => [
                'max_children',
                'enrollments_ids' => ['status']
            ]
        ]);
        foreach($self as $enrollment) {
            $pending_confirmed_enrollments_qty = 0;
            foreach($enrollment['camp_id']['enrollments_ids'] as $en) {
                if(in_array($en['status'], ['pending', 'confirmed'])) {
                    $pending_confirmed_enrollments_qty++;
                }
            }

            if($pending_confirmed_enrollments_qty >= $enrollment['camp_id']['max_children']) {
                return ['camp_id' => ['camp_full' => "The camp is full."]];
            }
        }

        return $result;
    }

    public static function getPolicies(): array {
        return [

            'remove-from-waitlist' => [
                'description' => "Check if the camp isn't full yet.",
                'function'    => 'policyRemoveFromWaitlist'
            ]

        ];
    }

    public static function onafterPending($self) {
        $self->update([
            'is_locked' => false,
            'status'    => 'pending'            // #todo - find why needed and fix error
        ]);

        $self->do('reset-camp-enrollments-qty');
    }

    /**
     * Lock enrollment after status to confirm
     */
    public static function onafterConfirm($self) {
        $self->update([
            'is_locked' => true,
            'status'    => 'confirmed'          // #todo - find why needed and fix error
        ]);

        $self->do('reset-camp-enrollments-qty');
        $self->do('generate-presences');
    }

    public static function onafterCancel($self) {
        $self->update([
            'is_locked'         => false,
            'cancellation_date' => time(),
            'status'            => 'canceled'   // #todo - find why needed and fix error
        ]);

        $self->do('reset-camp-enrollments-qty');
        $self->do('remove-presences');
    }

    public static function getWorkflow(): array {
        return [

            'pending' => [
                'description' => "The enrollment is waiting for confirmation, the entered child/parent data and provided documents need to be checked.",
                'transitions' => [
                    'confirm' => [
                        'status'        => 'confirmed',
                        'description'   => "Confirm the pending enrollment.",
                        'onafter'       => 'onafterConfirm'
                    ],
                    'cancel' => [
                        'status'        => 'canceled',
                        'description'   => "Cancel the pending enrollment.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'waitlisted' => [
                'description' => "The enrollment is waiting for a confirmation.",
                'transitions' => [
                    'pending' => [
                        'status'        => 'pending',
                        'description'   => "Remove from the waitlist.",
                        'policies'      => ['remove-from-waitlist'],
                        'onafter'       => 'onafterPending'
                    ],
                    'cancel' => [
                        'status'        => 'canceled',
                        'description'   => "Cancel the waiting enrollment.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'confirmed' => [
                'description' => "The enrollment is confirmed, the child can attend the camp.",
                'transitions' => [
                    'cancel' => [
                        'status'        => 'canceled',
                        'description'   => "Cancel the confirmed enrollment.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'canceled' => [
                'description' => "The enrollment has been canceled."
            ]

        ];
    }

    public static function canupdate($self, $values): array {
        $self->read([
            'is_locked', 'status', 'child_id', 'camp_id',
            'presence_day_1', 'presence_day_2', 'presence_day_3', 'presence_day_4', 'presence_day_5'
        ]);

        // If is_locked cannot be modified
        foreach($self as $enrollment) {
            if($enrollment['is_locked']) {
                foreach(array_keys($values) as $column) {
                    if(!in_array($column, ['is_locked', 'status', 'cancellation_date'])) {
                        return ['is_locked' => ['locked_enrollment' => "Cannot modify a locked enrollment."]];
                    }
                }
            }
        }

        // Check that camp isn't canceled
        if(isset($values['camp_id'])) {
            $camp = Camp::id($values['camp_id'])
                ->read(['status'])
                ->first();

            if($camp['status'] === 'canceled') {
                return ['camp_id' => ['canceled_camp' => "Cannot enroll to a canceled camp."]];
            }
        }

        // Check that camp is not already full and that the child hasn't been enrolled yet
        if(
            isset($values['camp_id'])
            || (isset($values['status']) && in_array($values['status'], ['pending', 'confirmed']))
            || isset($values['presence_day_1'])
            || isset($values['presence_day_2'])
            || isset($values['presence_day_3'])
            || isset($values['presence_day_4'])
            || isset($values['presence_day_5'])
        ) {
            foreach($self as $enrollment) {
                $status = $values['status'] ?? $enrollment['status'];

                if(in_array($status, ['pending', 'confirmed'])) {
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

                            $day_pending_confirmed_enrollments_qty = 0;

                            foreach($camp['enrollments_ids'] as $en) {
                                if($en['presence_day_'.$day] && in_array($en['status'], ['pending', 'confirmed']) && $en['id'] !== $enrollment['id']) {
                                    $day_pending_confirmed_enrollments_qty++;
                                }
                            }

                            if($day_pending_confirmed_enrollments_qty >= $camp['max_children']) {
                                if($day === 1){
                                    return ['camp_id' => ['day_1_full' => "The 1st day of the camp is full."]];
                                }
                                elseif($day === 2){
                                    return ['camp_id' => ['day_2_full' => "The 2nd day of the camp is full."]];
                                }
                                elseif($day === 3){
                                    return ['camp_id' => ['day_3_full' => "The 3rd day of the camp is full."]];
                                }
                                elseif($day === 4){
                                    return ['camp_id' => ['day_4_full' => "The 4th day of the camp is full."]];
                                }
                                else{
                                    return ['camp_id' => ['day_5_full' => "The 5th day of the camp is full."]];
                                }
                            }
                        }
                    }
                    else {
                        $pending_confirmed_enrollments_qty = 0;

                        foreach($camp['enrollments_ids'] as $en) {
                            if(in_array($en['status'], ['pending', 'confirmed']) && $en['id'] !== $enrollment['id']) {
                                $pending_confirmed_enrollments_qty++;
                            }
                        }
                        if($pending_confirmed_enrollments_qty >= $camp['max_children']) {
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
                        && in_array($en['status'], ['pending', 'confirmed'])
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
        $self->do('refresh-camp-product-line');
        $self->do('reset-camp-enrollments-qty');
    }

    public static function onupdateFamilyQuotient($self) {
        $self->do('refresh-camp-product-line');
    }

    public static function onupdatePresentDay($self) {
        $self->do('refresh-camp-product-line');
    }

    public static function onupdateCampClass($self) {
        $self->do('refresh-camp-product-line');
    }

    public static function onupdate($self, $values) {
        if(isset($values['status']) || (isset($values['state']) && $values['state'] === 'instance')) {
            $self->do('reset-camp-enrollments-qty');
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
            'is_clsh', 'camp_id', 'child_id', 'date_from', 'date_to',
            'presence_day_1', 'presence_day_2', 'presence_day_3', 'presence_day_4', 'presence_day_5',
            'daycare_day_1', 'daycare_day_2', 'daycare_day_3', 'daycare_day_4', 'daycare_day_5'
        ]);
        foreach($self as $enrollment) {
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
        file_put_contents(QN_LOG_STORAGE_DIR.'/tmp.log', 'doRefreshCampProductLine'.PHP_EOL, FILE_APPEND | LOCK_EX);
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
                'product_id' => [
                    'prices_ids' => [
                        'camp_class',
                        'family_quotient_min',
                        'family_quotient_max',
                        'price_list_id' => ['date_from', 'date_to']
                    ]
                ],
                'day_product_id' => [
                    'prices_ids' => [
                        'camp_class',
                        'family_quotient_min',
                        'family_quotient_max',
                        'price_list_id' => ['date_from', 'date_to']
                    ]
                ],
            ]
        ]);
        foreach($self as $id => $enrollment) {
            if(is_null($enrollment['camp_class']) || is_null($enrollment['child_id'])) {
                continue;
            }

            if($enrollment['is_clsh']) {
                $present_days = [
                    $enrollment['presence_day_1'],
                    $enrollment['presence_day_2'],
                    $enrollment['presence_day_3'],
                    $enrollment['presence_day_4'],
                    $enrollment['presence_day_5']
                ];

                $is_present_whole_camp = false;
                if($enrollment['clsh_type'] === '4-days' && $present_days[0] && $present_days[1] && $present_days[2] && $present_days[3]) {
                    $is_present_whole_camp = true;
                }
                elseif($enrollment['clsh_type'] === '5-days' && $present_days[0] && $present_days[1] && $present_days[2] && $present_days[3] && $present_days[4]) {
                    $is_present_whole_camp = true;
                }

                $product = $enrollment['camp_id']['product_id'];
                $qty = 1;
                if(!$is_present_whole_camp) {
                    $product = $enrollment['camp_id']['day_product_id'];

                    $qty = 0;
                    foreach($present_days as $present_day) {
                        if($present_day) {
                            $qty++;
                        }
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
                    foreach($product['prices_ids'] as $price) {
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
                    $camp_product_line = EnrollmentLine::search([
                        ['product_id', '=', $product['id']],
                        ['enrollment_id', '=', $id]
                    ])
                        ->read(['id'])
                        ->first();

                    if(is_null($camp_product_line)) {
                        EnrollmentLine::create([
                            'enrollment_id' => $id,
                            'product_id'    => $product['id'],
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
                $camp_classes = ['other'];
                if($enrollment['camp_class'] === 'close-member') {
                    $camp_classes = ['close-member', 'member', 'other'];
                }
                elseif($enrollment['camp_class'] === 'member') {
                    $camp_classes = ['member', 'other'];
                }

                $camp_price = null;
                foreach($camp_classes as $camp_class) {
                    foreach($enrollment['camp_id']['product_id']['prices_ids'] as $price) {
                        if(
                            $camp_class === $price['camp_class']
                            && $enrollment['camp_id']['date_from'] >= $price['price_list_id']['date_from']
                            && $enrollment['camp_id']['date_from'] <= $price['price_list_id']['date_to']
                        ) {
                            $camp_price = $price;
                            break;
                        }
                    }
                }

                if(!is_null($camp_price)) {
                    $camp_product_line = EnrollmentLine::search([
                        ['product_id', '=', $enrollment['camp_id']['product_id']['id']],
                        ['enrollment_id', '=', $id]
                    ])
                        ->read(['id'])
                        ->first();

                    if(is_null($camp_product_line)) {
                        EnrollmentLine::create([
                            'enrollment_id' => $id,
                            'product_id'    => $enrollment['camp_id']['product_id']['id'],
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

            'generate-presences' => [
                'description'   => "Generate the child day presences to the camp.",
                'policies'      => [],
                'function'      => 'doGeneratePresences'
            ],

            'remove-presences' => [
                'description'   => "Remove the child day presences to the camp.",
                'policies'      => [],
                'function'      => 'doRemovePresences'
            ],

            'refresh-camp-product-line' => [
                'description'   => "Creates/updates the enrollment line that concerns the product_id or day_product_id.",
                'policies'      => [],
                'function'      => 'doRefreshCampProductLine'
            ]

        ];
    }

    public static function ondelete($self): void {
        $self->do('delete-lines');
        $self->do('reset-camp-enrollments-qty');

        parent::ondelete($self);
    }
}
