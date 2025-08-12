<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Document extends Model {

    public static function getDescription(): string {
        return "Document needed to participate to a camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the document.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Description of the document.",
                'usage'             => 'text/plain'
            ],

            'camp_models_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\CampModel',
                'foreign_field'     => 'required_documents_ids',
                'rel_table'         => 'sale_camp_rel_campmodel_document',
                'rel_foreign_key'   => 'camp_model_id',
                'rel_local_key'     => 'document_id',
                'description'       => "Camp models that requires the document."
            ],

            'camps_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Camp',
                'foreign_field'     => 'required_documents_ids',
                'rel_table'         => 'sale_camp_rel_camp_document',
                'rel_foreign_key'   => 'camp_id',
                'rel_local_key'     => 'document_id',
                'description'       => "Camps that requires the document."
            ]

        ];
    }
}
