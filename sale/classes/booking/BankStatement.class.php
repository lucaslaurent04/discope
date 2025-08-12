<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

class BankStatement extends \sale\pay\BankStatement {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'generation'        => 'generateName'
            ],

            'statement_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BankStatementLine',
                'foreign_field'     => 'bank_statement_id',
                'description'       => 'The lines that are assigned to the statement.',
                'ondetach'          => 'null'
            ]

        ];
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];
        $statements = $om->read(get_called_class(), $oids, ['center_office_id.name', 'date', 'old_balance', 'new_balance']);
        foreach($statements as $oid => $statement) {
            $result[$oid] = sprintf("%s - %s - %s - %s", $statement['center_office_id.name'], date('Ymd', $statement['date']), $statement['old_balance'], $statement['new_balance']);
        }
        return $result;
    }

    public static function generateName() {
        return null;
    }
}
