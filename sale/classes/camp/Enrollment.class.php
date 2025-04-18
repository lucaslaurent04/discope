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

            'camp_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "The camp the child is enrolled to.",
                'required'          => true
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
            ]

        ];
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
            'camp_id' => [
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

        return parent::cancreate($self, $values);
    }
}
