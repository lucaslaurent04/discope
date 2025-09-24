<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace lathus\sale\camp;

use equal\orm\Model;

class Enrollment extends Model {

    public static function getDescription(): string {
        return "Override of camp Enrollment to add data fetched from CPA Lathus API.";
    }

    public static function canupdate($self, $values): array {
        return [];
    }
}
