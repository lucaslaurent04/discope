<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\channelmanager\Property;

list($params, $providers) = eQual::announce([
    'description'   => 'Synchronize discope rental units availabilities to cubilis, only for cubilis last updatable calendar day.',
    'help'          => 'Is meant to run on a daily basis, to sync cubilis calendar as much as possible.',
    'access'        => [
        'groups' => ['admins'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$result = [
    'errors'                => 0,
    'warnings'              => 0,
    'logs'                  => []
];

$properties = Property::search(['is_active', '=', true])
    ->read(['id', 'name'])
    ->get();

$last_updatable_day = strtotime('+2 years -2 days', time());

foreach($properties as $property) {
    try {
        eQual::run('do', 'sale_booking_channelmanager_cubilis_sync-all', [
            'property_id' => $property['id'],
            'date_from'   => $last_updatable_day,
            'date_to'     => $last_updatable_day
        ]);
        $result['logs'][] = "OK  - Successfully sent sync-all for property {$property['name']} [{$property['id']}] for date ".date('Y-m-d', $last_updatable_day);
    }
    catch(Exception $e) {
        $msg = $e->getMessage();
        trigger_error(
            "APP::error while sync-last-updatable-day for property {$property['name']} ".$msg,
            QN_REPORT_ERROR
        );
        ++$result['errors'];
        $result['logs'][] = "ERR - Error while sync-all for property {$property['name']} [{$property['id']}]: {$msg}";
    }
}

$context->httpResponse()
        ->body($result)
        ->send();
