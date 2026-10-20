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
if(!empty($enrollment['export_number'])) {
    $export_number = $enrollment['export_number'];
}

$camps = Camp::search(
    [
        ['id', 'in', $year_camps_ids],
        ['date_to', '<', $now]
    ],
    ['sort' => ['sojourn_number' => 'asc']]
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
            ],
            'enrollments_lines_ids' => [
                'product_id' => [
                    'camp_product_type',
                    'price_id' => [
                        'accounting_rule_id' => [
                            'accounting_rule_line_ids' => [
                                'account',
                                'share'
                            ]
                        ],
                        'analytic_section_id' => [
                            'code'
                        ]
                    ]
                ]
            ]
        ]
    ])
    ->get();

$has_enrollments_to_export = false;
foreach($camps as $camp) {
    if(count($camp['enrollments_ids']) > 0) {
        $has_enrollments_to_export = true;
        break;
    }
}
if(!$has_enrollments_to_export) {
    throw new Exception("nothing_to_export", EQ_ERROR_NOT_ALLOWED);
}

$writing_number = 0;
$maps_exported_enrollments_numbers = [];
$lines = [];

try {
    foreach($camps as $camp) {
        if(date('D', $camp['date_from']) === 'Sun') {
            // #memo - if a full camp starts on Sunday, we want it to start the next Monday instead for the export
            $camp_date = date('dmY', $camp['date_from'] + 86400);
        }
        else {
            $camp_date = date('dmY', $camp['date_from']);
        }

        foreach($camp['enrollments_ids'] as $enrollment) {
            $export_number++;
            $writing_number++;
            $writing_line = 0;

            $customer_accounting_account = '411C4526';

            $enrollment_number = Setting::parse_format('C%2d{year}/%04d{sequence}', [
                'year'      => $params['year'],
                'sequence'  => $export_number
            ]);
            $child_name = strtoupper(trim($enrollment['child_id']['lastname'])).' '.trim($enrollment['child_id']['firstname']);

            $lines[] = [
                'writing_number'        => $writing_number,
                'writing_line_number'   => ++$writing_line,
                'date'                  => $camp_date,
                'general_account'       => $customer_accounting_account,
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

            $accounting_rule_lines = [['account' => '70680000', 'share' => 1]];
            $product_analytic_account = 'A980084';

            foreach($enrollment['enrollments_lines_ids'] as $line) {
                if(in_array($line['product_id']['camp_product_type'], ['full', 'clsh-day', 'clsh-full-4-days', 'clsh-full-5-days'])) {
                    if(!empty($line['product_id']['price_id']['accounting_rule_id']['accounting_rule_lines_ids'])) {
                        $accounting_rule_lines = $line['product_id']['price_id']['accounting_rule_id']['accounting_rule_lines_ids'];
                    }
                    if(!empty($line['product_id']['price_id']['analytic_section_id']['code'])) {
                        $product_analytic_account = $line['product_id']['price_id']['analytic_section_id']['code'];
                    }
                }
            }

            foreach($accounting_rule_lines as $rule_line) {
                $amount = -1 * ($enrollment['price'] * $rule_line['share']);

                $lines[] = [
                    'writing_number'        => $writing_number,
                    'writing_line_number'   => ++$writing_line,
                    'date'                  => $camp_date,
                    'general_account'       => $rule_line['account'],
                    'analytic_account'      => $product_analytic_account,
                    'journal_account'       => 'VAC',
                    'file_type'             => '',
                    'file_number'           => $enrollment_number,
                    'amount'                => sprintf('%.02f', $amount),
                    'writing_label'         => $child_name,
                    'due_date'              => '',
                    'check_number'          => '',
                    'invoice_number'        => $enrollment_number,
                    'currency_type'         => 'E'
                ];
            }

            $maps_exported_enrollments_numbers[$enrollment['id']] = $export_number;
        }
    }

    $format_config = [
        'writing_number'        => ['length' => 6, 'pad_type' => STR_PAD_LEFT],
        'writing_line_number'   => ['length' => 6, 'pad_type' => STR_PAD_LEFT],
        'date'                  => ['length' => 8],
        'general_account'       => ['length' => 11],
        'analytic_account'      => ['length' => 10],
        'journal_account'       => ['length' => 3],
        'file_type'             => ['length' => 3],
        'file_number'           => ['length' => 15],
        'amount'                => ['length' => 15],
        'writing_label'         => ['length' => 50],
        'due_date'              => ['length' => 8],
        'check_number'          => ['length' => 12],
        'invoice_number'        => ['length' => 20],
        'currency_type'         => ['length' => 1]
    ];

    // format each line to AIGA format (handle padding and substring)
    $formatted_lines = [];
    foreach($lines as $line) {
        $line['writing_label'] = substr(TextTransformer::toAscii($line['writing_label']), 0, $format_config['writing_label']['length']);

        $formatted_line = [];
        foreach(array_keys($format_config) as $key) {
            $formatted_line[$key] = str_pad($line[$key], $format_config[$key]['length'], ' ', $format_config[$key]['pad_type'] ?? STR_PAD_RIGHT);
        }

        $formatted_lines[] = implode('', $formatted_line);
    }

    $result = implode(PHP_EOL, $formatted_lines);
}
catch(Exception $e) {
    if(!empty($maps_exported_enrollments_numbers)) {
        Enrollment::ids(array_keys($maps_exported_enrollments_numbers))->update([
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

    $export = null;

    try {
        // create the export archive
        $export = Export::create([
            'center_office_id'  => 1,
            'export_type'       => 'camps_enrollments',
            'data'              => $data,
            'object_class'      => Enrollment::getType(),
            'object_ids'        => json_encode(array_keys($maps_exported_enrollments_numbers))
        ])
            ->first();

        // set enrollments as exported
        foreach($maps_exported_enrollments_numbers as $enrollment_id => $export_number) {
            Enrollment::id($enrollment_id)->update([
                'is_exported'   => true,
                'export_number' => $export_number
            ]);
        }
    }
    catch(Exception $e) {
        if($export) {
            // remove export archive, if it was created
            Export::id($export['id'])->delete(true);
        }

        // set enrollments as not exported
        Enrollment::ids(array_keys($maps_exported_enrollments_numbers))->update([
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
