<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

class BankCheck extends \sale\pay\BankCheck  {

    public static function getColumns(): array {
        return [

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'The monetary value of the bank check.'
            ],


            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Funding',
                'description'       => 'The funding associated with the bank check, if applicable.'
            ],

            'booking_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcBookingId',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'The booking associated with the bank check, if applicable (computed field).',
                'instant'           => true,
                'store'             => true
            ],

            'payment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Payment',
                'description'       => 'The payment associated with the bank check.'
            ]

        ];
    }

    public static function calcBookingId($self): array {
        $result = [];
        $self->read(['funding_id' =>['id', 'booking_id']]);
        foreach($self as $id => $bankCheck) {
            if($bankCheck['funding_id']['booking_id']){
                $result[$id] = $bankCheck['funding_id']['booking_id'];
            }
        }
        return $result;
    }

    public static function onchange($event, $values): array {
        $result = parent::onchange($event, $values);

        if(isset($event['funding_id']) && strlen($event['funding_id']) > 0){
            $funding = Funding::search(['id', '=', $event['funding_id']])
                ->read(['booking_id' => ['id', 'name']])
                ->first();

            $result['booking_id'] = $funding['booking_id'];
        }

        return $result;
    }
}
