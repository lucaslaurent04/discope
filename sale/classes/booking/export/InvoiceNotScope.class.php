<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking\export;

use equal\orm\traits\IsNotScoped;
use sale\booking\Invoice;

class InvoiceNotScope extends Invoice {

    use IsNotScoped;

    public static function getColumns() {
        return [

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\export\InvoiceLineNotScope',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateInvoiceLinesIds'
            ]

        ];
    }
}
