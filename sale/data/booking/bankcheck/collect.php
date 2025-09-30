<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;

[$params, $providers] = eQual::announce([
    'description'   => "Advanced search for BankChecks: returns a collection of BankChecks according to extra parameters.",
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'sale\booking\BankCheck'
        ],

        'bank_check_number' => [
            'type'              => 'string',
            'description'       => 'The official unique number assigned to the bank check by the issuing bank.'
        ],

        'deposit_number' => [
            'type'              => 'string',
            'description'       => 'The official deposit number provided by the bank, used to track all associated checks.',
        ],

        'is_voucher' => [
            'type'              => 'string',
            'description'       => "The check is the voucher?",
            'selection'         => ['all', 'yes', 'no'],
            'default'           => 'all'
        ],

        'booking_id'        => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\Booking',
            'description'       => 'The booking associated with the bank check, if applicable.'
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context' ]
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$domain = [];

if(!empty($params['bank_check_number'])) {
    $domain[] = ['bank_check_number', '=', $params['bank_check_number']];
}

if(!empty($params['deposit_number'])) {
    $domain[] = ['deposit_number', '=', $params['deposit_number']];
}

if($params['is_voucher'] !== 'all') {
    $domain[] = ['is_voucher', '=', $params['is_voucher'] === 'yes'];
}

if(isset($params['booking_id']) && $params['booking_id'] > 0) {
    $domain[] = ['booking_id', '=', $params['booking_id']];
}

$params['domain'] = (new Domain($params['domain']))
    ->merge(new Domain($domain))
    ->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
