import { Component, OnInit, AfterViewInit, Input, Output, EventEmitter, ViewChildren, QueryList, ViewChild, OnChanges, SimpleChanges } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { ApiService, TreeComponent, SbDialogConfirmDialog } from 'sb-shared-lib';
import { BookingLineGroup } from '../../../../_models/booking_line_group.model';
import { BookingAccomodation } from '../../../../_models/booking_accomodation.model';
import { Booking } from '../../../../_models/booking.model';
import { RentalUnitClass } from 'src/app/model/rental.unit.class';
import { BehaviorSubject } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import { BookingServicesBookingGroupAccomodationAssignmentComponent } from './_components/assignment.component';
import { BookingServicesBookingGroupAccomodationAssignmentsEditorComponent } from './_components/assignmentseditor/assignmentseditor.component';
import { RentalUnitsSettings } from '../../../../../../services.component';

// declaration of the interface for the map associating relational Model fields with their components
interface BookingLineAccomodationComponentsMap {
    rental_unit_assignments_ids: QueryList<BookingServicesBookingGroupAccomodationAssignmentComponent>
}

@Component({
    selector: 'booking-services-booking-group-rentalunitassignment',
    templateUrl: 'accomodation.component.html',
    styleUrls: ['accomodation.component.scss']
})
export class BookingServicesBookingGroupAccomodationComponent extends TreeComponent<BookingAccomodation, BookingLineAccomodationComponentsMap> implements OnInit, AfterViewInit, OnChanges {
    // server-model relayed by parent
    @Input() set model(values: any) { this.update(values) }
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Input() mode: string = 'view';
    @Input() settings: RentalUnitsSettings;

    @Output() updated = new EventEmitter();
    @Output() deleted = new EventEmitter();

    @ViewChildren(BookingServicesBookingGroupAccomodationAssignmentComponent) BookingServicesBookingGroupAccomodationAssignmentComponents: QueryList<BookingServicesBookingGroupAccomodationAssignmentComponent>;
    @ViewChild('assignmentsEditor') assignmentsEditor: BookingServicesBookingGroupAccomodationAssignmentsEditorComponent;

    public ready: boolean = false;
    public assignments_editor_enabled: boolean = false;
    public rentalUnits$ = new BehaviorSubject<RentalUnitClass[]>([]);
    public rentalUnits: RentalUnitClass[] = [];
    public filteredRentalUnits$ = new BehaviorSubject<RentalUnitClass[]>([]);
    public filteredRentalUnits: RentalUnitClass[] = [];

    public selectedRentalUnits$ = new BehaviorSubject<number[]>([]);
    public selectedRentalUnits: number[] = [];
    public allRentalUnitsSelected: boolean = false;
    public action_in_progress: boolean = false;
    public showOnlyParents$ = new BehaviorSubject<boolean>(false);
    public showOnlyParents: boolean = false;
    public showOnlyChildren$ = new BehaviorSubject<boolean>(false);
    public showOnlyChildren: boolean = false;
    public filterBy$ = new BehaviorSubject<string>('');
    public filterBy = '';

    constructor(
        private api: ApiService,
        private dialog: MatDialog
    ) {
        super( new BookingAccomodation() );
    }

    public ngAfterViewInit() {
        // init local componentsMap
        let map:BookingLineAccomodationComponentsMap = {
            rental_unit_assignments_ids: this.BookingServicesBookingGroupAccomodationAssignmentComponents
        };
        this.componentsMap = map;
        this.refreshAvailableRentalUnits();
    }

    public async ngOnInit() {
        this.ready = true;

        this.selectedRentalUnits$.subscribe((selectedRentalUnits) => {
            this.selectedRentalUnits = selectedRentalUnits;
            this.allRentalUnitsSelected = this.filteredRentalUnits.length === selectedRentalUnits.length && this.filteredRentalUnits.length > 0;
        });

        this.rentalUnits$.subscribe((rentalUnits) => {
            this.rentalUnits = rentalUnits;

            this.refreshFilteredRentalUnits();
        });

        this.filteredRentalUnits$.subscribe((filteredRentalUnits) => {
            this.filteredRentalUnits = filteredRentalUnits;

            this.allRentalUnitsSelected = filteredRentalUnits.length === this.selectedRentalUnits.length && filteredRentalUnits.length > 0;

            this.selectedRentalUnits$.next(
                this.selectedRentalUnits.filter((sru) => filteredRentalUnits.map(fru => fru.id).includes(sru))
            );
        });

        this.showOnlyParents$.subscribe((showOnlyParents) => {
            this.showOnlyParents = showOnlyParents;

            if(showOnlyParents) {
                this.showOnlyChildren$.next(false);
            }

            this.refreshFilteredRentalUnits();
        });

        this.showOnlyChildren$.subscribe((showOnlyChildren) => {
            this.showOnlyChildren = showOnlyChildren;

            if(showOnlyChildren) {
                this.showOnlyParents$.next(false);
            }

            this.refreshFilteredRentalUnits();
        });

        this.filterBy$
            .pipe(debounceTime(300))
            .subscribe((filterBy) => {
                this.filterBy = filterBy;
                this.refreshFilteredRentalUnits();
            });
    }

    public async ngOnChanges(changes: SimpleChanges) {
        if(changes.settings) {
            if(this.settings.show === 'parents') {
                this.showOnlyParents$.next(true);
            }
            else if(this.settings.show === 'children') {
                this.showOnlyChildren$.next(true);
            }
        }
    }

    private refreshFilteredRentalUnits() {
        let rentalUnits = this.rentalUnits;

        if(this.filterBy.length > 0) {
            rentalUnits = rentalUnits.filter((rentalUnit) => {
                return rentalUnit.name.toLowerCase().includes(this.filterBy.toLowerCase());
            });
        }

        if(this.showOnlyParents) {
            rentalUnits = rentalUnits.filter((rentalUnit) => !rentalUnit.parent_id);
        } else if(this.showOnlyChildren) {
            rentalUnits = rentalUnits.filter((rentalUnit) => rentalUnit.parent_id);
        }

        this.filteredRentalUnits$.next(rentalUnits);
    }

    public async refreshAvailableRentalUnits() {
        // reset rental units listing
        try {
            // retrieve rental units available for assignment
            const data = await this.api.fetch('?get=sale_booking_rentalunits', {
                booking_line_group_id: this.instance.booking_line_group_id,
                product_model_id: this.instance.product_model_id.id
            });
            this.rentalUnits$.next(data);
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async update(values:any) {
        console.log('accommodation update', values);
        super.update(values);
    }

    /**
     * Add a rental unit assignment
     */
    /*
    public async oncreateAssignment() {
        try {
            const assignment:any = await this.api.create("sale\\booking\\SojournProductModelRentalUnitAssignement", {
                qty: 1,
                booking_id: this.booking.id,
                booking_line_group_id: this.group.id,
                sojourn_product_model_id: this.instance.id
            });
            // relay to parent
            this.updated.emit();

        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }
    */

    public async ondeleteAssignment(assignment_id: any) {
        try {
            await this.api.update(this.instance.entity, [this.instance.id], {rental_unit_assignments_ids: [-assignment_id]});
            this.instance.rental_unit_assignments_ids.splice(this.instance.rental_unit_assignments_ids.findIndex((e:any)=>e.id == assignment_id),1);
            // relay to parent
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async onupdateAssignment(assignment_id:any) {
        this.updated.emit();
    }

    public leftFilterBy(event: any) {
        this.filterBy$.next(event.target.value);
    }

    public leftSelectAllRentalUnits() {
        if(this.allRentalUnitsSelected) {
            this.selectedRentalUnits$.next([]);
        }
        else {
            this.selectedRentalUnits$.next(this.filteredRentalUnits.map(ru => ru.id));
        }
    }

    public leftShowOnlyParents(checked: boolean) {
        this.showOnlyParents$.next(checked);

        if(this.settings.store_rental_units_settings) {
            this.storeShow(checked ? 'parents' : 'all');
        }
    }

    public leftShowOnlyChildren(checked: boolean) {
        this.showOnlyChildren$.next(checked);

        if(this.settings.store_rental_units_settings) {
            this.storeShow(checked ? 'children' : 'all');
        }
    }

    private storeShow(show: 'all'|'parents'|'children') {
        let stored_map_bookings_rental_units_settings: string | null = localStorage.getItem('map_bookings_rental_units_settings');
        if(stored_map_bookings_rental_units_settings === null) {
            stored_map_bookings_rental_units_settings = '{}';
        }

        const map_bookings_rental_units_settings: {[key: number]: RentalUnitsSettings} = JSON.parse(stored_map_bookings_rental_units_settings);
        if(!map_bookings_rental_units_settings[this.booking.id]) {
            map_bookings_rental_units_settings[this.booking.id] = JSON.parse(JSON.stringify(this.settings));
        }

        map_bookings_rental_units_settings[this.booking.id].show = show;

        localStorage.setItem('map_bookings_rental_units_settings', JSON.stringify(map_bookings_rental_units_settings));
    }

    public leftSelectRentalUnit(checked: boolean, rental_unit_id: number) {
        let index = this.selectedRentalUnits.indexOf(rental_unit_id);
        if(index == -1) {
            this.selectedRentalUnits$.next([
                ...this.selectedRentalUnits$.value,
                rental_unit_id
            ]);
        }
        else if(!checked) {
            let selectedRentalUnits = [...this.selectedRentalUnits$.value];
            selectedRentalUnits.splice(index, 1);
            this.selectedRentalUnits$.next(selectedRentalUnits);
        }
    }

    public addSelection() {
        // for each rental unit in the selection, create a new assignment
        let runningActions: Promise<any>[] = [];

        let remaining_assignments: number = this.group.nb_pers - this.instance.qty;

        for(let rental_unit_id of this.selectedRentalUnits) {
            const rentalUnit = <RentalUnitClass> this.rentalUnits.find( (item) => item.id == rental_unit_id );
            if(!rentalUnit) {
                continue;
            }
            // #memo - we allow assignment value to be above strict required capacity
            /*
            if(remaining_assignments <= 0) {
                continue;
            }
            */
            let rental_unit_capacity = <number> rentalUnit.capacity;
            // compare with capacity of Product Model from SPM
            if(this.instance.product_model_id.qty_accounting_method == 'accomodation' && this.instance.product_model_id.capacity && this.instance.product_model_id.capacity < rental_unit_capacity) {
                rental_unit_capacity = <number> this.instance.product_model_id.capacity;
            }

            let assignment_qty: number = (remaining_assignments > 0) ? remaining_assignments : rental_unit_capacity;

            if(assignment_qty > rental_unit_capacity) {
                assignment_qty = rental_unit_capacity;
            }

            // this must not be changed
            // remaining_assignments -= assignment_qty;

            const promise = this.api.create("sale\\booking\\SojournProductModelRentalUnitAssignement", {
                rental_unit_id: rentalUnit.id,
                qty: assignment_qty,
                booking_id: this.booking.id,
                booking_line_group_id: this.group.id,
                sojourn_product_model_id: this.instance.id
            });
            runningActions.push(promise);
        }
        Promise.all(runningActions).then( () => {
            // relay refresh request to parent
            this.updated.emit();
        })
        .catch( (response) =>  {
            this.api.errorFeedback(response);
        });
        this.selectedRentalUnits$.next([]);
    }

    public addAll() {
        // select all displayed rental units
        this.selectedRentalUnits$.next(this.filteredRentalUnits.map(ru => ru.id));
        this.addSelection();
    }

    public async onclickEditAssignments() {
        const dialog = this.dialog.open(SbDialogConfirmDialog, {
            width: '33vw',
            data: {
                title: "Modification des unités locatives",
                message: "La réservation est en option ou confirmée et des consommations ont déjà été générées. \
                En cas de modifications dans les assignations d'unités locatives, les consommation seront regénérées, et le planning sera modifié en conséquence. \
                <br /><br />Confirmer cette action ?",
                yes: 'Oui',
                no: 'Non'
            }
        });

        try {
            await new Promise( async(resolve, reject) => {
                dialog.afterClosed().subscribe( async (result) => (result)?resolve(true):reject() );
            });
            this.assignments_editor_enabled = true;
        }
        catch(error) {
            // user discarded the dialog (selected 'no')
        }
    }

    public async onclickSaveAssignments() {
        console.log("resulting assignments:", this.assignmentsEditor.rentalUnitsAssignments);
        if(this.action_in_progress) {
            return;
        }
        if(this.group.is_extra) {
            return;
        }
        this.action_in_progress = true;
        let result: any[] = [];
        for(let assignment of this.assignmentsEditor.rentalUnitsAssignments) {
            result.push({rental_unit_id: assignment.rental_unit_id.id, qty: assignment.qty});
        }
        try {
            await this.api.call('/?do=sale_booking_update-sojourn-assignment', {
                product_model_id: this.instance.product_model_id.id,
                booking_line_group_id: this.group.id,
                assignments: result
            });
            this.assignments_editor_enabled = false;
            this.action_in_progress = false;
            // snack OK
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
            this.action_in_progress = false;
        }
    }

    public async onclickCancelAssignments() {
        this.assignments_editor_enabled = false;
    }
}
