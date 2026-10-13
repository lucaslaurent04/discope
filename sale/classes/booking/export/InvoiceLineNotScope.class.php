<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking\export;

use equal\orm\traits\IsNotScoped;
use sale\booking\InvoiceLine;

class InvoiceLineNotScope extends InvoiceLine {

    use IsNotScoped;
}
