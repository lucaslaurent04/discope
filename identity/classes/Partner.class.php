<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;
use equal\orm\Model;

class Partner extends Model {

    public static function getName() {
        return 'Partner';
    }

    public static function getDescription() {
        return "A Partner describes a relationship between two Identities (contact, employee, customer, provider, payer, other).";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'relation'          => ['partner_identity_id' => 'name'],
                'result_type'       => 'string',
                'store'             => true,
                'instant'           => true,
                'description'       => 'The display name of the partner (related organisation name).'
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'The identity organisation which the targeted identity is a partner of.',
                'default'           => 1
            ],

            'partner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'The targeted identity (the partner).',
                'onupdate'          => 'onupdatePartnerIdentityId',
                'required'          => true,
                'dependents'        => ['name', 'title', 'email', 'phone', 'mobile']
            ],

            'relationship' => [
                'type'              => 'string',
                'selection'         => [
                    'contact',
                    'employee',
                    'customer',
                    'provider',
                    'payer',
                    'other'
                ],
                'description'       => 'The kind of partnership that exists between the identities.'
            ],

            // if partner is a contact, keep the organisation (s)he is a contact from
            'partner_organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Target organisation which the contact is working for.',
                'visible'           => [ ['relationship', '=', 'contact'] ]
            ],

            // if partner is a contact, keep its 'position' within the
            'partner_position' => [
                'type'              => 'string',
                'description'       => 'Position of the contact (natural person) within the target organisation (legal person), e.g. \'director\', \'CEO\', \'Regional manager\'.',
                'visible'           => [ ['relationship', '=', 'contact'] ]
            ],

            // if partner is a customer, it can have an external reference (e.g. reference assigned by previous software)
            'customer_external_ref' => [
                'type'              => 'string',
                'description'       => 'External reference for customer, if any.',
                'visible'           => ['relationship', '=', 'customer']
            ],

            'email' => [
                'type'              => 'computed',
                'relation'          => ['partner_identity_id' => 'email'],
                'result_type'       => 'string',
                'usage'             => 'email',
                'store'             => true,
                'description'       => 'Email of the contact (from Identity).'
            ],

            'phone' => [
                'type'              => 'computed',
                'relation'          => ['partner_identity_id' => 'phone'],
                'result_type'       => 'string',
                'usage'             => 'phone',
                'store'             => true,
                'description'       => 'Phone number of the contact (from Identity).'
            ],

            'mobile' => [
                'type'              => 'computed',
                'relation'          => ['partner_identity_id' => 'mobile'],
                'result_type'       => 'string',
                'usage'             => 'phone',
                'store'             => true,
                'description'       => 'Mobile phone number of the contact (from Identity).'
            ],

            'title' => [
                'type'              => 'computed',
                'relation'          => ['partner_identity_id' => 'title'],
                'result_type'       => 'string',
                'store'             => true,
                'description'       => 'Title of the contact (from Identity).'
            ],

            'lang_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Lang',
                'description'       => "Preferred language of the partner (relates to identity).",
                'default'           => 1
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => 'Mark the partner as active.',
                'default'           => true
            ]
        ];
    }

    public function getUnique() {
        return [
            ['owner_identity_id', 'partner_identity_id', 'relationship']
        ];
    }

    public static function onupdatePartnerIdentityId($self) {
        $self->read([ 'partner_identity_id' => 'lang_id' ]);
        foreach($self as $id => $partner) {
            self::id($id)->update([ 'lang_id' => $partner['partner_identity_id']['lang_id'] ]);
        }
    }

    /**
     * Signature for single object change from views.
     *
     * @param  Object   $om        Object Manager instance.
     * @param  Array    $event     Associative array holding changed fields as keys, and their related new values.
     * @param  Array    $values    Copy of the current (partial) state of the object (fields depend on the view).
     * @param  String   $lang      Language (char 2) in which multilang field are to be processed.
     * @return Array    Associative array mapping fields with their resulting values.
     */
    public static function onchange($om, $event, $values, $lang='en') {
        $result = [];

        if(isset($event['partner_identity_id'])) {
            $identities = $om->read('identity\Identity', $event['partner_identity_id'], ['name']);
            if($identities > 0) {
                $identity = reset($identities);
                $result['name'] = $identity['name'];
            }
        }

        return $result;
    }
}
