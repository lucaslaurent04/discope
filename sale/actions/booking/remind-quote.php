<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'deprecated'    => "No followup notification is planned for quotes (customer details might still be incomplete).",
    'description'   => "Deprecated quote reminder controller kept to neutralize legacy scheduled tasks.",
    'params'        => [
        'id' => [
            'type'          => 'integer',
            'description'   => 'Identifier of the targeted booking.',
            'required'      => true
        ],
        'lang' => [
            'type'          => 'string',
            'description'   => 'Legacy language parameter.',
            'default'       => constant('DEFAULT_LANG')
        ]
    ],
    'constants'     => ['DEFAULT_LANG'],
    'access'        => [
        'visibility' => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$context->httpResponse()->status(204)->send();
