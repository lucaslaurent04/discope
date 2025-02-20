<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use identity\User;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for rental units: retrieves a collection of reports based on specified parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity'    =>  [
            'description'       => 'Entity type being queried.',
            'type'              => 'string',
            'default'           => 'realestate\RentalUnit'
        ],

        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
             'description'       => 'The center associated with the rental unit, used for center management.'
        ],

        'parent_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\RentalUnit',
            'description'       => "Rental Unit which current unit belongs to, if any."
        ],

        'type'      => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'building',
                'bedroom',
                'bed',
                'meetingroom',
                'diningroom',
                'room',
                'FFE'
            ],
            'default'           => 'all',
            'description'       => 'Type of the rental unit, which determines its capacity and usage.'
        ],

        'is_accomodation' => [
            'type'              => 'boolean',
            'description'       => 'Indicates whether the rental unit serves as an accommodation.',
            'default'           => null
        ],

        'has_prm_access' => [
            'type'              => 'boolean',
            'description'       => 'Indicates if the unit is accessible for persons with reduced mobility (PMR)',
            'default'           => null,
            'visible'           => ['is_accomodation', '=', true]
        ],

        'has_pvi_features' => [
            'type'              => 'boolean',
            'description'       => 'Indicates if the unit is adapted for persons with visual impairments (PDV).',
            'default'           => null,
            'visible'           => ['is_accomodation', '=', true]
        ],

        'has_phi_support' => [
            'type'              => 'boolean',
            'description'       => 'Indicates if the unit includes features for persons with hearing impairments (PDA).',
            'default'           => null,
            'visible'           => ['is_accomodation', '=', true]
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'auth' ]
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

$domain = [];


if(isset($params['center_id']) && $params['center_id'] > 0) {
    $domain[] = ['center_id', '=', $params['center_id']];
}
else {
    $user = User::id($auth->userId())->read(['centers_ids'])->first(true);
    if(count($user['centers_ids'])) {
        $domain[] = ['center_id', 'in', $user['centers_ids']];
    }
    else {
        $domain[] = ['center_id', '=', 0];
    }
}


if(isset($params['parent_id']) && $params['parent_id'] > 0) {
    $domain[] = ['parent_id', '=', $params['parent_id']];
}

if(isset($params['type']) && strlen($params['type']) > 0 && $params['type'] != 'all') {
    $domain[] = ['type', '=', $params['type']];
}


if($params['is_accomodation'] ?? false) {
    $domain[] = ['is_accomodation', '=', $params['is_accomodation']];
}


if($params['has_prm_access'] ?? false) {
    $domain[] = ['has_prm_access', '=', $params['has_prm_access']];
}

if($params['has_pvi_features'] ?? false) {
    $domain[] = ['has_pvi_features', '=', $params['has_pvi_features']];
}


if($params['has_phi_support'] ?? false) {
    $domain[] = ['has_phi_support', '=', $params['has_phi_support']];
}


$params['domain'] = (new Domain($params['domain']))
    ->merge(new Domain($domain))
    ->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
