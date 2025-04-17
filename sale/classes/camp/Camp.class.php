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
                    'pending',
                    'canceled'
                ],
                'description'       => "Status of the camp.",
                'default'           => 'pending'
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
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "When the camp ends.",
                'required'          => true
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
                    'week',
                    'weekend'
                ],
                'description'       => "Type of camp.",
                'default'           => 'week'
            ],

            'camp_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\CampModel',
                'description'       => "Model that was used as a base to create this camp.",
                'onupdate'          => 'onupdateCampModelId'
            ],

            'with_accommodation' => [
                'type'              => 'boolean',
                'description'       => "Does the camp include accommodation?",
                'default'           => false
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
                'default'           => 12
            ],

            'max_children' => [
                'type'              => 'integer',
                'description'       => "Max quantity of children that can take part to the camp.",
                'default'           => 20
            ],

            'ase_quota' => [
                'type'              => 'integer',
                'description'       => "Max quantity of children, using financial help \"Aide sociale à l'enfance\", that can take part to the camp.",
                'default'           => 0
            ],

            'need_license_ffe' => [
                'type'              => 'boolean',
                'description'       => "Does the camp requires to child to have a 'licence fédération française équitation'.",
                'default'           => false
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
                'rel_local_key'     => 'camp_id'
            ],

            'enrollments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Enrollment',
                'foreign_field'     => 'camp_id',
                'description'       => "All the enrollments linked to camp."
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

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['camp_model_id'])) {
            $camp_model = CampModel::id($event['camp_model_id'])
                ->read([
                    'name',
                    'camp_type',
                    'with_accommodation',
                    'employee_ratio',
                    'need_license_ffe',
                    'product_id' => ['id', 'name']
                ])
                ->first(true);

            if(!is_null($camp_model)) {
                $result['camp_type'] = $camp_model['camp_type'];
                $result['with_accommodation'] = $camp_model['with_accommodation'];
                $result['employee_ratio'] = $camp_model['employee_ratio'];
                $result['product_id'] = $camp_model['product_id'];
                $result['need_license_ffe'] = $camp_model['need_license_ffe'];

                if(empty($values['name'])) {
                    $result['name'] = $camp_model['name'];
                }
            }
        }
        if(isset($event['date_from'])) {
            if(isset($values['camp_type'])) {
                $date_from = date('Y-m-d', $event['date_from']);
                if($values['camp_type'] === 'week') {
                    $result['date_to'] = strtotime($date_from.' +6 days');
                }
                if($values['camp_type'] === 'weekend') {
                    $result['date_to'] = strtotime($date_from.' +1 days');
                }
            }
        }

        return $result;
    }

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
}
