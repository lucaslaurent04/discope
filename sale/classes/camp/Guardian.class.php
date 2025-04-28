<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Guardian extends Model {

    public static function getDescription(): string {
        return "Guardian of a child that participate to a camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Complete name of the child.",
                'store'             => true,
                'function'          => 'calcName'
            ],

            'firstname' => [
                'type'              => 'string',
                'description'       => "First name of the guardian of the child.",
                'required'          => true
            ],

            'lastname' => [
                'type'              => 'string',
                'description'       => "Last name of the guardian of the child.",
                'required'          => true
            ],

            'email' => [
                'type'              => 'string',
                'result_type'       => 'string',
                'usage'             => 'email',
                'description'       => "Email of the guardian of the child.",
                'required'          => true
            ],

            'mobile' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'description'       => "Mobile phone number of the child's guardian.",
                'required'          => true
            ],

            'phone' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'description'       => "Fix phone number of the child's guardian."
            ],

            'work_phone' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'description'       => "Work phone number of the child's guardian."
            ],

            'relation_type' => [
                'type'              => 'string',
                'selection'         => [
                    'mother',
                    'father',
                    'legal_tutor',
                    'family_member',
                    'home_manager'
                ],
                'description'       => "Relation of the person to the guardian.",
                'default'           => 'mother'
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => "Street and number of the child's guardian.",
                'required'          => true
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => "Optional info for mail dispatch (apartment, box, floor, ...)."
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => "Zip code of the child's guardian.",
                'required'          => true,
                'dependents'        => ['is_vienne']
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => "City of the child's guardian.",
                'required'          => true,
                'dependents'        => ['is_ccvg']
            ],

            'is_vienne' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Is the address in the \"Département Vienne ou Haute-Vienne\".",
                'store'             => true,
                'function'          => 'calcIsVienne',
                'dependents'        => ['is_ccvg']
            ],

            'is_ccvg' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Is the address in the \"Communauté des communes de Vienne & Gartempe\".",
                'store'             => true,
                'function'          => 'calcIsCcvg'
            ],

            'different_invoicing_address' => [
                'type'              => 'boolean',
                'description'       => "The invoicing address is different.",
                'default'           => false
            ],

            'invoicing_address_street' => [
                'type'              => 'string',
                'description'       => "Street and number of the child's guardian.",
                'visible'           => ['different_invoicing_address', '=', true]
            ],

            'invoicing_address_dispatch' => [
                'type'              => 'string',
                'description'       => "Optional info for mail dispatch (apartment, box, floor, ...).",
                'visible'           => ['different_invoicing_address', '=', true]
            ],

            'invoicing_address_zip' => [
                'type'              => 'string',
                'description'       => "Zip code of the child's guardian.",
                'visible'           => ['different_invoicing_address', '=', true]
            ],

            'invoicing_address_city' => [
                'type'              => 'string',
                'description'       => "City of the child's guardian.",
                'visible'           => ['different_invoicing_address', '=', true]
            ],

            'children_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Child',
                'foreign_field'     => 'guardians_ids',
                'rel_table'         => 'sale_camp_rel_child_guardian',
                'rel_foreign_key'   => 'child_id',
                'rel_local_key'     => 'guardian_id',
                'description'       => "Children of the guardian.",
                'onupdate'          => 'onupdateChildrenIds'
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['firstname', 'lastname']);
        foreach($self as $id => $child) {
            if(isset($child['firstname'], $child['lastname'])) {
                $result[$id] = $child['firstname'].' '.$child['lastname'];
            }
        }

        return $result;
    }

    public static function calcIsVienne($self): array {
        $result = [];
        $self->read(['address_zip']);
        foreach($self as $id => $guardian) {
            if(empty($guardian['address_zip'])) {
                $result[$id] = false;
                continue;
            }
            $start_zip = substr($guardian['address_zip'], 0, 2);
            $result[$id] = in_array($start_zip, ['86', '87']);
        }

        return $result;
    }

    public static function calcIsCcvg($self): array {
        $result = [];
        $CCVG_cities = [
            'Adriers', 'Antigny', 'Asnières-sur-Blour', 'Availles-Limouzine', 'Béthines', 'Bouresse',
            'Bourg-Archambault', 'Brigueil-le-Chantre', 'Bussière', 'Chapelle-Viviers', 'Civaux', 'Coulonges-les-Hérolles',
            'Fleix', 'Gouex', 'Haims', 'Isle-Jourdain', 'Jouhet', 'Journet', 'L’Isle-Jourdain', 'La Bussière', 'La Chapelle-Viviers',
            'La Trimouille', 'Lathus-Saint-Rémy', 'Lauthiers', 'Le Vigeant', 'Leignes-sur-Fontaine', 'Lhommaizé', 'Liglet', 'Luchapt',
            'Lussac-les-Châteaux', 'Mauprévoir', 'Mazerolles', 'Millac', 'Montmorillon', 'Moulismes', 'Moussac-sur-Vienne',
            'Mouterre-sur-Blourde', 'Nalliers', 'Nérignac', 'Paizay-le-Sec', 'Persac', 'Pindray', 'Plaisance', 'Pressac',
            'Queaux', 'Saint-Germain', 'Saint-Laurent-de-Jourdes', 'Saint-Léomer', 'Saint-Martin-l\'Ars', 'Saint-Pierre-de-Maillé',
            'Saint-Savin', 'Saulgé', 'Sillars', 'Thollet', 'Trimouille', 'Usson du Poitou', 'Valdivienne', 'Verrières', 'Vigeant', 'Villemort',
        ];

        $CCVG_cities_normalized = array_map(
            function($city) {
                return preg_replace("/[^a-zA-Z0-9]/", "", $city);
            },
            $CCVG_cities
        );

        $self->read(['address_city', 'is_vienne']);
        foreach($self as $id => $guardian) {
            if(empty($guardian['address_city']) || !$guardian['is_vienne']) {
                $result[$id] = false;
                continue;
            }

            $city = preg_replace("/[^a-zA-Z0-9]/", "", $guardian['address_city']);
            $result[$id] = in_array($city, $CCVG_cities_normalized);
        }

        return $result;
    }

    public static function onupdateChildrenIds($self) {
        $self->read(['children_ids' => ['main_guardian_id']]);
        foreach($self as $id => $guardian) {
            foreach($guardian['children_ids'] as $cid => $child) {
                if(is_null($child['main_guardian_id'])) {
                    Child::id($cid)->update(['main_guardian_id' => $id]);
                }
            }
        }
    }

    public static function onupdate($self, $values) {
        $self->read(['state', 'children_ids' => ['main_guardian_id']]);
        foreach($self as $id => $guardian) {
            if($guardian['state'] === 'draft' && isset($values['state']) && $values['state'] === 'instance') {
                Child::search(['main_guardian_id', '=', $id])
                    ->update(['camp_class' => null]);
            }
        }
    }

    public static function ondelete($self, $values): void {
        $self->read(['children_ids' => ['main_guardian_id', 'guardians_ids']]);

        $map_del_guardian_ids = [];
        foreach($self as $id => $guardian) {
            $map_del_guardian_ids[$id] = true;
        }

        foreach($self as $guardian) {
            foreach($guardian['children_ids'] as $cid => $child) {
                $new_main_guardian_id = null;
                foreach($child['children_ids']['guardians_ids'] as $g) {
                    if(!isset($map_del_guardian_ids[$g['id']])) {
                        $new_main_guardian_id = $g['id'];
                        break;
                    }
                }

                Child::id($cid)->update(['main_guardian_id' => $new_main_guardian_id]);
            }
        }

        parent::ondelete($self, $values);
    }
}
