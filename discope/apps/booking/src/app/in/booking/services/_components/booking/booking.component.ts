import { Component, Inject, OnInit, OnChanges, NgZone, Output, Input, ViewChildren, QueryList, AfterViewInit, SimpleChanges } from '@angular/core';

import { ApiService, ContextService, TreeComponent, RootTreeComponent, SbDialogConfirmDialog } from 'sb-shared-lib';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { MatDialog } from '@angular/material/dialog';
import { Observable, ReplaySubject, BehaviorSubject, async } from 'rxjs';

import { trigger, state, style, animate, transition } from '@angular/animations';

import { BookingServicesBookingGroupComponent } from './_components/group/group.component'
import { Booking } from './_models/booking.model';
import { BookingLineGroup } from './_models/booking_line_group.model';

// declaration of the interface for the map associating relational Model fields with their components
interface BookingComponentsMap {
    booking_lines_groups_ids: QueryList<BookingServicesBookingGroupComponent>
};


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

    public ready: boolean = false;
    public loading: boolean = true;
    public maximized_group_id: number = 0;

    constructor(
        private dialog: MatDialog,
        private api: ApiService,
        private context: ContextService
    ) {
        super( new Booking() );
    }

    ngOnChanges(changes: SimpleChanges) {
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

    public ngOnInit() {
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

    public onLoadStartGroup() {
        this.loading = true;
    }

    public onLoadEndGroup() {
        this.loading = false;
    }
}
