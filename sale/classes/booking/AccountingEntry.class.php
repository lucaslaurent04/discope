<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;

class AccountingEntry extends \finance\accounting\AccountingEntry {

    public static function getColumns() {
        return [

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Invoice',
                'description'       => 'Invoice that the line relates to.',
                'ondelete'          => 'cascade',
                'visible'           => ['has_invoice', '=', true]
            ],

            'invoice_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\InvoiceLine',
                'description'       => 'Invoice line the entry relates to.',
                'ondelete'          => 'cascade',
                'visible'           => ['has_invoice', '=', true]
            ]

        ];
    }

}
