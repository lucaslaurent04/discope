<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'description'   => "Identify and mark duplicate identities.",
    'help'          => "If no \"id\" is provided, the action will affect all identities that have not yet been evaluated for duplication.",
    'params'        => [
        'id' => [
            'type'          => 'integer',
            'description'   => "The identifier of the identity for which we want to reset the duplicate information.",
            'default'       => null
        ]
    ],
    'access'        => [
        'visibility' => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

if(!is_null($params['id'])) {
    $orm->callonce('identity\Identity', 'reCalcIsDuplicate', [$params['id']]);
}
else {
    $start = 0;
    $offset = $limit = 1000;
    $identities_ids = [];
    while($start == 0 || !empty($identities_ids)) {
        $identities_ids = $orm->search('identity\Identity', null, ['id' => 'asc'], $start, $limit);
        if($identities_ids > 0 && count($identities_ids)) {
            $orm->read('identity\Identity', $identities_ids, ['is_duplicate']);
        }

        $start = $limit;
        $limit += $offset;
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
