<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace finance\accounting;

use equal\orm\Model;
use discope\setting\Setting;
use sale\booking\Funding;
use sale\catalog\Product;

class Invoice extends Model {

    public static function getName() {
        return "Invoice";
    }

    public static function getDescription() {
        return "An invoice is a legal document issued by a seller to a buyer that relates to a sale, and is part of the accounting system.";
    }

    public static function getLink() {
        return "/accounting/#/invoice/object.id";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'alias',
                'alias'             => "number"
            ],

            'name_old' => [
                'type'              => 'string',
                'description'       => "Previous invoice number for invoice emitted before numbering change (as of february 2023)."
            ],

            'customer_ref' => [
                'type'              => 'string',
                'description'       => "Reference that must appear on invoice (requested by customer)."
            ],

            // #memo this field is defined in parent Model and is reset by several handlers. BUT it is not used (yet) nor is it a computed field.
            // #memo - do not use yet
            // 'payment_status'

            'is_deposit' => [
                'type'              => 'boolean',
                'description'       => "Marks the invoice as a deposit one, relating to a downpayment.",
                'default'           => false
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The organization that emitted the invoice.",
                'default'           => 1
            ],

            'status' => [
                'type'              => 'string',
                'description'       => "Current status of the invoice, either proforma, invoice or cancelled.",
                'selection'         => [
                    'proforma',             // draft invoice (no number yet)
                    'invoice',              // final invoice (with unique number and accounting entries)
                    'cancelled'             // the invoice has been cancelled (through reversing entries)
                ],
                'default'           => 'proforma',
                'onupdate'          => 'onupdateStatus'
            ],

            'type' => [
                'type'              => 'string',
                'description'       => "Type of the invoice, either invoice or credit note.",
                'selection'         => [
                    'invoice',
                    'credit_note'
                ],
                'default'           => 'invoice'
            ],

            'journal_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingJournal',
                'description'       => "Accounting journal the invoice relates to.",
                'store'             => true,
                'function'          => 'calcJournalId'
            ],

            'number' => [
                'type'              => 'string',
                'description'       => "Number of the invoice, according to organization logic (@see config/invoicing).",
                'default'          => '[proforma]',
            ],

            'is_paid' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Indicator of the invoice payment status.",
                'visible'           => ['status', '=', 'invoice'],
                'function'          => 'calcIsPaid',
                'store'             => true
            ],

            'reversed_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Invoice',
                'description'       => "Symmetrical link between credit note and cancelled invoice, if any.",
                'visible'           => [[['status', '=', 'cancelled']], [['type', '=', 'credit_note']]]
            ],

            'payment_status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',          // non-paid, payment terms delay running
                    'overdue',          // non-paid, and payment terms delay is over
                    'debit_balance',    // partially paid: customer still has to pay something
                    'credit_balance',   // fully paid and a reimbursement to customer is required
                    'balanced'          // fully paid and balanced
                ],
                'visible'           => ['status', '=', 'invoice'],
                'default'           => 'pending'
            ],

            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Message for identifying payments related to the invoice.",
                'store'             => true,
                'function'          => 'calcPaymentReference'
            ],

            'date' => [
                'type'              => 'datetime',
                'description'       => "Emission date of the invoice.",
                'default'           => time()
            ],

            'customer_id' => [
                'type'              => 'alias',
                'alias'             => 'partner_id'
            ],

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'description'       => "The counter party organization the invoice relates to.",
                'required'          => true
            ],

            'total_discount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'function'          => 'calcTotalDiscount',
                'description'       => "Total tax-excluded discount price of all lines of the invoice.",
                'store'             => false
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Total tax-excluded price of the invoice.",
                'help'              => "Is now rounded to two decimals for UBL compliance.",
                'store'             => true,
                'function'          => 'calcTotal'
            ],

            'subtotals' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/json',
                'description'       => "Sub totals, by vat rates, tax-excluded prices of the invoice.",
                'help'              => "Must sum lines prices totals keeping 4 decimals and rounded to 2 decimals at the end. e.g. '0.0', '6.0', '12.0', '21.0'.",
                'store'             => true,
                'function'          => 'calcSubTotals'
            ],

            'subtotals_vat' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/json',
                'description'       => "Sub totals, by vat rates, tax prices of the invoice.",
                'help'              => "Must sum lines prices totals keeping 4 decimals and rounded to 2 decimals at the end. e.g. '0.0', '6.0', '12.0', '21.0'.",
                'store'             => true,
                'function'          => 'calcSubTotalsVat'
            ],

            'total_vat' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total tax price of the invoice.",
                'store'             => true,
                'function'          => 'calcTotalVat'
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Final tax-included invoiced amount.",
                'help'              => "Sum of 'total' and 'subtotals_vat'.",
                'store'             => true,
                'function'          => 'calcPrice'
            ],

            'balance' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcBalance',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount left to be paid by customer.'
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\InvoiceLine',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateInvoiceLinesIds'
            ],

            'invoice_line_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\InvoiceLineGroup',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Groups of lines of the invoice.',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateInvoiceLineGroupsIds'
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Accounting entries relating to the lines of the invoice.',
                'ondetach'          => 'delete'
            ],

            'accounting_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcAccountingPrice',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-included price to record for accounting.',
                'store'             => true
            ],

            'accounting_total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcAccountingTotal',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price to record for accounting related outputs.',
                'store'             => true
            ],

            'display_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcDisplayPrice',
                'usage'             => 'amount/money:2',
                'store'             => true,
                'description'       => "Final tax-included amount used for display (inverted for credit notes)."
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'description'       => 'The funding the invoice originates from, if any.'
            ],

            // #memo - when emitted, (partially) paid non-invoiced fundings are attached to the invoice
            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Funding',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Fundings related to the invoice (should be max. 1).'
            ],

            'payment_terms_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\PaymentTerms',
                'description'       => "The payment terms to apply to the invoice.",
                'default'           => 1
            ],

            'due_date' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => "Deadline for the payment is expected, from payment terms.",
                'function'          => 'calcDueDate',
                'store'             => true
            ],

            'is_exported' => [
                'type'              => 'boolean',
                'description'       => 'Mark the invoice as exported (part of an export to elsewhere).',
                'default'           => false
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => 'Office the invoice relates to (for center management).',
                'required'          => true
            ],

            'has_orders' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking that the invoice originates from one or more orders (PoS).',
                'default'           => false
            ],

            'orders_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pos\Order',
                'foreign_field'     => 'invoice_id',
                'description'       => 'The orders (PoS) the invoice originates from, if any.'
            ],

        ];
    }

    private static function _getAvailableNumber($invoice_id) {
        $invoice = Invoice::id($invoice_id)
            ->read([
                'date',
                'organisation_id',
                'journal_id'        => ['type'],
                'center_office_id'  => ['code']]
            )
            ->first();

        if(is_null($invoice['journal_id'])) {
            throw new \Exception("unknown_journal_id", EQ_ERROR_INVALID_CONFIG);
        }

        $format = Setting::get_value('sale', 'accounting', 'invoice.sequence_format.'.$invoice['organisation_id']);
        $has_organisation_format = !is_null($format);
        if(!$has_organisation_format) {
            $format = Setting::get_value('sale', 'accounting', 'invoice.sequence_format', '%05d{sequence}');
        }

        $fiscal_year = Setting::get_value('finance', 'accounting', 'fiscal_year');
        $fiscal_date_from = Setting::get_value('finance', 'accounting', 'fiscal_year.date_from');
        $fiscal_date_to = Setting::get_value('finance', 'accounting', 'fiscal_year.date_to');

        // #memo - forces LO, VSG and HVG (has_vat) format for fiscal year 2026
        // #todo - to remove when fiscal year changed to 2027
        if(intval($fiscal_year) === 2026 && $has_organisation_format) {
            switch($invoice['journal_id']['type']) {
                case 'sales':
                    $format = '%2d{year}-%02d{office}-%05d{sequence}';
                    break;
                case 'sales_peppol':
                    $format = '%2d{year}-9%02d{office}-%04d{sequence}';
                    break;
            }
        }

        if(!$fiscal_year || !$fiscal_date_from || !$fiscal_date_to) {
            trigger_error("APP::unable to retrieve sequence for invoice", EQ_REPORT_ERROR);
            throw new \Exception("missing_mandatory_fiscal_config", EQ_ERROR_INVALID_CONFIG);
        }

        $date_from = strtotime($fiscal_date_from . ' 00:00:00');
        $date_to   = strtotime($fiscal_date_to   . ' 23:59:59');

        if($invoice['date'] < $date_from || $invoice['date'] > $date_to) {
            throw new \Exception("invoice_outside_fiscal_year", EQ_ERROR_INVALID_CONFIG);
        }

        $sequence_code = 'invoice.sequence.'.$invoice['center_office_id']['code'];
        if($invoice['journal_id']['type'] === 'sales_peppol') {
            $sequence_code = 'invoice.peppol.sequence.'.$invoice['center_office_id']['code'];
        }

        $sequence = Setting::fetch_and_add('sale', 'accounting', $sequence_code);
        if(!$sequence) {
            throw new \Exception("APP::unable to retrieve sequence for invoice", EQ_ERROR_INVALID_CONFIG);
        }

        $map_types = [
            'sales'         => '0',
            'sales_peppol'  => '1'
        ];
        if(!isset($map_types[$invoice['journal_id']['type']])) {
            trigger_error("APP::accounting journal type not allowed", EQ_REPORT_ERROR);
            throw new \Exception("wrong_accounting_journal_type");
        }

        return Setting::parse_format($format, [
            'year'      => $fiscal_year,
            'office'    => $invoice['center_office_id']['code'],
            'org'       => $invoice['organisation_id'],
            'type'      => $map_types[$invoice['journal_id']['type']],
            'sequence'  => $sequence
        ]);
    }

    public static function calcIsPaid($self) {
        $result = [];
        // #memo - fundings_ids targets all fundings relating to invoice: this includes the installments
        // we need to limit the check to the direct funding, if any
        $self->read(['status', 'price', 'funding_id' => ['paid_amount']]);
        foreach($self as $id => $invoice) {
            if($invoice['status'] != 'invoice') {
                // proforma invoices cannot be marked as paid
                continue;
            }

            $is_paid = false;
            if($invoice['price'] == 0) {
                // mark the invoice as paid, whatever its funding
                $is_paid = true;
            }
            elseif(isset($invoice['funding_id']['paid_amount']) && $invoice['funding_id']['paid_amount'] == $invoice['price']) {
                $is_paid = true;
            }

            $result[$id] = $is_paid;
        }

        return $result;
    }

    public static function calcPaymentReference($self) {
        $result = [];
        $self->read(['number']);
        foreach($self as $id => $invoice) {
            $number = intval($invoice['number']);
            // arbitrary value : 155 for balance (final) invoice
            $code_ref = 155;
            $result[$id] = self::_get_payment_reference($code_ref, $number);
        }

        return $result;
    }

    public static function calcJournalId($self): array {
        $result = [];
        $self->read([
            'status',
            'center_office_id',
            'organisation_id'   => [
                'has_vat'
            ],
            'partner_id'        => [
                'partner_identity_id' => [
                    'has_vat'
                ]
            ]
        ]);
        foreach($self as $id => $invoice) {
            if(is_null($invoice['center_office_id']) || $invoice['status'] === 'proforma') {
                continue;
            }

            $accounting_journals = AccountingJournal::search([
                ['center_office_id', '=', $invoice['center_office_id']],
                ['type', 'in', ['sales', 'sales_peppol']]
            ])
                ->read(['type'])
                ->get();

            $map_types_accounting_journals_ids = [];
            foreach($accounting_journals as $jid => $accounting_journal) {
                $map_types_accounting_journals_ids[$accounting_journal['type']] = $jid;
            }

            $journal_type = 'sales';
            // #todo - remove year 2026 check when not needed anymore
            if($invoice['organisation_id']['has_vat'] && $invoice['partner_id']['partner_identity_id']['has_vat'] && intval(date('Y')) >= 2026) {
                $journal_type = 'sales_peppol';
            }

            $journal_id = $map_types_accounting_journals_ids[$journal_type];

            $result[$id] = $journal_id;
        }

        return $result;
    }

    /**
     * #memo - this should not include installment [non-invoiced pre-payments] (we should deal with display_price instead)
     */
    public static function calcTotalDiscount($self): array {
        $result = [];
        $self->read(['invoice_lines_ids' => ['total', 'total_no_discount']]);
        foreach($self as $id => $invoice) {
            $total_discount = 0.0;
            foreach($invoice['invoice_lines_ids'] as $line) {
                // total is rounded to 2 decimals for compatibility with older data that were computed with 4 decimals
                $total_discount = round($total_discount + ($line['total_no_discount'] - round($line['total'], 2)), 2);
            }

            $result[$id] = $total_discount;
        }

        return $result;
    }

    /**
     * #memo - this should not include installment [non-invoiced pre-payments] (we should deal with display_price instead)
     */
    public static function calcTotal($self): array {
        $result = [];
        $self->read(['invoice_lines_ids' => ['total']]);
        foreach($self as $id => $invoice) {
            $total = 0.0;
            foreach($invoice['invoice_lines_ids'] as $line) {
                $total = round($total + $line['total'], 2);
            }

            $result[$id] = $total;
        }

        return $result;
    }

    public static function calcSubTotals($self): array {
        $result = [];
        $self->read(['invoice_lines_ids' => ['vat_rate', 'total']]);
        foreach($self as $id => $invoice) {
            $subtotals = [];
            foreach($invoice['invoice_lines_ids'] as $line) {
                $vat_rate_index = number_format($line['vat_rate'] * 100, 2, '.', '');
                if(!isset($subtotals[$vat_rate_index])) {
                    $subtotals[$vat_rate_index] = 0.0;
                }

                // #memo - total is rounded to 2 decimals for compatibility with older data that were computed with 4 decimals
                $subtotals[$vat_rate_index] = round($subtotals[$vat_rate_index] + round($line['total'], 2), 2);
            }

            // #memo - has to be rounded on 2 decimals here and not on each line
            $result[$id] = json_encode(array_map(fn($subtotal) => round($subtotal, 2), $subtotals));
        }

        return $result;
    }

    public static function calcSubTotalsVat($self): array {
        $result = [];
        $self->read(['subtotals']);
        foreach($self as $id => $invoice) {
            $subtotals = json_decode($invoice['subtotals'], true);

            $subtotals_vat = [];
            foreach($subtotals as $vat_rate_index => $subtotal) {
                $vat_rate = ((float) $vat_rate_index) / 100;
                $subtotals_vat[$vat_rate_index] = round($subtotal * $vat_rate, 2);
            }

            $result[$id] = json_encode($subtotals_vat);
        }

        return $result;
    }

    public static function calcTotalVat($self): array {
        $result = [];
        $self->read(['subtotals_vat']);
        foreach($self as $id => $invoice) {
            $subtotals_vat = json_decode($invoice['subtotals_vat'], true);

            $total_vat = 0.0;
            foreach($subtotals_vat as $subtotal) {
                $total_vat = round($total_vat + $subtotal, 2);
            }

            $result[$id] = $total_vat;
        }

        return $result;
    }

    /**
     * #memo - this should not include installment [non-invoiced pre-payments] (we should deal with display_price instead)
     */
    public static function calcPrice($self): array {
        $result = [];
        $self->read(['total', 'total_vat']);
        foreach($self as $id => $booking) {
            $result[$id] = round($booking['total'] + $booking['total_vat'], 2);
        }

        return $result;
    }

    public static function calcBalance($self) {
        $result = [];
        $self->read(['booking_id', 'type', 'status', 'is_deposit', 'fundings_ids', 'price']);
        foreach($self as $id => $invoice) {
            if($invoice['status'] == 'cancelled') {
                $result[$id] = 0;
            }
            else {
                if($invoice['is_deposit'] || $invoice['type'] == 'credit_note') {
                    $fundings = Funding::ids($invoice['fundings_ids'])
                        ->read(['paid_amount'])
                        ->get(true);

                    $result[$id] = $invoice['price'];
                    if($invoice['type'] == 'credit_note') {
                        $result[$id] = -$result[$id];
                    }
                    foreach($fundings as $funding) {
                        $result[$id] -= $funding['paid_amount'];
                    }
                    $result[$id] = round($result[$id], 2);
                }
                else {
                    $fundings = Funding::search(['booking_id', '=', $invoice['booking_id']])
                        ->read(['type', 'invoice_id', 'paid_amount'])
                        ->get(true);

                    $result[$id] = $invoice['price'];
                    foreach($fundings as $funding) {
                        // #memo - all paid amount must be considered, even negative ones
                        if(/*$funding['type'] == 'invoice' &&*/ $funding['invoice_id'] != $id) {
                            continue;
                        }
                        $result[$id] -= $funding['paid_amount'];
                    }
                    $result[$id] = round($result[$id], 2);
                }
            }
        }

        return $result;
    }

    public static function calcDueDate($self) {
        $result = [];
        $self->read([
            'created',
            'payment_terms_id' => [
                'delay_from',
                'delay_count'
            ]
        ]);
        foreach($self as $id => $invoice) {
            $from = $invoice['payment_terms_id']['delay_from'] ?? '';
            $delay = $invoice['payment_terms_id']['delay_count'] ?? '';

            $origin = $invoice['created'];

            switch($from) {
                case 'created':
                    $due_date = $origin + ($delay*24*3600);
                    break;
                case 'next_month':
                default:
                    $due_date = strtotime(date("Y-m-t", $origin)) + ($delay*24*3600);
                    break;
            }

            $result[$id] = $due_date;
        }

        return $result;
    }

    public static function calcAccountingTotal($self): array {
        $result = [];
        $self->read([
            'organisation_id',
            'is_deposit',
            'invoice_lines_ids' => [
                'total',
                'product_id',
                'downpayment_invoice_id' => ['status']
            ]
        ]);
        foreach($self as $id => $invoice) {
            // retrieve downpayment product
            $downpayment_product_id = 0;
            $downpayment_sku = Setting::get_value('sale', 'organization', 'sku.downpayment.'.$invoice['organisation_id']);
            if($downpayment_sku) {
                $products_ids = Product::search(['sku', '=', $downpayment_sku])->ids();
                if($products_ids) {
                    $downpayment_product_id = reset($products_ids);
                }
            }

            $total = 0.0;
            foreach($invoice['invoice_lines_ids'] as $line) {
                if($line['product_id'] == $downpayment_product_id) {
                    // deposit invoice
                    if($invoice['is_deposit']) {
                        $total = round($total + $line['total'], 2);
                    }
                    else {
                        // if the line refers to an invoiced downpayment and if the related downpayment invoice hasn't been cancelled
                        if(isset($line['downpayment_invoice_id']['status']) && $line['downpayment_invoice_id']['status'] === 'invoice') {
                            // remove deposit from accounting total
                            // #memo - total should be a negative value
                            $total = round($total + $line['total'], 2);
                        }
                        else {
                            // ignore installment
                        }
                    }
                }
                else {
                    $total = round($total + $line['total'], 2);
                }
            }

            $result[$id] = $total;
        }

        return $result;
    }

    public static function calcAccountingPrice($self): array {
        $result = [];
        $self->read(['accounting_total', 'subtotals_vat']);
        foreach($self as $id => $invoice) {
            $subtotals_vat = json_decode($invoice['subtotals_vat'], true);

            $total_vat = 0.0;
            foreach($subtotals_vat as $subtotal_vat) {
                $total_vat = round($total_vat + $subtotal_vat, 2);
            }

            $result[$id] = round($invoice['accounting_total'] + $total_vat, 2);
        }

        return $result;
    }

    public static function calcDisplayPrice($self): array {
        $result = [];
        $self->read(['type', 'price']);
        foreach($self as $id => $invoice) {
            if($invoice['type'] === 'invoice') {
                $result[$id] = $invoice['price'];
            }
            else {
                $result[$id] = -$invoice['price'];
            }
        }

        return $result;
    }

    public static function onupdateInvoiceLinesIds($self) {
        $self->update(['price' => null, 'total' => null]);
    }

    public static function onupdateInvoiceLineGroupsIds($self) {
        $self->update(['price' => null, 'total' => null]);
    }

    /**
     * Handler triggered after a status change occurred.
     *
     * # todo - use getWorkflow with policies to replace onupdateStatus and canupdate
     */
    public static function onupdateStatus($om, $oids, $values, $lang) {
        // a number must be assigned to the invoice (if not already set)
        if(isset($values['status']) && $values['status'] == 'invoice') {

            // pass-1 - assign invoice number and check the date consistency
            $invoices = $om->read(self::getType(), $oids, ['journal_id', 'number', 'date', 'center_office_id', 'organisation_id'], $lang);

            foreach($invoices as $oid => $invoice) {
                // #memo - we don't want to assign a new number to invoiced and cancelled invoices
                if($invoice['number'] !== '[proforma]') {
                    continue;
                }
                // find most recent invoice emitted by the center office
                $last_invoices_ids = $om->search(self::getType(), [['center_office_id', '=', $invoice['center_office_id']], ['status', '<>', 'proforma']], ['date' => 'desc'], 0, 1);
                if($last_invoices_ids > 0 && count($last_invoices_ids)) {
                    $last_invoice_id = reset($last_invoices_ids);
                    $res = $om->read(self::getType(), $last_invoice_id, ['date']);
                    if($res > 0 && count($res)) {
                        $last_invoice = reset($res);
                        // if date is before last update, set invoice date to the last invoice date
                        if($last_invoice['date'] > $invoice['date']) {
                            $om->update(self::getType(), $oid, ['date' => $last_invoice['date']], $lang);
                        }
                    }
                }

                // assign number to invoice
                $om->update(self::getType(), $oid, ['number' => self::_getAvailableNumber($oid)], $lang);
            }

            // pass-2 - generate accounting entries
            foreach($invoices as $oid => $invoice) {
                // #memo - we don't want to update entries of an existing invoice
                if($invoice['number'] != '[proforma]') {
                    continue;
                }

                // generate accounting entries
                $invoices_accounting_entries = self::_generateAccountingEntries($om, $oid, [], $lang);

                if($invoice['journal_id'] && isset($invoices_accounting_entries[$oid])) {
                    $accounting_entries = $invoices_accounting_entries[$oid];
                    // create new entries objects and assign to the sale journal relating to the center_office_id
                    foreach($accounting_entries as $entry) {
                        $entry['journal_id'] = $invoice['journal_id'];
                        $om->create(\finance\accounting\AccountingEntry::getType(), $entry);
                    }
                }
            }

        }
    }

    /**
     * Check whether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager   $om         ObjectManager instance.
     * @param  array                      $oids       List of objects identifiers.
     * @param  array                      $values     Associative array holding the new values to be assigned.
     * @param  string                     $lang       Language in which multilang fields are being updated.
     * @return array                      Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     *
     * # todo - use getWorkflow with policies to replace canupdate (see onupdateStatus)
     */
    public static function canupdate($om, $oids, $values, $lang='en') {
        $allowed_fields = ['customer_ref', 'payment_status', 'is_paid', 'is_exported', 'funding_id', 'reversed_invoice_id'];

        $invoices = $om->read(self::getType(), $oids, ['status']);

        if($invoices > 0) {
            foreach($invoices as $ids => $invoice) {
                // status can only be changed from 'proforma' to 'invoice' and from 'invoice' to 'cancelled'
                if($invoice['status'] == 'proforma') {
                    if(isset($values['status']) && !in_array($values['status'], ['proforma', 'invoice'])) {
                        return ['status' => ['non_editable' => 'Invoice status can only be updated from proforma to invoice.']];
                    }
                }
                elseif($invoice['status'] == 'invoice') {
                    if(count($values) == 1 && isset($values['status']) && in_array($values['status'], ['cancelled'])) {
                        // changing status to 'cancelled' is allowed
                    }
                    // otherwise, only allow modifiable fields
                    elseif( count(array_diff(array_keys($values), $allowed_fields)) > 0 ) {
                        return ['status' => ['non_editable' => 'Invoice can only be updated while its status is proforma ['.implode(',', array_keys($values)).'].']];
                    }
                }
            }
        }
        // bypass parents rules
        return [];
    }

    /**
     * Check whether the invoice can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om         ObjectManager instance.
     * @param  array                       $oids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     *
     * # todo - use getWorkflow with policies to replace canupdate (see onupdateStatus)
     */
    public static function candelete($om, $oids) {
        $res = $om->read(get_called_class(), $oids, ['status', 'type']);

        if($res > 0) {
            foreach($res as $oids => $odata) {
                if($odata['status'] != 'proforma') {
                    return ['status' => ['non_removable' => "An invoice can only be deleted while its status is proforma."]];
                }
                if($odata['type'] == 'credit_note') {
                    return ['type' => ['non_removable' => "An invoice of type \"credit note\" can't be deleted."]];
                }
            }
        }
        return parent::candelete($om, $oids);
    }

    /**
     * Generate the accounting entries according to the invoice lines.
     *
     * @param  \equal\orm\ObjectManager    $om         ObjectManager instance.
     * @param  array                       $oids       List of objects identifiers.
     * @param  array                       $values     (unused)
     * @param  string                      $lang       Language code in which to process the request.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     *
     * # todo - use $self instead of $om
     */
    public static function _generateAccountingEntries($om, $oids, $values, $lang) {
        $result = [];
        // generate the accounting entries
        $invoices = $om->read(self::getType(), $oids, ['status', 'type', 'organisation_id', 'invoice_lines_ids'], $lang);
        if($invoices > 0) {
            // retrieve specific accounts numbers
            $account_sales = Setting::get_value('finance', 'accounting', 'account.sales', 'not_found');
            $account_sales_taxes = Setting::get_value('finance', 'accounting', 'account.sales_taxes', 'not_found');
            $account_trade_debtors = Setting::get_value('finance', 'accounting', 'account.trade_debtors', 'not_found');

            $res = $om->search(\finance\accounting\AccountChartLine::getType(), ['code', '=', $account_sales]);
            $account_sales_id = reset($res);

            $res = $om->search(\finance\accounting\AccountChartLine::getType(), ['code', '=', $account_sales_taxes]);
            $account_sales_taxes_id = reset($res);

            $res = $om->search(\finance\accounting\AccountChartLine::getType(), ['code', '=', $account_trade_debtors]);
            $account_trade_debtors_id = reset($res);

            if(!$account_sales_id || !$account_sales_taxes_id || !$account_trade_debtors_id) {
                // a mandatory value could not be retrieved
                trigger_error("ORM::missing mandatory account", EQ_REPORT_ERROR);
                return [];
            }

            foreach($invoices as $oid => $invoice) {
                if($invoice['status'] != 'invoice') {
                    continue;
                }
                // default downpayment product to null
                $downpayment_product_id = 0;
                // #todo - store this value in the settings
                // discount product is the same for all organisations: KA-Remise-A [65]
                $discount_product_id = 65;
                // retrieve downpayment product
                $downpayment_sku = Setting::get_value('sale', 'organization', 'sku.downpayment.'.$invoice['organisation_id']);
                if($downpayment_sku) {
                    $products_ids = $om->search(\sale\catalog\Product::getType(), ['sku', '=', $downpayment_sku]);
                    if($products_ids) {
                        $downpayment_product_id = reset($products_ids);
                    }
                }

                $accounting_entries = [];
                // fetch invoice lines
                $lines = $om->read('finance\accounting\InvoiceLine', $invoice['invoice_lines_ids'], [
                    'name', 'description', 'product_id', 'qty', 'total', 'price',
                    'price_id.accounting_rule_id.accounting_rule_line_ids'
                ], $lang);

                if($lines > 0) {
                    $debit_vat_sum = 0.0;
                    $credit_vat_sum = 0.0;
                    $prices_sum = 0.0;
                    $downpayments_sum = 0.0;
                    $discounts_sum = 0.0;

                    foreach($lines as $lid => $line) {
                        $vat_amount = abs($line['price']) - abs($line['total']);
                        // line refers to a downpayment
                        // (by convention qty is always negative for installments: this allows to distinguish installment invoices from balance invoice)
                        if($line['product_id'] == $downpayment_product_id && $line['qty'] < 0) {
                            // sum up downpayments (VAT incl. price)
                            $downpayments_sum += abs($line['price']);
                            // if some VAT is due, deduct the sum accordingly
                            $debit_vat_sum += $vat_amount;
                            // create a debit line with the product, on account "sales"
                            $debit = abs($line['total']);
                            $credit = 0.0;
                            $accounting_entries[] = [
                                'name'              => $line['name'],
                                'has_invoice'       => true,
                                'invoice_id'        => $oid,
                                'invoice_line_id'   => $lid,
                                'account_id'        => $account_sales_id,
                                'debit'             => ($invoice['type'] == 'invoice')?$debit:$credit,
                                'credit'            => ($invoice['type'] == 'invoice')?$credit:$debit
                            ];
                        }
                        elseif($line['product_id'] == $discount_product_id && ($line['qty'] < 0 || $line['price'] < 0) ) {
                            // sum up downpayments (VAT incl. price)
                            $discounts_sum += abs($line['price']);
                            // if some VAT is due, deduct the sum accordingly
                            $debit_vat_sum += $vat_amount;
                            // create a debit line with the product, on account "sales"
                            $debit = abs($line['total']);
                            $credit = 0.0;
                            $accounting_entries[] = [
                                'name'              => $line['name'],
                                'has_invoice'       => true,
                                'invoice_id'        => $oid,
                                'invoice_line_id'   => $lid,
                                'account_id'        => $account_sales_id,
                                'debit'             => ($invoice['type'] == 'invoice')?$debit:$credit,
                                'credit'            => ($invoice['type'] == 'invoice')?$credit:$debit
                            ];
                        }
                        // line is a regular product line
                        else {
                            // sum up VAT amounts
                            $credit_vat_sum += $vat_amount;
                            // sum up sale prices (VAT incl. price)
                            $prices_sum += $line['price'];
                            $rule_lines = [];
                            // handle downpayments
                            if($line['product_id'] == $downpayment_product_id) {
                                // generate virtual rule for downpayment with account "sales"
                                $rule_lines = [
                                    ['account_id' => $account_sales_id, 'share' => 1.0]
                                ];
                            }
                            if($line['product_id'] == $discount_product_id) {
                                // generate virtual rule for discount with account "sales"
                                $rule_lines = [
                                    ['account_id' => $account_sales_id, 'share' => 1.0]
                                ];
                            }
                            elseif (isset($line['price_id.accounting_rule_id.accounting_rule_line_ids'])) {
                                // for products, retrieve all lines of accounting rule
                                $rule_lines = $om->read(\finance\accounting\AccountingRuleLine::getType(), $line['price_id.accounting_rule_id.accounting_rule_line_ids'], ['account_id', 'share']);
                            }
                            foreach($rule_lines as $rid => $rline) {
                                if(isset($rline['account_id']) && isset($rline['share'])) {
                                    // create a credit line with product name, on the account related by the product (VAT excl. price)
                                    $debit = 0.0;
                                    $credit = round($line['total'] * $rline['share'], 2);
                                    $accounting_entries[] = [
                                        'name'              => $line['name'],
                                        'has_invoice'       => true,
                                        'invoice_id'        => $oid,
                                        'invoice_line_id'   => $lid,
                                        'account_id'        => $rline['account_id'],
                                        'debit'             => ($invoice['type'] == 'invoice')?$debit:$credit,
                                        'credit'            => ($invoice['type'] == 'invoice')?$credit:$debit
                                    ];
                                }
                            }
                        }
                    }

                    // create a credit line on account "taxes to pay"
                    if($credit_vat_sum > 0) {
                        $debit = 0.0;
                        $credit = round($credit_vat_sum, 2);
                        // assign with handling of reversing entries
                        $accounting_entries[] = [
                            'name'          => 'taxes TVA à payer',
                            'has_invoice'   => true,
                            'invoice_id'    => $oid,
                            'account_id'    => $account_sales_taxes_id,
                            'debit'         => ($invoice['type'] == 'invoice')?$debit:$credit,
                            'credit'        => ($invoice['type'] == 'invoice')?$credit:$debit
                        ];
                    }

                    // create a debit line on account "taxes to pay"
                    if($debit_vat_sum > 0) {
                        $debit = round($debit_vat_sum, 2);
                        $credit = 0.0;
                        // assign with handling of reversing entries
                        $accounting_entries[] = [
                            'name'          => 'taxes TVA à payer',
                            'has_invoice'   => true,
                            'invoice_id'    => $oid,
                            'account_id'    => $account_sales_taxes_id,
                            'debit'         => ($invoice['type'] == 'invoice')?$debit:$credit,
                            'credit'        => ($invoice['type'] == 'invoice')?$credit:$debit
                        ];
                    }

                    // create a debit line on account "trade debtors"
                    $debit = round($prices_sum-$downpayments_sum-$discounts_sum, 2);
                    $credit = 0.0;
                    // assign with handling of reversing entries
                    $accounting_entries[] = [
                        'name'          => 'créances commerciales',
                        'has_invoice'   => true,
                        'invoice_id'    => $oid,
                        'account_id'    => $account_trade_debtors_id,
                        'debit'         => ($invoice['type'] == 'invoice')?$debit:$credit,
                        'credit'        => ($invoice['type'] == 'invoice')?$credit:$debit
                    ];

                    // append generated entries to result
                    $result[$oid] = $accounting_entries;
                }
            }
        }
        return $result;
    }

    /**
     * Compute a Structured Reference using belgian SCOR (StructuredCommunicationReference) reference format.
     *
     * Note:
     *  format is aaa-bbbbbbb-XX
     *  where Xaaa is the prefix, bbbbbbb is the suffix, and XX is the control number, that must verify (aaa * 10000000 + bbbbbbb) % 97
     *  as 10000000 % 97 = 76
     *  we do (aaa * 76 + bbbbbbb) % 97
     */
    protected static function _get_payment_reference($prefix, $suffix) {
        $a = intval($prefix);
        $b = intval($suffix);
        $control = ((76*$a) + $b ) % 97;
        $control = ($control == 0)?97:$control;
        return sprintf("%3d%04d%03d%02d", $a, $b / 1000, $b % 1000, $control);
    }
}
