<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace documents;

class Export extends Document {

    /** @deprecated */
    public function getTable() {
        return self::getModelTable();
    }

    public static function getModelTable() {
        return 'lodging_documents_export';
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => 'Office the invoice relates to (for center management).',
            ],

            'export_type' => [
                'type'              => 'string',
                'selection'         => [
                    'invoices',
                    'invoices_peppol',
                    'payments'
                ],
                'required'          => true,
                'readonly'          => true
            ],

            'is_exported' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark the archive as already exported.'
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Namespace of the concerned entity.'
            ],

            'object_ids' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => 'Ids of the associated entities.'
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['created', 'export_type', 'center_office_id' => ['name']]);
        foreach($self as $id => $export) {
            $result[$id] = sprintf(
                '%s - %s - %s',
                date('Ymd', $export['created']),
                $export['export_type'],
                $export['center_office_id']['name']
            );
        }

        return $result;
    }

    public static function policyReversible($self): array {
        $result = [];
        $self->read(['object_class']);
        foreach($self as $export) {
            if(empty($export['object_class'])) {
                return ['object_class' => ['not_configured' => "The object class must be configured to reverse the export."]];
            }
        }

        return $result;
    }

    public static function getPolicies(): array {
        return [

            'reversible' => [
                'description'   => "Checks if the export can be reversed.",
                'function'      => 'policyReversible'
            ]

        ];
    }

    protected static function doReverse($self) {
        $self->read(['object_class', 'object_ids']);
        foreach($self as $export) {
            $ids = json_decode($export['object_ids'], true);
            if(!empty($ids)) {
                $export['object_class']::ids($ids)->update(['is_exported' => false]);
            }
        }
    }

    public static function getActions(): array {
        return [

            'reverse' => [
                'description'   => "Before deletion, reverse the export, set 'is_exported' false for concerned entities.",
                'policies'      => [],
                'function'      => 'doReverse'
            ]

        ];
    }
}
