<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pay;
use equal\orm\Model;

class BankCheck extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true
            ],

            'bank_check_number' => [
                'type'              => 'string',
                'description'       => 'The official unique number assigned to the bank check by the issuing bank.',
                'dependents'        => ['name']
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'The monetary value of the bank check.',
                'dependents'        => ['name']
            ],

            'emission_date' => [
                'type'              => 'date',
                'description'       => "The date when the bank check was issued.",
                'default'           => function () { return time(); },
                'dependents'        => ['name']
            ],

            'has_signature' => [
                'type'              => 'boolean',
                'description'       => "The bank check has the signature",
                'default'           => false
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'paid',
                    'rejected'
                ],
                'description'       => 'The current processing status of the bank check.',
                'default'           => 'pending'
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'description'       => 'The funding associated with the bank check, if applicable.'
            ],

            'bank_check_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "'A detailed description or note related to this bank check."
            ],

            'deposit_number' => [
                'type'        => 'string',
                'description' => 'The official deposit number provided by the bank, used to track all associated checks.',
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'The bank check has been registered and is waiting to be transferred to the bank.',
                'transitions' => [
                    'reject' => [
                        'description' => 'The bank check has been rejected by the bank.',
                        'status' => 'rejected',
                    ],
                    'pay' => [
                        'description' => 'The bank check has been marked as paid, and a transfer has been created in the bank statement.',
                        'status' => 'paid',
                    ],
                ],
            ],
            'paid' => [
                'description' => 'The bank check has been marked as paid. It can now be linked to the funding.',
                'transitions' => [
                    'pending' => [
                        'description' => 'The payment has been removed and funding has been updated.',
                        'status' => 'pending',
                    ],
                    'reject' => [
                        'description' => 'The bank check has been rejected by the bank.',
                        'status' => 'rejected',
                    ],
                ],
            ],
            'rejected' => [
                'description' => 'The bank check has been rejected. The client must be contacted.',
                'transitions' => [],
            ],
        ];
    }


    public static function calcName($self) {
        $result = [];
        $self->read(['bank_check_number', 'amount', 'emission_date']);
        foreach($self as $id => $bankCheck) {
            $result[$id] = $bankCheck['emission_date'] . ' ' . $bankCheck['bank_check_number'] . '(' . $bankCheck['amount'] .')';
        }
        return $result;
    }

    public static function canupdate($om, $oids, $values, $lang) {

        return self::validateAmount($values, function() use ($om, $oids, $values, $lang) {
            return parent::canupdate($om, $oids, $values, $lang);
        });
    }

    public static function cancreate($om, $values, $lang) {
        return self::validateAmount($values, function() use ($om, $values, $lang) {
            return parent::cancreate($om, $values, $lang);
        });
    }


    private static function validateAmount($values, $callback) {

        if ($values['amount'] < 0) {
            return ['amount' => ['non_editable' => 'The amount of the bank check cannot be negative.']];
        }

        return $callback();
    }


}
