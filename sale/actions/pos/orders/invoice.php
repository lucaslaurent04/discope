<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use finance\accounting\Invoice;
use finance\accounting\InvoiceLine;
use finance\accounting\InvoiceLineGroup;
use sale\pos\Order;

list($params, $providers) = eQual::announce([
    'description'   => "Generates an invoice with all cashdesk orders made by the given Center for a given month.",
    'params'        => [
        'domain' =>  [
            'description'   => 'Domain to limit the result set (specifying a month is mandatory).',
            'type'          => 'array',
            'default'       => []
        ],
        'params' =>  [
            'description'   => 'Additional params, if any',
            'type'          => 'array',
            'default'       => []
        ]
    ],
    'access' => [
        'groups'            => ['pos.default.user', 'pos.default.administrator', 'admins'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'adapt']
]);

list($context, $orm, $dap) = [$providers['context'], $providers['orm'], $providers['adapt']];

/** @var \equal\data\adapt\DataAdapter */
$adapter = $dap->get('json');

if(isset($params['params']['all_months'])) {
    $all_months = $adapter->adaptIn($params['params']['all_months'], 'number/boolean');
    if($all_months) {
        throw new Exception('missing_month', EQ_ERROR_INVALID_PARAM);
    }
}

if(!isset($params['params']['center_id'])) {
    throw new Exception('missing_center', EQ_ERROR_INVALID_PARAM);
}

$center_id = $adapter->adaptIn($params['params']['center_id'], 'number/integer');
if($center_id <= 0) {
    throw new Exception('missing_center', EQ_ERROR_INVALID_PARAM);
}

$center = Center::id($center_id)
    ->read(['pos_default_customer_id', 'organisation_id', 'center_office_id'])
    ->first(true);

if(!$center) {
    throw new Exception('missing_center', EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$center['pos_default_customer_id']) {
    throw new Exception('unsupported_center', EQ_ERROR_INVALID_PARAM);
}

if(!isset($params['params']['date'])) {
    throw new Exception('missing_month', EQ_ERROR_INVALID_PARAM);
}

$date = $adapter->adaptIn($params['params']['date'], 'date/plain');
if(is_null($date) || $date <= 0) {
    throw new Exception('missing_month', EQ_ERROR_INVALID_PARAM);
}

$first_date = strtotime(date('Y-m-01 00:00:00', $date));
$last_date = strtotime('first day of next month', $first_date);

// search cashdesk orders ("vente comptoir") - not related to a booking
$orders = Order::search([
        ['status', '=', 'paid'],
        ['price', '>', 0],
        ['funding_id', '=', null],
        ['booking_id', '=', null],
        ['invoice_id', '=', null],
        ['center_id', '=', $center_id],
        // #memo - we do not use start date to make sure that any passed order not yet invoiced is included
        ['created', '<', $last_date],
        ['created', '>=', strtotime('2024-04-01 00:00:00')]
    ])
    ->read([
        'id', 'name', 'status', 'created',
        'customer_id',
        'order_lines_ids' => [
            'product_id' => ['id', 'name'],
            'price_id',
            'vat_rate',
            'unit_price',
            'qty',
            'free_qty',
            'discount',
            'price',
            'total'
        ]
    ])
    ->get(true);

// retrieve customer id
$customer_id = $center['pos_default_customer_id'];

// create invoice and invoice lines
$invoice = Invoice::create([
        'date'              => time(),
        'organisation_id'   => $center['organisation_id'],
        'center_office_id'  => $center['center_office_id'],
        'status'            => 'proforma',
        'partner_id'        => $customer_id,
        'has_orders'        => true
    ])
    ->read(['id'])
    ->first(true);

$invoice_line_group = InvoiceLineGroup::create([
        'name'              => 'Ventes comptoir',
        'invoice_id'        => $invoice['id']
    ])
    ->read(['id'])
    ->first(true);

$orders_ids = [];

foreach($orders as $order) {
    // check order consistency
    if($order['status'] != 'paid') {
        continue;
    }
    try {
        $orders_ids[] = $order['id'];
        // create invoice lines
        foreach($order['order_lines_ids'] as $line) {
            // create line in several steps (not to overwrite final values from the line - that might have been manually adapted)
            InvoiceLine::create([
                    'invoice_id'                => $invoice['id'],
                    'invoice_line_group_id'     => $invoice_line_group['id'],
                    'product_id'                => $line['product_id']['id'],
                    'description'               => $line['product_id']['name'],
                    'price_id'                  => $line['price_id']
                ])
                ->update([
                    'vat_rate'                  => $line['vat_rate'],
                    'unit_price'                => $line['unit_price'],
                    'qty'                       => $line['qty'],
                    'free_qty'                  => $line['free_qty'],
                    'discount'                  => $line['discount']
                ])
                ->update([
                    'total'                     => $line['total']
                ])
                ->update([
                    'price'                     => $line['price']
                ]);
        }
        // attach the invoice to the Order, and mark it as having an invoice
        Order::id($order['id'])->update(['invoice_id' => $invoice['id']]);
    }
    catch(Exception $e) {
        // ignore errors (must be resolved manually)
    }
}

// create (exportable) payments for involved orders
// #memo - waiting to be confirmed (the teams to be ready for the accounting)
/*
* Ovifat : 27
* Wanne  : 30
* LLN : 28
* Eupen: 24
* HSL : 26
* VSG : 25
* HVG : 32
*/
if(in_array($center_id, [27, 30, 28, 24, 26]) && $date >= strtotime('2024-04-01 00:00:00')) {
    eQual::run('do', 'sale_pos_orders_payments', [
            'ids' => $orders_ids
        ]);
}

$context->httpResponse()
        ->status(204)
        ->send();
