<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Messages: returns a collection of Message according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'type'              => 'string',
            'description'       => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'default'           => 'discope\core\alert\Message'
        ],
        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\CenterOffice',
            'description'       => 'Office the message relates to (for targeting the users).'
        ],
        'enrollment_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Enrollment',
            'description'       => 'Enrollment to filter on.'
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$domain = $params['domain'];

if(isset($params['center_office_id']) && $params['center_office_id'] > 0) {
    // #memo - center_office_id does not exist on Message and group_id is used instead
    $domain = Domain::conditionAdd($domain, ['group_id', '=', $params['center_office_id']]);
}

if(isset($params['enrollment_id']) && $params['enrollment_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['object_id', '=', $params['enrollment_id']]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context
    ->httpResponse()
    ->body($result)
    ->send();
