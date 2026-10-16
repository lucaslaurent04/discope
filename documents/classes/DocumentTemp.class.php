<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace documents;

class DocumentTemp extends Document {

    /** @deprecated */
    public function getTable() {
        return self::getModelTable();
    }

    public static function getModelTable() {
        return 'documents_document_temp';
    }

    public static function calcLink($self): array {
        $result = [];
        $self->read(['hash']);
        foreach($self as $id => $document) {
            if(strlen($document['hash'])) {
                $result[$id] = '/document/'.$document['hash'].'?is_temp=true';
            }
        }

        return $result;
    }
}
