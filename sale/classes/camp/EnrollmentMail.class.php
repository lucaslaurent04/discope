<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class EnrollmentMail extends Model {

    public static function getDescription(): string {
        return "";
    }

    public static function getColumns(): array {
        return [

            'mail_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Mail',
                'description'       => "The mail that was sent.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'mail_type' => [
                'type'              => 'string',
                'selection'         => [
                    'pre-registration',
                    'confirmation'
                ],
                'default'           => 'pre-registration'
            ],

            'enrollments_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Enrollment',
                'foreign_field'     => 'enrollment_mails_ids',
                'rel_table'         => 'sale_camp_rel_enrollment_mail',
                'rel_foreign_key'   => 'enrollment_id',
                'rel_local_key'     => 'enrollment_mail_id',
                'description'       => "The enrollments that are linked to this mail."
            ]

        ];
    }
}
