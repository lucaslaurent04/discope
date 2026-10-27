<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;

class Invoice extends \finance\accounting\Invoice {

    public static function getLink() {
        return "/booking/#/booking/object.booking_id/invoice/object.id";
    }

    public static function getColumns() {

        return [

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\InvoiceLine',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateInvoiceLinesIds'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'Booking the invoice relates to.',
                'required'          => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Funding',
                'description'       => 'The funding the invoice originates from, if any.'
            ],

            // override to use booking_id in `calcPaymentReference`
            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPaymentReference',
                'description'       => 'Message for identifying payments related to the invoice.',
                'store'             => true
            ],

            'reversed_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Invoice',
                'description'       => "Symmetrical link between credit note and cancelled invoice, if any.",
                'visible'           => [[['status', '=', 'cancelled']], [['type', '=', 'credit_note']]]
            ],

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'description'       => "The counter party organization the invoice relates to.",
                'required'          => true,
                'onupdate'          => 'onupdatePartnerId'
            ],

            'customer_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Identity of the customer (from partner).'
            ],

            'is_paid' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Indicator of the invoice payment status.",
                'visible'           => ['status', '=', 'invoice'],
                'function'          => 'calcIsPaid',
                'store'             => true
            ],

            'notice_html' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => "Additional notes to display on the final invoice (html)."
            ]

        ];
    }

    public static function calcIsPaid($self) {
        $result = [];
        $self->read([
            'status',
            'type',
            'price',
            'funding_id' => ['is_paid'],
            'fundings_ids' => ['paid_amount']
        ]);

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
            elseif($invoice['type'] === 'invoice') {
                $total_paid = 0;
                foreach($invoice['fundings_ids'] as $funding) {
                    $total_paid += $funding['paid_amount'];
                }
                if(round($total_paid, 2) >= round($invoice['price'], 2)) {
                    $is_paid = true;
                }
            }
            elseif($invoice['type'] === 'credit_note') {
                // #memo - marking arbitrary a funding as paid is accepted for an emitted credit note
                if($invoice['funding_id'] && $invoice['funding_id']['is_paid']) {
                    $is_paid = true;
                }
            }

            $result[$id] = $is_paid;
        }

        return $result;
    }

    public static function calcPaymentReference($self) {
        $result = [];
        $self->read(['booking_id' => ['payment_reference']]);
        foreach($self as $id => $invoice) {
            $result[$id] = $invoice['booking_id']['payment_reference'];
        }

        return $result;
    }

    public static function onupdatePartnerId($self) {
        $self->read(['partner_id' => ['partner_identity_id']]);
        foreach($self as $id => $invoice) {
            $self->update(['customer_identity_id' => $invoice['partner_id']['partner_identity_id'] ?? null]);
        }
    }

    public static function cancreate($om, $values, $lang='en') {
        $bookings = $om->read(Booking::getType(), [$values['booking_id']], ['center_id.organisation_id.has_vat']);
        if(!empty($bookings)) {
            $booking = reset($bookings);
            if($booking['center_id.organisation_id.has_vat']) {
                // the partner must be the same for all booking's invoices
                $domain = [
                    ['booking_id', '=', $values['booking_id']],
                    ['status', '<>', 'cancelled'],
                    ['is_deposit', '=', true],
                    ['type', '=', 'invoice']
                ];
                $other_invoices_ids = $om->search(self::getType(), $domain, ['created' => 'asc']);
                if(!empty($other_invoices_ids)) {
                    $other_invoices = $om->read(self::getType(), $other_invoices_ids, ['partner_id']);
                    if(!empty($other_invoices)) {
                        $other_invoice = reset($other_invoices);
                        if($values['partner_id'] !== $other_invoice['partner_id']) {
                            return ['partner_id' => ['must_be_same_partner_id' => "All booking's invoices must target the same customer."]];
                        }
                    }
                }
            }
        }

        return parent::cancreate($om, $values, $lang);
    }

    public static function canupdate($om, $oids, $values, $lang='en') {
        if(isset($values['partner_id'])) {
            $invoices = $om->read(self::getType(), $oids, ['booking_id', 'booking_id.center_id.organisation_id.has_vat']);

            if($invoices > 0) {
                foreach($invoices as $id => $invoice) {
                    if(!$invoice['booking_id.center_id.organisation_id.has_vat']) {
                        continue;
                    }

                    // the partner must be the same for all booking's invoices
                    $domain = [
                        ['id', '<>', $id],
                        ['booking_id', '=', $invoice['booking_id']],
                        ['status', '<>', 'cancelled'],
                        ['is_deposit', '=', true],
                        ['type', '=', 'invoice']
                    ];
                    $other_invoices_ids = $om->search(self::getType(), $domain, ['created' => 'asc']);
                    if(!empty($other_invoices_ids)) {
                        $other_invoices = $om->read(self::getType(), $other_invoices_ids, ['partner_id']);
                        if(!empty($other_invoices)) {
                            $other_invoice = reset($other_invoices);
                            if($values['partner_id'] !== $other_invoice['partner_id']) {
                                return ['partner_id' => ['must_be_same_partner_id' => "All booking's invoices must target the same customer."]];
                            }
                        }
                    }
                }
            }
        }

        return parent::canupdate($om, $oids, $values, $lang);
    }
}
