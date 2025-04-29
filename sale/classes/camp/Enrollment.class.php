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
                'dependents'        => ['camp_class']
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
        if(isset($event['child_id'])) {
            $child = Child::id($event['child_id'])
                ->read(['camp_class', 'birthdate'])
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
        $self->read(['works_council_id', 'child_id' => ['camp_class']]);
        foreach($self as $id => $enrollment) {
            $camp_class = $enrollment['child_id']['camp_class'];
            if(!is_null($enrollment['works_council_id'])) {
                if($camp_class === 'other') {
                    $camp_class = 'member';
                }
                elseif($camp_class === 'member') {
                    $camp_class = 'close-member';
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
            'price_adapters_ids'    => ['amount']
        ]);
        foreach($self as $id => $enrollment) {
            $total = 0.0;
            foreach($enrollment['enrollment_lines_ids'] as $enrollment_line) {
                $total += $enrollment_line['total'];
            }

            foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                $total -= $price_adapter['amount'];
            }

            $result[$id] = $total;
        }

        return $result;
    }

    public static function calcPrice($self): array {
        $result = [];
        $self->read([
            'enrollment_lines_ids'  => ['price'],
            'price_adapters_ids'    => ['amount']
        ]);
        foreach($self as $id => $enrollment) {
            $price = 0.0;
            foreach($enrollment['enrollment_lines_ids'] as $enrollment_line) {
                $price += $enrollment_line['price'];
            }

            foreach($enrollment['price_adapters_ids'] as $price_adapter) {
                $price -= $price_adapter['amount'];
            }

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

    /**
     * Lock enrollment after status to confirm
     */
    public static function onafterConfirm($self) {
        $self->update(['is_locked' => true]);
    }

    public static function onafterCancel($self) {
        $self->update(['cancellation_date' => time()]);
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
                        'policies'      => ['remove-from-waitlist']
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
            'is_locked',
            'status',
            'child_id',
            'enrollment_lines_ids' => [
                'id'
            ],
            'camp_id' => [
                'id',
                'max_children',
                'ase_quota',
                'enrollments_ids' => [
                    'status',
                    'child_id',
                    'is_ase'
                ]
            ]
        ]);

        // If is_locked cannot be modified
        foreach($self as $enrollment) {
            if($enrollment['is_locked']) {
                return ['is_locked' => ['locked_enrollment' => "Cannot modify a locked enrollment."]];
            }
        }

        // Check that camp is not already full and that child is not already enrolled
        if(isset($values['camp_id']) || (isset($values['status']) && in_array($values['status'], ['pending', 'confirmed']))) {
            foreach($self as $enrollment) {
                $status = $values['status'] ?? $enrollment['status'];

                $camp = Camp::id($values['camp_id'] ?? $enrollment['camp_id']['id'])
                    ->read([
                        'max_children',
                        'enrollments_ids' => [
                            'status',
                        ]
                    ])
                    ->first();

                if(in_array($status, ['pending', 'confirmed'])) {
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

        // Check that child not already enrolled to camp
        if(isset($values['child_id'])) {
            foreach($self as $enrollment) {
                $camp = Camp::id($values['camp_id'] ?? $enrollment['camp_id']['id'])
                    ->read([
                        'enrollments_ids' => [
                            'child_id',
                        ]
                    ])
                    ->first();

                foreach($camp['enrollments_ids'] as $en) {
                    if($en['child_id'] === $values['child_id'] && $en['id'] !== $enrollment['id']) {
                        return ['child_id' => ['already_enrolled' => "The child has already enrolled to this camp."]];
                    }
                }
            }
        }

        // Check max quot ase
        if(isset($values['is_ase']) && $values['is_ase']) {
            foreach($self as $enrollment) {
                $ase_children_qty = 1;
                foreach($enrollment['camp_id']['enrollments_ids'] as $en) {
                    if($en['is_ase'] && $en['id'] !== $enrollment['id']) {
                        $ase_children_qty++;
                    }
                }

                if($ase_children_qty > $enrollment['camp_id']['ase_quota']) {
                    return ['is_ase' => ['too_many_ase_children' => "The ase children quota is full."]];
                }
            }
        }

        // Check prices isn't missing for child specific camp_class
        if(isset($values['camp_id']) || isset($values['child_id'])) {
            foreach($self as $enrollment) {
                $camp_id = $values['camp_id'] ?? $enrollment['camp_id']['id'];
                $camp = Camp::id($camp_id)
                    ->read([
                        'product_id' => [
                            'prices_ids' => [
                                'camp_class'
                            ]
                        ]
                    ])
                    ->first();

                $child_id = $values['child_id'] ?? $enrollment['child_id'];
                $child = Child::id($child_id)
                    ->read(['camp_class'])
                    ->first();

                $camp_class_price = null;
                foreach($camp['product_id']['prices_ids'] as $price) {
                    if($child['camp_class'] === $price['camp_class']) {
                        $camp_class_price = $price;
                    }
                }

                if(is_null($camp_class_price)) {
                    return ['child_id' => ['camp_class_price_missing' => "The price for the child camp class is missing."]];
                }
            }
        }

        // Check that child is not already enrolled to another camp at the same time
        if(isset($values['camp_id']) || isset($values['child_id'])) {
            foreach($self as $enrollment) {
                $camp_id = $values['camp_id'] ?? $enrollment['camp_id']['id'];
                $child_id = $values['child_id'] ?? $enrollment['child_id'];

                $camp = Camp::id($camp_id)
                    ->read(['date_from', 'date_to'])
                    ->first();

                $child = Child::id($child_id)
                    ->read(['enrollments_ids' => ['camp_id' => ['date_from', 'date_to']]])
                    ->first();

                foreach($child['enrollments_ids'] as $en) {
                    if($enrollment['id'] !== $en['id'] && $camp['date_from'] <= $en['camp_id']['date_to'] && $camp['date_to'] >= $en['camp_id']['date_from']) {
                        return ['child_id' => ['child_already_enrolled_to_other_camp' => "Child has already been enrolled to another camp during this period."]];
                    }
                }
            }
        }

        return parent::cancreate($self, $values);
    }

    /**
     * Creates the first enrollment line with the camp product.
     */
    public static function onupdateCampId($self) {
        $self->read([
            'enrollment_lines_ids',
            'child_id'  => [
                'camp_class'
            ],
            'camp_id'   => [
                'product_id' => [
                    'prices_ids' => [
                        'camp_class'
                    ]
                ]
            ]
        ]);

        foreach($self as $id => $enrollment) {
            if(!isset($enrollment['camp_id']) || !isset($enrollment['child_id']) || !empty($enrollment['enrollment_lines_ids'])) {
                continue;
            }

            $camp_class_price = null;
            foreach($enrollment['camp_id']['product_id']['prices_ids'] as $price) {
                if($enrollment['child_id']['camp_class'] === $price['camp_class']) {
                    $camp_class_price = $price;
                }
            }

            EnrollmentLine::create([
                'enrollment_id' => $id,
                'product_id'    => $enrollment['camp_id']['product_id']['id'],
                'price_id'      => $camp_class_price['id'],
                'qty'           => 1
            ]);
        }
    }

    /**
     * Adapts the lines prices to the new camp_class.
     */
    public static function onupdateCampClass($self) {
        $self->read([
            'camp_class',
            'enrollment_lines_ids' => [
                'product_id',
                'price_id' => ['camp_class']
            ]
        ]);
        foreach($self as $enrollment) {
            if(is_null($enrollment['camp_class'])) {
                continue;
            }

            foreach($enrollment['enrollment_lines_ids'] as $lid => $line) {
                if(is_null($line['price_id']['camp_class']) || $line['price_id']['camp_class'] === $enrollment['camp_class']) {
                    continue;
                }

                $price = Price::search([
                    ['product_id', '=', $line['product_id']],
                    ['camp_class', '=', $enrollment['camp_class']]
                ])
                    ->read(['id'])
                    ->first();

                if(!is_null($price)) {
                    EnrollmentLine::id($lid)
                        ->update(['price_id' => $price['id']]);
                }
            }
        }
    }
}
