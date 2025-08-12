<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use identity\User;
use sale\booking\Booking;
use sale\booking\TimeSlot;

list($params, $providers) = announce([
    'description'   => 'Advanced search for Consumptions: returns a collection of Consumptions according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        // inherited params
        'entity' =>  [
            'description'       => 'Full name (including namespace) of the class to look into.',
            'type'              => 'string',
            'default'           => 'sale\booking\Consumption'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit.",
            'default'           => strtotime('Today')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval Upper limit.',
            'default'           => strtotime('+7 days midnight')
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => "The center to which the booking relates to."
        ],
        'time_slot_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\TimeSlot',
            'description'       => 'Indicator of the moment of the day when the consumption occurs (from schedule).',
        ],
        'is_not_option' => [
            'type'              => 'boolean',
            'description'       => 'Discard quote and option bookings.',
            'default'           =>  true
        ],
        'consumption_type' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'meal',
                'snack'
            ],
            'default'           => 'meal'
        ],
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

/*
    Add conditions to the domain to consider advanced parameters
*/
$domain = $params['domain'];

if(isset($params['center_id']) && $params['center_id'] > 0) {
    // add constraint on center_id
    $domain = Domain::conditionAdd($domain, ['center_id', '=', $params['center_id']]);
}
else {
    // if no center is provided, fallback to current users'
    $user = User::id($auth->userId())->read(['centers_ids'])->first(true);
    if(count($user['centers_ids']) == 1) {
        $domain = Domain::conditionAdd($domain, ['center_id', '=', reset($user['centers_ids'])]);
    }
    else {
        $domain = Domain::conditionAdd($domain, ['center_id', '=', 0]);
    }
}

if(isset($params['date_from'])) {
    // add constraint on date_from
    $domain = Domain::conditionAdd($domain, ['date', '>=', $params['date_from']]);
}

if(isset($params['date_to'])) {
    // add constraint on date_to
    $domain = Domain::conditionAdd($domain, ['date', '<=', $params['date_to']]);
}

if(isset($params['time_slot_id'])) {
    // add constraint on date_to
    $domain = Domain::conditionAdd($domain, ['time_slot_id', '=', $params['time_slot_id']]);
}

if(isset($params['is_not_option']) && $params['is_not_option']) {
    $bookings_ids = [];
    $bookings_ids = Booking::search(['status', 'not in', ['quote','option']], ['is_cancelled', '=', false])->ids();
    $domain = Domain::conditionAdd($domain, ['booking_id', 'in', $bookings_ids]);
}

if(isset($params['consumption_type']) && $params['consumption_type']!= 'all') {
    $field = $params['consumption_type'] === 'meal' ? 'is_meal' : 'is_snack';
    $domain = Domain::conditionAdd($domain, [$field, '=', true]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$time_slots = TimeSlot::search([])->read(['id', 'code', 'order'])->get();

$time_slot_details = [];
foreach ($time_slots as $time_slot) {
    $time_slot_details[$time_slot['id']] = [
        'order' => $time_slot['order']
    ];
}

foreach ($result as &$item) {
    if (isset($item['time_slot_id']['id']) && isset($time_slot_details[$item['time_slot_id']['id']])) {
        $item['time_slot_id']['order'] = $time_slot_details[$item['time_slot_id']['id']]['order'];
    }
}

if(in_array('time_slot_id.name', $params['fields'])) {
    $dates = array_map(function ($a) { return $a['date'];}, $result);
    $time_slots = array_map(function ($a) { return $a['time_slot_id']['order'];}, $result);
    // sort by date and then by time_slot order
    array_multisort($dates, SORT_ASC, $time_slots, SORT_ASC, $result);
}

$context->httpResponse()
        ->body($result)
        ->send();
