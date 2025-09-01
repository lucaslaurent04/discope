<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BookingActivity;
use sale\camp\Camp;
use sale\camp\CampGroup;

[$params, $providers] = eQual::announce([
    'description'   => "Clone the given camp first group's activities.",
    'help'          => "Usually the max camp group quantity is two.",
    'params'        => [

        'id' => [
            'type'              => 'integer',
            'description'       => "Identifier of the camp that needs its group to be cloned.",
            'min'               => 1,
            'required'          => true
        ]

    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['camp.default.administrator'],
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

$camp = Camp::id($params['id'])
    ->read([
        'camp_groups_ids' => [
            'booking_activities_ids' => [
                'product_id',
                'product_model_id',
                'activity_date',
                'time_slot_id',
                'schedule_from',
                'schedule_to'
            ]
        ]
    ])
    ->first(true);

if(is_null($camp)) {
    throw new Exception("unknown_camp", EQ_ERROR_UNKNOWN_OBJECT);
}

$group = CampGroup::create(['camp_id' => $camp['id']])
    ->read(['id'])
    ->first();

if(empty($camp['camp_groups_ids'])) {
    throw new Exception("no_group_to_clone", EQ_ERROR_INVALID_PARAM);
}

$group_to_clone = $camp['camp_groups_ids'][0];

foreach($group_to_clone['booking_activities_ids'] as $activity) {
    BookingActivity::create([
        'camp_id'           => $camp['id'],
        'camp_group_id'     => $group['id'],
        'product_id'        => $activity['product_id'],
        'product_model_id'  => $activity['product_model_id'],
        'activity_date'     => $activity['activity_date'],
        'time_slot_id'      => $activity['time_slot_id'],
        'schedule_from'     => $activity['schedule_from'],
        'schedule_to'       => $activity['schedule_to']
    ]);
}

$context->httpResponse()
        ->status(204)
        ->send();
