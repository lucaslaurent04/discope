import { Component, OnInit, OnChanges, Input, ViewChildren, QueryList, AfterViewInit, SimpleChanges } from '@angular/core';

import { ApiService, ContextService, TreeComponent, RootTreeComponent, SbDialogConfirmDialog } from 'sb-shared-lib';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { MatDialog } from '@angular/material/dialog';

import { trigger, style, animate, transition } from '@angular/animations';

import { BookingServicesBookingGroupComponent } from './_components/group/group.component'
import { Booking } from './_models/booking.model';
import { BookingLineGroup } from './_models/booking_line_group.model';

import { BookingLine } from './_models/booking_line.model';

import { BookedServicesDisplaySettings, RentalUnitsSettings } from '../../services.component';

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
    @Input() rental_units_settings: RentalUnitsSettings;

    // By convention, `ready` is set to true once the component has completed its
    // initial lifecycle phase: constructor + first ngOnChanges (if any) + ngOnInit,
    // and the view has been initialized (ngAfterViewInit). At this point, all
    // @Input values are available and the component is rendered in the DOM.
    public ready: boolean = false;
    public loading: boolean = true;
    private loadingStartTime: number;

    public maximized_group_id: number = 0;
    public time_slots: { id: number, name: string, code: 'B'|'AM'|'L'|'PM'|'D'|'EV' }[] = [];
    public sojourn_types: { id: number, name: 'GA'|'GG' }[] = [];
    public meal_types: { id: number, name: string, code: string }[] = [];
    public meal_places: { id: number, name: string, code: string }[] = [];

    public mapGroupsIdsHasActivity: {[key: number]: boolean};

    constructor(
        private dialog: MatDialog,
        private api: ApiService,
        private context: ContextService
    ) {
        super( new Booking() );
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(!this.ready) {
            console.debug('BookingServicesBookingComponent::ngOnChanges : first call - ignoring');
            return;
        }
        console.debug('BookingServicesBookingComponent::ngOnChanges', changes);
        if(changes.booking_id && this.booking_id > 0) {
            this.load(this.booking_id);
        }
    }

    public ngAfterViewInit() {
        console.debug('BookingServicesBookingComponent::ngAfterViewInit');
        // init local componentsMap
        this.componentsMap = {
            booking_lines_groups_ids: this.bookingServicesBookingGroups
        } as BookingComponentsMap;
    }

    public async ngOnInit() {
        console.debug('BookingServicesBookingComponent::ngOnInit');
        const [timeSlots, sojournTypes, mealTypes, mealPlaces] = await Promise.all([
            this.api.collect('sale\\booking\\TimeSlot', [], ['id','name','code']),
            this.api.collect('sale\\booking\\SojournType', [], ['id','name']),
            this.api.collect('sale\\booking\\MealType', [], ['id','name','code']),
            this.api.collect('sale\\booking\\MealPlace', [], ['id','name','code'])
        ]);

        this.time_slots = timeSlots;
        this.sojourn_types = sojournTypes;
        this.meal_types = mealTypes;
        this.meal_places = mealPlaces;

        if(this.booking_id > 0) {
            await this.load(this.booking_id);
        }

        this.ready = true;
    }


    /**
     * Load an Booking object using the sale_pos_order_tree controller
     * @param booking_id
     */
    public async load(booking_id: number) {
        if(booking_id <= 0){
            return;
        }

        try {
            this.loading = true;
            const result: any = await this.api.fetch('?get=sale_booking_tree', { id: booking_id });
            if (result) {
                this.update(result);
                this.initMapGroupsIdsHasActivity(result);
            }
        }
        catch (e) {
            console.warn(e);
            if((e as any)?.status === 403) {
                window.location.href = '/auth';
            }
        }
        finally {
            this.loading = false;
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
                // optimistic UI - instant remove in view
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
        if(!this.loadingStartTime) { this.loading = false; return; }
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

    private initMapGroupsIdsHasActivity(booking: Booking) {
        this.mapGroupsIdsHasActivity = {};
        for(let group of booking.booking_lines_groups_ids as BookingLineGroup[]) {
            let hasActivity = false;
            for(let line of group.booking_lines_ids as BookingLine[]) {
                if(line.is_activity) {
                    hasActivity = true;
                    break;
                }
            }
            this.mapGroupsIdsHasActivity[group.id] = hasActivity;
        }
    }
}
