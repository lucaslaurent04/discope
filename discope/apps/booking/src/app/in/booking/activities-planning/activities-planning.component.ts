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
import { AgeRangeAssignment } from './_models/age-range-assignment.model';
import { Partner } from './_models/partner.model';
import { BookingLine } from './_models/booking-line.model';
import { FormControl } from '@angular/forms';

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

    public loading: boolean = true;

    private bookingId: number = null;
    public booking = new Booking();

    public weekStartDate: Date = null;
    public weekEndDate: Date = null;
    public weekDescription: string = null;

    public weekDescriptionFormControl: FormControl;

    public showPrevBtn: boolean = false;
    public showNextBtn: boolean = false;

    private mapTimeSlotIdCode: {[key: number]: 'AM'|'PM'|'EV'} = {};

    public selectedDay: string = null;
    public selectedTimeSlot: 'AM'|'PM'|'EV' = 'AM';
    public selectedGroup: BookingLineGroup = null;
    public selectedItem$ = new BehaviorSubject<string>(null);

    public activityGroups: BookingLineGroup[] = [];
    public mapGroupAgeRangeAssignment: {[groupId: number]: AgeRangeAssignment} = {};

    public selectedActivity: Activity = null;
    public planning: Planning = {};

    public employees: Partner[] = [];
    public providers: Partner[] = [];

    constructor(
        private api: BookingApiService,
        private context: ContextService,
        private route: ActivatedRoute
    ) {
        this.weekDescriptionFormControl = new FormControl('');
    }

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

                await this.loadWeekActivities();

                if(this.planning?.[this.selectedDay]?.[this.selectedTimeSlot]?.[this.selectedGroup.activity_group_num]) {
                    this.selectedActivity = this.planning[this.selectedDay][this.selectedTimeSlot][this.selectedGroup.activity_group_num];
                }

                this.selectedItem$.next(`${this.selectedDay}_${this.selectedTimeSlot}_${this.selectedGroup.activity_group_num}`);

                this.loadEmployees(Object.getOwnPropertyNames(new Partner()));
                this.loadProviders(Object.getOwnPropertyNames(new Partner()));
            }
            catch(response) {
                console.warn(response);
            }

            this.loading = false;
        });

        this.selectedItem$.pipe(debounceTime(300)).subscribe((selectedItem) => {
            if(!this.selectedActivity) {
                this.activityDetailsComponent.focusInput();
            }
        });
    }

    private updateActivityWeekDescription(activityWeeksDescriptions: string, weekStartDate: Date) {
        this.weekDescription = '';
        if(activityWeeksDescriptions) {
            const weekStartKey = weekStartDate.toISOString().split("T")[0];
            let map_activity_weeks_descriptions = JSON.parse(activityWeeksDescriptions) as {[key: string]: string};
            if(map_activity_weeks_descriptions[weekStartKey]) {
                this.weekDescription = map_activity_weeks_descriptions[weekStartKey];
            }
        }
        this.weekDescriptionFormControl.setValue(this.weekDescription);
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

                const weekEndDate = new Date(this.weekStartDate);
                weekEndDate.setDate(weekEndDate.getDate() + 6);
                this.weekEndDate = weekEndDate;

                this.showPrevBtn = this.weekStartDate.getTime() > this.booking.date_from.getTime();
                this.showNextBtn = this.weekEndDate.getTime() < this.booking.date_to.getTime();

                this.updateActivityWeekDescription(this.booking.activity_weeks_descriptions, this.weekStartDate);
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
            this.activityGroups = await this.api.collect(
                    'sale\\booking\\BookingLineGroup',
                    domain,
                    fields,
                    'activity_group_num',
                    'asc',
                    0,
                    100
                );

            for(let group of this.activityGroups) {
                group.date_from = new Date(group.date_from);
                group.date_to = new Date(group.date_to);

                if(group.activity_group_num === 1) {
                    this.selectedGroup = group;
                    this.selectedDay = this.formatDayIndex(this.selectedGroup.date_from);
                }
            }

            await this.loadMapGroupAgeRangeAssigment();
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    private async loadMapGroupAgeRangeAssigment() {
        let ageRangeAssignmentsIds: number[] = [];
        for(let group of this.activityGroups) {
            if(group.age_range_assignments_ids.length === 1) {
                ageRangeAssignmentsIds = [
                    ...ageRangeAssignmentsIds,
                    ...group.age_range_assignments_ids
                ];
            }
        }

        if(ageRangeAssignmentsIds.length > 0) {
            const ageRangeAssignments = await this.api.read('sale\\booking\\BookingLineGroupAgeRangeAssignment', ageRangeAssignmentsIds, Object.getOwnPropertyNames(new AgeRangeAssignment()));

            const mapGroupAgeRangeAssignment: {[groupId: number]: AgeRangeAssignment} = {};
            for(let ageRangeAssign of ageRangeAssignments as AgeRangeAssignment[]) {
                mapGroupAgeRangeAssignment[ageRangeAssign.booking_line_group_id] = ageRangeAssign;
            }

            this.mapGroupAgeRangeAssignment = mapGroupAgeRangeAssignment;
        }
    }

    private async loadEmployees(fields: string[]) {
        try {
            const domain = ['relationship','=', 'employee'];

            this.employees = await this.api.collect(
                    'hr\\employee\\Employee',
                    domain,
                    fields,
                    'name',
                    'asc',
                    0,
                    1000
                );
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    private async loadProviders(fields: string[]) {
        try {
            const domain = ['relationship','=', 'provider'];

            this.providers = await this.api.collect(
                    'sale\\provider\\Provider',
                    domain,
                    fields,
                    'id',
                    'asc',
                    0,
                    500
                );
        }
        catch(response) {
            console.log('unexpected error', response);
        }
    }

    public async previousWeek() {
        this.loading = true;

        const weekStartDate = new Date(this.weekStartDate);
        weekStartDate.setDate(weekStartDate.getDate() - 7);
        this.weekStartDate = weekStartDate;

        if(this.weekStartDate < this.booking.date_from) {
            this.selectedDay = this.formatDayIndex(this.booking.date_from);
        }
        else {
            this.selectedDay = this.formatDayIndex(this.weekStartDate);
        }

        const weekEndDate = new Date(this.weekStartDate);
        weekEndDate.setDate(weekEndDate.getDate() + 6);
        this.weekEndDate = weekEndDate;

        this.showPrevBtn = this.weekStartDate.getTime() > this.booking.date_from.getTime();
        this.showNextBtn = this.weekEndDate.getTime() < this.booking.date_to.getTime();

        this.updateActivityWeekDescription(this.booking.activity_weeks_descriptions, this.weekStartDate);

        await this.loadWeekActivities();

        if(this.planning?.[this.selectedDay]?.[this.selectedTimeSlot]?.[this.selectedGroup.activity_group_num]) {
            this.selectedActivity = this.planning[this.selectedDay][this.selectedTimeSlot][this.selectedGroup.activity_group_num];
        }
        else {
            this.selectedActivity = null;
        }

        this.loading = false;
    }

    public async nextWeek() {
        this.loading = true;

        const weekStartDate = new Date(this.weekStartDate);
        weekStartDate.setDate(weekStartDate.getDate() + 7);
        this.weekStartDate = weekStartDate;

        this.selectedDay = this.formatDayIndex(this.weekStartDate);

        const weekEndDate = new Date(this.weekStartDate);
        weekEndDate.setDate(weekEndDate.getDate() + 6);
        this.weekEndDate = weekEndDate;

        this.showPrevBtn = this.weekStartDate.getTime() > this.booking.date_from.getTime();
        this.showNextBtn = this.weekEndDate.getTime() < this.booking.date_to.getTime();

        this.updateActivityWeekDescription(this.booking.activity_weeks_descriptions, this.weekStartDate);

        await this.loadWeekActivities();

        if(this.planning?.[this.selectedDay]?.[this.selectedTimeSlot]?.[this.selectedGroup.activity_group_num]) {
            this.selectedActivity = this.planning[this.selectedDay][this.selectedTimeSlot][this.selectedGroup.activity_group_num];
        }
        else {
            this.selectedActivity = null;
        }

        this.loading = false;
    }

    private async loadWeekActivities() {
        try {
            const weekStartDate = (new Date(this.weekStartDate)).getTime() / 1000;
            const weekEndDate = (new Date(this.weekStartDate)).setDate(this.weekStartDate.getDate() + 6) / 1000;

            const domainActivities = [
                ['booking_id', '=', this.bookingId],
                ['activity_date', '>=', weekStartDate],
                ['activity_date', '<=', weekEndDate]
            ];
            const activitiesPromise = this.api.collect(
                    'sale\\booking\\BookingActivity',
                    domainActivities,
                    Object.getOwnPropertyNames(new Activity()),
                    'id',
                    'asc',
                    0,
                    500
                );

            const domainBookingLines = [
                ['booking_id', '=', this.bookingId]
            ];
            const bookingLinesPromise = this.api.collect(
                    'sale\\booking\\BookingLine',
                    domainBookingLines,
                    Object.getOwnPropertyNames(new BookingLine()),
                    'id',
                    'asc',
                    0,
                    500
                );

            const [activities, bookingLines] = await Promise.all([activitiesPromise, bookingLinesPromise]);

            this.planning = {};
            for(let activity of activities) {
                for(let bookingLine of bookingLines) {
                    if(activity.activity_booking_line_id === bookingLine.id) {
                        activity.activity_booking_line_id = bookingLine;
                    }
                }

                const formattedDate = this.formatDayIndex(new Date(activity.activity_date));
                if(this.planning[formattedDate] === undefined) {
                    this.planning[formattedDate] = {};
                }

                const timeSlotCode = this.mapTimeSlotIdCode[activity.time_slot_id];
                if(this.planning[formattedDate][timeSlotCode] === undefined) {
                    this.planning[formattedDate][timeSlotCode] = {};
                }

                this.planning[formattedDate][timeSlotCode][activity.group_num] = activity;

                if(this.selectedActivity && this.selectedActivity.id === activity.id) {
                    this.selectedActivity = activity;
                }
            }
        }
        catch(response) {
            console.log(response)
        }
    }

    public async onScheduleFromChanged(scheduleFrom: string) {
        this.loading = true;

        try {
            await this.api.update('sale\\booking\\BookingActivity', [this.selectedActivity.id], {
                schedule_from: scheduleFrom
            });

            await this.loadWeekActivities();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onScheduleToChanged(scheduleTo: string) {
        this.loading = true;

        try {
            await this.api.update('sale\\booking\\BookingActivity', [this.selectedActivity.id], {
                schedule_to: scheduleTo
            });

            await this.loadWeekActivities();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onNbPersChanged(nbPers: number) {
        this.loading = true;

        try {
            await this.api.call('?do=sale_booking_update-activity-group-info', {
                id: this.selectedGroup.id,
                nb_pers: nbPers
            });

            await this.loadActivityGroups(Object.getOwnPropertyNames(new BookingLineGroup()));
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onAgeFromChanged(ageFrom: number) {
        this.loading = true;

        try {
            await this.api.call('?do=sale_booking_update-activity-group-info', {
                id: this.selectedGroup.id,
                age_from: ageFrom
            });

            await this.loadActivityGroups(Object.getOwnPropertyNames(new BookingLineGroup()));
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onAgeToChanged(ageTo: number) {
        this.loading = true;

        try {
            await this.api.call('?do=sale_booking_update-activity-group-info', {
                id: this.selectedGroup.id,
                age_to: ageTo
            });

            await this.loadActivityGroups(Object.getOwnPropertyNames(new BookingLineGroup()));
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onHasPersonWithDisabilityChanged(hasPersonWithDisability: boolean) {
        this.loading = true;

        try {
            await this.api.call('?do=sale_booking_update-activity-group-info', {
                id: this.selectedGroup.id,
                has_person_with_disability: hasPersonWithDisability
            });

            this.selectedGroup.has_person_with_disability = hasPersonWithDisability;
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onParticipantsOptionsChanged({ person_disability_description }: { person_disability_description: string }) {
        this.loading = true;

        try {
            await this.api.call('?do=sale_booking_update-activity-group-info', {
                id: this.selectedGroup.id,
                person_disability_description
            });

            this.selectedGroup.person_disability_description = person_disability_description;
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onProductSelected(product: Product) {
        this.loading = true;

        // notify backend about the change
        try {
            let timeSlotId: number = null;
            for(let [id, code] of Object.entries(this.mapTimeSlotIdCode)) {
                if(this.selectedTimeSlot === code) {
                    timeSlotId = +id;
                }
            }

            await this.api.create('sale\\booking\\BookingActivity', {
                booking_id: this.booking.id,
                booking_line_group_id: this.selectedGroup.id,
                product_id: product.id,
                activity_date: (new Date(this.selectedDay)).getTime() / 1000,
                time_slot_id: timeSlotId
            });

            await this.loadWeekActivities();

            if(this.planning[this.selectedDay][this.selectedTimeSlot][this.selectedGroup.activity_group_num]) {
                this.selectedActivity = this.planning[this.selectedDay][this.selectedTimeSlot][this.selectedGroup.activity_group_num];
            }
        }
        catch(response: any) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onActivityDeleted() {
        this.loading = true;

        try {
            if(this.selectedActivity.activity_booking_line_id) {
                await this.api.update('sale\\booking\\BookingLineGroup', [this.selectedGroup.id], {booking_lines_ids: [-this.selectedActivity.activity_booking_line_id.id]});
            }
            else {
                await this.api.remove('sale\\booking\\BookingActivity', [this.selectedActivity.id], true);
            }

            await this.loadWeekActivities();

            this.selectedActivity = null;
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onEmployeeChanged({employeeId, onFail}: {employeeId: number, onFail: () => void}) {
        this.loading = true;

        try {
            await this.api.update('sale\\booking\\BookingActivity', [this.selectedActivity.id], {employee_id: employeeId});

            await this.loadWeekActivities();
        }
        catch(response) {
            onFail();

            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onProvidersChanged({providersIds: newProvidersIds, onFail}: {providersIds: number[], onFail: () => void}) {
        this.loading = true;

        let providersIdsToRemove = [];
        for(let provId of this.selectedActivity.providers_ids) {
            let idStillPresent = false;
            for(let newProvId of newProvidersIds) {
                if(provId === newProvId) {
                    idStillPresent = true;
                    break;
                }
            }
            if(!idStillPresent) {
                providersIdsToRemove.push(-provId);
            }
        }

        try {
            await this.api.update('sale\\booking\\BookingActivity', [this.selectedActivity.id], {providers_ids: [...providersIdsToRemove, ...newProvidersIds]});

            await this.loadWeekActivities();
        }
        catch(response) {
            onFail();

            this.api.errorFeedback(response);
        }

        this.loading = false;
    }

    public async onWeekDescriptionChanges() {
        this.loading = true;

        let map_activity_weeks_descriptions = JSON.parse(this.booking.activity_weeks_descriptions) as {[key: string]: string};
        if(!map_activity_weeks_descriptions) {
            map_activity_weeks_descriptions = {};
        }
        map_activity_weeks_descriptions[this.weekStartDate.toISOString().split("T")[0]] = this.weekDescriptionFormControl.value;

        try {
            await this.api.update('sale\\booking\\Booking', [this.booking.id], {activity_weeks_descriptions: JSON.stringify(map_activity_weeks_descriptions)});
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loading = false;
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
