<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'description'   => "Render a booking quote as a PDF document, given its id.",
    'params'        => [
        'params' => [
            'type'          => 'array',
            'description'   => "Additional params to relay to the data controller.",
            'default'       => []
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'adapt']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context, 'adapt' => $dap] = $providers;

$adapter = $dap->get('json');

$date_from = strtotime('last Sunday');
$date_to = strtotime('Sunday this week');

if(!empty($params['params'])) {
    if(isset($params['params']['date_from'])) {
        $date_from = $adapter->adaptIn($params['params']['date_from'], 'date/time');
    }
    if(isset($params['params']['date_to'])) {
        $date_to = $adapter->adaptIn($params['params']['date_to'], 'date/time');
    }
}

$output = eQual::run('get', 'sale_camp_print-activities-planning', compact('date_from', 'date_to'));

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="planning.pdf"')
        ->body($output)
        ->send();
