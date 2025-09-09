<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use identity\CenterOffice;

[$params, $providers] = eQual::announce([
    'description'   => "Cette action clôturera l'année comptable. Les nouvelles factures seront désormais sur l'année en cours et il ne sera plus possible d'émettre des factures sur l'année précédente.\n
                        ATTENTION: cette opération en peut pas être annulée.",
    'params'        => [],
    'access'        => [
        'visibility'    => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$increment_year = function(string $date_str): string {
    // "2024-01-01" → "2025-01-01"
    $parts = explode('-', $date_str);
    if (count($parts) !== 3) {
        throw new Exception('invalid_date_format', EQ_ERROR_INVALID_CONFIG);
    }
    return sprintf('%04d-%02d-%02d', intval($parts[0]) + 1, intval($parts[1]), intval($parts[2]));
};


$fiscal_year = Setting::get_value('finance', 'accounting', 'fiscal_year');

if(!$fiscal_year) {
    throw new Exception("missing_fiscal_year", EQ_ERROR_INVALID_CONFIG);
}

$date_from = Setting::get_value('finance', 'accounting', 'fiscal_year.date_from');
$date_to = Setting::get_value('finance', 'accounting', 'fiscal_year.date_to');

if(!$date_from || !$date_to) {
    throw new Exception("missing_fiscal_year_dates", EQ_ERROR_INVALID_CONFIG);
}

$new_date_from = $increment_year($date_from);
$new_date_to   = $increment_year($date_to);

$date = time();

if($date < strtotime($new_date_from) || $date > strtotime($new_date_to)) {
    throw new Exception("fiscal_year_mismatch", EQ_ERROR_CONFLICT_OBJECT);
}

$from_year = intval(substr($new_date_from, 0, 4));
$to_year   = intval(substr($new_date_to, 0, 4));

$new_fiscal_year = (string) $from_year;

// update fiscal year to current year
Setting::set_value('finance', 'accounting', 'fiscal_year.date_from', $new_date_from);
Setting::set_value('finance', 'accounting', 'fiscal_year.date_to',   $new_date_to);
Setting::set_value('finance', 'accounting', 'fiscal_year', $new_fiscal_year);

// reset invoice sequences for all Center Offices
$center_offices = CenterOffice::search()->read(['id', 'code'])->get(true);
foreach($center_offices as $center_office) {
    Setting::set_value('sale', 'accounting', 'invoice.sequence.'.$center_office['code'], 1);
}

$context->httpResponse()
        ->status(204)
        ->send();
