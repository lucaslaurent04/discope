<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
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

            'child_health_remarks' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Remarks about the child's health at the time of the enrollment."
            ],

            'camp_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "The camp the child is enrolled to.",
                'required'          => true,
                'onupdate'          => 'onupdateCampId'
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
            ]

        ];
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

    public static function policyRemoveFromWaitlist($self) {
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
                return ['camp_id' => ['full' => "The camp is full."]];
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

    public static function getWorkflow(): array {
        return [

            'pending' => [
                'description' => "The enrollment is waiting for confirmation, the entered child/parent data and provided documents need to be checked.",
                'transitions' => [
                    'confirm' => [
                        'status'        => 'confirmed',
                        'description'   => "Confirm the pending enrollment."
                    ],
                    'cancel' => [
                        'status'        => 'canceled',
                        'description'   => "Cancel the pending enrollment."
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
                        'description'   => "Cancel the waiting enrollment."
                    ]
                ]
            ],

            'confirmed' => [
                'description' => "The enrollment is confirmed, the child can attend the camp.",
                'transitions' => [
                    'cancel' => [
                        'status'        => 'canceled',
                        'description'   => "Cancel the confirmed enrollment."
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
            'child_id',
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

        foreach($self as $enrollment) {
            if($enrollment['is_locked']) {
                return ['is_locked' => ['locked_enrollment' => "Cannot modify a locked enrollment."]];
            }

            $status = $values['status'] ?? $enrollment['status'];
            if($status === 'pending') {
                $pending_confirmed_enrollments_qty = 0;
                foreach($enrollment['camp_id']['enrollments_ids'] as $en) {
                    if(in_array($en['status'], ['pending', 'confirmed'])) {
                        $pending_confirmed_enrollments_qty++;
                    }
                }

                if($pending_confirmed_enrollments_qty >= $enrollment['camp_id']['max_children']) {
                    return ['camp_id' => ['full' => "The camp is full."]];
                }
            }

            foreach($enrollment['camp_id']['enrollments_ids'] as $en) {
                if($en['child_id'] === $values['child_id']) {
                    return ['child_id' => ['already_enrolled' => "The child has already enrolled to this camp."]];
                }
            }
        }

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

        return parent::cancreate($self, $values);
    }

    public static function onupdateCampId($self) {
        $self->read([
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
            if(!isset($enrollment['camp_id']) || !isset($enrollment['child_id'])) {
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
}
