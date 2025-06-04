<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use PhpOffice\PhpSpreadsheet\IOFactory;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Bookings: returns a collection of Booking according to extra parameters.',
    'params'        => [
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;


$input_files = ['employe.xlsx'];

$lang = 'fr';

$mapping = [
    'num_employe'               => 'employee.extref_employee',
    'nom_employe'               => 'identity.lastname',
    'prenom_employe'            => 'identity.firstname',
    'adresse_employe'           => 'identity.address_street',
    'code_postal_employe'       => 'identity.address_zip',
    'ville_employe'             => 'identity.address_city',
    'tel_employe'               => 'identity.phone',
    'tel_portable_employe'      => 'identity.mobile',
    'email_employe'             => 'identity.email',
    'diplome_employe'           => 'employee.description',
    'date_naissance_employe'    => 'identity.date_of_birth',
    'archive_employe'           => 'employee.is_active',
    'num_type_anim'             => 'employee.activity_type',
];

$typeAnimLabelMap = [
    '1' => 'Sport',
    '2' => 'Cirque',
    '4' => 'Tous',
    '5' => 'Ferme',
    '7' => 'Centre Equestre',
];

$identityIdCounter = 100;
$employeeIdCounter = 100;

$identities = [];
$employees = [];

foreach($input_files as $input_file) {
    $inputFile = EQ_BASEDIR . '/packages/lathus/import/' . $input_file;

    if(!file_exists($inputFile)) {
        throw new Exception('missing_file', EQ_ERROR_INVALID_CONFIG);
    }

    $spreadsheet = IOFactory::load($inputFile);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    $headers = array_map('trim', array_shift($rows));

    foreach ($rows as $row) {
        $identityId = $identityIdCounter++;
        $employeeId = $employeeIdCounter++;

        $identity = [
            'id' => $identityId,
            'creator' => 1,
            'modifier' => 1,
            'state' => 'instance',
        ];

        $employee = [
            'id' => $employeeId,
            'creator' => 1,
            'modifier' => 1,
            'state' => 'instance',
            'relationship' => 'employee',
            'owner_identity_id' => 1,
            'lang_id' => 1,
            'partner_identity_id' => $identityId,
            'is_active' => true,
        ];

        foreach ($headers as $col => $field) {
            if (!isset($row[$col])) {
                continue;
            }

            $value = trim((string) $row[$col]);
            if ($value === '') {
                continue;
            }

            $target = $mapping[$field] ?? null;
            if (!$target) {
                continue;
            }

            if ($target === 'employee.activity_type') {
                $employee['activity_type'] = $typeAnimLabelMap[$value] ?? null;
            }
            elseif ($target === 'employee.is_active') {
                // archive_employe = VRAI => is_active = false
                $employee['is_active'] = !(strtolower($value) === 'vrai');
            }
            elseif (strpos($target, 'identity.') === 0) {
                $key = substr($target, 9);
                $identity[$key] = $value;
            }
            elseif (strpos($target, 'employee.') === 0) {
                $key = substr($target, 9);
                $employee[$key] = $value;
            }
        }

        $identities[] = $identity;
        $employees[] = $employee;
    }
}

$data = [
    [
        'name' => 'identity\\Identity',
        'lang' => $lang,
        'data' => $identities,
    ],
    [
        'name' => 'hr\\employee\\Employee',
        'lang' => $lang,
        'data' => $employees,
    ],
];


$timestamp = date('Ymd_His');

$import_folder_path = EQ_BASEDIR.'/import/'.$timestamp;
if(!mkdir($import_folder_path, 0754, true)) {
    throw new Exception(serialize(['folder_creation_error' => "unable to create output folder $path"]), EQ_ERROR_UNKNOWN);
}

$outputFile = $import_folder_path . "/import_{$timestamp}.json";
file_put_contents($outputFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));


$context->httpResponse()
        ->status(204)
        ->send();
