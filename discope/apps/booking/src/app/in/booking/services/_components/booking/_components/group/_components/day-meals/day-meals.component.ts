import { BookingMeal } from '../../../../_models/booking_meal.model';
import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { BookingLineGroup } from '../../../../_models/booking_line_group.model';
import { Booking } from '../../../../_models/booking.model';
import { ApiService } from 'sb-shared-lib';

export interface BookingMealDay {
    date: Date,
    B: BookingMeal | null,
    L: BookingMeal | null,
    D: BookingMeal | null,
}

@Component({
    selector: 'booking-services-booking-group-day-meals',
    templateUrl: 'day-meals.component.html',
    styleUrls: ['day-meals.component.scss']
})
export class BookingServicesBookingGroupDayMealsComponent implements OnInit {

    @Input() bookingMealDay: BookingMealDay;
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Input() timeSlots: { id: number, name: string, code: 'B'|'AM'|'L'|'PM'|'D'|'EV' }[];
    @Input() mealTypes: { id: number, name: string, code: string }[];

    @Output() loadStart = new EventEmitter();
    @Output() loadEnd = new EventEmitter();
    @Output() updated = new EventEmitter();

    public ready: boolean = false;

    public mapTimeSlotsCode : any = {
        'B': null,
        'AM': null,
        'L': null,
        'PM': null,
        'D': null,
        'EV': null
    };

    constructor(
        private api: ApiService
    ) {
    }

    public ngOnInit() {
        this.ready = true;

        for(let timeSlot of this.timeSlots) {
            this.mapTimeSlotsCode[timeSlot.code] = timeSlot;
        }
    }

    public async ondeleteMeal(mealId: number) {
        this.loadStart.emit();

        try {
            await this.api.remove('sale\\booking\\BookingMeal', [mealId], true);
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loadEnd.emit();
    }
}
