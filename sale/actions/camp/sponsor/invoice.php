<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Camp;
use sale\camp\price\PriceAdapter;
use sale\camp\Sponsor;

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
            'default'           => function() {
                return strtotime('first day of january this year');
            }
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Use price adapters of enrollments to camps before this date.",
            'required'          => true,
            'default'           => function() {
                return strtotime('last day of december this year');
            }
        ]

    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.user'],
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

$sponsor = Sponsor::id($params['id'])
    ->read(['id'])
    ->first();

if(is_null($sponsor)) {
    throw new Exception("unknown_object", EQ_ERROR_UNKNOWN_OBJECT);
}

$camps = Camp::search([
    ['date_from', '>=', $params['date_from']],
    ['date_to', '<=', $params['date_to']]
])
    ->read(['enrollments_ids'])
    ->get();

$enrollments_ids = [];
foreach($camps as $camp) {
    $enrollments_ids = array_merge(
        $enrollments_ids,
        $camp['enrollments_ids']
    );
}

if(empty($enrollments_ids)) {
    throw new Exception("no_enrollments_for_dates", EQ_ERROR_INVALID_PARAM);
}

$price_adapters_ids = PriceAdapter::search([
    ['enrollment_id', 'in', $enrollments_ids],
    ['sponsor_id', '=', $sponsor['id']]
])
    ->ids();

$output = eQual::run('do', 'sale_camp_sponsor_generate-invoice-pdf', ['ids' => $price_adapters_ids]);

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
