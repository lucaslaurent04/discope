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


$input_files = ['client.xlsx', 'indiv_client.xlsx', 'prospect.xlsx'];

$lang = 'fr';

$mapping = [
    'num_client' => 'customer.customer_external_ref',
    'titre_client' => 'identity.legal_name',
    'nom_client' => 'identity.lastname',
    'prenom_client' => 'identity.firstname',
    'adr1_client' => 'identity.address_street',
    'adr2_client' => 'identity.address_dispatch',
    'code_postal_client' => 'identity.address_zip',
    'ville_client' => 'identity.address_city',
    'pays_client' => 'identity.address_country',
    'tel_client' => 'identity.phone',
    'fax_client' => 'identity.fax',
    'mail_client' => 'identity.email',
    'rem_client' => 'identity.description',
    'date_naissance_client' => 'identity.date_of_birth',
    'num_compta_client' => 'identity.accounting_account',
    'id_type_pm' => 'identity.type_id',
    'id_nature_client_pm' => 'customer.customer_nature_id',
];

$typeIdMap = [
    '1' => 4,
    '2' => 4,
    '3' => 5,
    '4' => 4,
    '5' => 4,
];

// Initial counters
$identityIdCounter = 1000;
$customerIdCounter = 1000;

$identities = [];
$customers = [];

foreach($input_files as $input_file) {

    $inputFile = EQ_BASEDIR . '/packages/lathus/import/' . $input_file;

    if(!file_exists($inputFile)) {
        throw new Exception('missing_file', EQ_ERROR_INVALID_CONFIG);
    }

    $spreadsheet = IOFactory::load($inputFile);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    $headers = array_map('trim', array_shift($rows));

    foreach($rows as $row) {
        $identityId = $identityIdCounter++;
        $customerId = $customerIdCounter++;

        $identity = [
            'id' => $identityId,
            'creator' => 1,
            'modifier' => 1,
            'state' => 'instance',
            'address_country' => 'FR'
        ];

        $customer = [
            'id' => $customerId,
            'creator' => 1,
            'modifier' => 1,
            'state' => 'instance',
            'relationship' => 'customer',
            'owner_identity_id' => 1,
            'lang_id' => 1,
            'rate_class_id' => 1,
            'customer_type_id' => 1,
            'partner_identity_id' => $identityId,
        ];

        foreach($headers as $col => $field) {
            if(!isset($row[$col])) {
                continue;
            }

            $value = $row[$col];
            $value = is_null($value) ? '' : trim((string) $value);

            if($value === '') {
                continue;
            }

            $target = $mapping[$field] ?? null;

            if(!$target) {
                continue;
            }

            if($target === 'identity.phone') {
                $customer['customer_nature_id'] = str_replace('O', '0', str_replace([' ', ',', '.'], '', $value));
            }
            elseif($target === 'identity.type_id') {
                $identity['type_id'] = isset($typeIdMap[$value]) ? $typeIdMap[$value] : null;
                $customer['customer_type_id'] = isset($typeIdMap[$value]) ? $typeIdMap[$value] : null;
            }
            elseif($target === 'customer.customer_nature_id') {
                $customer['customer_nature_id'] = intval($value);
            }
            elseif(is_string($target) && strpos($target, 'identity.') === 0) {
                $identityField = substr($target, strlen('identity.'));
                $identity[$identityField] = $value;
            }
            elseif(is_string($target) && strpos($target, 'customer.') === 0) {
                $customerField = substr($target, strlen('customer.'));
                $customer[$customerField] = $value;
            }
        }

        $identities[] = $identity;
        $customers[] = $customer;
    }
}

$data = [
    [
        'name' => 'identity\\Identity',
        'lang' => $lang,
        'data' => $identities,
    ],
    [
        'name' => 'sale\\customer\\Customer',
        'lang' => $lang,
        'data' => $customers,
    ]
];

$timestamp = date('Ymd_His');

$import_folder_path = EQ_BASEDIR.'/import/'.$timestamp;
if(!mkdir($import_folder_path, 0754, true)) {
    throw new Exception(serialize(['folder_creation_error' => "unable to create output folder $import_folder_path"]), EQ_ERROR_UNKNOWN);
}

$outputFile = $import_folder_path . "/import_{$timestamp}.json";
file_put_contents($outputFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));


$context->httpResponse()
        ->status(204)
        ->send();
