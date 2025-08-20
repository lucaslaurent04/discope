<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\price;
use equal\orm\Model;

class Price extends Model {
    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'function'          => 'calcName',
                'result_type'       => 'string',
                'store'             => true,
                'description'       => 'The display name of the price.'
            ],

            'price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Tax excluded price.",
                'onupdate'          => 'onupdatePrice',
                'required'          => true
            ],

            'price_vat' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcPriceVat',
                'usage'             => 'amount/money:4',
                'description'       => "Tax included price. This field is used to allow encoding prices VAT incl.",
                'store'             => true,
                'onupdate'          => 'onupdatePriceVat'
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => ['direct', 'computed'],
                'default'           => 'direct'
            ],

            'calculation_method_id' => [
                'type'              => 'string',
                'description'       => "Method to use for price computation.",
                'visible'           => ['type', '=', 'computed']
            ],

            'price_list_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\PriceList',
                'description'       => "The Price List the price belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade',
                'onupdate'          => 'onupdatePriceListId'
            ],

            'is_active' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcIsActive',
                'store'             => true,
                'description'       => "Is the price currently applicable?"
            ],

            'accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule',
                'description'       => "Selling accounting rule. If set, overrides the rule of the product this price is assigned to.",
                'onupdate'          => 'onupdateAccountingRuleId'
            ],

            'analytic_section_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AnalyticSection',
                'description'       => 'Analytic section (overloads the product model one, if any).'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => "The Product (sku) the price applies to.",
                'required'          => true,
                'onupdate'          => 'onupdateProductId'
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/rate',
                'function'          => 'calcVatRate',
                'description'       => 'VAT rate applied on the price (from accounting rule).',
                'store'             => true,
                'readonly'          => true
            ],

            'has_rate_class' => [
                'type'        => 'boolean',
                'description' => 'Indicates whether the price depends on a rate class.',
                'default'     => false
            ],

            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => 'The rate class that applies to the price, defining variations based on the target audience.',
                'visible'           => ['has_rate_class', '=', true]
            ],

        ];
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];
        $res = $om->read(__CLASS__, $oids, ['product_id.sku', 'price_list_id.name', 'has_rate_class', 'rate_class_id.name']);
        if($res > 0 && count($res)) {
            foreach($res as $oid => $odata) {

            $name= "{$odata['product_id.sku']} - {$odata['price_list_id.name']}";

            if (!empty($odata['has_rate_class']) && !empty($odata['rate_class_id.name'])) {
                $name .= " - {$odata['rate_class_id.name']}";
            }
            $result[$oid] = $name;
            }
        }
        return $result;
    }

    public static function calcVatRate($om, $oids, $lang) {
        $result = [];
        $prices = $om->read(__CLASS__, $oids, ['accounting_rule_id.vat_rule_id.rate']);

        if($prices > 0 && count($prices)) {
            foreach($prices as $pid => $price) {
                if(isset($price['accounting_rule_id.vat_rule_id.rate'])) {
                    $result[$pid] = $price['accounting_rule_id.vat_rule_id.rate'];
                }
            }
        }
        return $result;
    }

    public static function calcPriceVat($om, $oids, $lang) {
        $result = [];
        $prices = $om->read(__CLASS__, $oids, ['price', 'vat_rate']);

        if($prices > 0 && count($prices)) {
            foreach($prices as $pid => $price) {
                $result[$pid] = $price['price'] * (1.0 + $price['vat_rate']);
            }
        }
        return $result;
    }

    public static function calcIsActive($om, $oids, $lang) {
        $result = [];
        $prices = $om->read(__CLASS__, $oids, ['price_list_id.is_active']);

        if($prices > 0 && count($prices)) {
            foreach($prices as $pid => $price) {
                $result[$pid] = $price['price_list_id.is_active'];
            }
        }
        return $result;
    }

    public static function onupdateAccountingRuleId($om, $oids, $values, $lang) {
        $res = $om->write(__CLASS__, $oids, ['vat_rate' => null, 'price_vat' => null]);
    }

    public static function onupdatePriceListId($om, $oids, $values, $lang) {
        $om->write(__CLASS__, $oids, ['name' => null], $lang);
    }

    public static function onupdateProductId($om, $oids, $values, $lang) {
        $om->write(__CLASS__, $oids, ['name' => null], $lang);
    }

    /**
     * Update price, based on VAT incl. price and applied VAT rate
     */
    public static function onupdatePriceVat($om, $oids, $values, $lang) {
        $prices = $om->read(__CLASS__, $oids, ['price_vat', 'vat_rate']);

        if($prices > 0 && count($prices)) {
            foreach($prices as $pid => $price) {
                $om->write(__CLASS__, $pid, ['price' => $price['price_vat'] / (1.0 + $price['vat_rate'])]);
            }
        }
    }

    public static function onupdatePrice($om, $oids, $values, $lang) {
        $om->write(__CLASS__, $oids, ['price_vat' => null]);
    }

    public function getUnique() {
        return [
            ['product_id', 'price_list_id', 'rate_class_id']
        ];
    }


    /**
     * Signature for single object change from views.
     *
     * @param  array    $event     Associative array holding changed fields as keys, and their related new values.
     * @param  array    $values    Copy of the current (partial) state of the object.
     * @return array    Associative array mapping fields with their resulting values.
     */
    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['price'])) {
            $result['price_vat'] = round($event['price'] * (1 + $values['vat_rate'] ?? 0), 4);
        }
        elseif(isset($event['price_vat'])) {
            $result['price'] = round($event['price_vat'] / (1 + $values['vat_rate'] ?? 0), 4);
        }
        return $result;
    }
}
