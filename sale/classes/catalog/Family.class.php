<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\catalog;
use equal\orm\Model;

class Family extends Model {

	public static function getName() {
        return "Product Family";
    }

    public static function getDescription() {
        return "A Product Family is a group of goods produced under the same brand. Families support hierarchy.";
    }

    public static function getColumns() {
        /**
         * A Product Family is a group of goods produced under the same brand.
         * Families support hierarchy.
         */

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Name of the product family. A family is a group of goods produced under the same brand.",
                'required'          => true,
                'multilang'         => true
            ],

            'children_ids' => [ 
                'type'              => 'one2many', 
                'foreign_object'    => 'sale\catalog\Family', 
                'foreign_field'     => 'parent_id'
            ],

            'parent_id' => [
                'type'              => 'many2one',
                'description'       => "Product Family which current family belongs to, if any.",
                'foreign_object'    => 'sale\catalog\Family'
            ],

            'path' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Full path of the family with ancestors.',
                'store'             => true,
                'function'          => 'calcPath'
            ],

            'centers_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'identity\Center',
                'foreign_field'     => 'product_families_ids',
                'rel_table'         => 'sale_product_family_rel_identity_center',
                'rel_foreign_key'   => 'center_id',
                'rel_local_key'     => 'family_id'
            ],

            'product_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'foreign_field'     => 'family_id',
                'description'       => "Product models which current product belongs to the family."
            ]

        ];
    }

    protected static function calcPath($self, $lang): array {
        $result = [];
        $self->read(['name', 'parent_id']);
        foreach($self as $id => $category) {
            $result[$id] = self::addParentPath($category['name'], $category['parent_id'], $lang);
        }

        return $result;
    }

    public static function addParentPath($path, $parent_id, $lang) {
        if(is_null($parent_id)) {
            return $path;
        }

        $parent_category = self::id($parent_id)
            ->lang($lang)
            ->read(['name', 'parent_id'])
            ->first();

        return self::addParentPath(
            $parent_category['name'].'/'.$path,
            $parent_category['parent_id'],
            $lang
        );
    }
}
