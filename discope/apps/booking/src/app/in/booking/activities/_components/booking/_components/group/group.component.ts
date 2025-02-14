import { Component, EventEmitter, Input, Output, QueryList } from '@angular/core';
import { BookingLineGroup } from '../../../../../services/_components/booking/_models/booking_line_group.model';
import { ApiService, AuthService, ContextService, TreeComponent } from 'sb-shared-lib';
import { BookingActivitiesBookingGroupLineComponent } from './_components/line/line.component';
import { Booking } from '../../../../../services/_components/booking/_models/booking.model';

// declaration of the interface for the map associating relational Model fields with their components
interface BookingLineGroupComponentsMap {
    booking_lines_ids: QueryList<BookingActivitiesBookingGroupLineComponent>
}

@Component({
    selector: 'booking-activities-booking-group',
    templateUrl: 'group.component.html',
    styleUrls: ['group.component.scss']
})
export class BookingActivitiesBookingGroupComponent extends TreeComponent<BookingLineGroup, BookingLineGroupComponentsMap> {

    @Input() set model(values: any) { this.update(values) }
    @Input() booking: Booking;

    @Output() toggle  = new EventEmitter();

    public folded: boolean = true;

    public ready: boolean = false;
    public loading: boolean = false;

    constructor(
        private api: ApiService,
        private auth: AuthService,
        private context: ContextService
    ) {
        super(new BookingLineGroup());
    }

    public ngOnInit() {
        this.ready = true;

        console.log(this.instance);
    }

    public fold() {
        this.folded = true;
    }

    public toggleFold() {
        this.folded = !this.folded;
        this.toggle.emit(this.folded);
    }
}
