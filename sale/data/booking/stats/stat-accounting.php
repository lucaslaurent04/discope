<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Invoice;

list($params, $providers) = eQual::announce([
    'description'   => 'Provides the quantities and prices for a given accounting rule over a specific period',
    'params'        => [
        'organisation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Identity',
            'description'       => 'Center filter',
        ],
        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\CenterOffice',
            'description'       => "Center to which the booking is assigned. Input: Used to filter by center.",
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => 'Start date for filtering',
            'default'           =>  mktime(0, 0, 0, date('m')-1, 1)
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => 'End date for filtering.',
            'default'           => mktime(0, 0, 0, date('m')+2, 0)
        ],

        'accounting_rule_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\AccountingRule',
            'description'       => "Applied accounting rule.",
            'require'           => true
        ],

        'center_office' => [
            'type'              => 'string',
            'description'       => 'Unique identifier of the center.'
        ],

        'invoice' => [
            'type'              => 'string',
            'description'       => 'Unique identifier of the invoice.'
        ],

        'qty' => [
            'type'              => 'integer',
            'description'       => 'Quantity of accounting rule invoiced.'
        ],

        'total' => [
            'type'              => 'float',
            'description'       => 'Total amount invoiced, excluding tax.'
        ],

        'price' => [
            'type'              => 'float',
            'description'       => 'Total amount invoiced, including tax.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context' ]
]);

/**
 * @var \equal\php\Context $context
 */
$context = $providers['context'];


$domain = [];

$date_to = strtotime(date('Y-m-d 00:00:00', strtotime('+1 day', $params['date_to'])));


if( (isset($params['center_office_id']) || isset($params['organisation_id'])) && isset($params['accounting_rule_id']) ) {
    $domain = array_merge(
        [
            ['state', 'in', ['instance', 'archive']],
            ['date', '>=', $params['date_from']],
            ['date', '<=', $date_to],
            ['status', '=', 'invoice']
        ],
        isset($params['center_office_id']) ? [['center_office_id', '=', $params['center_office_id']]] : [],
        isset($params['organisation_id']) ? [['organisation_id', '=', $params['organisation_id']]] : []
    );
}

if($domain){
    $invoices = Invoice::search($domain,  ['sort'  => ['date' => 'asc'] ])
        ->read([
            'name',
            'organisation_id' => ['id', 'name'],
            'center_office_id' => ['id', 'name'],
            'invoice_lines_ids' => [
                'id', 'name', 'product_id', 'qty', 'unit_price', 'total', 'price',
                'price_id' => ['accounting_rule_id']
            ]
        ])
        ->get(true);
}

foreach($invoices as $invoice) {
    $organisation_id = $invoice['organisation_id']['id'];
    $center_office_id = $invoice['center_office_id']['id'];
    $invoice_name = $invoice['name'];

    if(!isset($map_organisation_accounting[$organisation_id])) {
        $map_organisation_accounting[$organisation_id] = [];
    }

    if(!isset($map_organisation_accounting[$organisation_id][$center_office_id])) {
        $map_organisation_accounting[$organisation_id][$center_office_id] = [];
    }

    foreach($invoice['invoice_lines_ids'] as $invoice_line) {

        $accounting_rule_id = $invoice_line['price_id']['accounting_rule_id'];
        if(isset($params['accounting_rule_id']) && $accounting_rule_id !== $params['accounting_rule_id']) {
            continue;
        }

        if(!isset($map_organisation_accounting[$organisation_id][$center_office_id][$invoice_name])) {
            $map_organisation_accounting[$organisation_id][$center_office_id][$invoice_name] =  [
                'organisation_id'       => $organisation_id,
                'center_office_id'      => $center_office_id,
                'center_office_name'    => $invoice['center_office_id']['name'],
                'invoice'               => $invoice_name,
                'qty'                   => 0,
                'total'                 => 0,
                'price'                 => 0
            ];
        }

        $map_organisation_accounting[$organisation_id][$center_office_id][$invoice_name]['qty'] += $invoice_line['qty'];
        $map_organisation_accounting[$organisation_id][$center_office_id][$invoice_name]['total'] += $invoice_line['total'];
        $map_organisation_accounting[$organisation_id][$center_office_id][$invoice_name]['price'] += $invoice_line['price'];

    }
}


$result = [];
foreach($map_organisation_accounting as $organisation_id => $center_offices) {
    foreach($center_offices as $center_office_id => $invoices) {
        foreach($invoices as $invoice_id => $invoice) {
            $result[] = [
                'organisation_id'  => $product_stat['organisation_id'],
                'center_office_id' => $product_stat['center_office_id'],
                'center_office'    => $invoice['center_office_name'],
                'invoice'          => $invoice['invoice'],
                'qty'              => $invoice['qty'],
                'total'            => round($invoice['total'],2),
                'price'            => round($invoice['price'],2)
            ];
        }
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();
