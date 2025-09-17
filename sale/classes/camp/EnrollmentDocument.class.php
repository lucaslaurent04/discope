<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class EnrollmentDocument extends Model {

    public static function getColumns(): array {
        return [

            'enrollment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Enrollment',
                'required'          => true,
                'readonly'          => true
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Document',
                'required'          => true,
                'readonly'          => true
            ],

            'received' => [
                'type'              => 'boolean',
                'description'       => "Has the document been received?",
                'default'           => false,
                'onupdate'          => 'onupdateReceived'
            ]

        ];
    }

    public function getUnique(): array {
        return [
            ['enrollment_id', 'document_id']
        ];
    }

    public static function onupdateReceived($self) {
        $self->read(['enrollment_id']);
        foreach($self as $enrollment_doc) {
            Enrollment::id($enrollment_doc['enrollment_id'])
                ->update(['all_documents_received' => null]);
        }
    }
}
