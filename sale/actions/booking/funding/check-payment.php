<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Funding;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => "Checks that a given funding has been paid (should be scheduled on due_date).",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the funding to check.",
            'required'      => true
        ]

    ],
    'access'        => [
        'visibility'    => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
['context' => $context, 'auth' => $auth, 'dispatch' => $dispatch] = $providers;

// switch to root account (access is 'private')
$user_id = $auth->userId();
$auth->su();

$funding = Funding::id($params['id'])
    ->read([
        'is_paid',
        'due_amount',
        'due_date',
        'booking_id'    => ['center_office_id' => ['code']],
        'enrollment_id' => ['center_office_id' => ['code']]
    ])
    ->first();

if(is_null($funding)) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$funding['is_paid'] && $funding['due_amount'] > 0) {
    if(isset($funding['booking_id'])) {
        // dispatch a message for notifying users
        $dispatch->dispatch('lodging.booking.payments', 'sale\booking\Booking', $funding['booking_id']['id'], 'warning', null, [], [], null, $funding['booking_id']['center_office_id']['id']);

        $payment_remind = Setting::get_value('sale', 'features', 'payment.remind.active.' . $funding['booking_id']['center_office_id']['code'], true);
        if($payment_remind) {
            try {
                eQual::run('do', 'sale_booking_funding_remind-payment', ['id' => $params['id']]);
            }
            catch(Exception $e) {
                // something went wrong : ignore
            }
        }
    }
    elseif(isset($funding['enrollment_id'])) {
        // dispatch a message for notifying users
        $dispatch->dispatch('lodging.camp.payments', 'sale\camp\Enrollment', $funding['enrollment_id']['id'], 'warning', null, [], [], null, $funding['enrollment_id']['center_office_id']['id']);
    }
}

$auth->su($user_id);

$context->httpResponse()
        ->status(204)
        ->send();
