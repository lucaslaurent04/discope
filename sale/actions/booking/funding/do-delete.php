<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Funding;

[$params, $providers] = eQual::announce([
    'description'   => "Removes a refund funding.",
    'help'          => "Refund funding are expected to be created manually and are therefore allowed for removal.",
    'params'        => [
        'id' =>  [
            'description'    => 'Identifier of the targeted funding.',
            'type'           => 'many2one',
            'foreign_object' => 'sale\booking\Funding',
            'required'       => true
        ]
    ],
    'access'        => [
        'groups'        => ['booking.default.user', 'finance.default.administrator']
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

$funding = Funding::id($params['id'])
    ->read(['type', 'due_amount', 'paid_amount', 'invoice_id' => ['status']])
    ->first(true);

if(!$funding) {
    throw new Exception("unknown_funding", EQ_ERROR_INVALID_PARAM);
}

if($funding['paid_amount'] != 0) {
    throw new Exception("funding_already_paid", EQ_ERROR_INVALID_PARAM);
}

// fundings related to invoices cannot be deleted if the invoice isn't a proforma
if($funding['type'] == 'invoice' && $funding['invoice_id']['status'] !== 'proforma') {
    throw new Exception("invalid_funding_type", EQ_ERROR_INVALID_PARAM);
}

if($funding['type'] == 'invoice') {
    eQual::run('do', 'sale_booking_invoice_do-delete', [
        'id' => $funding['invoice_id']['id']
    ]);
}

Funding::id($funding['id'])->delete(true);

$context->httpResponse()
        ->status(204)
        ->send();
