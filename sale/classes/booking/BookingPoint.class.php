<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;

class BookingPoint extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The computed name is based on the booking and customer',
                'function'          => 'calcName',
                'store'             => true,
                'instant'           => true
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'Reference to the booking this entry is associated with.',
                'required'          => true,
                'ondelete'          => 'cascade',
                'dependents'        =>  ['customer_id', 'nb_paying_pers', 'nb_nights', 'points_value', 'name']
            ],

            'booking_apply_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'Booking to which the discount was applied.',
                'ondelete'          => 'cascade',
            ],

            'customer_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'store'             => true,
                'relation'          => ['booking_id' => 'customer_id'],
                'description'       => "Customer associated with the booking, determined based on the selected identity."
            ],

            'nb_paying_pers' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Estimated number of paying individuals involved in the booking.',
                'function'          => 'calcNbPayingPers',
                'store'             => true,
                'instant'           => true
            ],

            'nb_nights' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Number of nights in the booking.',
                'function'          => 'calcNbNights',
                'store'             => true,
                'instant'           => true
            ],

            'points_value' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Number of points earned from the target booking.',
                'function'          => 'calcPointsValue',
                'store'             => true,
                'instant'           => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description of the booking points."
            ],

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['booking_id' => ['name'], 'customer_id' => ['name'],  'points_value']);
        foreach($self as $id => $point) {
            $result[$id] = $point['booking_id']['name'] .' - '. $point['customer_id']['name'];
        }
        return $result;
    }

    /**
     * computes 1 points per night per paying person
     */
    public static function calcPointsValue($self) {
        $result = [];
        $self->read(['nb_nights' , 'nb_paying_pers']);
        foreach($self as $id => $point) {
            $result[$id] = $point['nb_nights'] * $point['nb_paying_pers'];

        }
        return $result;
    }

    public static function calcNbNights($self) {
        $result = [];
        $self->read(['booking_id' => ['date_from', 'date_to']]);
        foreach($self as $id => $point) {
            $result[$id] =  round( ($point['booking_id']['date_to'] - $point['booking_id']['date_from'])/ (60*60*24) );
        }
        return $result;
    }

    public function getUnique() {
        return [
            ['booking_id']
        ];
    }

    public static function calcNbPayingPers($self) {
        $result = [];
        $self->read(['booking_id' => ['booking_lines_groups_ids']]);
        foreach($self as $id => $point) {
            $total_qty = 0;
            $total_free_qty = 0;
            $booking_lines_groups_ids = $point['booking_id']['booking_lines_groups_ids'];
            foreach($booking_lines_groups_ids as $booking_line_group_id){
                $booking_line_group = BookingLineGroup::search(
                    [
                        ['id','=', $booking_line_group_id],
                        ['group_type' , '=', 'sojourn']
                    ])
                    ->read([
                        'nb_nights',
                        'booking_lines_ids'
                    ])
                    ->first(true);

                foreach($booking_line_group['booking_lines_ids'] as $booking_line_id){
                    $booking_line = BookingLine::search(
                        [
                            ['id','=', $booking_line_id],
                            ['is_accomodation' , '=', true]
                        ])
                        ->read([
                            'qty',
                            'free_qty',
                            'product_model_id' => ['qty_accounting_method']])
                        ->first(true);

                    if($booking_line){
                        $qty =  $booking_line['qty'];
                        $free_qty = $booking_line['free_qty'];

                        if($booking_line['product_model_id']['qty_accounting_method'] == 'person'){
                            $qty = round($qty/$booking_line_group['nb_nights']);
                            $free_qty = round($free_qty /$booking_line_group['nb_nights']);
                        }

                        $total_qty = $total_qty + $qty;
                        $total_free_qty = $total_free_qty + $free_qty;
                    }
                }
            }
            $result[$id] =  $total_qty - $total_free_qty;
        }
        return $result;
    }

}
