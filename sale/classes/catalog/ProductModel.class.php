<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\catalog;
use equal\orm\Model;

class ProductModel extends Model {

    public static function getName() {
        return "Product Model";
    }

    public static function getDescription() {
        return "Product Models act as common denominator for products variants (referred to as \"Products\").\n
         These objects are used for catalogs generation: for instance, if a picture is related to a Product, it is associated on the Product Model level.\n
         A Product Model has at minimum one variant, which means at minimum one SKU.\n";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Name of the product model (used for all variants).",
                'required'          => true
            ],

            'family_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Family',
                'description'       => "Product Family which current product belongs to.",
                'onupdate'          => 'onupdateFamilyId',
                'required'          => true
            ],

            'selling_accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule'
            ],

            'buying_accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule'
            ],

            'stat_section_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\stats\StatSection',
                'description'       => 'Statistics section to which relates the product, if any.'
            ],

            'can_buy' => [
                'type'              => 'boolean',
                'description'       => "Can this product be purchassed?",
                'default'           => false
            ],

            'can_sell' => [
                'type'              => 'boolean',
                'description'       => "Can this product be sold?",
                'default'           => true,
                'onupdate'          => 'onupdateCanSell'
            ],

            'cost' => [
                'type'              => 'boolean',
                'description'       => 'Buying cost.',
                'visible'           => ['can_buy', '=', true]
            ],

            'is_pack' => [
                'type'              => 'boolean',
                'description'       => "Is the product a bundle of other products?",
                'default'           => false,
                'onupdate'          => 'onupdateIsPack'
            ],

            'has_own_price' => [
                'type'              => 'boolean',
                'description'       => 'Has the pack its own price, or do we use each sub-product price?',
                'default'           => false,
                'visible'           => ['is_pack', '=', true],
                'onupdate'          => 'onupdateHasOwnPrice'
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => [
                    'consumable',
                    'service'
                ],
                'required'          => true,
                'default'           => 'service'
            ],

            'consumable_type' => [
                'type'              => 'string',
                'selection'         => [
                    'simple',
                    'storable'
                ],
                'visible'           => ['type', '=', 'consumable']
            ],

            'service_type' => [
                'type'              => 'string',
                'selection'         => [
                    'simple',
                    'schedulable'
                ],
                'visible'           => ['type', '=', 'service'],
                'default'           => 'simple'
            ],

            'schedule_type' => [
                'type'              => 'string',
                'selection'         => [
                    'time',
                    'timerange'
                ],
                'default'           => 'time',
                'visible'           => [ ['type', '=', 'service'], ['service_type', '=', 'schedulable'] ]
            ],

            'schedule_default_value' => [
                'type'              => 'string',
                'description'       => "Default value of the schedule according to type (time: '9:00', timerange: '9:00-10:00').",
                'visible'           => [ ['type', '=', 'service'], ['service_type', '=', 'schedulable'] ]
            ],

            'schedule_offset' => [
                'type'              => 'integer',
                'description'       => 'Default number of days to set-off the service from a sojourn start date.',
                'default'           => 0,
                'visible'           => [ ['type', '=', 'service'], ['service_type', '=', 'schedulable'] ]
            ],

            'time_slot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\TimeSlot',
                'description'       => "The specific time slot at which the service can take place.",
                'help'              => "This value is used when creating the consumptions relating to scheduled products (mostly meals).",
                'visible'           => [ ['type', '=', 'service'], ['service_type', '=', 'schedulable'] ]
            ],

            'time_slots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\booking\TimeSlot',
                'foreign_field'     => 'product_models_ids',
                'rel_table'         => 'sale_catalog_product_model_rel_sale_booking_timeslot',
                'rel_foreign_key'   => 'time_slot_id',
                'rel_local_key'     => 'product_model_id',
                'description'       => "The specific time slots at which the service can take place.",
                'help'              => "This field applies only to activities that can be scheduled on specific time slots. Most of the time a product is linked to a single time slot.",
                'visible'           => [
                                            [
                                                ['type', '=', 'service'],
                                                ['service_type', '=', 'schedulable'] ,
                                                ['is_activity', '=', true],
                                                ['is_fullday', '=', false]
                                            ],
                                            [
                                                ['type', '=', 'service'],
                                                ['service_type', '=', 'schedulable'] ,
                                                ['is_meal', '=', true]
                                            ],
                                            [
                                                ['type', '=', 'service'],
                                                ['service_type', '=', 'schedulable'] ,
                                                ['is_snack', '=', true]
                                            ]
                                        ],
            ],

            'tracking_type' => [
                'type'              => 'string',
                'selection'         => [
                    'none',
                    'batch',
                    'sku',
                    'upc'
                ],
                'visible'           => [ ['type', '=', 'consumable'], ['consumable_type', '=', 'storable'] ],
                'default'           => 'sku'
            ],

            'description_delivery' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description for delivery notes.",
                'multilang'         => true
            ],

            'description_receipt' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description for reception vouchers.",
                'multilang'         => true
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Group',
                'foreign_field'     => 'product_models_ids',
                'rel_table'         => 'sale_catalog_product_rel_productmodel_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'productmodel_id',
                'onupdate'          => 'onupdateGroupsIds'
            ],

            'categories_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Category',
                'foreign_field'     => 'product_models_ids',
                'rel_table'         => 'sale_product_rel_productmodel_category',
                'rel_foreign_key'   => 'category_id',
                'rel_local_key'     => 'productmodel_id'
            ],

            'products_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\Product',
                'foreign_field'     => 'product_model_id',
                'description'       => "Product variants that are related to this model.",
            ],

            'qty_accounting_method' => [
                'type'              => 'string',
                'description'       => 'The way the product quantity has to be computed (per unit [default], per person, or per accommodation [resource]).',
                'selection'         => [
                    'person',           // depends on the number of people
                    'accomodation',     // depends on the number of nights
                    'unit'              // only depends on quantity
                ],
                'default'           => 'unit'
            ],

            'booking_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingType',
                'description'       => "The kind of booking it is about.",
                'default'           => 1                // default to 'general public'
            ],

            'is_repeatable' => [
                'type'              => 'boolean',
                'description'       => 'Model relates to a consumption that is repeated each day of the sojourn.',
                'default'           => false,
                'visible'           => [ 'has_duration', '=', false ]
            ],

            'is_accomodation' => [
                'type'              => 'boolean',
                'description'       => 'Model relates to a rental unit that is an accommodation.',
                'visible'           => [ ['type', '=', 'service'], ['is_rental_unit', '=', true] ]
            ],

            'is_rental_unit' => [
                'type'              => 'boolean',
                'description'       => 'Is the product a rental_unit?',
                'default'           => false,
                'onupdate'          => 'onupdateIsRentalUnit',
                'visible'           => [ ['type', '=', 'service'], ['is_meal', '=', false] , ['is_snack', '=', false], ['is_activity', '=', false], ['is_transport', '=', false], ['is_supply', '=', false] ]
            ],

            'is_meal' => [
                'type'              => 'boolean',
                'description'       => 'Is the product a meal? (meals might be part of the board / included services of the stay).',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_rental_unit', '=', false] , ['is_snack', '=', false], ['is_activity', '=', false], ['is_transport', '=', false], ['is_supply', '=', false] ]
            ],

            'is_snack' => [
                'type'              => 'boolean',
                'description'       => 'Is the product a snack?.',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_rental_unit', '=', false], ['is_meal', '=', false], ['is_activity', '=', false], ['is_transport', '=', false], ['is_supply', '=', false] ]
            ],

            'is_activity' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the product is an activity or animation.',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_rental_unit', '=', false], ['is_snack', '=', false], ['is_meal', '=', false], ['is_transport', '=', false], ['is_supply', '=', false] ]
            ],

            'is_transport' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the product is an activity transport service (transport to and back from an activity).',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_rental_unit', '=', false], ['is_snack', '=', false], ['is_meal', '=', false], ['is_activity', '=', false], ['is_supply', '=', false] ]
            ],

            'is_supply' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the product is an activity supply service (supply to rent for an activity).',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_rental_unit', '=', false], ['is_snack', '=', false], ['is_meal', '=', false], ['is_activity', '=', false], ['is_transport', '=', false] ]
            ],

            'activity_scope' => [
                'type'              => 'string',
                'description'       => 'Specifies whether the activity is internal or external.',
                'selection'         => [
                    'internal',
                    'external',
                ],
                'default'           => 'internal',
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] ]
            ],

            'has_activity_duration' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the activity has a specific duration.',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] ]
            ],

            'activity_duration' => [
                'type'              => 'float',
                'description'       => 'Specifies the duration of the activity (in hours).',
                'default'           => 4,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true], ['has_activity_duration', '=', true] ]
            ],

            'has_transport_required' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the activity requires transport.',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] ]
            ],

            'transport_product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'description'       => 'References the transport product model associated with the activity.',
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true], ['has_transport_required', '=', true] ]
            ],

            'has_supply' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the product requires specific supplies.',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] ]
            ],

            'supplies_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Supply',
                'foreign_field'     => 'product_models_ids',
                'rel_table'         => 'sale_catalog_product_model_rel_sale_catalog_supplies',
                'rel_foreign_key'   => 'supply_id',
                'rel_local_key'     => 'product_model_id',
                'description'       => 'References the supplies required for the activity.',
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true], ['has_supply', '=', true] ]
            ],

            'has_provider' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the product requires specific provider.',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true], ['has_staff_required', '=', false] ]
            ],

            'providers_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\provider\Provider',
                'foreign_field'     => 'product_models_ids',
                'rel_table'         => 'sale_catalog_product_model_rel_sale_provider_providers',
                'rel_foreign_key'   => 'provider_id',
                'rel_local_key'     => 'product_model_id',
                'description'       => 'References the providers required for the activity.',
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true], ['has_provider', '=', true], ['has_staff_required', '=', false] ]
            ],

            'is_fullday' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the animation lasts the full day. If true, assignments must include both AM and PM; otherwise, only one of them.',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] ]
            ],

            'is_billable' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the activity is billable (generates a service line in invoicing).',
                'default'           => true,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] ]
            ],

            'has_staff_required' => [
                'type'              => 'boolean',
                'description'       => 'Indicates whether the activity requires dedicated staff to be assigned.',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] ]
            ],

            'has_rental_unit' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the activity requires the assignation of a rental unit.",
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] ],
                'onupdate'          => 'onupdateHasRentalUnit'
            ],

            'activity_rental_units_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\RentalUnit',
                'foreign_field'     => 'product_models_ids',
                'rel_table'         => 'sale_catalog_product_model_rel_realestate_rentalunit',
                'rel_foreign_key'   => 'rental_unit_id',
                'rel_local_key'     => 'product_model_id',
                'description'       => 'Rental Units this Activity relate.',
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true], ['has_rental_unit', '=', true] ],
                'onupdate'          => 'onupdateActivityRentalUnitIds'
            ],

            'has_age_range' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the product model has age-based participation restrictions.",
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] ]
            ],

            'age_minimum' => [
                'type'              => 'integer',
                'description'       => "Specifies the minimum age required to participate in the activity.",
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] , ['has_age_range', '=', true]]
            ],

            'age_ranges_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\customer\AgeRange',
                'foreign_field'     => 'product_models_ids',
                'rel_table'         => 'sale_catalog_product_model_rel_sale_customer_age_ranges',
                'rel_foreign_key'   => 'age_range_id',
                'rel_local_key'     => 'product_model_id',
                'description'       => "Defines the applicable age ranges for the product model, ensuring age-specific eligibility for activities or services.",
                'visible'           => [ ['type', '=', 'service'], ['is_activity', '=', true] , ['has_age_range', '=', true]]
            ],

            'nutritional_coefficient' => [
                'type'              => 'integer',
                'description'       => "The nutritional coefficient of the meal.",
                'default'           => 1,
                'visible'           => ['is_meal', '=', true]
            ],

            'rental_unit_assignement' => [
                'type'              => 'string',
                'description'       => 'The way the product is assigned to a rental unit (a specific unit, a specific category, or based on capacity match).',
                'selection'         => [
                    'unit',             // only one specific rental unit can be assigned to the products
                    'category',         // only rental units of the specified category can be assigned to the products
                    'auto'              // rental unit assignment is based on required qty/capacity (best match first)
                ],
                'default'           => 'category',
                'visible'           => [ ['is_rental_unit', '=', true] ]
            ],

            'has_duration' => [
                'type'              => 'boolean',
                'description'       => 'Does the product have a specific duration.',
                'default'           => false,
                'visible'           => ['type', '=', 'service']
            ],

            'duration' => [
                'type'              => 'integer',
                'description'       => 'Duration of the service (in days), used for planning.',
                'default'           => 1,
                'visible'           => [ ['type', '=', 'service'], ['has_duration', '=', true] ]
            ],

            'capacity' => [
                'type'              => 'integer',
                'description'       => 'Capacity implied by the service (used for filtering rental units).',
                'default'           => 1
            ],

            // a product either refers to a specific rental unit, or to a category of rental units (both allowing to find matching units for a given period and a capacity)
            'rental_unit_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\RentalUnitCategory',
                'description'       => "Rental Unit Category this Product related to, if any.",
                'visible'           => [ ['is_rental_unit', '=', true], ['rental_unit_assignement', '=', 'category'] ]
            ],

            'rental_unit_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\RentalUnit',
                'description'       => "Specific Rental Unit this Product related to, if any",
                'visible'           => [ ['is_rental_unit', '=', true], ['rental_unit_assignement', '=', 'unit'] ],
                'onupdate'          => 'onupdateRentalUnitId'
            ],

            'allow_price_adaptation' => [
                'type'              => 'boolean',
                'description'       => 'Flag telling if price adaptation can be applied on the variants (or children for packs).',
                'default'           => true,
                'visible'           => ['is_pack', '=', true],
                'onupdate'          => 'onupdateAllowPriceAdaptation'
            ],

            'meal_location' => [
                'type'              => 'string',
                'selection'         => [
                    'inside',
                    'outside',
                    'takeaway'
                ],
                'default'           => 'inside',
                'visible'           => [
                    ['is_meal', '=', true],
                    ['is_repeatable', '=', false]
                ]
            ],

            'grouping_code_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\GroupingCode',
                'description'       => "Specific GroupingCode this Product Model related to, if any",
                'onupdate'          => 'onupdateGroupingCode'
            ],

        ];
    }

    public static function onchange($om, $event, $values, $lang='en') {
        $result = [];

        if(isset($event['is_meal'])) {
            $result['is_snack'] = false;
        }
        if(isset($event['is_snack'])) {
            $result['is_meal'] = false;
        }
        return $result;
    }

    /**
     *
     * Update related products is_pack
     */
    public static function onupdateIsPack($om, $oids, $values, $lang) {
        $models = $om->read(get_called_class(), $oids, ['products_ids', 'is_pack']);
        foreach($models as $mid => $model) {
            $om->write('sale\catalog\Product', $model['products_ids'], ['is_pack' => $model['is_pack']]);
        }
    }


    public static function onupdateHasOwnPrice($om, $oids, $values, $lang) {
        $models = $om->read(get_called_class(), $oids, ['products_ids', 'has_own_price']);
        foreach($models as $mid => $model) {
            $om->write('sale\catalog\Product', $model['products_ids'], ['has_own_price' => $model['has_own_price']]);
        }
    }


    /**
     *
     * Update related products can_sell
     */
    public static function onupdateCanSell($om, $oids, $values, $lang) {
        $models = $om->read(get_called_class(), $oids, ['products_ids', 'can_sell']);
        foreach($models as $mid => $model) {
            $om->write('sale\catalog\Product', $model['products_ids'], ['can_sell' => $model['can_sell']]);
        }
    }

    public static function onupdateFamilyId($om, $oids, $values, $lang) {
        $models = $om->read(get_called_class(), $oids, ['products_ids', 'family_id']);
        foreach($models as $mid => $model) {
            $om->write('sale\catalog\Product', $model['products_ids'], ['family_id' => $model['family_id']]);
        }
    }

    public static function onupdateGroupsIds($om, $oids, $values, $lang) {
        $models = $om->read(get_called_class(), $oids, ['products_ids', 'groups_ids']);
        foreach($models as $mid => $model) {
            $products = $om->read('sale\catalog\Product', $model['products_ids'], ['groups_ids']);
            foreach($products as $pid => $product) {
                $groups_ids = array_map(function($a) {return "-$a";}, (array) $product['groups_ids']);
                $groups_ids = array_merge($groups_ids, $model['groups_ids']);
                $om->write('sale\catalog\Product', $pid, ['groups_ids' => $groups_ids]);
            }
        }
    }

    /**
     * Keep activity_rental_units_ids synced with has_rental_unit
     */
    public static function onupdateHasRentalUnit($self) {
        $self->read(['has_rental_unit', 'activity_rental_units_ids']);
        foreach($self as $id => $product_model) {
            if(!$product_model['has_rental_unit'] && !empty($product_model['activity_rental_units_ids'])) {
                $ids_to_remove = [];
                foreach($product_model['activity_rental_units_ids'] as $rental_unit_id) {
                    $ids_to_remove[] = -$rental_unit_id;
                }
                self::id($id)->update(['activity_rental_units_ids' => $ids_to_remove]);
            }
        }
    }

    /**
     * Keep has_rental_unit synced with activity_rental_units_ids
     */
    public static function onupdateActivityRentalUnitIds($self) {
        $self->read(['has_rental_unit', 'activity_rental_units_ids']);
        foreach($self as $id => $product_model) {
            if(!empty($product_model['activity_rental_units_ids']) && !$product_model['has_rental_unit']) {
                self::id($id)->update(['has_rental_unit' => true]);
            }
            elseif(empty($product_model['activity_rental_units_ids']) && $product_model['has_rental_unit']) {
                self::id($id)->update(['has_rental_unit' => false]);
            }
        }
    }

    /**
     * Assign the related rental unity capacity as own capacity.
     */
    public static function onupdateRentalUnitId($om, $ids, $values, $lang) {
        $models = $om->read(self::gettype(), $ids, ['rental_unit_id.capacity', 'rental_unit_id.is_accomodation'], $lang);
        foreach($models as $id => $model) {
            $om->update(self::gettype(), $id, ['capacity' => $model['rental_unit_id.capacity'], 'is_accomodation' => $model['rental_unit_id.is_accomodation']]);
        }
    }

    /**
     * Sync model with variants (products) upon change for `allow_price_adaptation`
     */
    public static function onupdateAllowPriceAdaptation($om, $ids, $values, $lang) {
        $models = $om->read(self::getType(), $ids, ['products_ids', 'allow_price_adaptation'], $lang);
        foreach($models as $id => $model) {
            $om->update('sale\catalog\Product', $model['products_ids'], ['allow_price_adaptation' => $model['allow_price_adaptation']]);
        }
    }

    public static function onupdateIsRentalUnit($om, $ids, $values, $lang) {
        $models = $om->read(self::getType(), $ids, ['is_rental_unit'], $lang);
        foreach($models as $id => $model) {
            if(!$model['is_rental_unit']) {
                $om->update(self::gettype(), $id, ['is_accomodation' => false]);
            }
        }
    }

    public static function onupdateGroupingCode($om, $ids, $values, $lang) {
        $models = $om->read(self::getType(), $ids, ['products_ids','grouping_code_id'], $lang);
        foreach($models as $id => $model) {
            $om->update('sale\catalog\Product', $model['products_ids'], ['grouping_code_id' => $model['grouping_code_id']]);
        }
    }

}
