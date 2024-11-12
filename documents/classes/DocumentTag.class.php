<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace documents;

use equal\orm\Model;

class DocumentTag extends Model {

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Name of the document Tag (used for all variants).",
                'required'          => true
            ],
            
            'description' => [
                'type'              => 'string',
                'description'       => "Short string describing the purpose and usage of the category."
            ],

            'documents_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'documents\Document',
                'foreign_field'     => 'tags_ids',
                'rel_table'         => 'documents_rel_document_tag',
                'rel_foreign_key'   => 'document_id',
                'rel_local_key'     => 'tag_id'
            ]
        ];
    }   
}