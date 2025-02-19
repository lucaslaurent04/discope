import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { BookingLineGroup } from '../../../../_models/booking_line_group.model';
import { Booking } from '../../../../_models/booking.model';
import { BookingActivityDay } from '../../group.component';

@Component({
    selector: 'booking-services-booking-group-day-activities',
    templateUrl: 'day-activities.component.html',
    styleUrls: ['day-activities.component.scss']
})
export class BookingServicesBookingGroupDayActivitiesComponent implements OnInit {

    @Input() bookingActivityDay: BookingActivityDay
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Input() timeSlots: { id: number, name: string, code: 'B'|'AM'|'L'|'PM'|'D'|'EV' }[];

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

    public ngOnInit() {
        this.ready = true;

        for(let timeSlot of this.timeSlots) {
            this.mapTimeSlotsCode[timeSlot.code] = timeSlot;
        }
    }

    public onupdateActivity() {
        // relay to parent
        this.updated.emit();
    }
}
