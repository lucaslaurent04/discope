<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace finance\accounting;

use equal\orm\Model;

class AccountingJournal extends Model {

    public static function getName() {
        return "Accounting journal";
    }

    public static function getDescription() {
        return "An accounting journal is a list of accounting entries grouped by their nature.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Label for identifying the journal.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Verbose detail of the role of the journal.',
                'multilang'         => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code (optional).',
                'unique'            => true
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => [
                    'general_ledger',
                    'sales',
                    'sales_peppol',
                    'purchases',
                    'bank_cash',
                    'miscellaneous'
                ],
                "required"          => true,
                'description'       => "Type of journal or ledger."
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The organisation the journal belongs to.",
                'default'           => 1
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    =>  'finance\accounting\AccountingEntry',
                'foreign_field'     => 'journal_id',
                'description'       => 'Accounting entries relating to the journal.',
                'ondetach'          => 'null'
            ],

            'index' => [
                'type'              => 'integer',
                'description'       => 'Counter for payments exports.',
                'default'           => 120000
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => 'Management Group the accounting journal belongs to.',
                'onupdate'          => 'updateCenterOfficeId'
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['code', 'organisation_id' => ['name']]);
        foreach($self as $id => $journal) {
            $result[$id] = $journal['code'].' - '.$journal['organisation_id']['name'];
        }

        return $result;
    }

    public static function updateCenterOfficeId($self) {
        $self->read(['center_office_id' => ['organisation_id']]);
        foreach($self as $id => $journal) {
            AccountingJournal::id($id)->update([
                'organisation_id' => $journal['center_office_id']['organisation_id'] ?? null
            ]);
        }
    }
}
