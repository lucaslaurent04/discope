<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;

class AccountingEntry extends \finance\accounting\AccountingEntry {

    public static function getColumns() {
        return [

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Invoice',
                'description'       => 'Invoice that the line relates to.',
                'ondelete'          => 'cascade',
                'visible'           => ['has_invoice', '=', true]
            ],

            'invoice_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\InvoiceLine',
                'description'       => 'Invoice line the entry relates to.',
                'ondelete'          => 'cascade',
                'visible'           => ['has_invoice', '=', true]
            ]

        ];
    }

}
