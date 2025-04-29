<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Camp extends Model {

    public static function getDescription(): string {
        return "Activity camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the camp.",
                'required'          => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'published',
                    'canceled'
                ],
                'description'       => "Status of the camp.",
                'default'           => 'draft'
            ],

            'remarks' => [
                'type'              => 'string',
                'description'       => "Description of the camp.",
                'usage'             => 'text/plain'
            ],

            'public_description' => [
                'type'              => 'string',
                'description'       => "Public description of the camp.",
                'usage'             => 'text/plain'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "When the camp starts.",
                'required'          => true,
                'dependents'        => ['enrollments_ids' => ['date_from']]
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "When the camp ends.",
                'required'          => true,
                'dependents'        => ['enrollments_ids' => ['date_to']]
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => 'The product targeted by the line.',
                'required'          => true,
                'domain'            => ['is_camp', '=', true]
            ],

            'camp_type' => [
                'type'              => 'string',
                'selection'         => [
                    'sport',
                    'circus',
                    'culture',
                    'environment',
                    'horse-riding'
                ],
                'description'       => "Type of camp.",
                'default'           => 'sport'
            ],

            'is_clsh' => [
                'type'              => 'boolean',
                'description'       => "Is \"Centre loisir sans hébergement\"",
                'default'           => false
            ],

            'camp_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\CampModel',
                'description'       => "Model that was used as a base to create this camp.",
                'onupdate'          => 'onupdateCampModelId',
                'required'          => true
            ],

            'min_age' => [
                'type'              => 'integer',
                'description'       => "Minimal age of the participants.",
                'required'          => true
            ],

            'max_age' => [
                'type'              => 'integer',
                'description'       => "Maximal age of the participants.",
                'required'          => true
            ],

            'employee_ratio' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'usage'             => 'number/integer{1,50}',
                'description'       => "The quantity of children one employee can handle alone.",
                'store'             => true,
                'function'          => 'calcDefaultEmployeeRatio',
                'dependents'        => ['max_children'],
                'onupdate'          => 'onupdateEmployeeRatio'
            ],

            'max_children' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Max quantity of children that can take part to the camp.",
                'store'             => true,
                'function'          => 'calcMaxChildren'
            ],

            'camp_group_qty' => [
                'type'              => 'integer',
                'description'       => "The quantity of camp groups.",
                'default'           => 1,
                'dependents'        => ['max_children']
            ],

            'ase_quota' => [
                'type'              => 'integer',
                'description'       => "Max quantity of children, using financial help \"Aide sociale à l'enfance\", that can take part to the camp.",
                'default'           => 4
            ],

            'accounting_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Specific accounting code for the camp.",
                'unique'            => true,
                'store'             => true,
                'function'          => 'calcAccountingCode'
            ],

            'need_license_ffe' => [
                'type'              => 'boolean',
                'description'       => "Does the camp requires to child to have a 'licence fédération française équitation'.",
                'default'           => false
            ],

            'camp_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\CampGroup',
                'foreign_field'     => 'camp_id',
                'description'       => "The groups of children of the camp.",
                'ondetach'          => 'delete'
            ],

            'required_skills_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Skill',
                'foreign_field'     => 'camps_ids',
                'rel_table'         => 'sale_camp_rel_camp_skill',
                'rel_foreign_key'   => 'skill_id',
                'rel_local_key'     => 'camp_id',
                'description'       => "Skills needed to participate to the camp."
            ],

            'required_documents_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Document',
                'foreign_field'     => 'camps_ids',
                'rel_table'         => 'sale_camp_rel_camp_document',
                'rel_foreign_key'   => 'document_id',
                'rel_local_key'     => 'camp_id',
                'description'       => "Documents needed to participate to the camp."
            ],

            'employees_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'camps_ids',
                'rel_table'         => 'sale_hr_rel_camp_employee',
                'rel_foreign_key'   => 'employee_id',
                'rel_local_key'     => 'camp_id',
                'description'       => "Employees that will take care of the children during the camp."
            ],

            'enrollments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Enrollment',
                'foreign_field'     => 'camp_id',
                'description'       => "All the enrollments linked to camp.",
                'ondetach'          => 'delete'
            ]

        ];
    }

    public static function getWorkflow(): array {
        return [

            'draft' => [
                'description' => "The camp is still being configured.",
                'transitions' => [
                    'publish' => [
                        'status'        => 'published',
                        'description'   => "Publish the camp on the website."
                    ],
                    'cancel' => [
                        'status'        => 'canceled',
                        'description'   => "Cancel the camp."
                    ]
                ]
            ],

            'published' => [
                'description' => "The camp is configured and published on the website.",
                'transitions' => [
                    'cancel' => [
                        'status'        => 'canceled',
                        'description'   => "Cancel the camp."
                    ]
                ]
            ],

            'canceled' => [
                'description' => "The camp was canceled.",
            ]

        ];
    }

    public static function calcDefaultEmployeeRatio($self): array {
        $result = [];
        $self->read(['camp_model_id' => ['default_employee_ratio']]);
        foreach($self as $id => $camp) {
            $result[$id] = $camp['camp_model_id']['default_employee_ratio'] ?? 12;
        }

        return $result;
    }

    public static function calcMaxChildren($self): array {
        $result = [];
        $self->read(['employee_ratio', 'camp_group_qty']);
        foreach($self as $id => $camp) {
            $result[$id] = $camp['employee_ratio'] * $camp['camp_group_qty'];
        }

        return $result;
    }

    public static function calcAccountingCode($self): array {
        $result = [];
        $last_accounting_code = self::search([], ['sort' => ['created' => 'desc']])
            ->read(['accounting_code'])
            ->first();

        $code = 0;
        if(!is_null($last_accounting_code)) {
            $code_array = explode('C', $last_accounting_code['accounting_code']);
            if(isset($code_array[1])) {
                $code = (int) $code_array[1];
            }
        }

        foreach($self as $id => $camp) {
            $result[$id] = '411C'.str_pad(++$code, 4, '0', STR_PAD_LEFT);
        }

        return $result;
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['camp_model_id'])) {
            $camp_model = CampModel::id($event['camp_model_id'])
                ->read([
                    'name',
                    'camp_type',
                    'employee_ratio',
                    'need_license_ffe',
                    'ase_quota',
                    'product_id' => ['id', 'name']
                ])
                ->first(true);

            if(!is_null($camp_model)) {
                $result['camp_type'] = $camp_model['camp_type'];
                $result['employee_ratio'] = $camp_model['employee_ratio'];
                $result['product_id'] = $camp_model['product_id'];
                $result['need_license_ffe'] = $camp_model['need_license_ffe'];
                $result['ase_quota'] = $camp_model['ase_quota'];

                if(empty($values['name'])) {
                    $result['name'] = $camp_model['name'];
                }
            }
        }
        if(isset($event['date_from'])) {
            $date_from = date('Y-m-d', $event['date_from']);
            $result['date_to'] = strtotime($date_from.' +5 days');
        }

        return $result;
    }

    /**
     * Sync camp model required skills and documents with the camp.
     */
    public static function onupdateCampModelId($self) {
        $self->read(['camp_model_id' => ['required_skills_ids', 'required_documents_ids']]);
        foreach($self as $id => $camp) {
            if(!is_null($camp['camp_model_id'])) {
                self::id($id)->update([
                    'required_skills_ids'       => $camp['camp_model_id']['required_skills_ids'],
                    'required_documents_ids'    => $camp['camp_model_id']['required_documents_ids']
                ]);
            }
        }
    }

    public static function onupdateEmployeeRatio($self) {
        $self->read(['camp_groups_ids']);
        foreach($self as $camp) {
            CampGroup::ids($camp['camp_groups_ids'])
                ->update(['max_children' => null]);
        }
    }

    /**
     * Creates first camp group that is necessary.
     */
    public static function onupdate($self, $values) {
        $self->read(['camp_groups_ids']);
        foreach($self as $id => $camp) {
            if(count($camp['camp_groups_ids']) > 0) {
                continue;
            }

            CampGroup::create([
                'camp_id'      => $id,
                'max_children' => $camp['employee_ratio']
            ]);
        }
    }

    public static function canupdate($self, $values): array {
        $self->read([
            'camp_groups_ids',
            'camp_group_qty',
            'enrollments_ids' => ['status']
        ]);

        // Checks that modification of camp groups still allows enough enrollments
        if(isset($values['camp_groups_ids'])) {
            foreach($self as $camp) {
                $enrolled_children_qty = 0;
                foreach($camp['enrollments_ids'] as $enrollment) {
                    if(in_array($enrollment['status'], ['pending', 'confirmed'])) {
                        $enrolled_children_qty++;
                    }
                }

                $values_camp_groups_ids = array_map(
                    function($id) {
                        return (int) $id;
                    },
                    $values['camp_groups_ids']
                );

                $to_add_camp_groups_ids = [];
                $to_remove_camp_groups_ids = [];
                foreach($values_camp_groups_ids as $id) {
                    if($id > 0) {
                        $to_add_camp_groups_ids[] = $id;
                    }
                    else {
                        $to_remove_camp_groups_ids[] = $id * -1;
                    }
                }

                $final_camp_group_ids = [];
                foreach(array_merge($camp['camp_groups_ids'], $to_add_camp_groups_ids) as $id) {
                    if(!in_array($id, $to_remove_camp_groups_ids)) {
                        $final_camp_group_ids[] = $id;
                    }
                }
                $final_camp_group_ids = array_unique($final_camp_group_ids);
                if(empty($final_camp_group_ids)) {
                    return [
                        'camp_groups_ids' => [
                            'one_needed' => "A camp should have at least one camp group."
                        ]
                    ];
                }

                if($enrolled_children_qty === 0) {
                    continue;
                }

                $groups = CampGroup::ids($final_camp_group_ids)
                    ->read(['max_children'])
                    ->get();

                $max_children = 0;
                foreach($groups as $group) {
                    $max_children += $group['max_children'];
                }

                if($enrolled_children_qty > $max_children) {
                    return [
                        'camp_groups_ids' => [
                            'too_many_children' => "There is too many children enrolled in the camp groups."
                        ]
                    ];
                }
            }
        }

        // Checks that modification of employee ratio still allows enough enrollments
        if(isset($values['employee_ratio'])) {
            foreach($self as $camp) {
                $enrolled_children_qty = 0;
                foreach($camp['enrollments_ids'] as $enrollment) {
                    if(in_array($enrollment['status'], ['pending', 'confirmed'])) {
                        $enrolled_children_qty++;
                    }
                }

                if($enrolled_children_qty > ($camp['camp_group_qty'] * $values['employee_ratio'])) {
                    return [
                        'employee_ratio' => [
                            'too_many_children' => "There is too many children enrolled in the camp to modify the employee ratio to {$values['employee_ratio']}."
                        ]
                    ];
                }
            }
        }

        return parent::canupdate($self, $values);
    }
}
