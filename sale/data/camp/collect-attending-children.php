<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Field;
use sale\camp\Camp;

[$params, $providers] = eQual::announce([
    'description'   => "Manage children that are attending the current camps.",
    'params'        => [
        'only_weekend' => [
            'type'              => 'boolean',
            'description'       => "Show only the children present during the weekend.",
            'default'           => false
        ],
        'only_birthday' => [
            'type'              => 'boolean',
            'description'       => "Show only the children with birthday during camp.",
            'default'           => false
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit (defaults to first day of the current week).",
            'default'           => fn() => strtotime('Monday this week')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit (defaults to last day of the current week).",
            'default'           => fn() => strtotime('Sunday this week')
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
 * @var \equal\php\Context                  $context
 * @var \equal\data\adapt\AdapterProvider   $adapter_provider
 */
['context' => $context, 'adapt' => $adapter_provider] = $providers;

$json_adapter = $adapter_provider->get('json');

$camps = Camp::search(
    [
        ['date_from', '>=', $params['date_from']],
        ['date_from', '<=', $params['date_to']]
    ]
)
    ->read([
        'short_name',
        'date_from',
        'date_to',
        'enrollments_ids' => [
            'status',
            'weekend_extra',
            'is_ase',
            'child_id' => [
                'firstname',
                'lastname',
                'gender',
                'birthdate',
                'main_guardian_id'  => ['name'],
                'institution_id'    => ['name'],
                'is_foster'
            ],
            'enrollment_lines_ids' => [
                'product_id'
            ]
        ]
    ])
    ->get();

$result = [];

foreach($camps as $camp) {
    foreach($camp['enrollments_ids'] as $enrollment) {
        if(!in_array($enrollment['status'], ['confirmed', 'validated'])) {
            continue;
        }

        $child = $enrollment['child_id'];

        $month_day = date('m-d', $child['birthdate']);
        $year_birthday = DateTime::createFromFormat('Y-m-d', date('Y').'-'.$month_day);

        $result[] = array_merge(
            $child,
            [
                /*
                 * Adapt
                 */
                'main_guardian_id'  => ['id' => $child['main_guardian_id']['id'], 'name' => $child['main_guardian_id']['name']],
                'institution_id'    => ['id' => $child['institution']['id'], 'name' => $child['institution']['name']],
                'birthdate'         => $json_adapter->adaptOut($child['birthdate'], Field::MAP_TYPE_USAGE['date']),

                /*
                 * Add AttendingChild fields
                 */
                'camp_id'           => ['id' => $camp['id'], 'name' => $camp['short_name']],
                'weekend_extra'     => $enrollment['weekend_extra'],
                'has_camp_birthday' => $year_birthday->getTimestamp() >= $camp['date_from'] && $year_birthday->getTimestamp() <= $camp['date_to'],
                'is_ase'            => $enrollment['is_ase']
            ]
        );
    }
}

if($params['only_weekend']) {
    $result = array_filter(
        $result,
        fn($item) => in_array($item['weekend_extra'], ['full', 'saturday-morning'])
    );
}

if($params['only_birthday']) {
    $result = array_filter(
        $result,
        fn($item) => $item['has_camp_birthday']
    );
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body(array_values($result))
        ->send();
