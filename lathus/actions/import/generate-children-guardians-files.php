<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use PhpOffice\PhpSpreadsheet\IOFactory;
use sale\camp\Skill;

[$params, $providers] = eQual::announce([
    'description'   => "Generate children and guardians import files for Lathus from their old data.",
    'params'        => [],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$lang = 'fr';

$map_parent_old_to_new_guardian_id = [];
$map_parent_old_to_new_institution_id = [];

$type_parent_institutions = [
    'Foyer Mandela',
    'STRUCTURE',
    'famille d\'accueil',
    'Centre placement',
    'Directeur de structure'
];

$nom_parent_institutions = [
    'ALSEA 87', 'Lieu de Vie Timoun Yo', 'MECS', 'Association St Nicolas', 'Mme CAILLET', 'LVA L\'ETINCELLE', 'MECS La Bergerie', 'Action Enfance 86', 'AMESHAG 86',
    'LVA les Robinsons', 'LE VIEUX COLLEGE', 'IDEF 37', 'LVA Les Robins', 'LVA EMA', 'PFSE - Croix Rouge', 'Mme.BACLE', 'Mme. CHAUDRON',
    'LVA LE PASSAGE', 'M. Mme GROUPIL', 'Mme BOURDEAU', 'Mme.Robin_x000d_Mme.ROBIN', 'Mme PARESSANT', 'M. FAJARDO', 'AMESHAG 86', 'Mme.MAMODE', 'Mme. CASSANT', 'LDV SALVERT',
    'LE TEMPS VOULU', 'GAILLEDRAT', 'MEURANT', 'Mme MAISONNETTE', 'PFS LE POINTEAU', 'MECS Les Métives', 'LE POINTEAU 16', 'MDE 79', 'LVA Courte Echelle', 'Mme. VIGNE',
    'Mme. Nogueira', 'Mme.McCOMISH-LORAIN', 'Mme. Rivet', 'ADIASEAA 36', 'Mme CHAMBORD', 'CSE Caisse Ep', 'M. PLAURENDEAU', 'MDS 60',
    'LVA EQUI-PASSAGE', 'Centre Nouvel Horizon', 'CDEF Haute Vienne', 'Mme. COCHEGRUE', 'Mme MAISONNETTE', 'LVA Chant d oiseaux', 'M HOME 36',
    'DPDS 36', 'Mme. LUTGEN', 'La Vie devant soi', 'M. DECEMME', 'Mme. Lamarche', 'Mme. Lombard', 'LDV les Repères', 'Fondation VERDIER',
    'APLB16', 'MAE de Barroux', 'LVA EVEA', 'Mme. Ripoteau', 'MAEB', 'Mme.MARCHISIO', 'M. Riviere', 'PFS APLB16', 'La sauvegarde 95', 'FOYER CELINE LEBRET',
    'Mme DELORME', 'Vjoly', 'MAISON EFNANT ST FRAIGNE', 'Mme Chabenat', 'OUDART', 'La vie familial', 'Mme Boutet', 'Asso le temps voulu',
    'LDV la courte echelle', 'Mme Voldoire', 'Mme RENARD', 'LDV chant doiseaux', 'Fondation Action Enfance', 'Mme. Violle', 'Village d\'Enfants', 'Fondation Auteuil', 'BARBAULT',
    'UPASE MONJOIE', 'Mme buord', 'LVA Happyday', 'JOURDAIN', 'Interrogation', 'Centre Colbert ASE 36', 'FOYER EDUCATIF CELINE LEBRET', 'Foyer educatif Céline Lebret', 'Logement Jeune 93',
    'ASSOCIATION LE ST NICOLAS', 'Ass Passage', 'Mme.Vigne', 'ALSEA', 'ASE 36', 'UPE 79', 'Mme ALEXANDRE', 'UDAF 86', 'UDAF 37', 'LDV Le chant d\'Oiseaux', 'Aide Sociale à l\'Enfance',
    'Maison Départementale', 'M. Mme LEMAY - LEMERCIER', 'Foyer éducatif Mixte', 'Foyer des Métives', 'Foyer Mandela', 'FOYER Paul Nicolas', 'SC La Houchardiere', 'MDS Fontaine le Comte',
    'la vie familiale', 'Maison enfants', 'Pole enfance', 'Plate forme Sociale', 'LOGIS DES FERRIERES', 'La Courte Echelle', 'Lieu de Vie', 'LDV HAPPYDAYS', 'LVA MAISON DE MAILLE',
    'Foyer Céline Lebret', 'SOS VILLAGE ENFANTS'
];

/*************
 * Guardians *
 *************/

$guardian_mapping = [
    'num_parent'                => 'external_ref',
    'nom_parent'                => 'lastname',
    'prenom_parent'             => 'firstname',
    'adresse_parent'            => 'address_street',
    'adresse2_parent'           => 'address_dispatch',
    'code_postal_parent'        => 'address_zip',
    'ville_parent'              => 'address_city',
    'tel_maison'                => 'phone',
    'telephone_pere_parent'     => 'phone',
    'telephone_mere_parent'     => 'phone',
    'tel_portable_pere_parent'  => 'mobile',
    'tel_portable_mere_parent'  => 'mobile',
    'tel_travail_pere_parent'   => 'work_phone',
    'tel_travail_mere_parent'   => 'work_phone',
    'mail_parent'               => 'email'
];

$map_type_parent_relation_type = [
    'Structure'                                 => 'legal-tutor',
    'Educateur'                                 => 'legal-tutor',
    'Directeur du centre'                       => 'legal-tutor',
    'AMIS'                                      => 'legal-tutor',
    'Responsable légal'                         => 'legal-tutor',
    'Assistante sociale'                        => 'legal-tutor',
    'Chef de service'                           => 'legal-tutor',
    'PARENTS'                                   => 'legal-tutor',
    'parents'                                   => 'legal-tutor',
    'TUTRICE'                                   => 'legal-tutor',
    'Foyer Mandela'                             => 'legal-tutor',

    'Mère'                                      => 'mother',

    'Père'                                      => 'father',
    'PERE'                                      => 'father',

    'Grand Parent'                              => 'family-member',
    'Grand mere'                                => 'family-member',
    'Grands parent'                             => 'family-member',
    'Cousine'                                   => 'family-member',
    'Sœur'                                      => 'family-member',
    'tante'                                     => 'family-member',
    'tonton'                                    => 'family-member',

    'Centre Départemental d\'Action Sociale'    => 'departmental-council',

    'Assistante maternelle'                     => 'childminder',
    'Assistante Familiale'                      => 'childminder',
    'Assistante familiale'                      => 'childminder',
    'Assistante familliale'                     => 'childminder',
];

$map_titre_parent_relation_type = [
    'Mlle'  => 'mother',
    'Mme'   => 'mother',
    'Mr.'   => 'father'
];

$file_path = EQ_BASEDIR.'/packages/lathus/import/guardian.xlsx';
if(!file_exists($file_path)) {
    throw new Exception("missing_file", EQ_ERROR_INVALID_CONFIG);
}

$spreadsheet = IOFactory::load($file_path);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

$headers = array_map('trim', array_shift($rows));

$index_col_nom_parent = array_search('nom_parent', $headers);
$index_col_prenom_parent = array_search('prenom_parent', $headers);
$index_col_type_parent = array_search('type_parent', $headers);
$index_col_titre_parent = array_search('titre_parent', $headers);

$guardian_id = 0;
$guardians = [];
foreach($rows as $row) {
    $nom_parent = trim($row[$index_col_nom_parent]);
    $prenom_parent = trim($row[$index_col_prenom_parent]);
    $type_parent = trim($row[$index_col_type_parent]);
    // skip if is an institution
    if($row[array_keys($headers)[0]] === '14499' || (in_array($nom_parent, $nom_parent_institutions) && !$prenom_parent) || in_array($type_parent, $type_parent_institutions)) {
        continue;
    }

    $guardian_id++;
    if(isset($row[array_keys($headers)[0]])) {
        $map_parent_old_to_new_guardian_id[$row[array_keys($headers)[0]]] = $guardian_id;
    }

    $guardian = [
        'id'            => $guardian_id,
        'creator'       => 1,
        'modifier'      => 1,
        'state'         => 'instance'
    ];

    $titre_parent = trim($row[$index_col_titre_parent]);
    if(!empty($type_parent) && isset($map_type_parent_relation_type[$type_parent])) {
        $guardian['relation_type'] = $map_type_parent_relation_type[$type_parent];
    }
    elseif(!empty($titre_parent) && isset($map_titre_parent_relation_type[$titre_parent])) {
        $guardian['relation_type'] = $map_titre_parent_relation_type[$titre_parent];
    }

    foreach($headers as $col => $field) {
        if(!isset($row[$col])) {
            continue;
        }

        $value = trim($row[$col]);
        if(empty($value)) {
            continue;
        }

        $target = $guardian_mapping[$field] ?? null;
        if(is_null($target)) {
            continue;
        }

        if(in_array($target, ['phone', 'mobile', 'work_phone'])) {
            if($target === 'work_phone' && preg_match('/poste\s*(\d+)/i', $value, $matches)) {
                $guardian['work_phone_ext'] = $matches[1];
                $value = trim(preg_replace('/poste\s*\d+/i', '', $value));
            }

            $value = preg_replace('/\D/', '', $value);
            if(strlen($value) > 10 && $value[0] === '0' && $value[1] !== '0') {
                $value = substr($value, 0, 10);
            }
        }
        elseif($target === 'relation_type') {
            switch($field) {
                case 'type_parent':
                    $value = $map_type_parent_relation_type[$value];
                    break;
                case 'titre_parent':
                    $value = $map_titre_parent_relation_type[$value];
                    break;
            }
        }

        if(!isset($guardian[$target]) && !empty($value)) {
            $guardian[$target] = $value;
        }
    }

    $guardians[] = $guardian;
}

/****************
 * Institutions *
 ****************/

$institution_mapping = [
    'num_parent'                => 'external_ref',
    'nom_parent'                => 'name',
    'prenom_parent'             => 'name',
    'adresse_parent'            => 'address_street',
    'adresse2_parent'           => 'address_dispatch',
    'code_postal_parent'        => 'address_zip',
    'ville_parent'              => 'address_city',
    'tel_maison'                => 'phone',
    'telephone_pere_parent'     => 'phone',
    'telephone_mere_parent'     => 'phone',
    'tel_portable_pere_parent'  => 'mobile',
    'tel_portable_mere_parent'  => 'mobile',
    'tel_travail_pere_parent'   => 'work_phone',
    'tel_travail_mere_parent'   => 'work_phone',
    'mail_parent'               => 'email'
];

$file_path = EQ_BASEDIR.'/packages/lathus/import/guardian.xlsx';
if(!file_exists($file_path)) {
    throw new Exception("missing_file", EQ_ERROR_INVALID_CONFIG);
}

$spreadsheet = IOFactory::load($file_path);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

$headers = array_map('trim', array_shift($rows));

$index_col_nom_parent = array_search('nom_parent', $headers);
$index_col_prenom_parent = array_search('prenom_parent', $headers);
$index_col_type_parent = array_search('type_parent', $headers);
$index_col_titre_parent = array_search('titre_parent', $headers);

$institution_id = 0;
$institutions = [];
foreach($rows as $row) {
    $nom_parent = trim($row[$index_col_nom_parent]);
    $prenom_parent = trim($row[$index_col_prenom_parent]);
    $type_parent = trim($row[$index_col_type_parent]);
    // skip if isn't an institution
    if($row[array_keys($headers)[0]] !== '14499' && (!in_array($nom_parent, $nom_parent_institutions) || $prenom_parent) && !in_array($type_parent, $type_parent_institutions)) {
        continue;
    }

    $institution_id++;
    if(isset($row[array_keys($headers)[0]])) {
        $map_parent_old_to_new_institution_id[$row[array_keys($headers)[0]]] = $institution_id;
    }

    $institution = [
        'id' => $institution_id
    ];

    foreach($headers as $col => $field) {
        if(!isset($row[$col])) {
            continue;
        }

        $value = trim($row[$col]);
        if(empty($value)) {
            continue;
        }

        $target = $institution_mapping[$field] ?? null;
        if(is_null($target)) {
            continue;
        }

        if($target === 'name' && !empty($institution['name'])) {
            $institution['name'] = $institution['name'].' '.$value;
        }
        elseif(in_array($target, ['phone', 'mobile', 'work_phone'])) {
            if($target === 'work_phone' && preg_match('/poste\s*(\d+)/i', $value, $matches)) {
                $institution['work_phone_ext'] = $matches[1];
            }

            $value = preg_replace('/\D/', '', $value);
            if(!empty($value) && $value[0] === '0' && strlen($value) > 10) {
                $value = substr($value, 0, 10);
            }
        }

        if(!isset($institution[$target]) && !empty($value)) {
            $institution[$target] = $value;
        }
    }

    $institutions[] = $institution;
}

/************
 * Children *
 ************/

$child_mapping = [
    'num_enfant'            => 'external_ref',
    'nom_enfant'            => 'lastname',
    'prenom_enfant'         => 'firstname',
    'sexe_enfant'           => 'gender',
    'date_naissance_enfant' => 'birthdate',
    'num_license_enfant'    => 'license_ffe'
];

$map_gender = [
    'G' => 'M',
    'g' => 'M',
    'F' => 'F',
    'f' => 'F'
];

$file_path = EQ_BASEDIR.'/packages/lathus/import/child.xlsx';
if(!file_exists($file_path)) {
    throw new Exception("missing_file", EQ_ERROR_INVALID_CONFIG);
}

$spreadsheet = IOFactory::load($file_path);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

$headers = array_map('trim', array_shift($rows));

$child_id = 0;
$children = [];
foreach($rows as $row) {
    $child = [
        'id'                => ++$child_id,
        'creator'           => 1,
        'modifier'          => 1,
        'state'             => 'instance',
        'guardians_ids'     => [],
        'skills_ids'        => [],
        'has_license_ffe'   => false
    ];

    $address = [
        'street'    => '',
        'dispatch'  => '',
        'zip'       => '',
        'city'      => ''
    ];

    foreach($headers as $col => $field) {
        if(!isset($row[$col])) {
            continue;
        }

        $value = trim($row[$col]);
        if(empty($value)) {
            continue;
        }

        // handle fields without mapping
        switch($field) {
            case 'num_parent_enfant':
                if(isset($map_parent_old_to_new_guardian_id[$value])) {
                    $child['guardians_ids'] = [$map_parent_old_to_new_guardian_id[$value]];
                    $child['main_guardian_id'] = $map_parent_old_to_new_guardian_id[$value];
                }
                elseif(isset($map_parent_old_to_new_institution_id[$value])) {
                    $child['institution_id'] = $map_parent_old_to_new_institution_id[$value];
                    $child['is_foster'] = true;
                }
                continue 2;
            case 'galop_enfant':
                $galop_skill = Skill::search(['name', 'ilike', '%galop '.$value.'%'])
                    ->read(['id'])
                    ->first();

                if(!is_null($galop_skill)) {
                    $child['skills_ids'][] = $galop_skill['id'];
                }
                continue 2;
            case 'num_license_enfant':
                $child['has_license_ffe'] = true;
                $child['license_ffe'] = $value;
                continue 2;
            case 'adresse_enfant':
                $address['street'] = $value;
                continue 2;
            case 'adresse2_enfant':
                $address['dispatch'] = $value;
                continue 2;
            case 'code_postal_enfant':
                $address['zip'] = $value;
                continue 2;
            case 'ville_enfant':
                $address['city'] = $value;
                continue 2;
        }

        $target = $child_mapping[$field] ?? null;
        if(is_null($target)) {
            continue;
        }

        if($target === 'gender') {
            if(isset($map_gender[$value])) {
                $value = $map_gender[$value];
            }
        }
        elseif($target === 'birthdate') {
            $date = DateTime::createFromFormat('m/d/Y', $value);
            $value = $date->format('Y-m-d');
        }

        if(!isset($child[$target])) {
            $child[$target] = $value;
        }
    }

    $children[] = $child;

    if(isset($child['guardians_ids'][0])) {
        foreach($guardians as &$guardian) {
            if($guardian['id'] !== $child['guardians_ids'][0]) {
                continue;
            }

            if(
                !is_null($guardian)
                && (!empty($address['street']) && !empty($address['zip'])  && !empty($address['city']))
                && (empty($guardian['address_street']) || empty($guardian['address_zip']) || empty($guardian['address_city']))
            ) {
                $guardian['address_street'] = $address['street'];
                $guardian['address_dispatch'] = !empty($address['dispatch']) ? $address['dispatch'] : null;
                $guardian['address_zip'] = $address['zip'];
                $guardian['address_city'] = $address['city'];
                break;
            }
        }
    }
    elseif(isset($child['institution_id'])) {
        foreach($institutions as &$institution) {
            if(isset($child['institution_id']) && $institution['id'] !== $child['institution_id']) {
                continue;
            }

            if(
                !is_null($institution)
                && (!empty($address['street']) && !empty($address['zip'])  && !empty($address['city']))
                && (empty($institution['address_street']) || empty($institution['address_zip']) || empty($institution['address_city']))
            ) {
                $institution['address_street'] = $address['street'];
                $institution['address_dispatch'] = !empty($address['dispatch']) ? $address['dispatch'] : null;
                $institution['address_zip'] = $address['zip'];
                $institution['address_city'] = $address['city'];
                break;
            }
        }
    }
}

$data = [
    [
        'name' => 'sale\camp\Guardian',
        'lang' => $lang,
        'data' => $guardians
    ],
    [
        'name' => 'sale\camp\Institution',
        'lang' => $lang,
        'data' => $institutions
    ],
    [
        'name' => 'sale\camp\Child',
        'lang' => $lang,
        'data' => $children
    ]
];

$timestamp = date('Ymd_His');

$import_folder_path = EQ_BASEDIR."/import/$timestamp";
if(!mkdir($import_folder_path, 0754, true)) {
    throw new Exception(serialize(["folder_creation_error" => "unable to create output folder $import_folder_path"]), EQ_ERROR_UNKNOWN);
}

$output_file = "$import_folder_path/import_$timestamp.json";
file_put_contents($output_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$context->httpResponse()
        ->status(204)
        ->send();
