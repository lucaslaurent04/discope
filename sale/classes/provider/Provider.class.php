<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\provider;

class Provider extends \identity\Partner {

    public static function getName() {
        return 'Provider';
    }

    public static function getColumns() {
        return [

            'relationship' => [
                'type'              => 'string',
                'default'           => 'provider',
                'description'       => 'Force relationship to Provider.'
            ],

            'address' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcAddress',
                'description'       => 'Main address from related Identity.',
                'store'             => true,
                'instant'           => true
            ],


            'vat_number' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Value Added Tax identification number, if any.',
                'function'          => 'calcVatNumber',
                'store'             => true,
                'instant'           => true
            ],

            'registration_number' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Organisation registration number (company number).',
                'function'          => 'calcRegistrationNumber',
                'store'             => true,
                'instant'           => true
            ],

            'partner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'The targeted identity (the partner).',
                'onupdate'          => 'onupdatePartnerIdentityId',
                'required'          => true,
                'instant'           => true
            ],

            'product_models_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'foreign_field'     => 'providers_ids',
                'rel_table'         => 'sale_catalog_product_model_rel_sale_provider_providers',
                'rel_foreign_key'   => 'product_model_id',
                'rel_local_key'     => 'provider_id',
                'description'       => "The product models that can be required providers.",
            ]

        ];
    }

    public static function calcAddress($self) {
        $result = [];
        $self->read(['partner_identity_id' => ['address_street', 'address_city']]);
        foreach($self as $id => $provider) {
            if(isset($provider['partner_identity_id']['address_street'], $provider['partner_identity_id']['address_city'])) {
                $result[$id] = "{$provider['partner_identity_id']['address_street']} ${$provider['partner_identity_id']['address_city']}";
            }
        }

        return $result;
    }

    public static function calcVatNumber($self) {
        $result = [];
        $self->read(['partner_identity_id' => ['vat_number']]);
        foreach($self as $id => $provider) {
            if(isset($provider['partner_identity_id']['vat_number'])) {
                $result[$id] = $provider['partner_identity_id']['vat_number'];
            }
        }

        return $result;
    }

    public static function calcRegistrationNumber($self) {
        $result = [];
        $self->read(['partner_identity_id' => ['registration_number']]);
        foreach($self as $id => $provider) {
            if(isset($provider['partner_identity_id']['registration_number'])) {
                $result[$id] = $provider['partner_identity_id']['registration_number'];
            }
        }

        return $result;
    }
}
