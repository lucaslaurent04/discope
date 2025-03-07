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
                'type'              => 'alias',
                'alias'             => 'bankCheck_number'
            ],

            'bankCheck_number' => [
                'type'              => 'string',
                'description'       => 'The official unique number assigned to the bank check by the issuing bank.',
                'required'          => true
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'The monetary value of the bank check.'
            ],

            'emission_date' => [
                'type'              => 'date',
                'description'       => "The date when the bank check was issued.",
                'default'           => time()
            ],

            'has_signature' => [
                'type'              => 'boolean',
                'description'       => "The bank check has the signature validated",
                'default'           => true,
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'registered',
                    'in_process',
                    'paid',
                    'rejected'
                ],
                'description'       => 'The current processing status of the bank check.',
                'default'           => 'registered'
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'description'       => 'The funding associated with the bank check, if applicable.'
            ],

            'bankCheck_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "'A detailed description or note related to this bank check."
            ]
        ];
    }

    public static function getWorkflow() {
        return [
            'registered' => [
                'description' => 'The bank check has been enregistred and waiting to been tranfert to the bank.',
                'icon' => 'edit',
                'transitions' => [
                    'process' => [
                        'description' => 'Sets the bank check as ready for transfering.',
                        'status' => 'in_process',
                    ],
                ],
            ],
            'in_process' => [
                'description' => 'The bank check has been tranfering for the banck and it is waitong for the validation.',
                'icon' => 'pending',
                'transitions' => [
                    'reject' => [
                        'description' => 'The back check has been rejeted for the bank.',
                        'status' => 'rejected',
                    ],
                    'pay' => [
                        'description' => 'The back check has been  paid for the banck and a transfer was been created in the banck stament line.',
                        'status' => 'paid',
                    ],
                ],
            ],
            'rejected' => [
                'description' => 'The bank check as been paid for the rejected, it must to conect to the client.',
                'transitions' => [],
            ],
            'paid' => [
                'description' => 'The bank check as been paid for the paid, it can be payment to the funding.',
                'transitions' => [],
            ],
        ];
    }

    public static function canupdate($om, $oids, $values, $lang) {

        return self::validateAmount($values, function() use ($om, $oids, $values, $lang) {
            return parent::canupdate($om, $oids, $values, $lang);
        });
    }

    public static function cancreate($om, $values, $lang) {
        if (isset($values['funding_id'])) {
            $funding = Funding::id($values['funding_id'])->read(['is_paid'])->first(true);

            if ($funding['is_paid']) {
                return ['funding_id' => ['non_editable' => 'Modifications are not allowed once the funding has been fully paid.']];
            }
        }

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
