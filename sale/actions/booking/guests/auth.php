<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'	=>	"Attempts to temporarily log a user in with a nonce token.",
    'params' 		=>	[
        'nonce' =>  [
            'description'   => "Nonce token to be used for authentication.",
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm'],
    'constants'     => ['AUTH_ACCESS_TOKEN_VALIDITY', 'AUTH_REFRESH_TOKEN_VALIDITY', 'AUTH_TOKEN_HTTPS']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

$jwt = $params['nonce'];

$check = $auth->verifyToken($jwt, constant('AUTH_SECRET_KEY'));
if($check === false || $check <= 0) {
    throw new Exception('invalid_token', EQ_ERROR_NOT_ALLOWED);
}

$token = $auth->decodeToken($jwt);

if(!isset($token['payload'])) {
    throw new Exception('malformed_token', EQ_ERROR_NOT_ALLOWED);
}

$payload = $token['payload'];

if(!isset($payload['booking_id']) || !isset($payload['email']) || !isset($payload['exp'])) {
    throw new Exception('malformed_token', EQ_ERROR_NOT_ALLOWED);
}

if($payload['exp'] < time()) {
    throw new Exception('auth_expired_token', EQ_ERROR_INVALID_USER);
}

$booking = Booking::id($payload['booking_id'])
    ->read(['id', 'status', 'contacts_ids' => ['partner_identity_id' => ['email']]])
    ->first(true);

if(!$booking) {
    throw new Exception('unknown_booking', EQ_ERROR_NOT_ALLOWED);
}

if(!in_array($booking['status'], ['confirmed', 'validated', 'checkedin'])) {
    throw new Exception('expired_booking', EQ_ERROR_NOT_ALLOWED);
}

$found = false;
foreach($booking['contacts_ids'] as $id => $contact) {
    if($contact['partner_identity_id']['email'] == $payload['email']) {
        $found = true;
        break;
    }
}

if(!$found) {
    throw new Exception('unrelated_contact', EQ_ERROR_NOT_ALLOWED);
}

// generate a guest access token, valid for 15 days
$guest_access_token  = $auth->encode([
        'booking_id' => $booking['id'],
        'email'      => $payload['email'],
        'exp'        => time() + (15 * 86400)
    ]);

$context->httpResponse()
        ->cookie('guest_access_token',  $guest_access_token, [
            'expires'   => time() + 86400,
            'httponly'  => true,
            'secure'    => constant('AUTH_TOKEN_HTTPS')
        ])
        ->status(204)
        ->send();
