<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use sale\booking\Invoice;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for Reports: returns a collection of Reports according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'sale\booking\InvoiceLine'
        ],
        'organisation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Identity',
            'description'       => 'Center filter',
            'domain'            => ['id', 'in', [1, 2, 3, 4]]
        ],
        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\CenterOffice',
            'description'       => 'Office the invoice relates to (for center management).'
        ],
        'customer_identity_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Identity',
            'description'       => 'Identity of the customer (from partner).'
        ],
        'product_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\catalog\Product',
            'description'       => 'The product (SKU) the line relates to.'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit."
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval Upper limit.'
        ],
        'all_states' => [
            'type'              => 'boolean',
            'description'       => 'Include lines from archived invoices.',
            'default'           => true
        ]
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
list($context, $orm) = [ $providers['context'], $providers['orm'] ];
$domain = $params['domain'];

if(isset($params['product_id']) && $params['product_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['product_id', '=', $params['product_id']]);
}

if(isset($params['invoice_id']) && $params['invoice_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['invoice_id', '=', $params['invoice_id']]);
}

if(isset($params['organisation_id']) && $params['organisation_id'] > 0) {
    $invoices_ids = Invoice::search(['organisation_id', 'in', $params['organisation_id']])->ids();
    if(count($invoices_ids)) {
        $domain = Domain::conditionAdd($domain, ['invoice_id', 'in', $invoices_ids]);
    }
}

$invoice_states = ['instance'];

if($params['all_states']) {
    $invoice_states[] = 'archive';
}

if(isset($params['center_office_id']) && $params['center_office_id'] > 0) {
    $invoices_ids = Invoice::search([
            ['center_office_id', 'in', $params['center_office_id']],
            ['state', 'in', $invoice_states],
        ])
        ->ids();
    if(count($invoices_ids)) {
        $domain = Domain::conditionAdd($domain, ['invoice_id', 'in', $invoices_ids]);
    }
}

if(isset($params['customer_identity_id']) && $params['customer_identity_id'] > 0) {
    $invoices_ids = Invoice::search([
            ['customer_identity_id', 'in', $params['customer_identity_id']],
            ['state', 'in', $invoice_states]
        ])
        ->ids();
    if(count($invoices_ids)) {
        $domain = Domain::conditionAdd($domain, ['invoice_id', 'in', $invoices_ids]);
    }
}

if(isset($params['date_from']) && $params['date_from'] > 0) {
    $invoices_ids = Invoice::search([
            ['date', '>=', $params['date_from']],
            ['state', 'in', $invoice_states]
        ])
        ->ids();
    if(count($invoices_ids)) {
        $domain = Domain::conditionAdd($domain, ['invoice_id', 'in', $invoices_ids]);
    }
}

if(isset($params['date_to']) && $params['date_to'] > 0) {
    $date_to = strtotime(date('Y-m-d 00:00:00', strtotime('+1 day', $params['date_to'])));
    $invoices_ids = Invoice::search([
            ['date', '<=', $date_to],
            ['state', 'in', $invoice_states]
        ])
        ->ids();
    if(count($invoices_ids)) {
        $domain = Domain::conditionAdd($domain, ['invoice_id', 'in', $invoices_ids]);
    }
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
