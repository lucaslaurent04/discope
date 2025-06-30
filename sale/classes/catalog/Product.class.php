<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\catalog;
use equal\orm\Model;

class Product extends Model {

    public static function getName() {
        return "Product";
    }

    public static function getDescription() {
        return "A Product is a variant of a Product Model. There is always at least one Product for a given Product Model.\n
         Within the organisation, a product is always referenced by a SKU code (assigned to each variant of a Product Model).\n
         A SKU code identifies a single product with all its specific characteristics.\n";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => 'The full name of the product (label + sku).'
            ],

            'code_legacy' => [
                'type'              => 'string',
                'description'       => "Old code of the product."
            ],

            // #todo - deprecate
            'ref_pack_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\PackLine',
                'foreign_field'     => 'child_product_id',
                'description'       => "Pack lines that relate to the product."
            ],

            'label' => [
                'type'              => 'string',
                'description'       => 'Human readable mnemo for identifying the product. Allows duplicates.',
                'required'          => true,
                'onupdate'          => 'onupdateLabel'
            ],

            'sku' => [
                'type'              => 'string',
                'description'       => "Stock Keeping Unit code for internal reference. Must be unique.",
                'required'          => true,
                'unique'            => true,
                'onupdate'          => 'onupdateSku'
            ],

            'ean' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.ean',
                'description'       => "IAN/EAN code for barcode generation."
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description of the variant (specifics)."
            ],

            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'description'       => "Product Model of this variant.",
                'required'          => true,
                'onupdate'          => 'onupdateProductModelId',
                'dependents'        => ['has_own_price', 'is_pack', 'is_rental_unit', 'is_meal', 'is_snack', 'is_activity', 'is_transport', 'is_supply']
            ],

            'family_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Family',
                'description'       => "Product Family which current product belongs to."
            ],

            'is_pack' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'is_pack'],
                'description'       => 'Is the product a pack? (from model).',
                'store'             => true,
                'readonly'          => true
            ],

            'is_locked' => [
                'type'              => 'boolean',
                'description'       => 'Is the pack static? (cannot be modified).',
                'default'           => false,
                'visible'           => [ ['is_pack', '=', true] ]
            ],

            'has_own_price' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'has_own_price'],
                'description'       => 'Product is a pack with its own price (from model).',
                'visible'           => ['is_pack', '=', true],
                'store'             => true,
                'readonly'          => true
            ],

            'pack_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\PackLine',
                'foreign_field'     => 'parent_product_id',
                'description'       => "Products that are bundled in the pack.",
                'ondetach'          => 'delete'
            ],

            'product_attributes_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\ProductAttribute',
                'foreign_field'     => 'product_id',
                'description'       => "Attributes set for the product.",
                'ondetach'          => 'delete'
            ],

            'is_rental_unit' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'is_rental_unit'],
                'instant'           => true,
                'store'             => true
            ],

            'is_meal' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'is_meal'],
                'instant'           => true,
                'store'             => true
            ],

            'is_snack' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'is_snack'],
                'store'             => true
            ],

            'is_activity' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'is_activity'],
                'instant'           => true,
                'store'             => true
            ],

            'is_transport' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'is_transport'],
                'instant'           => true,
                'store'             => true
            ],

            'is_supply' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'is_supply'],
                'instant'           => true,
                'store'             => true
            ],

            'is_camp' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'is_camp'],
                'store'             => true
            ],

            'is_fullday' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['product_model_id' => 'is_fullday'],
                'instant'           => true,
                'store'             => true
            ],

            'is_billable' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Indicates whether the activity is billable (generates a service line in invoicing).",
                'relation'          => ['product_model_id' => 'is_billable'],
                'instant'           => true,
                'store'             => true
            ],

            'is_freebie_allowed' => [
                'type'              => 'boolean',
                'description'       => 'Is the product eligible for freebies?',
                'help'              => 'If not set, the product will not be considered when computing the freebies eligibility.',
                'default'           => false
            ],

            // if the organisation uses price-lists, the price to use depends on the applicable

            'prices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\price\Price',
                'foreign_field'     => 'product_id',
                'description'       => "Prices that are related to this product.",
                'ondetach'          => 'delete'
            ],

            'stat_section_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\stats\StatSection',
                'description'       => 'Statistics section (overloads the model one, if any).'
            ],

            'analytic_section_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AnalyticSection',
                'description'       => 'Analytic section (overloads the model one, if any).'
            ],

            /* can_buy and can_sell are adapted when related values are changed in parent product_model */

            'can_buy' => [
                'type'              => 'boolean',
                'description'       => "Can this product be purchased?",
                'default'           => false
            ],

            'can_sell' => [
                'type'              => 'boolean',
                'description'       => "Can this product be sold?",
                'default'           => true
            ],

            'allow_price_adaptation' => [
                'type'              => 'boolean',
                'description'       => 'Flag telling if price adaptation can be applied on the variants (or children for packs).',
                'default'           => true,
                'visible'           => ['is_pack', '=', true],
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Group',
                'foreign_field'     => 'products_ids',
                'rel_table'         => 'sale_catalog_product_rel_product_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'product_id'
            ],

            'has_age_range' => [
                'type'              => 'boolean',
                'description'       => "Applies on a specific age range?",
                'default'           => false
            ],

            'age_range_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\AgeRange',
                'description'       => 'Customers age range the product is intended for.',
                'onupdate'          => 'onupdateAgeRangeId',
                'visible'           => [ ['has_age_range', '=', true] ]
            ],

            'has_rate_class' => [
                'type'              => 'boolean',
                'description'       => "Applies on a specific rate class?",
                'default'           => false
            ],

            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => 'Customers rate class the product is intended for.',
                'onupdate'          => 'onupdateRateClassId',
                'visible'           => [ ['has_rate_class', '=', true] ]
            ],

            'grouping_code_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\catalog\GroupingCode',
                'function'          => 'calcGroupingCode',
                'description'       => "Specific GroupingCode this Product related to, if any",
                'instant'           => true,
                'store'             => true
            ]
        ];
    }


    public static function calcGroupingCode($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_model_id.grouping_code_id'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_model_id.grouping_code_id'];
            }
        }
        return $result;
    }
    /**
     * Computes the display name of the product as a concatenation of Label and SKU.
     *
     */
    public static function calcName($om, $ids, $lang) {
        $result = [];
        $res = $om->read(get_called_class(), $ids, ['label', 'sku'], $lang);
        foreach($res as $id => $odata) {
            if( (isset($odata['label']) && strlen($odata['label']) > 0 ) || (isset($odata['sku']) && strlen($odata['sku']) > 0) ) {
                $result[$id] = "{$odata['label']} ({$odata['sku']})";
            }
        }
        return $result;
    }

    public static function onupdateLabel($om, $ids, $values, $lang) {
        $om->update(self::getType(), $ids, ['name' => null], $lang);
    }

    public static function onupdateSku($om, $ids, $values, $lang) {
        $products = $om->read(self::getType(), $ids, ['prices_ids']);
        if($products > 0 && count($products)) {
            $prices_ids = [];
            foreach($products as $product) {
                $prices_ids = array_merge($prices_ids, $product['prices_ids']);
            }
            $om->update('sale\price\Price', $prices_ids, ['name' => null], $lang);
        }
        $om->update(self::getType(), $ids, ['name' => null], $lang);
    }

    public static function onupdateProductModelId($om, $ids, $values, $lang) {
        $products = $om->read(get_called_class(), $ids, ['product_model_id.can_sell', 'product_model_id.groups_ids', 'product_model_id.family_id']);
        foreach($products as $id => $product) {
            $om->update(self::getType(), $id, [
                'can_sell'      => $product['product_model_id.can_sell'],
                'groups_ids'    => $product['product_model_id.groups_ids'],
                'family_id'     => $product['product_model_id.family_id']
            ]);
        }
    }

    public static function onupdateAgeRangeId($self) {
        $self->read(['age_range_id']);
        foreach($self as $id => $product) {
            self::id($id)->update(['has_age_range' => boolval($product['age_range_id'])]);
        }
    }

    public static function onupdateRateClassId($self) {
        $self->read(['rate_class_id']);
        foreach($self as $id => $product) {
            self::id($id)->update(['has_rate_class' => boolval($product['rate_class_id'])]);
        }
    }

}
