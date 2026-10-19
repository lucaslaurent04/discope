<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use discope\setting\Setting;
use documents\Export;
use equal\text\TextTransformer;
use sale\camp\Camp;
use sale\camp\Enrollment;

[$params, $providers] = eQual::announce([
    'description'   => "Create an financial export of ended camps' enrollments.",
    'params'        => [
        'year' => [
            'type'          => 'integer',
            'description'   => 'The year the export needs to be created for.',
            'default'       => fn() => (int) date('Y')
        ]
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'constants'     => ['EMAIL_ERRORS_RECIPIENT'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$start_year = strtotime("first day of January {$params['year']}");
$end_year = strtotime("last day of December {$params['year']}");

$now = time();

$year_camps_ids = Camp::search([
    ['date_from', '>=', $start_year],
    ['date_to', '<', $end_year]
])
    ->ids();

$enrollment = Enrollment::search(
    [
        ['is_exported', '=', true],
        ['camp_id', 'in', $year_camps_ids]
    ],
    ['sort' => ['export_number' => 'desc']]
)
    ->read(['export_number'])
    ->first();

$export_number = 0;
if(isset($enrollment['export_number'])) {
    $export_number = $enrollment['export_number'];
}

$camps = Camp::search(
    [
        ['id', 'in', $year_camps_ids],
        ['date_to', '<', $now]
    ],
    [
        'sort' => ['date_from' => 'asc']
    ]
)
    ->read([
        'date_from',
        'enrollments_ids' => [
            '@domain' => [
                ['is_exported', '=', false],
                ['status', 'in', ['confirmed', 'validated']]
            ],
            'status',
            'price',
            'child_id' => [
                'firstname',
                'lastname'
            ]
        ]
    ])
    ->get(true);

$writing_number = 0;
$exported_enrollments_ids = [];
$lines = [];

try {
    foreach($camps as $camp) {
        $camp_date = date('dmY', $camp['date_from']);          // +1 day if Sunday

        foreach($camp['enrollments_ids'] as $enrollment) {
            $export_number++;
            $writing_number++;
            $writing_line = 0;

            $enrollment_number = Setting::parse_format('C%2d{year}/%04d{sequence}', [
                'year'      => $params['year'],
                'sequence'  => $export_number
            ]);
            $child_name = strtoupper(trim($enrollment['child_id']['lastname'])).' '.trim($enrollment['child_id']['firstname']);

            $lines[] = [
                'writing_number'        => $writing_number,
                'writing_line_number'   => ++$writing_line,
                'date'                  => $camp_date,
                'general_account'       => '411C4526',
                'analytic_account'      => '',
                'journal_account'       => 'VAC',
                'file_type'             => '',
                'file_number'           => $enrollment_number,
                'amount'                => sprintf('%.02f', $enrollment['price']),
                'writing_label'         => $child_name,
                'due_date'              => '',
                'check_number'          => '',
                'invoice_number'        => $enrollment_number,
                'currency_type'         => 'E'
            ];

            $lines[] = [
                'writing_number'        => $writing_number,
                'writing_line_number'   => ++$writing_line,
                'date'                  => $camp_date,
                'general_account'       => '70680000',
                'analytic_account'      => 'A980084',
                'journal_account'       => 'VAC',
                'file_type'             => '',
                'file_number'           => $enrollment_number,
                'amount'                => sprintf('%.02f', $enrollment['price']),
                'writing_label'         => $child_name,
                'due_date'              => '',
                'check_number'          => '',
                'invoice_number'        => $enrollment_number,
                'currency_type'         => 'E'
            ];

            Enrollment::id($enrollment['id'])->update([
                'is_exported'   => true,
                'export_number' => $export_number
            ]);

            $exported_enrollments_ids[] = $enrollment['id'];
        }
    }

    // format each line to AIGA format (handle padding and substring)
    $formatted_lines = [];
    foreach($lines as $line) {
        $writing_label = substr(TextTransformer::toAscii($line['writing_label']), 0, 50);

        $formatted_lines[] = [
            'writing_number'        => str_pad($line['writing_number'], 6, ' ', STR_PAD_LEFT),
            'writing_line_number'   => str_pad($line['writing_line_number'], 6, ' ', STR_PAD_LEFT),
            'date'                  => str_pad($line['date'], 8),
            'general_account'       => str_pad($line['general_account'], 11),
            'analytic_account'      => str_pad($line['analytic_account'], 10),
            'journal_account'       => str_pad($line['journal_account'], 3),
            'file_type'             => str_pad($line['file_type'], 3),
            'file_number'           => str_pad($line['file_number'], 15),
            'amount'                => str_pad($line['amount'], 15),
            'writing_label'         => str_pad($writing_label, 50),
            'due_date'              => str_pad($line['due_date'], 8),
            'check_number'          => str_pad($line['check_number'], 12),
            'invoice_number'        => str_pad($line['invoice_number'], 20),
            'currency_type'         => str_pad($line['currency_type'], 1)
        ];
    }

    $result = implode(
        PHP_EOL,
        array_map(
            function($line) {
                return $line['writing_number'].$line['writing_line_number'].$line['date'].$line['general_account']
                    .$line['analytic_account'] .$line['journal_account'].$line['file_type'].$line['file_number']
                    .$line['amount'].$line['writing_label'].$line['due_date'] .$line['check_number']
                    .$line['invoice_number'].$line['currency_type'];
            },
            $formatted_lines
        )
    );
}
catch(Exception $e) {
    if(!empty($exported_enrollments_ids)) {
        Enrollment::ids($exported_enrollments_ids)->update([
            'is_exported'   => false,
            'export_number' => null
        ]);
    }
}

if(!empty($result)) {
    // generate the zip archive
    $tmp_file = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive();
    if($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        // could not create the ZIP archive
        throw new Exception("Unable to create a ZIP file.", EQ_ERROR_UNKNOWN);
    }

    $zip->addFromString('discope_camp_invoices_export_for_AIGA.txt', $result);

    $zip->close();

    // read raw data
    $data = file_get_contents($tmp_file);
    unlink($tmp_file);

    if($data === false) {
        throw new Exception("Unable to retrieve ZIP file content.", EQ_ERROR_UNKNOWN);
    }

    $user_id = $auth->userId();

    // switch to root user
    $auth->su();

    try {
        // create the export archive
        $export = Export::create([
            'center_office_id'  => 1,
            'export_type'       => 'camps_enrollments',
            'data'              => $data,
            'object_class'      => Enrollment::getType(),
            'object_ids'        => json_encode($exported_enrollments_ids)
        ])
            ->read(['id'])
            ->first();
    }
    catch(Exception $e) {
        // set enrollments as not imported
        Enrollment::ids($exported_enrollments_ids)->update([
            'is_exported'   => false,
            'export_number' => null
        ]);

        throw $e;
    }

    $auth->su($user_id);
}

$context
    ->httpResponse()
    ->status(201)
    ->send();
