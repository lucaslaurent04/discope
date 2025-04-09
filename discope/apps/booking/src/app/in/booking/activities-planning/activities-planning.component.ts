import { Component, HostListener, OnInit, ViewChild } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { BookingApiService } from '../_services/booking.api.service';
import { ContextService } from 'sb-shared-lib';
import { Booking } from './_models/booking.model';
import { Activity } from './_models/activity.model';
import { BookingLineGroup } from './_models/booking-line-group.model';
import { TimeSlot } from './_models/time-slot.model';
import { Product } from './_models/product.model';
import { BookingActivitiesPlanningActivityDetailsComponent } from './_components/activity-details/activity-details.component';
import { BehaviorSubject } from 'rxjs';
import { debounceTime } from 'rxjs/operators';

type PlanningTimeSlot = {
    [groupNum: number]: Activity;
}

type Planning = {
    [dateIndex: string]: {
        AM?: PlanningTimeSlot;
        PM?: PlanningTimeSlot;
        EV?: PlanningTimeSlot;
    };
};

@Component({
    selector: 'booking-activities-planning',
    templateUrl: 'activities-planning.component.html',
    styleUrls: ['activities-planning.component.scss']
})
export class BookingActivitiesPlanningComponent implements OnInit {

    @ViewChild(BookingActivitiesPlanningActivityDetailsComponent) activityDetailsComponent!: BookingActivitiesPlanningActivityDetailsComponent;

    @HostListener('document:keyup', ['$event'])
    public async onKeydown(event: KeyboardEvent) {
        if(event.key === 'Delete' && this.selectedGroup && !this.selectedGroup.is_locked && this.selectedActivity) {
            await this.onActivityDeleted();
        }
    }

    private bookingId: number = null;
    public booking = new Booking();

    public weekStartDate: Date = null;
    public weekEndDate: Date = null;

    public showPrevBtn: boolean = false;
    public showNextBtn: boolean = false;

    private mapTimeSlotIdCode: {[key: number]: 'AM'|'PM'|'EV'} = {};

    public selectedDay: string = null;
    public selectedTimeSlot: 'AM'|'PM'|'EV' = 'AM';
    public selectedGroup: BookingLineGroup = null;
    public selectedItem$ = new BehaviorSubject<string>(null);

    public activityGroups: BookingLineGroup[] = [];

    public selectedActivity: Activity = null;
    public planning: Planning = {};

    constructor(
        private api: BookingApiService,
        private context: ContextService,
        private route: ActivatedRoute
    ) {}

    public ngOnInit() {
        this.route.params.subscribe(async (params) => {
            this.bookingId = <number>params['booking_id'];

            try {
                // load booking object
                await this.loadBooking(Object.getOwnPropertyNames(new Booking()));

                // load time slots "AM", "PM" and "EV"
                await this.loadTimeSlots(Object.getOwnPropertyNames(new TimeSlot()));

                // load groups of type "camp"
                await this.loadActivityGroups(Object.getOwnPropertyNames(new BookingLineGroup()));

                // relay change to context (to display sidemenu panes according to current object)
                this.context.change({
                    context_only: true,   // do not change the view
                    context: {
                        entity: 'sale\\booking\\Booking',
                        type: 'form',
                        purpose: 'view',
                        domain: ['id', '=', this.bookingId]
                    }
                });

                await this.loadWeekActivities(Object.getOwnPropertyNames(new Activity()));

                if(this.planning[this.selectedDay][this.selectedTimeSlot][this.selectedGroup.activity_group_num]) {
                    this.selectedActivity = this.planning[this.selectedDay][this.selectedTimeSlot][this.selectedGroup.activity_group_num];
                }

                this.selectedItem$.next(`${this.selectedDay}_${this.selectedTimeSlot}_${this.selectedGroup.activity_group_num}`);
            }
            catch(response) {
                console.warn(response);
            }
        });

        this.selectedItem$.pipe(debounceTime(300)).subscribe((selectedItem) => {
            if(!this.selectedActivity) {
                this.activityDetailsComponent.focusInput();
            }
        });
    }

    private async loadBooking(fields: string[]) {
        try {
            const bookings: Booking[] = await this.api.read('sale\\booking\\Booking', [this.bookingId], fields);
            if(bookings && bookings.length) {
                // update local object
                for(let field of Object.keys(bookings[0])) {
                    if(['date_from', 'date_to'].includes(field)) {
                        // @ts-ignore
                        this.booking[field] = new Date(bookings[0][field as keyof Booking]);
                    }
                    else {
                        // @ts-ignore
                        this.booking[field] = bookings[0][field as keyof Booking];
                    }
                }
                // assign booking to Booking API service (for conditioning calls)
                this.api.setBooking(this.booking);

                const weekStartDate = new Date(this.booking.date_from);
                if(weekStartDate.getDay() !== 1) {
                    const diffToMonday = weekStartDate.getDay() === 0 ? 6 : weekStartDate.getDay() - 1;
                    weekStartDate.setDate(weekStartDate.getDate() - diffToMonday);
                }
                this.weekStartDate = weekStartDate;

                this.selectedDay = this.formatDayIndex(this.weekStartDate);

                const weekEndDate = new Date(this.weekStartDate);
                weekEndDate.setDate(weekEndDate.getDate() + 6);
                this.weekEndDate = weekEndDate;

                this.showPrevBtn = this.weekStartDate.getTime() > this.booking.date_from.getTime();
                this.showNextBtn = this.weekEndDate.getTime() < this.booking.date_to.getTime();
            }
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    private async loadTimeSlots(fields: string[]) {
        try {
            const domain = ['code', 'in', ['AM', 'PM', 'EV']];

            const timeSlots: TimeSlot[] = await this.api.collect('sale\\booking\\TimeSlot', domain, fields);
            for(let timeSlot of timeSlots) {
                this.mapTimeSlotIdCode[timeSlot.id] = timeSlot.code;
            }
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    private async loadActivityGroups(fields: string[]) {
        try {
            const domain = [
                ['group_type', '=', 'camp'],
                ['booking_id', '=', this.bookingId]
            ];
            this.activityGroups = await this.api.collect('sale\\booking\\BookingLineGroup', domain, fields);

            for(let group of this.activityGroups) {
                if(group.activity_group_num === 1) {
                    this.selectedGroup = group;
                }
            }
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    public async previousWeek() {
        const weekStartDate = new Date(this.weekStartDate);
        weekStartDate.setDate(weekStartDate.getDate() - 7);
        this.weekStartDate = weekStartDate;

        this.selectedDay = this.formatDayIndex(this.weekStartDate);

        const weekEndDate = new Date(this.weekStartDate);
        weekEndDate.setDate(weekEndDate.getDate() + 6);
        this.weekEndDate = weekEndDate;

        await this.loadWeekActivities(Object.getOwnPropertyNames(new Activity()));
    }

    public async nextWeek() {
        const weekStartDate = new Date(this.weekStartDate);
        weekStartDate.setDate(weekStartDate.getDate() + 7);
        this.weekStartDate = weekStartDate;

        this.selectedDay = this.formatDayIndex(this.weekStartDate);

        const weekEndDate = new Date(this.weekStartDate);
        weekEndDate.setDate(weekEndDate.getDate() + 6);
        this.weekEndDate = weekEndDate;

        await this.loadWeekActivities(Object.getOwnPropertyNames(new Activity()));
    }

    private async loadWeekActivities(fields: string[]) {
        try {
            const weekStartDate = (new Date(this.weekStartDate)).getTime() / 1000;
            const weekEndDate = (new Date(this.weekStartDate)).setDate(this.weekStartDate.getDate() + 6) / 1000;

            const domain = [
                ['booking_id', '=', this.bookingId],
                ['activity_date', '>=', weekStartDate],
                ['activity_date', '<=', weekEndDate]
            ];
            const activities: Activity[] = await this.api.collect('sale\\booking\\BookingActivity', domain, fields);

            this.planning = {};
            for(let activity of activities) {
                const formattedDate = this.formatDayIndex(new Date(activity.activity_date));
                if(this.planning[formattedDate] === undefined) {
                    this.planning[formattedDate] = {};
                }

                const timeSlotCode = this.mapTimeSlotIdCode[activity.time_slot_id];
                if(this.planning[formattedDate][timeSlotCode] === undefined) {
                    this.planning[formattedDate][timeSlotCode] = {};
                }

                this.planning[formattedDate][timeSlotCode][activity.group_num] = activity;
            }
        }
        catch(response) {
            console.log(response)
        }
    }

    public async onProductSelected(product: Product) {
        let newLine: any = null;

        // notify back-end about the change
        try {
            let timeSlotId: number = null;
            for(let [id, code] of Object.entries(this.mapTimeSlotIdCode)) {
                if(this.selectedTimeSlot === code) {
                    timeSlotId = +id;
                }
            }

            const domain = [
                ['booking_line_group_id', '=', this.selectedGroup.id]
            ];
            const bookingLines = await this.api.collect('sale\\booking\\BookingLine', domain, ['id']);
            const order = bookingLines.length;

            newLine = await this.api.create('sale\\booking\\BookingLine', {
                order: order + 1,
                booking_id: this.booking.id,
                booking_line_group_id: this.selectedGroup.id,
                service_date: (new Date(this.selectedDay)).getTime() / 1000,
                time_slot_id: timeSlotId
            });
            await this.api.call('?do=sale_booking_update-bookingline-product', {
                id: newLine.id,
                product_id: product.id
            });

            await this.loadWeekActivities(Object.getOwnPropertyNames(new Activity()));

            if(this.planning[this.selectedDay][this.selectedTimeSlot][this.selectedGroup.activity_group_num]) {
                this.selectedActivity = this.planning[this.selectedDay][this.selectedTimeSlot][this.selectedGroup.activity_group_num];
            }
        }
        catch(response: any) {
            if(newLine) {
                // this.deleteLine.emit(newLine.id);
            }
            this.api.errorFeedback(response);
        }
    }

    public async onActivityDeleted() {
        try {
            // #todo #refresh - this triggers onupdateBookingLinesIds, which triggers _resetPrices
            await this.api.update('sale\\booking\\BookingLineGroup', [this.selectedGroup.id], {booking_lines_ids: [-this.selectedActivity.activity_booking_line_id]});

            await this.loadWeekActivities(Object.getOwnPropertyNames(new Activity()));

            this.selectedActivity = null;
        }
        catch(response) {
            console.log('RESPONSE', response);
            this.api.errorFeedback(response);
        }
    }

    public formatDayIndex(date: Date) {
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

    public timeSlotName(timeSlotCode: 'AM'|'PM'|'EV'): string {
        return {
            'AM': 'Matin',
            'PM': 'Après-Midi',
            'EV': 'Soir',
        }[timeSlotCode];
    }

    public onDaySelected(dateString: string) {
        this.selectedDay = dateString;
        this.selectedItem$.next(`${dateString}_${this.selectedTimeSlot}_${this.selectedGroup.activity_group_num}`);
    }

    public onTimeSlotSelected(timeSlotCode: 'AM'|'PM'|'EV') {
        this.selectedTimeSlot = timeSlotCode;
        this.selectedItem$.next(`${this.selectedDay}_${timeSlotCode}_${this.selectedGroup.activity_group_num}`);
    }

    public onGroupSelected(group: BookingLineGroup) {
        this.selectedGroup = group;
        this.selectedItem$.next(`${this.selectedDay}_${this.selectedTimeSlot}_${group.activity_group_num}`);
    }

    public onActivitySelected(activity: Activity|null) {
        this.selectedActivity = activity;
    }
}
