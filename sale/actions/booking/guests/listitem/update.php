<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\GuestListItem;

list($params, $providers) = eQual::announce([
    'description'   => "Update targeted Guest List Item.",
    'params'        => [
        'entity' =>  [
            'description'   => 'Full name (including namespace) of the class to look into.',
            'type'          => 'string',
            'default'       => 'sale\booking\GuestListItem'
        ],
        'id' =>  [
            'description'   => 'Unique identifier of the object to update.',
            'type'          => 'integer',
            'default'       => 0
        ],
        'ids' =>  [
            'description'   => 'List of Unique identifiers of the objects to update.',
            'type'          => 'array',
            'default'       => []
        ],
        'fields' =>  [
            'description'   => 'Associative array mapping fields to be updated with their related values.',
            'type'          => 'array',
            'default'       => []
        ],
        'force' =>  [
            'description'   => 'Flag for forcing update in case a concurrent change is detected.',
            'type'          => 'boolean',
            'default'       => false
        ],
        'lang' => [
            'description '  => 'Specific language for multilang field.',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ]
    ],
    'constants'     => ['DEFAULT_LANG'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access'        => [
        'visibility'    => 'public'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
list('context' => $context, 'auth' => $auth) = $providers;

$guest_user = null;

$current_user_id = $auth->userId();
if($current_user_id != QN_ROOT_USER_ID) {
    $guest_user = eQual::run('get','sale_booking_guests_userinfo');
    if(!$guest_user) {
        throw new Exception("unknown_guest_user", QN_ERROR_UNKNOWN_OBJECT);
    }
}

if(empty($params['ids'])) {
    if(!isset($params['id']) || $params['id'] <= 0) {
        throw new Exception("object_invalid_id", QN_ERROR_INVALID_PARAM);
    }
    $params['ids'][] = $params['id'];
}

$guest_list_items = GuestListItem::ids($params['ids'])->read(['id', 'booking_id'])->get(true);
if(count($guest_list_items) < count($params['ids'])) {
    throw new Exception("unknown_guest_list_item", QN_ERROR_UNKNOWN_OBJECT);
}

foreach($guest_list_items as $guest_list_item) {
    if(!is_null($guest_user) && $guest_user['booking_id'] !== $guest_list_item['booking_id']) {
        throw new Exception("not_allowed", QN_ERROR_NOT_ALLOWED);
    }
}

if($params['entity'] !== 'sale\booking\GuestListItem') {
    throw new Exception("invalid_entity", QN_ERROR_INVALID_PARAM);
}

$auth->su();
$result = eQual::run('do', 'model_update', $params);
$auth->su(0);

$context->httpResponse()
        ->body($result)
        ->send();
