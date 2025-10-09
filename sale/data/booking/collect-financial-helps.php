<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use equal\orm\Domain;
use equal\orm\DomainCondition;
use sale\booking\Booking;
use sale\catalog\Product;

[$params, $providers] = eQual::announce([
    'description'   => "Collects the booking lines that uses financial helps products.",
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' => [
            'type'              => 'string',
            'description'       => "Full name (including namespace) of the class to look into (e.g. 'core\\User').",
            'default'           => 'sale\booking\BookingLine'
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit.",
            'default'           => fn() => strtotime('first day of january this year')
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit.",
            'default'           => fn() => strtotime('last day of december this year')
        ]

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

$setting_financial_help_products = Setting::get_value('sale', 'booking', 'financial_helps_products', '');

$financial_helps_products = explode(',', $setting_financial_help_products);

if($financial_helps_products === false) {
    throw new Exception("financial_helps_products_not_configured", EQ_ERROR_INVALID_CONFIG);
}

$products_ids = Product::search(['sku', 'in', $financial_helps_products])->ids();
if(empty($products_ids)) {
    throw new Exception("invalid_financial_help_products_skus", EQ_ERROR_INVALID_CONFIG);
}

$domain = new Domain($params['domain']);

$domain->addCondition(
    new DomainCondition('product_id', 'in', $products_ids)
);

$bookings_ids = Booking::search([
    ['date_from', '>=', $params['date_from']],
    ['date_from', '<=', $params['date_to']],
    ['status', 'in', ['debit_balance', 'credit_balance', 'balanced']]
])
    ->ids();

$domain->addCondition(
    new DomainCondition('booking_id', 'in', $bookings_ids)
);

$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
