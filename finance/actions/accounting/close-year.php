<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use discope\setting\Setting;
use identity\CenterOffice;

[$params, $providers] = eQual::announce([
    'description'   => "This action will close the accounting year. New invoices will now be for the current year, and it will no longer be possible to issue invoices for the previous year.",
    'help'          => "WARNING:  this action cannot be cancelled.",
    'params'        => [
    ],
    'access'        => [
        'visibility'    => 'private'
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

$incrementYear = function(string $date_str): string {
    // "2024-01-01" → "2024-12-31"
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

$new_date_from = $incrementYear($date_from);
$new_date_to = $incrementYear($date_to);

$date = time();

if($date < strtotime($new_date_from) || $date > strtotime($new_date_to)) {
    throw new Exception("fiscal_year_mismatch", EQ_ERROR_CONFLICT_OBJECT);
}

$fiscal_year_format = Setting::get_value('finance', 'accounting', 'fiscal_year.format', '%4d{from_year}');

$from_year = intval(substr($new_date_from, 0, 4));
$to_year   = intval(substr($new_date_to, 0, 4));

$new_fiscal_year = Setting::parse_format($fiscal_year_format, [
    'from_year' => $from_year,
    'to_year'   => $to_year
]);

// update fiscal year to current year
Setting::set_value('finance', 'accounting', 'fiscal_year.date_from', $new_date_from);
Setting::set_value('finance', 'accounting', 'fiscal_year.date_to', $new_date_to);
Setting::set_value('finance', 'accounting', 'fiscal_year', $new_fiscal_year);

// reset invoice sequences for all Center Offices
$center_offices = CenterOffice::search()->read(['id', 'code'])->get(true);
foreach($center_offices as $center_office) {
    Setting::set_sequence('sale', 'accounting', 'invoice.sequence.'.$center_office['code'], 1);
}

$context
    ->httpResponse()
    ->status(204)
    ->send();
