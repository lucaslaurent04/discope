<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

list($params, $providers) = eQual::announce([
    'description'   => 'Returns descriptor of current User, based on received guest_access_token (no user_id).',
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'UTF-8',
        'accept-origin'     => '*',
        'errors'            => ['invalid_token', 'malformed_token', 'expired_token']
    ],
    'constants'     => ['AUTH_SECRET_KEY'],
    'access'        => [
        'visibility'        => 'public'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

$request = $context->getHttpRequest();
$jwt = $request->cookie('guest_access_token');
$check = $auth->verifyToken($jwt, constant('AUTH_SECRET_KEY'));

if($check === false || $check <= 0) {
    throw new Exception('invalid_token', EQ_ERROR_NOT_ALLOWED);
}

$token = $auth->decodeToken($jwt);
$payload = $token['payload'];

if(!isset($payload['exp']) || !isset($payload['booking_id']) || !isset($payload['email'])) {
    throw new Exception('malformed_token', EQ_ERROR_NOT_ALLOWED);
}

if($payload['exp'] < time()) {
    throw new Exception('expired_token', QN_ERROR_INVALID_USER);
}

$user = [
        'booking_id' => $payload['booking_id'],
        'email'      => $payload['email']
    ];

$context->httpResponse()
        ->body($user)
        ->send();
