<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

class BankCheck extends \sale\pay\BankCheck  {

    public static function getColumns(): array {
        return [

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "The monetary value of the bank check."
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Funding',
                'description'       => "The funding associated with the bank check, if applicable."
            ],

            'enrollment_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcEnrollmentId',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => "The enrollment associated with the bank check, if applicable (computed field).",
                'instant'           => true,
                'store'             => true
            ],

            'payment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Payment',
                'description'       => "The payment associated with the bank check."
            ]

        ];
    }

    public static function calcEnrollmentId($self): array {
        $result = [];
        $self->read(['funding_id' => ['enrollment_id']]);
        foreach($self as $id => $bankCheck) {
            if(isset($bankCheck['funding_id']['enrollment_id'])) {
                $result[$id] = $bankCheck['funding_id']['enrollment_id'];
            }
        }
        return $result;
    }

    public static function onchange($event, $values): array {
        $result = parent::onchange($event, $values);

        if(isset($event['funding_id']) && strlen($event['funding_id']) > 0) {
            $funding = Funding::id($event['funding_id'])
                ->read(['enrollment_id' => ['name']])
                ->first();

            $result['enrollment_id'] = $funding['enrollment_id'];
        }

        return $result;
    }
}
