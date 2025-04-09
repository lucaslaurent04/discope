import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges } from '@angular/core';
import { BookingLineGroup } from '../../_models/booking-line-group.model';
import { Booking } from '../../_models/booking.model';
import { Activity } from '../../_models/activity.model';

@Component({
    selector: 'booking-activities-planning-week-activities',
    templateUrl: 'week-activities.component.html',
    styleUrls: ['week-activities.component.scss']
})
export class BookingActivitiesPlanningWeekActivitiesComponent implements OnInit, OnChanges {

    @Input() startDate: Date;
    @Input() endDate: Date;
    @Input() booking: Booking;
    @Input() groups: BookingLineGroup[];
    @Input() mapDateTimeSlotGroupActivity: any;
    @Input() selectedDay: string|null;
    @Input() selectedTimeSlot: 'AM'|'PM'|'EV'|null;
    @Input() selectedGroup: BookingLineGroup|null;
    @Input() selectedActivity: Activity|null;

    @Output() daySelected = new EventEmitter<string>();
    @Output() timeSlotSelected = new EventEmitter<'AM'|'PM'|'EV'>();
    @Output() groupSelected = new EventEmitter<BookingLineGroup>();
    @Output() activitySelected = new EventEmitter<Activity>();

    public days: string[] = [];

    public gapBetweenTimeSlot = 50;
    public gapBetweenGroupLine = 7;

    public ngOnInit() {
        console.log('init BookingActivitiesPlanningWeekActivitiesComponent');
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.startDate || changes.endDate) {
            this.refreshDays();
        }
    }

    private refreshDays() {
        this.days = [];

        const startDate = new Date(this.startDate);
        const endDate = new Date(this.endDate);

        while(startDate <= endDate) {
            this.days.push(this.getDateDayIndex(startDate));
            startDate.setDate(startDate.getDate() + 1);
        }
    }

    private getDateDayIndex(date: Date): string {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Add 1 because months are 0-based
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    public dayName(dateString: string) {
        const dayName = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        const date = new Date(dateString);

        return dayName[date.getDay()];
    }

    public dateComplete(dateString: string) {
        const date = new Date(dateString);
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Add 1 because months are 0-based
        const day = String(date.getDate()).padStart(2, '0');

        return `${day}/${month}`;
    }

    public dayNameHeight() {
        const dayNameHeight = 24;

        return this.gapBetweenTimeSlot + dayNameHeight;
    }

    public timeSlotIconSeparatorHeight() {
        const gQty = this.groups.length;
        const iconHeight = 24;
        const groupLineHeight = 24;

        return this.gapBetweenTimeSlot - iconHeight + (gQty * groupLineHeight) + ((gQty - 1) * this.gapBetweenGroupLine);
    }

    public dateInBooking(dateString: string) {
        const date = new Date(dateString);

        return date >= this.booking.date_from && date <= this.booking.date_to;
    }

    public activityShortName(activityName: string) {
        return activityName.replace(/\s\([^)]+\)$/, '');
    }

    public selectGroupActivity(dateString: string, timeSlotCode: string, group: BookingLineGroup) {
        this.daySelected.emit(dateString);
        this.timeSlotSelected.emit(timeSlotCode as 'AM'|'PM'|'EV');
        this.groupSelected.emit(group);

        if(this.mapDateTimeSlotGroupActivity[dateString]?.[timeSlotCode]?.[group.activity_group_num]) {
            this.activitySelected.emit(this.mapDateTimeSlotGroupActivity[dateString]?.[timeSlotCode]?.[group.activity_group_num]);
        }
        else {
            this.activitySelected.emit(null);
        }
    }
}
