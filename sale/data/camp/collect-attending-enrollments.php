<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Camp;

[$params, $providers] = eQual::announce([
    'description'   => "Manage children that are attending the current camps.",
    'params'        => [

        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit (defaults to first day of the current week).",
            'default'           => fn() => strtotime('last Sunday')
        ],

        'camp_age_range' => [
            'type'              => 'string',
            'description'       => "Age range of the camp the enrollment relates to.",
            'selection'         => [
                'all',
                '6-to-9',
                '10-to-12',
                '13-to-16'
            ],
            'default'           => 'all'
        ],

        'only_weekend' => [
            'type'              => 'boolean',
            'description'       => "Show only the children present during the weekend.",
            'default'           => false
        ],

        'only_saturday' => [
            'type'              => 'boolean',
            'description'       => "Show only the children present during the saturday morning.",
            'default'           => false
        ],

        'only_birthday' => [
            'type'              => 'boolean',
            'description'       => "Show only the children with birthday during camp.",
            'default'           => false
        ]

    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['camp.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$day_of_week = date('w', $params['date_from']);

// find previous Sunday
$sunday = $params['date_from'] - ($day_of_week * 86400);

// next Friday (+5 days)
$friday = $sunday + (5 * 86400);

$camps = Camp::search(
    [
        ['date_from', '>=', $sunday],
        ['date_from', '<=', $friday]
    ]
)
    ->read([
        'short_name',
        'date_from',
        'date_to',
        'enrollments_ids' => [
            'child_firstname',
            'child_lastname',
            'child_gender',
            'child_birthdate',
            'camp_age_range',
            'is_foster',
            'status',
            'weekend_extra',
            'is_ase',
            'child_remarks',
            'main_guardian_mobile',
            'main_guardian_phone',
            'camp_id'           => ['name'],
            'main_guardian_id'  => ['name'],
            'institution_id'    => ['name']
        ]
    ])
    ->adapt('json')
    ->get();

$result = [];

foreach($camps as $camp) {
    foreach($camp['enrollments_ids'] as $enrollment) {
        if($enrollment['status'] !== 'validated') {
            continue;
        }

        $result[] = $enrollment;
    }
}

if($params['camp_age_range'] !== 'all') {
    $result = array_filter(
        $result,
        fn($item) => $item['camp_age_range'] === $params['camp_age_range']
    );
}

if($params['only_saturday']) {
    $result = array_filter(
        $result,
        fn($item) => $item['weekend_extra'] === 'saturday-morning'
    );
}
elseif($params['only_weekend']) {
    $result = array_filter(
        $result,
        fn($item) => $item['weekend_extra'] === 'full'
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
