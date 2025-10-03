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
                'help'              => 'This field is not marked as required to allow non-null initial points (from history).',
                'unique'            => true,
                'ondelete'          => 'cascade',
                'dependents'        =>  ['customer_id', 'name']
            ],

            'booking_apply_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'Booking on which the discount was applied.',
                'ondelete'          => 'cascade',
                'domain'            => ['customer_id', '=', 'object.customer_id'],
            ],

            'customer_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'store'             => true,
                'instant'           => true,
                'relation'          => ['booking_id' => 'customer_id'],
                'description'       => "Customer associated with the booking, determined based on the selected identity."
            ],

            'nb_paying_pers' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Estimated number of paying individuals involved in the booking.',
                'function'          => 'calcNbPayingPers',
                'store'             => false
            ],

            'nb_nights' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Number of nights in the booking.',
                'function'          => 'calcNbNights',
                'store'             => false
            ],

            'points_value' => [
                'type'              => 'float',
                'description'       => 'Number of points earned from the target booking.',
            ],

            'is_applicable' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag telling if the points can be applied (origin booking is invoiced).',
                'function'          => 'calcIsApplicable'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description of the booking points."
            ]

        ];
    }

    public static function getAction() {
        return [
            'refresh_points' => [
                'description'   => 'Re-compute assigned points according to origin Booking.',
                'policies'      => [],
                'function'      => 'doRefreshPoints'
            ]
        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['booking_id' => ['name'], 'customer_id' => ['name'],  'points_value']);
        foreach($self as $id => $point) {
            $parts = [];
            if($point['booking_id'] && strlen($point['booking_id']['name'])) {
                $parts[] = $point['booking_id']['name'];
            }
            if($point['customer_id'] && strlen($point['customer_id']['name'])) {
                $parts[] = $point['customer_id']['name'];
            }
            if(count($parts)) {
                $result[$id] = implode(' - ', $parts);
            }
        }
        return $result;
    }

    /**
     * computes 1 points per night per paying person
     */
    protected static function doRefreshPoints($self) {
        $self->read(['nb_nights' , 'nb_paying_pers']);
        foreach($self as $id => $bookingPoint) {
            $points = $bookingPoint['nb_nights'] * $bookingPoint['nb_paying_pers'];
            self::id($id)->update(['points_value' => $points]);
        }
    }

    protected static function calcIsApplicable($self) {
        $result = [];
        $self->read(['booking_apply_id', 'booking_id' => ['status']]);
        foreach($self as $id => $bookingPoint) {
            $is_applicable = false;
            if(!$bookingPoint['booking_apply_id']) {
                if(in_array($bookingPoint['booking_id']['status'], ['debit_balance', 'credit_balance', 'balanced'], true)) {
                    $is_applicable = true;
                }
            }
            $result[$id] = $is_applicable;
        }
        return $result;
    }

    protected static function calcNbNights($self) {
        $result = [];
        $self->read(['booking_id' => ['date_from', 'date_to']]);
        foreach($self as $id => $point) {
            $result[$id] =  round( ($point['booking_id']['date_to'] - $point['booking_id']['date_from']) / (60*60*24) );
        }
        return $result;
    }

    protected static function calcNbPayingPers($self) {
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
                            'product_model_id' => ['qty_accounting_method']
                        ])
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
