<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\CenterOffice;
use identity\User;

[$params, $providers] = eQual::announce([
    'description'   => "Returns descriptor of current User, based on received access_token",
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'UTF-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

// retrieve current User identifier (HTTP headers lookup through Authentication Manager)
$user_id = $auth->userId();
// make sure user is authenticated
if($user_id <= 0) {
    throw new Exception("user_unknown", EQ_ERROR_NOT_ALLOWED);
}

// request directly the mapper to bypass permission check on User class
$ids = $orm->search('identity\User', ['id', '=', $user_id]);
// make sure the User object is available
if(!count($ids)) {
    throw new Exception("unexpected_error", EQ_ERROR_INVALID_USER);
}

// user has always READ right on its own object
$user = User::ids($ids)
    ->read([
        'login',
        'name',
        'language',
        'organisation_id',
        'centers_ids',
        'center_offices_ids',
        'identity_id'       => ['firstname', 'lastname'],
        'groups_ids'        => ['name']
    ])
    ->adapt('json')
    ->first(true);

if(!$user) {
    throw new Exception("unexpected_error", EQ_ERROR_INVALID_USER);
}

// append info about user's Center Office
$preferred_center_office_id = reset($user['center_offices_ids']);
$user['center_office'] = CenterOffice::id($preferred_center_office_id)
    ->read(['id', 'name', 'docs_default_mode', 'printer_type', 'rentalunits_manual_assignment'])
    ->adapt('json')
    ->first(true);

// append list of user's groups
$user['groups'] = array_values(array_map(function ($a) {return $a['name'];}, $user['groups_ids']));
unset($user['groups_ids']);

// send back basic info of the User object
$context
    ->httpResponse()
    ->body($user)
    ->send();
