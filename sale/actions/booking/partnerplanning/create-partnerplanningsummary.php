<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Partner;
use sale\booking\PartnerPlanningSummary;

[$params, $providers] = eQual::announce([
    'description'   => "Creates a planning summary for a specific partner.",
    'params'        => [

        'ids' => [
            'type'              => 'array',
            'description'       => "List of partners ids concerned by the creation.",
            'required'          => true
        ],

        'params' => [
            'description'       => 'Additional params to relay to the data controller.',
            'type'              => 'array',
            'default'           => []
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'adapt']
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\data\adapt\DataAdapterProvider $dap
 */
['context' => $context, 'adapt' => $dap] = $providers;

$adapter = $dap->get('json');

if(empty($params['ids'])) {
    throw new Exception("empty_ids", EQ_ERROR_INVALID_PARAM);
}

$partners = Partner::ids($params['ids'])
    ->read(['id'])
    ->get();

if(count($partners) !== count($params['ids'])) {
    throw new Exception("unknown_partner", EQ_ERROR_UNKNOWN_OBJECT);
}

$date_from = null;
$date_to = null;

if(!empty($params['params'])) {
    if(isset($params['params']['date_from'])) {
        $date_from = $adapter->adaptIn($params['params']['date_from'], 'date/time');
    }
    if(isset($params['params']['date_to'])) {
        $date_to = $adapter->adaptIn($params['params']['date_to'], 'date/time');
    }
}

if(is_null($date_from)) {
    throw new Exception("missing_date_from", EQ_ERROR_INVALID_PARAM);
}

if(is_null($date_to)) {
    throw new Exception("missing_date_to", EQ_ERROR_INVALID_PARAM);
}

foreach($partners as $partner) {
    $mail_content = \eQual::run('get', 'sale_booking_partnerplanningsummary_generate-mail-content', [
        'id'        => $partner['id'],
        'date_from' => $date_from,
        'date_to'   => $date_to
    ]);

    PartnerPlanningSummary::create([
        'partner_id'    => $partner['id'],
        'date_from'     => $date_from,
        'date_to'       => $date_to,
        'mail_content'  => $mail_content
    ]);
}

$context->httpResponse()
        ->status(201)
        ->send();
