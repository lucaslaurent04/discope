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

        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit (defaults to last day of the current week).",
            'default'           => fn() => strtotime('Saturday this week')
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
            'child_firstname',
            'child_lastname',
            'child_gender',
            'child_birthdate',
            'is_foster',
            'status',
            'weekend_extra',
            'is_ase',
            'child_remarks',
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

if($params['only_weekend']) {
    $result = array_filter(
        $result,
        fn($item) => $item['weekend_extra'] === 'full'
    );
}
elseif($params['only_saturday']) {
    $result = array_filter(
        $result,
        fn($item) => $item['weekend_extra'] === 'saturday-morning'
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
