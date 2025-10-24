<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use lathus\sale\camp\Guardian as LathusGuardian;
use lathus\sale\camp\Institution as LathusInstitution;
use sale\booking\Payment;
use sale\camp\Camp;
use sale\camp\Child;
use sale\camp\Enrollment;
use sale\camp\followup\Task;
use sale\camp\Guardian;
use sale\camp\Institution;
use sale\camp\price\PriceAdapter;
use sale\camp\Sponsor;
use sale\camp\WorksCouncil;
use sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => "Pull enrollments from CPA Lathus API.",
    'params'        => [
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
['context' => $context, 'orm' => $orm, 'dispatch' => $dispatch] = $providers;

/**
 * Methods
 */

$sanitizePhoneNumber = function($phone_number) {
    $phone_number = str_replace(['.', '/', ' ', '-'], '', $phone_number);
    return substr($phone_number, 0, 16);
};

$normalizeName = function($city) {
    $city = iconv('UTF-8', 'ASCII//TRANSLIT', $city);
    $city = preg_replace("/[^a-zA-Z0-9]/", "", $city);
    return strtolower($city);
};

$findOrCreateGuardian = function($ext_guardian, $ext_child, $child_id) use($sanitizePhoneNumber) {
    $guardian = null;

    $child = Child::id($child_id)
        ->read(['guardians_ids'])
        ->first();

    if(!empty($child['guardians_ids'])) {
        $guardian = Guardian::search([
            ['firstname', 'ilike', trim($ext_guardian['prenom'])],
            ['lastname', 'ilike', trim($ext_guardian['nom'])],
            ['id', 'in', $child['guardians_ids']]
        ])
            ->read(['id'])
            ->first();
    }
    elseif(isset($ext_guardian['telephonePortable']) || isset($ext_guardian['telephoneDomicile']) || isset($ext_guardian['telephoneTravail'])) {
        $domain = [
            ['firstname', 'ilike', trim($ext_guardian['prenom'])],
            ['lastname', 'ilike', trim($ext_guardian['nom'])]
        ];
        if(!empty($ext_guardian['telephonePortable'])) {
            $domain[] = ['mobile', '=', trim($ext_guardian['telephonePortable'])];
        }
        elseif(!empty($ext_guardian['telephoneDomicile'])) {
            $domain[] = ['phone', '=', trim($ext_guardian['telephoneDomicile'])];
        }
        elseif(!empty($ext_guardian['telephoneTravail'])) {
            $domain[] = ['work_phone', '=', trim($ext_guardian['telephoneTravail'])];
        }

        $guardian = Guardian::search([
            ['firstname', 'ilike', trim($ext_guardian['prenom'])],
            ['lastname', 'ilike', trim($ext_guardian['nom'])]
        ])
            ->read(['id'])
            ->first();

        if(!is_null($guardian)) {
            Guardian::id($guardian['id'])
                ->update(['children_ids' => [$child['id']]]);
        }
    }

    if(is_null($guardian)) {
        $guardian_data = [
            'firstname'         => ucwords(strtolower($ext_guardian['prenom'])),
            'lastname'          => $ext_guardian['nom'],
            'email'             => $ext_child['mail'],
            'relation_type'     => 'other',
            'address_street'    => $ext_child['adresse1'],
            'address_dispatch'  => $ext_child['adresse2'],
            'address_zip'       => $ext_child['codePostal'],
            'address_city'      => $ext_child['ville'],
            'children_ids'      => [$child['id']]
        ];

        if(!empty($ext_guardian['telephonePortable'])) {
            $guardian_data['mobile'] = $sanitizePhoneNumber($ext_guardian['telephonePortable']);
        }

        if(!empty($ext_guardian['telephoneDomicile'])) {
            $guardian_data['phone'] = $sanitizePhoneNumber($ext_guardian['telephoneDomicile']);
        }
        elseif(!empty($ext_child['telephone'])) {
            $guardian_data['phone'] = $sanitizePhoneNumber($ext_child['telephone']);
        }

        if(!empty($ext_guardian['telephoneTravail'])) {
            $guardian_data['work_phone'] = $sanitizePhoneNumber($ext_guardian['telephoneTravail']);
        }

        $guardian = LathusGuardian::create($guardian_data)
            ->read(['id'])
            ->first();
    }

    return $guardian;
};

// Do "cache" institutions to not call db every findOrCreate
$institutions = [];

$findOrCreateInstitution = function($ext_institution) use($sanitizePhoneNumber, $normalizeName, &$institutions) {
    if(empty($institutions)) {
        $institutions = Institution::search()
            ->read(['name'])
            ->get();
    }

    $normalized_ext_institution_name = $normalizeName($ext_institution['nom']);

    $institution = null;
    foreach($institutions as $inst) {
        if($normalized_ext_institution_name === $normalizeName($inst['name'])) {
            $institution = $inst;
        }
    }

    if(is_null($institution)) {
        $institution = LathusInstitution::create([
            'name'              => $ext_institution['nom'],
            'address_street'    => $ext_institution['adresse1'],
            'address_dispatch'  => $ext_institution['adresse2'],
            'address_zip'       => $ext_institution['codePostal'],
            'address_city'      => $ext_institution['ville'],
            'email'             => $ext_institution['mail'],
            'phone'             => $sanitizePhoneNumber($ext_institution['telephone'])
        ])
            ->read(['name'])
            ->first();

        // add created institution to "cache"
        $institutions[] = $institution;
    }

    return $institution;
};

$currency = Setting::get_value('core', 'locale', 'currency', '€');
$formatMoney = function ($value) use($currency) {
    return number_format((float)($value), 2, ",", ".") . ' ' .$currency;
};

/**
 * Action
 */

/*
    1) Fetch enrollments from CPA Lathus API
*/

$data = [];

$count_attempts = 0;
$flag_success = false;
while(!$flag_success) {
    try {
        $data = eQual::run('get', 'lathus_camp_enrollments');
        $flag_success = true;
    }
    catch(Exception $e) {
        ++$count_attempts;
    }

    if(!$flag_success && $count_attempts >= 3) {
        throw new Exception('cpa_lathus_api_unreachable', QN_ERROR_UNKNOWN);
    }
}

usort($data, function($a, $b) {
    return (new DateTime($a['date']))->getTimestamp() <=> (new DateTime($b['date']))->getTimestamp();
});

/*
    2) Add enrollments that haven't been added yet
*/

if(!empty($data)) {
    //  2.1) Remove already handled enrollments from data received

    $fetched_external_refs = [];
    foreach($data as $ext_enrollment) {
        $fetched_external_refs[] = $ext_enrollment['wpOrderId'];
    }

    $handled_enrollments = [];
    if(!empty($fetched_external_refs)) {
        $handled_enrollments = Enrollment::search(['external_ref', 'in', $fetched_external_refs])
            ->read(['external_ref'])
            ->get(true);
    }

    $handled_enrollments_ext_refs = array_column($handled_enrollments, 'external_ref');

    foreach($data as $index => $ext_enrollment) {
        if(in_array($ext_enrollment['wpOrderId'], $handled_enrollments_ext_refs)) {
            unset($data[$index]);
        }
    }

    //  2.2) Create camp map on sojourn_number key

    $map_soj_nums = [];
    foreach($data as $ext_enrollment) {
        $map_soj_nums[$ext_enrollment['metaJson']['numeroCamp']] = true;
    }

    $camps = Camp::search([
        ['sojourn_number', 'in', array_keys($map_soj_nums)],
        ['date_from', '>=', strtotime('first day of january this year')],
        ['date_from', '<', strtotime('last day of december this year')]
    ])
        ->read(['sojourn_number', 'saturday_morning_product_id'])
        ->get();

    $map_soj_nums_camps = [];
    foreach($camps as $camp) {
        $map_soj_nums_camps[$camp['sojourn_number']] = $camp;
    }

    //  2.3) Add external enrollments

    foreach($data as $ext_enrollment) {
        if(!isset($map_soj_nums_camps[$ext_enrollment['metaJson']['numeroCamp']])) {
            continue;
        }

        $camp = $map_soj_nums_camps[$ext_enrollment['metaJson']['numeroCamp']];

        //  2.3.1) Find/create child

        $ext_child = $ext_enrollment['metaJson']['enfant'];

        $ext_child_birthdate = DateTime::createFromFormat('d/m/Y', $ext_child['dateDeNaissance'])->getTimestamp();
        $ext_child_gender = $ext_child['sexe'] === 'Fille' ? 'F' : 'M';

        $child = Child::search([
            ['firstname', 'ilike', trim($ext_child['prenom'])],
            ['lastname', 'ilike', trim($ext_child['nom'])],
            ['birthdate', '=', $ext_child_birthdate],
            ['gender', '=', $ext_child_gender]
        ])
            ->read(['id'])
            ->first();

        if(is_null($child)) {
            $ext_child_horseriding = $ext_enrollment['metaJson']['equitation'] ?? null;

            $child = Child::create([
                'firstname'         => ucwords(strtolower($ext_child['prenom'])),
                'lastname'          => $ext_child['nom'],
                'birthdate'         => $ext_child_birthdate,
                'gender'            => $ext_child_gender,
                'is_cpa_member'     => $ext_enrollment['metaJson']['aides']['hasClubCpa'] ?? false,
                'cpa_club'          => $ext_enrollment['metaJson']['aides']['clubCpa'] ?? null,
                'has_license_ffe'   => !is_null($ext_child_horseriding),
                'license_ffe'       => $ext_child_horseriding ? $ext_child_horseriding['dernierGalopValide'] : null,
                'year_license_ffe'  => $ext_child_horseriding ? $ext_child_horseriding['anneeLicence'] : null,
                'external_ref'      => $ext_enrollment['wpOrderId']
            ])
                ->read(['id'])
                ->first();
        }

        //  2.3.2) Create guardians and institution

        //  2.3.2.1) Create main guardian

        $main_guardian = $findOrCreateGuardian($ext_enrollment['metaJson']['pere'], $ext_child, $child['id']);

        Child::id($child['id'])->update(['main_guardian_id' => $main_guardian['id']]);

        //  2.3.2.2) Create second guardian or institution

        switch($ext_enrollment['metaJson']['typeReservation']) {
            case 'Particulier':
                $ext_second_guardian = $ext_enrollment['metaJson']['mere'];

                if(!empty($ext_second_guardian['prenom']) && !empty($ext_second_guardian['nom'])) {
                    $findOrCreateGuardian($ext_second_guardian, $ext_child, $child['id']);
                }
                break;
            case 'Structure':
                $institution = $findOrCreateInstitution($ext_enrollment['metaJson']['institution']);

                Child::id($child['id'])->update([
                    'is_foster'         => true,
                    'institution_id'    => $institution['id']
                ]);

                break;
        }

        //  2.3.4) Create enrollment

        $c = Camp::id($camp['id'])
            ->read(['max_children', 'enrollments_qty'])
            ->first();

        $enrollment_status = 'pending';
        if($c['enrollments_qty'] >= $c['max_children']) {
            $enrollment_status = 'waitlisted';
        }

        $enrollment = Enrollment::create([
            'date_created'      => (new DateTime($ext_enrollment['date']))->getTimestamp(),
            'camp_id'           => $camp['id'],
            'child_id'          => $child['id'],
            'main_guardian_id'  => $main_guardian['id'],
            'is_external'       => true,
            'external_ref'      => $ext_enrollment['wpOrderId'],
            'external_data'     => json_encode($ext_enrollment),
            'status'            => $enrollment_status
        ])
            ->read(['center_office_id', 'camp_id' => ['date_from']])
            ->first();

        $enrollment_warnings = [];

        //  2.3.5) Handle adding additional enrollment lines if child stays on the weekend or until Saturday

        $saturday_morning = false;
        $weekend = false;
        foreach($ext_enrollment['metaJson']['sejour']['montantOptionPourCalcul'] as $option) {
            if(strpos($option, 'Option 1') === 0) {
                $saturday_morning = true;
            }
            elseif(strpos($option, 'Option 2') === 0) {
                $weekend = true;
            }
        }

        if($saturday_morning !== $weekend) {
            $weekend_extra = $saturday_morning ? 'saturday-morning' : 'full';

            Enrollment::id($enrollment['id'])->update(['weekend_extra' => $weekend_extra]);
        }
        elseif($saturday_morning && $weekend) {
            Enrollment::id($enrollment['id'])->update(['weekend_extra' => 'full']);

            $dispatch->dispatch('lodging.camp.pull_enrollments.weekend_extra_inconsistency', 'sale\camp\Enrollment', $enrollment['id'], 'warning', null, [], [], null, 1);
        }

        //  2.3.5) Create price adapters for: sponsors, works councils, loyalty discounts and custom discounts

        $discount_amount = 0;

        if(isset($ext_enrollment['metaJson']['reductions'])) {
            $sponsorings = $ext_enrollment['metaJson']['reductions'];

            //  2.3.5.1) Handle sponsor "commune"

            if(!empty($sponsorings['montantAideCommunePourCalcul'])) {
                $sponsor_name = preg_replace("/\s*\([^)]*\)/", "", $sponsorings['montantAideCommunePourCalcul']);

                $sponsor = Sponsor::search([
                    ['name', 'ilike', $sponsor_name],
                    ['sponsor_type', '=', 'commune']
                ])
                    ->read(['name', 'amount', 'sponsor_type'])
                    ->first();

                if(!is_null($sponsor)) {
                    PriceAdapter::create([
                        'enrollment_id'         => $enrollment['id'],
                        'sponsor_id'            => $sponsor['id'],
                        'name'                  => $sponsor['name'],
                        'value'                 => intval($sponsorings['montantAideCommune']),
                        'origin_type'           => $sponsor['sponsor_type'],
                        'price_adapter_type'    => 'amount',
                        'is_manual_discount'    => false
                    ]);
                }
                else {
                    PriceAdapter::create([
                        'enrollment_id'         => $enrollment['id'],
                        'name'                  => $sponsorings['montantAideCommunePourCalcul'],
                        'value'                 => intval($sponsorings['montantAideCommune']),
                        'origin_type'           => 'commune',
                        'price_adapter_type'    => 'amount',
                        'is_manual_discount'    => false
                    ]);

                    $dispatch->dispatch('lodging.camp.pull_enrollments.sponsor_not_found', 'sale\camp\Enrollment', $enrollment['id'], 'important', null, [], [], null, 1);

                    $enrollment_warnings[] = "Nom de l'aidant (commune) non trouvé : {$sponsorings['montantAideCommunePourCalcul']}";
                }
            }

            //  2.3.5.2) Handle sponsor "community-of-communes"

            if(!empty($sponsorings['montantPriseEnChargePourCalcul'])) {
                $sponsor_name = preg_replace("/\s*\([^)]*\)/", "", $sponsorings['montantPriseEnChargePourCalcul']);

                $sponsor = Sponsor::search([
                    ['name', 'ilike', $sponsor_name],
                    ['sponsor_type', '=', 'community-of-communes']
                ])
                    ->read(['name', 'amount', 'sponsor_type'])
                    ->first();

                if(!is_null($sponsor)) {
                    PriceAdapter::create([
                        'enrollment_id'         => $enrollment['id'],
                        'sponsor_id'            => $sponsor['id'],
                        'name'                  => $sponsor['name'],
                        'value'                 => intval($sponsorings['montantPriseEnCharge']),
                        'origin_type'           => $sponsor['sponsor_type'],
                        'price_adapter_type'    => 'amount',
                        'is_manual_discount'    => false
                    ]);
                }
                else {
                    PriceAdapter::create([
                        'enrollment_id'         => $enrollment['id'],
                        'name'                  => $sponsorings['montantPriseEnChargePourCalcul'],
                        'value'                 => intval($sponsorings['montantPriseEnCharge']),
                        'origin_type'           => 'community-of-communes',
                        'price_adapter_type'    => 'amount',
                        'is_manual_discount'    => true
                    ]);

                    $dispatch->dispatch('lodging.camp.pull_enrollments.sponsor_not_found', 'sale\camp\Enrollment', $enrollment['id'], 'important', null, [], [], null, 1);

                    $enrollment_warnings[] = "Nom de l'aidant (communauté de communes) non trouvé : {$sponsorings['montantPriseEnChargePourCalcul']}";
                }
            }

            //  2.3.5.3) Handle works council

            if(!empty($sponsorings['montantComiteEntreprisePourCalcul'])) {
                $works_council = WorksCouncil::search(['name', 'ilike', $sponsorings['montantComiteEntreprisePourCalcul']])
                    ->read(['code'])
                    ->first();

                if(!is_null($works_council)) {
                    if(isset($ext_enrollment['metaJson']['code']['ce']) && strtoupper(trim($ext_enrollment['metaJson']['code']['ce'])) === strtoupper($works_council['code'])) {
                        Enrollment::id($enrollment['id'])
                            ->update(['works_council_id' => $works_council['id']]);
                    }
                    else {
                        $dispatch->dispatch('lodging.camp.pull_enrollments.work_council_wrong_code', 'sale\camp\Enrollment', $enrollment['id'], 'warning', null, [], [], null, 1);

                        if(empty($ext_enrollment['metaJson']['code']['ce'])) {
                            $enrollment_warnings[] = "Aucun code CE renseigné par le client.";
                        }
                        else {
                            $enrollment_warnings[] = "Code CE renseigné par client : {$ext_enrollment['metaJson']['code']['ce']}";
                        }
                    }
                }
                else {
                    $dispatch->dispatch('lodging.camp.pull_enrollments.work_council_not_found', 'sale\camp\Enrollment', $enrollment['id'], 'important', null, [], [], null, 1);

                    $enrollment_warnings[] = "Nom du CE (conseil d'entreprise) non trouvé : {$sponsorings['montantComiteEntreprisePourCalcul']}";
                }
            }

            //  2.3.5.4) Handle loyalty discount

            if(!empty($sponsorings['montantOptionFidelitePourCalcul'])) {
                $discount = null;
                if(strpos($sponsorings['montantOptionFidelitePourCalcul'], 'Option 1') === 0) {
                    $discount = '80_euro';
                }
                elseif(strpos($sponsorings['montantOptionFidelitePourCalcul'], 'Option 2') === 0) {
                    $discount = '10_percent';
                }

                switch($discount) {
                    case '80_euro':
                        PriceAdapter::create([
                            'enrollment_id'         => $enrollment['id'],
                            'name'                  => "Réduction de 80 €",
                            'description'           => "Réduction de 80 € sur le 3e séjour de la même famille (frère ou sœur)",
                            'value'                 => floatval($sponsorings['montantOptionFidelite']),
                            'origin_type'           => 'loyalty-discount',
                            'price_adapter_type'    => 'amount',
                            'is_manual_discount'    => true
                        ]);
                        break;
                    case '10_percent':
                        // Use amount "price_adapter_type" for 10% to be sure to have same discount as the one proposed online
                        PriceAdapter::create([
                            'enrollment_id'         => $enrollment['id'],
                            'name'                  => "Réduction de 10%",
                            'description'           => "Réduction de 10% sur le tarif du 2e séjour du même enfant",
                            'value'                 => floatval($sponsorings['montantOptionFidelite']),
                            'origin_type'           => 'loyalty-discount',
                            'price_adapter_type'    => 'amount',
                            'is_manual_discount'    => true
                        ]);
                        break;
                }

                $discount_amount += floatval($sponsorings['montantOptionFidelite']);
            }

            //  2.3.5.5) Handle custom discount

            if(!empty($sponsorings['montantAutre'])) {
                PriceAdapter::create([
                    'enrollment_id'         => $enrollment['id'],
                    'name'                  => !empty($sponsorings['libelleAutre']) ? $sponsorings['libelleAutre'] : "Autre aide (préciser)",
                    'description'           => "Aide entrée par l'utilisateur lors de l'inscription en ligne.",
                    'value'                 => floatval($sponsorings['montantAutre']),
                    'origin_type'           => 'other',
                    'price_adapter_type'    => 'amount',
                    'is_manual_discount'    => true
                ]);

                $discount_amount += floatval($sponsorings['montantAutre']);
            }
        }

        //  2.3.6) Handle help from CAF and MSA

        if(isset($ext_enrollment['metaJson']['aides'])) {
            $helps = $ext_enrollment['metaJson']['aides'];

            if(!empty($helps['montantCaf'])) {
                PriceAdapter::create([
                    'enrollment_id'         => $enrollment['id'],
                    'name'                  => "CAF".(!empty(trim($helps['libelleCaf'])) ? ': "'.trim($helps['libelleCaf']).'"' : ''),
                    'description'           => "Aide CAF".(!empty(trim($helps['libelleCaf'])) ? ': "'.trim($helps['libelleCaf']).'"' : ''),
                    'value'                 => floatval($helps['montantCaf']),
                    'origin_type'           => 'department-caf',
                    'price_adapter_type'    => 'amount',
                    'is_manual_discount'    => true
                ]);
            }

            if(!empty($helps['montantMSA'])) {
                PriceAdapter::create([
                    'enrollment_id'         => $enrollment['id'],
                    'name'                  => "MSA".(!empty(trim($helps['libelleMSA'])) ? ': "'.trim($helps['libelleMSA']).'"' : ''),
                    'description'           => "Aide MSA".(!empty(trim($helps['libelleMSA'])) ? ': "'.trim($helps['libelleMSA']).'"' : ''),
                    'value'                 => floatval($helps['montantMSA']),
                    'origin_type'           => 'department-msa',
                    'price_adapter_type'    => 'amount',
                    'is_manual_discount'    => true
                ]);
            }
        }

        //  2.3.7) Force price of the enrollment to the one given by Lathus API

        $ext_price = floatval($ext_enrollment['metaJson']['sejour']['montantTotal']);
        if(!empty($ext_enrollment['metaJson']['sejour']['montantOption'])) {
            $ext_price += floatval($ext_enrollment['metaJson']['sejour']['montantOption']);
        }

        $enrollment_price = Enrollment::id($enrollment['id'])->read(['price'])->first()['price'];
        $ext_price_discounted = $ext_price - $discount_amount;
        if($enrollment_price !== $ext_price_discounted) {
            $dispatch->dispatch('lodging.camp.pull_enrollments.price_mismatch', 'sale\camp\Enrollment', $enrollment['id'], 'warning', null, [], [], null, 1);

            $enrollment_warnings[] = "Prix calculé par le site web {$formatMoney($ext_price_discounted)}";
            $enrollment_warnings[] = "Prix calculé par Discope {$formatMoney($enrollment_price)}";
        }

        //  2.3.9) Confirm enrollment to generate its funding

        if($enrollment_status === 'pending') {
            try {
                //  If spot available confirm, else add to waiting list
                eQual::run('do', 'sale_camp_enrollment_confirm', [
                    'id' => $enrollment['id']
                ]);
            }
            catch(Exception $e) {
                trigger_error("APP::sale_camp_enrollment_confirm unable to confirm/waitlist the enrollment", E_USER_WARNING,);
            }
            finally {
                $en = Enrollment::id($enrollment['id'])
                    ->read(['status'])
                    ->first();
            }
        }

        //  2.3.10) Handle payments

        if(!empty($ext_enrollment['metaJson']['reglement']['montantChequesVacances'])) {
            Task::create([
                'enrollment_id' => $enrollment['id'],
                'name'          => 'Réception chèque vacances',
                'notes'         => "Montant du chèque vacances attendu {$formatMoney($ext_enrollment['metaJson']['reglement']['montantChequesVacances'])}"
            ]);
        }

        if(isset($ext_enrollment['metaJson']['payment']['method'])) {
            switch($ext_enrollment['metaJson']['payment']['method']) {
                case 'cheque':
                    if(!empty($ext_enrollment['metaJson']['reglement']['montantCheque'])) {
                        Task::create([
                            'enrollment_id' => $enrollment['id'],
                            'name'          => 'Réception chèque bancaire',
                            'notes'         => "Montant du chèque bancaire attendu {$formatMoney($ext_enrollment['metaJson']['reglement']['montantCheque'])}"
                        ]);
                    }
                    break;
                case 'monetico':
                    if(!empty($ext_enrollment['metaJson']['reglement']['montantCB'])) {
                        $funding = Funding::search(['enrollment_id', '=', $enrollment['id']])
                            ->read(['id'])
                            ->first();

                        if(is_null($funding)) {
                            //  If not confirmed we need to create the funding anyway to handle the payment
                            Enrollment::id($enrollment['id'])->do('generate_funding');

                            $funding = Funding::search(['enrollment_id', '=', $enrollment['id']])
                                ->read(['id'])
                                ->first();
                        }

                        Payment::create([
                            'enrollment_id'     => $enrollment['id'],
                            'center_office_id'  => $enrollment['center_office_id'],
                            'is_manual'         => false,
                            'amount'            => floatval($ext_enrollment['metaJson']['reglement']['montantCB']),
                            'payment_origin'    => 'online',
                            'payment_method'    => 'bank_card'
                        ])
                            ->update(['funding_id' => $funding['id']]);
                    }
                    break;
            }
        }

        if(!empty($enrollment_warnings)) {
            Enrollment::id($enrollment['id'])->update([
                'description' => implode('', array_map(fn($error) => "<p>$error</p>", $enrollment_warnings))
            ]);
        }
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
