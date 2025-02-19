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

    public mapTimeSlotsCodeId : any = {
        'B': 0,
        'AM': 0,
        'L': 0,
        'PM': 0,
        'D': 0,
        'EV': 0
    };

    public ngOnInit() {
        this.ready = true;

        for(let timeSlot of this.timeSlots) {
            this.mapTimeSlotsCodeId[timeSlot.code] = timeSlot.id;
        }
    }

    public onupdateActivity() {
        // relay to parent
        this.updated.emit();
    }
}
