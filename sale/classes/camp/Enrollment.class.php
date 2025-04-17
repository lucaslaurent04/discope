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
                    'confirm' => [
                        'status'        => 'confirmed',
                        'description'   => "Confirm the enrollment because another was cancelled."
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
                'enrollments_ids' => ['status', 'child_id']
            ]
        ]);

        foreach($self as $enrollment) {
            $status = $values['status'] ?? $enrollment['status'];
            if($status === 'pending') {
                $not_canceled_enrollments_qty = 0;
                foreach($enrollment['camp_id']['enrollments_ids'] as $en) {
                    if($en['status'] !== 'canceled') {
                        $not_canceled_enrollments_qty++;
                    }
                }

                if($not_canceled_enrollments_qty >= $enrollment['camp_id']['max_children']) {
                    return ['camp_id' => ['full' => "The camp is full."]];
                }
            }

            foreach($enrollment['camp_id']['enrollments_ids'] as $en) {
                if($en['child_id'] === $values['child_id']) {
                    return ['child_id' => ['already_enrolled' => "The child has already enrolled to this camp."]];
                }
            }
        }

        return parent::cancreate($self, $values);
    }
}
