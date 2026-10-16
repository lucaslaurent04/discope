<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;

class InvoiceLineGroup extends \finance\accounting\InvoiceLineGroup {

    public static function getColumns() {
        return [

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Invoice',
                'description'       => 'Invoice the line is related to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\InvoiceLine',
                'foreign_field'     => 'invoice_line_group_id',
                'description'       => 'Detailed lines of the group.',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateInvoiceLinesIds'
            ]

        ];
    }

    public static function onupdateInvoiceLinesIds($self) {
        $self->do('reset-invoice-prices');
    }

    protected static function doResetInvoicePrices($self) {
        $self->read(['invoice_id']);

        $map_invoices_ids = [];
        foreach($self as $group) {
            $map_invoices_ids[$group['invoice_id']] = true;
        }

        Invoice::ids(array_keys($map_invoices_ids))
            ->update([
                'price' => null,
                'total' => null
            ]);
    }

    public static function getActions() {
        return [
            'reset-invoice-prices' => [
                'description'   => "Reset the prices of the invoices.",
                'policies'      => [],
                'function'      => 'doResetInvoicePrices'
            ]
        ];
    }
}
