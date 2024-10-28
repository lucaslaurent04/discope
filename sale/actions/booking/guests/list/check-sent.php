<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Check if the guest List sent has been sent by the client 10 days after sending it.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

$booking = Booking::id($params['id'])
            ->read(['id',
                    'name',
                    'center_office_id' => ['id'],
                    'status',
                    'customer_id'  => ['partner_identity_id' => ['lang_id' => ['code']]],
                    'guest_list_id' => ['id', 'status'],
                    'contacts_ids' => [ 'is_direct_contact',  'email']
            ])
            ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$guest_list = $booking['guest_list_id'];
if(!$guest_list) {
    throw new Exception("unknown_guest_list", QN_ERROR_UNKNOWN_OBJECT);
}

$recipient = [
    'email' => '',
    'lang' => ''
];


$httpResponse = $context->httpResponse()->status(200);

$result = $booking['id'];
if ($guest_list['status'] == 'pending'){
    foreach($booking['contacts_ids'] as $contact) {
        if ($contact['is_direct_contact']){
            $recipient['email']= $contact['email'];
            $recipient['lang'] = $booking['customer_id']['partner_identity_id']['lang_id']['code'];
            break;
        }
    }
    if(empty($recipient['email'])) {
        $dispatch->dispatch('lodging.booking.guest.reminder.failed', 'sale\booking\Booking', $booking['id'], 'important', 'lodging_guest_list_check-sent', ['id' => $booking['id']], [], null, $booking['center_office_id']['id']);
        $httpResponse->status(qn_error_http(QN_ERROR_MISSING_PARAM));
    }
    else {
        $dispatch->cancel('lodging.booking.guest.reminder.failed', 'sale\booking\Booking', $booking['id']);
        eQual::run('do', 'sale_booking_guests_list_send-remind', [
                'id'    => $booking['id'],
                'email' => $recipient['email'],
                'lang'  => $recipient['lang']
            ]);
    }


}
else {
    $dispatch->cancel('lodging.booking.guest.reminder.failed', 'sale\booking\Booking', $booking['id']);
}


$httpResponse->body($result)
             ->send();
