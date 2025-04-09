import { Component, Input, OnInit } from '@angular/core';
import { BookingLineGroup } from '../../_models/booking-line-group.model';

@Component({
    selector: 'booking-activities-planning-booking-group-details',
    templateUrl: 'booking-group-details.component.html',
    styleUrls: ['booking-group-details.component.scss']
})
export class BookingActivitiesPlanningBookingGroupDetailsComponent implements OnInit {

    @Input() group: BookingLineGroup|null;

    public ngOnInit() {
        console.log('init BookingActivitiesPlanningBookingGroupDetailsComponent');
    }
}
