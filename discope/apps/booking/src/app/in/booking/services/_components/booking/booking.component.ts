import { Component, OnInit, OnChanges, Input, ViewChildren, QueryList, AfterViewInit, SimpleChanges } from '@angular/core';

import { ApiService, ContextService, TreeComponent, RootTreeComponent, SbDialogConfirmDialog } from 'sb-shared-lib';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { MatDialog } from '@angular/material/dialog';

import { trigger, style, animate, transition } from '@angular/animations';

import { BookingServicesBookingGroupComponent } from './_components/group/group.component'
import { Booking } from './_models/booking.model';
import { BookingLineGroup } from './_models/booking_line_group.model';
import { BookingActivity } from './_models/booking_activity.model';
import { BookingLine } from './_models/booking_line.model';
import { BookingActivityDay } from './_components/group/_components/day-activities/day-activities.component';
import { BookedServicesDisplaySettings } from '../../services.component';
import { BookingMealDay } from './_components/group/_components/day-meals/day-meals.component';
import { BookingMeal } from './_models/booking_meal.model';

// declaration of the interface for the map associating relational Model fields with their components
interface BookingComponentsMap {
    booking_lines_groups_ids: QueryList<BookingServicesBookingGroupComponent>
}

@Component({
  selector: 'booking-services-booking',
  templateUrl: 'booking.component.html',
  styleUrls: ['booking.component.scss'],
  animations: [
    trigger(
      'groupInOutAnimation',
      [
        transition(
          ':enter',
          [
            style({ height: 0, opacity: 0 }),
            animate('.15s linear', style({ height: '35px', opacity: 1 }))
          ]
        ),
        transition(
          ':leave',
          [
            animate('.1s linear', style({ height: 0 }))
          ]
        )
      ]
    )
  ]
})
export class BookingServicesBookingComponent
    extends TreeComponent<Booking, BookingComponentsMap>
    implements RootTreeComponent, OnInit, OnChanges, AfterViewInit {

    @ViewChildren(BookingServicesBookingGroupComponent) bookingServicesBookingGroups: QueryList<BookingServicesBookingGroupComponent>;
    @Input() booking_id: number;
    @Input() display_settings: BookedServicesDisplaySettings;

    public ready: boolean = false;
    public loading: boolean = true;
    private loadingStartTime: number;

    public maximized_group_id: number = 0;
    public time_slots: { id: number, name: string, code: 'B'|'AM'|'L'|'PM'|'D'|'EV' }[] = [];
    public sojourn_types: { id: number, name: 'GA'|'GG' }[] = [];
    public meal_types: { id: number, name: string, code: string }[] = [];
    public meal_places: { id: number, name: string, code: string }[] = [];
    public mapGroupsIdsBookingActivitiesDays: {[key: number]: BookingActivityDay[]} = {};
    public mapGroupsIdsBookingMealsDays: {[key: number]: BookingMealDay[]} = {};
    public mapGroupsIdsHasActivity: {[key: number]: boolean};

    constructor(
        private dialog: MatDialog,
        private api: ApiService,
        private context: ContextService
    ) {
        super( new Booking() );
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.booking_id && this.booking_id > 0) {
            try {
                this.load(this.booking_id);
                this.ready = true;
            }
            catch(error) {
                console.warn(error);
            }
        }
    }

    public ngAfterViewInit() {
        // init local componentsMap
        let map:BookingComponentsMap = {
            booking_lines_groups_ids: this.bookingServicesBookingGroups
        };
        this.componentsMap = map;
    }

    public async ngOnInit() {
        this.time_slots = await this.api.collect('sale\\booking\\TimeSlot', [], ['id', 'name', 'code']);
        this.sojourn_types = await this.api.collect('sale\\booking\\SojournType', [], ['id', 'name']);
        this.meal_types = await this.api.collect('sale\\booking\\MealType', [], ['id', 'name', 'code']);
        this.meal_places = await this.api.collect('sale\\booking\\MealPlace', [], ['id', 'name', 'code']);
    }

    /**
     * Load an Booking object using the sale_pos_order_tree controller
     * @param booking_id
     */
    public load(booking_id: number) {
        if(booking_id > 0) {
            // #memo - init generates multiple load which badly impacts the UX
            // this.loading = true;
            this.api.fetch('?get=sale_booking_tree', {id:booking_id})
            .then( (result:any) => {
                if(result) {
                    console.debug('received updated booking', result);
                    this.update(result);
                    this.initMapGroupsIdsBookingActivitiesDays(result);
                    this.initMapGroupsIdsBookingMealsDays(result);
                    this.initMapGroupsIdsHasActivity(result);
                    this.loading = false;
                }
            })
            .catch(response => {
                console.warn(response);
                // if a 403 response is received, we assume that the user is not identified: redirect to /auth
                if(response.status == 403) {
                    window.location.href = '/auth';
                }
            });
        }
    }

    /**
     *
     * @param values
     */
    public update(values:any) {
        super.update(values);
    }

    public cancreateGroup() {
        if(['quote', 'checkedin','checkedout'].indexOf(this.instance.status) >= 0) {
            return true;
        }
        // locked booking cannot be reverted to quote but should allow modification
        if(['confirmed', 'validated'].indexOf(this.instance.status) >= 0 && this.instance.is_locked) {
            return true;
        }
        return false;
    }

    public async oncreateGroup() {
        this.loading = true;
        try {
            // unfold all groups
            this.maximized_group_id = 0;
            this.bookingServicesBookingGroups.forEach( (item:BookingServicesBookingGroupComponent) => item.fold() );

            await this.api.fetch('?do=sale_booking_update-groups-add', {id: this.instance.id});
            // reload booking tree (set loading to false afterward)
            this.load(this.instance.id);
        }
        catch(response) {
            this.api.errorFeedback(response);
            this.loading = false;
        }
    }

    public async oncloneGroup(group_id: number) {
        this.loading = true;
        try {
            await this.api.fetch('?do=sale_booking_clone-group', {id: group_id});

            // reload booking tree
            this.load(this.instance.id);
        }
        catch(response) {
            this.api.errorFeedback(response);
            this.loading = false;
        }
    }

    public async ondeleteGroup(group_id:number) {

        const dialog = this.dialog.open(SbDialogConfirmDialog, {
                width: '33vw',
                data: {
                    title: "Suppression d'un groupe de services",
                    message: 'Cette action supprimera définitivement le groupe de service visé.<br /><br />Confirmer cette action ?',
                    yes: 'Oui',
                    no: 'Non'
                }
            });

        try {
            await new Promise( async(resolve, reject) => {
                dialog.afterClosed().subscribe( async (result) => (result)?resolve(true):reject() );
            });
            try {
                // instant remove in view
                this.instance.booking_lines_groups_ids = this.instance.booking_lines_groups_ids.filter( (group:any) => group.id !== group_id);
                await this.api.fetch('?do=sale_booking_update-groups-remove', {id: this.instance.id, booking_line_group_id: group_id});
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
            // reload booking tree or rollback
            this.load(this.instance.id);
        }
        catch(response) {
            // user discarded the dialog (selected 'no')
            return;
        }
    }

    public onupdateGroup() {
        // reload booking tree
        this.load(this.instance.id);
    }

    public ondropGroup(event:CdkDragDrop<any>) {
        moveItemInArray(this.instance.booking_lines_groups_ids, event.previousIndex, event.currentIndex);
        for(let i = Math.min(event.previousIndex, event.currentIndex), n = Math.max(event.previousIndex, event.currentIndex); i <= n; ++i) {
            // #todo #refresh
            this.api.update((new BookingLineGroup()).entity, [this.instance.booking_lines_groups_ids[i].id], {order: i+1})
            .catch(response => this.api.errorFeedback(response));
        }
    }

    public ontoggleGroup(group_id:number, folded: boolean) {
        if(!folded) {
            this.maximized_group_id = group_id;
        }
        else {
            this.maximized_group_id = 0;
        }
    }

    /**
     * handle loading from sub components
     */
    public onLoadStartGroup() {
        this.loading = true;
        this.loadingStartTime = Date.now();
    }

    /**
     * enact loading end from sub components while forcing a minimum duration
     */
    public onLoadEndGroup() {
        const elapsed = Date.now() - this.loadingStartTime;
        const minDuration = 250;
        const remaining = minDuration - elapsed;

        if (remaining > 0) {
            setTimeout(() => this.loading = false, remaining);
        }
        else {
            this.loading = false;
        }
    }

    private initMapGroupsIdsBookingActivitiesDays(booking: Booking) {
        this.mapGroupsIdsBookingActivitiesDays = {};
        for(let group of booking.booking_lines_groups_ids) {
            this.mapGroupsIdsBookingActivitiesDays[group.id] = this.createGroupBookingActivitiesDays(group);
        }
    }

    private createGroupBookingActivitiesDays(group: BookingLineGroup) {
        const bookingActivitiesDays = [];
        let date = new Date(group.date_from);
        const dateTo = new Date(group.date_to);
        while(date <= dateTo) {
            const bookingActivityDay: BookingActivityDay = {
                date: new Date(date),
                AM: null,
                PM: null,
                EV: null
            };

            for(let bookingActivity of group.booking_activities_ids as BookingActivity[]) {
                let activityDate = new Date(bookingActivity.activity_date).toISOString().split('T')[0];
                if(activityDate !== date.toISOString().split('T')[0]) {
                    continue;
                }

                let activityBookingLine: BookingLine | undefined = group.booking_lines_ids.find(
                    (bookingLine: BookingLine) => bookingLine.id === bookingActivity.activity_booking_line_id
                );

                if(activityBookingLine === undefined || !activityBookingLine.service_date) {
                    continue;
                }

                const timeSlot = this.time_slots.find((timeSlot: any) => timeSlot.id === bookingActivity.time_slot_id);
                if(!timeSlot || !['AM', 'PM', 'EV'].includes(timeSlot.code)) {
                    continue;
                }

                bookingActivityDay[timeSlot.code as 'AM'|'PM'|'EV'] = {
                    ...bookingActivity,
                    entity: 'sale\\booking\\BookingActivity',
                    activity_booking_line_id: activityBookingLine,
                    transports_booking_lines_ids: group.booking_lines_ids.filter(
                        (bookingLine: BookingLine) => bookingActivity.transports_booking_lines_ids.map(Number).includes(bookingLine.id)
                    ),
                    supplies_booking_lines_ids: group.booking_lines_ids.filter(
                        (bookingLine: BookingLine) => bookingActivity.supplies_booking_lines_ids.map(Number).includes(bookingLine.id)
                    )
                };
            }

            bookingActivitiesDays.push(bookingActivityDay);

            date.setDate(date.getDate() + 1);
        }

        return bookingActivitiesDays;
    }

    private initMapGroupsIdsBookingMealsDays(booking: Booking) {
        this.mapGroupsIdsBookingMealsDays = {};
        for(let group of booking.booking_lines_groups_ids) {
            this.mapGroupsIdsBookingMealsDays[group.id] = this.createGroupBookingMealsDays(group);
        }
    }

    private createGroupBookingMealsDays(group: BookingLineGroup) {
        const bookingMealsDays = [];
        let date = new Date(group.date_from);
        const dateTo = new Date(group.date_to);
        while(date <= dateTo) {
            const bookingMealDay: BookingMealDay = {
                date: new Date(date),
                B: null,
                AM: null,
                L: null,
                PM: null,
                D: null
            };

            for(let bookingMeal of group.booking_meals_ids as BookingMeal[]) {
                let mealDate = new Date(bookingMeal.date).toISOString().split('T')[0];
                if(mealDate !== date.toISOString().split('T')[0]) {
                    continue;
                }

                const timeSlot = this.time_slots.find((timeSlot: any) => timeSlot.id === bookingMeal.time_slot_id);
                if(!timeSlot || !['B', 'AM', 'L', 'PM', 'D'].includes(timeSlot.code)) {
                    continue;
                }

                bookingMealDay[timeSlot.code as 'B'|'AM'|'L'|'PM'|'D'] = bookingMeal;
            }

            bookingMealsDays.push(bookingMealDay);

            date.setDate(date.getDate() + 1);
        }

        return bookingMealsDays;
    }

    private initMapGroupsIdsHasActivity(booking: Booking) {
        this.mapGroupsIdsHasActivity = {};
        for(let group of booking.booking_lines_groups_ids as BookingLineGroup[]) {
            let hasActivity = false;
            for(let line of group.booking_lines_ids as BookingLine[]) {
                if(line.is_activity) {
                    hasActivity = true;
                }
            }

            this.mapGroupsIdsHasActivity[group.id] = hasActivity;
        }
    }
}
