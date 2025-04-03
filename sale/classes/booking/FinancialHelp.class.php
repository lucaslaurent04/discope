<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use equal\orm\Model;

class FinancialHelp extends Model {

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the organization that will take care of the payment.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "More information about the financial help and the organisation who's responsible for it."
            ],

            'status' => [
                'type'              => 'string',
                'description'       => "The current status of the financial help.",
                'selection'         => [
                    'pending',
                    'invoiced'
                ],
                'default'           => 'pending'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Date at which the financial help starts to be available.",
                'default'           => function() {
                    return strtotime('first day of january this year');
                }
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Date until which the financial help remains available.",
                'required'          => true,
                'default'           => function() {
                    return strtotime('last day of december this year');
                }
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The total amount of the financial help.",
                'required'          => true,
                'min'               => 0,
                'dependencies'      => ['remaining_amount']
            ],

            'remaining_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The remaining amount of the financial help.",
                'store'             => true,
                'function'          => 'calcRemainingAmount'
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Payment',
                'foreign_field'     => 'financial_help_id',
                'description'       => "The payments that are based on this financial help."
            ]

        ];
    }

    public static function getConstraints(): array {
        return [

            'amount' =>  [
                'too_low' => [
                    'message'       => "Amount must be greater than 0.",
                    'function'      => function($amount) {
                        return $amount > 0;
                    }
                ]
            ]

        ];
    }

    public static function calcRemainingAmount($self): array {
        $result = [];
        $self->read(['amount', 'payments_ids' => ['amount']]);

        foreach($self as $id => $financial_help) {
            $remaining_amount = $financial_help['amount'];
            foreach($financial_help['payments_ids'] as $payment) {
                $remaining_amount -= $payment['amount'];
            }

            $result[$id] = $remaining_amount;
        }

        return $result;
    }

    public static function getWorkflow(): array {
        return [

            'pending' => [
                'description' => "The financial help is pending and can be used to pay for part of customers booking invoices.",
                'transitions' => [
                    'invoice' => [
                        'status'        => 'invoiced',
                        'description'   => "Mark the financial help as payments \"invoiced\" to the helper."
                    ]
                ]
            ],

            'invoiced' => [
                'description' => "The financial help payments where invoiced to the helper."
            ]

        ];
    }
}
