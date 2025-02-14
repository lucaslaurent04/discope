import { AfterViewInit, Component, Input, OnChanges, QueryList, SimpleChanges, ViewChildren } from '@angular/core';
import { Booking } from '../../../services/_components/booking/_models/booking.model';
import { ApiService, TreeComponent, RootTreeComponent } from 'sb-shared-lib';
import { BookingActivitiesBookingGroupComponent } from './_components/group/group.component';
import { animate, style, transition, trigger } from '@angular/animations';

// declaration of the interface for the map associating relational Model fields with their components
interface BookingComponentsMap {
    booking_lines_groups_ids: QueryList<BookingActivitiesBookingGroupComponent>
}

@Component({
  selector: 'booking-activities-booking',
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
export class BookingActivitiesBookingComponent
    extends TreeComponent<Booking, BookingComponentsMap>
    implements RootTreeComponent, OnChanges, AfterViewInit {

    @ViewChildren(BookingActivitiesBookingGroupComponent) bookingActivitiesBookingGroups: QueryList<BookingActivitiesBookingGroupComponent>;
    @Input() booking_id: number;

    public ready: boolean = false;
    public loading: boolean = true;
    public maximized_group_id: number = 0;

    constructor(
        private api: ApiService
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
            booking_lines_groups_ids: this.bookingActivitiesBookingGroups
        };
        this.componentsMap = map;
    }

    public load(booking_id: number) {
        if(booking_id > 0) {
            // #memo - init generates multiple load which badly impacts the UX
            // this.loading = true;
            this.api.fetch('?get=sale_booking_tree', {id:booking_id})
                .then( (result:any) => {
                    console.log('result', result)
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
}
