<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'description'   => "Invoice the given price adapters between two dates to a sponsor.",
    'params'        => [

        'id' => [
            'type'              => 'integer',
            'description'       => "Id of the sponsor to invoice.",
            'required'          => true
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "Use price adapters of enrollments to camps after this date.",
            'required'          => true,
            'default'           => fn() => strtotime('first day of january this year')
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Use price adapters of enrollments to camps before this date.",
            'required'          => true,
            'default'           => fn() => strtotime('last day of december this year')
        ]

    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.administrator'],
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$output = eQual::run('get', 'sale_camp_print-sponsor-invoice', [
    'id'        => $params['id'],
    'date_from' => $params['date_from'],
    'date_to'   => $params['date_to']
]);

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
