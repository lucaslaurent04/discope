import { Component, OnInit, AfterViewInit, Input, Output, EventEmitter, ChangeDetectorRef, ViewChildren, QueryList, ViewChild } from '@angular/core';
import { FormControl, Validators } from '@angular/forms';
import { ApiService, AuthService, ContextService, TreeComponent, SbDialogConfirmDialog } from 'sb-shared-lib';
import { BookingLineGroup } from '../../_models/booking_line_group.model';
import { BookingLine } from '../../_models/booking_line.model';
import { Booking } from '../../_models/booking.model';
import { UserClass } from 'sb-shared-lib/lib/classes/user.class';

import { BookingServicesBookingGroupLineComponent } from './_components/line/line.component';
import { BookingServicesBookingGroupAccomodationComponent } from './_components/accomodation/accomodation.component';
import { BookingServicesBookingGroupMealPrefComponent } from './_components/mealpref/mealpref.component';
import { BookingServicesBookingGroupAgeRangeComponent } from './_components/agerange/agerange.component';

import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { Observable, ReplaySubject } from 'rxjs';
import { debounceTime, map, mergeMap } from 'rxjs/operators';
import { BookingMealPref } from '../../_models/booking_mealpref.model';
import { BookingAgeRangeAssignment } from '../../_models/booking_agerange_assignment.model';
import { MatAutocomplete } from '@angular/material/autocomplete';
import { MatDialog } from '@angular/material/dialog';
import { BookingActivityDay } from './_components/day-activities/day-activities.component';
import { BookedServicesDisplaySettings, RentalUnitsSettings } from '../../../../services.component';
import { BookingMealDay } from './_components/day-meals/day-meals.component';
import { BookingServicesBookingGroupDialogParticipantsOptionsComponent } from './_components/dialog-participants-options/dialog-participants-options.component';


// declaration of the interface for the map associating relational Model fields with their components
interface BookingLineGroupComponentsMap {
    booking_lines_ids: QueryList<BookingServicesBookingGroupLineComponent>,
    meal_preferences_ids: QueryList<BookingServicesBookingGroupMealPrefComponent>,
    age_range_assignments_ids: QueryList<BookingServicesBookingGroupAgeRangeComponent>,
    sojourn_product_models_ids: QueryList<BookingServicesBookingGroupAccomodationComponent>
}

interface vmModel {
    price: {
        value: number
    }
    name: {
        value: string,
        display_name: string,
        formControl: FormControl
    },
    daterange: {
        start: {
            formControl: FormControl
        },
        end: {
            formControl: FormControl
        },
        nights_count: number
    },
    timerange: {
        checkin: {
            formControl: FormControl
        },
        checkout: {
            formControl: FormControl
        }
    },
    participants_count: {
        formControl: FormControl
    },
    sojourn_type: {
        value: string
    },
    pack: {
        name: string,
        is_locked: boolean,
        inputClue: ReplaySubject < any > ,
        filteredList: Observable < any > ,
        inputChange: (event: any) => void,
        focus: () => void,
        restore: () => void,
        reset: () => void
    },
    rate_class: {
        name: string
        inputClue: ReplaySubject < any > ,
        filteredList: Observable < any > ,
        inputChange: (event: any) => void,
        focus: () => void,
        restore: () => void,
        reset: () => void,
        display: (type: any) => string
    },
    lines: {
        drop: (event: CdkDragDrop < any > ) => void
    }
}

@Component({
    selector: 'booking-services-booking-group',
    templateUrl: 'group.component.html',
    styleUrls: ['group.component.scss']
})
export class BookingServicesBookingGroupComponent extends TreeComponent<BookingLineGroup, BookingLineGroupComponentsMap> implements OnInit, AfterViewInit  {
    // server-model relayed by parent
    @Input() set model(values: any) { this.update(values) }
    @Input() booking: Booking;
    @Input() timeSlots: { id: number, name: string, code: 'B'|'AM'|'L'|'PM'|'D'|'EV' }[];
    @Input() sojournTypes: { id: number, name: 'GA'|'GG' }[] = [];
    @Input() mealTypes: { id: number, name: string, code: string }[] = [];
    @Input() mealPlaces: { id: number, name: string, code: string }[] = [];
    @Input() bookingActivitiesDays: BookingActivityDay[];
    @Input() bookingMealsDays: BookingMealDay[];
    @Input() displaySettings: BookedServicesDisplaySettings;
    @Input() rentalUnitsSettings: RentalUnitsSettings;

    @Output() loadStart = new EventEmitter();
    @Output() loadEnd   = new EventEmitter();
    @Output() updated = new EventEmitter();
    @Output() deleted = new EventEmitter();
    @Output() toggle  = new EventEmitter();

    public user: UserClass = null;

    public folded: boolean = true;
    public groupSummaryOpen: boolean = false;
    public groupTypeOpen: boolean = false;
    public groupNbPersOpen: boolean = false;
    public groupDatesOpen: boolean = false;
    public openedActivityIds: number[] = [];
    public providedMealsQty: number = 0;
    public mealsShowSnack: boolean = false;

    public action_in_progress: boolean = false;

    public mode: string = 'view';

    @ViewChild('packAutocomplete') packAutocomplete: MatAutocomplete;

    @ViewChildren(BookingServicesBookingGroupLineComponent) bookingServicesBookingLineComponents: QueryList<BookingServicesBookingGroupLineComponent>;
    @ViewChildren(BookingServicesBookingGroupAccomodationComponent) bookingServicesBookingGroupAccomodationComponents: QueryList<BookingServicesBookingGroupAccomodationComponent>;
    @ViewChildren(BookingServicesBookingGroupMealPrefComponent) bookingServicesBookingGroupMealPrefComponents: QueryList<BookingServicesBookingGroupMealPrefComponent>;
    @ViewChildren(BookingServicesBookingGroupAgeRangeComponent) bookingServicesBookingGroupAgeRangeComponents: QueryList<BookingServicesBookingGroupAgeRangeComponent>;

    public ready: boolean = false;
    public loading: boolean = false;

    public vm: vmModel;

    constructor(
        private cd: ChangeDetectorRef,
        private api: ApiService,
        private auth: AuthService,
        private dialog: MatDialog,
        private context: ContextService
    ) {
        super( new BookingLineGroup() );

        this.vm = {
            price: {
                value: 0
            },
            name: {
                value: '',
                display_name: '',
                formControl: new FormControl('', Validators.required)
            },
            daterange: {
                start: {
                    formControl: new FormControl()
                },
                end: {
                    formControl: new FormControl()
                },
                nights_count: 0
            },
            timerange: {
                checkin: {
                    formControl: new FormControl()
                },
                checkout: {
                    formControl: new FormControl()
                }
            },
            participants_count: {
                formControl: new FormControl('', Validators.required)
            },
            sojourn_type: {
                value: 'GG'
            },
            pack: {
                name: '',
                is_locked: false,
                inputClue: new ReplaySubject(1),
                filteredList: new Observable(),
                inputChange: (event: any) => this.packInputChange(event),
                focus: () => this.packFocus(),
                restore: () => this.packRestore(),
                reset: () => this.packReset()
            },
            rate_class: {
                name: '',
                inputClue: new ReplaySubject(1),
                filteredList: new Observable(),
                inputChange: (event: any) => this.rateClassInputChange(event),
                focus: () => this.rateClassFocus(),
                restore: () => this.rateClassRestore(),
                reset: () => this.rateClassReset(),
                display: (type: any) => this.rateClassDisplay(type)
            },
            lines: {
                drop: (event: CdkDragDrop < any > ) => this.lineDrop(event)
            }
        };
    }

    public ngAfterViewInit() {
        // init local componentsMap
        let map:BookingLineGroupComponentsMap = {
            booking_lines_ids: this.bookingServicesBookingLineComponents,
            meal_preferences_ids: this.bookingServicesBookingGroupMealPrefComponents,
            age_range_assignments_ids: this.bookingServicesBookingGroupAgeRangeComponents,
            sojourn_product_models_ids: this.bookingServicesBookingGroupAccomodationComponents
        };
        this.componentsMap = map;
    }

    public ngOnInit() {
        if(this.booking.status == 'quote' || (this.instance.is_extra && !this.instance.has_consumptions)) {
            this.mode = 'edit';
        }

        this.auth.getObservable().subscribe( async (user: UserClass) => {
            this.user = user;
        });

        this.vm.pack.filteredList = this.vm.pack.inputClue.pipe(
            debounceTime(300),
            map( (value:any) => (typeof value === 'string' ? value : (value == null)?'':value.name) ),
            mergeMap( async (name:string) => this.filterPacks(name) )
        );

        this.vm.rate_class.filteredList = this.vm.rate_class.inputClue.pipe(
            debounceTime(300),
            map( (value:any) => (typeof value === 'string' ? value : (value == null)?'':value.name) ),
            mergeMap( async (name:string) => this.filterRateClasses(name) )
        );

        this.vm.name.formControl.valueChanges.subscribe( (value:string)  => {
            this.vm.name.value = value;
        });

        this.vm.timerange.checkin.formControl.valueChanges.subscribe( () => {
            this.onchangeTimeFrom();
        });

        this.vm.timerange.checkout.formControl.valueChanges.subscribe( () => {
            this.onchangeTimeTo();
        });

        this.ready = true;
    }

    public update(values:any) {
        super.update(values);
        // assign VM values
        this.vm.name.formControl.setValue(this.instance.name);
        this.vm.pack.name = (this.instance.has_pack && this.instance.pack_id && Object.keys(this.instance.pack_id).length) ? this.instance.pack_id.name : '';
        this.vm.pack.is_locked = this.instance.is_locked;
        this.vm.rate_class.name = this.instance.rate_class_id.name;
        this.vm.daterange.start.formControl.setValue(new Date(this.instance.date_from.toString()));
        this.vm.daterange.end.formControl.setValue(new Date(this.instance.date_to.toString()));
        this.vm.daterange.nights_count = this.instance.nb_nights;
        this.vm.timerange.checkin.formControl.setValue(this.instance.time_from.substring(0, 5));
        this.vm.timerange.checkout.formControl.setValue(this.instance.time_to.substring(0, 5));
        this.vm.participants_count.formControl.setValue(this.instance.nb_pers);
        this.vm.price.value = this.instance.price;
        this.vm.sojourn_type.value = (this.instance.sojourn_type_id == 1)?'GA':'GG';

        this.providedMealsQty = 0;
        for(let bookingMeal of this.instance.booking_meals_ids) {
            if(!bookingMeal.is_self_provided) {
                this.providedMealsQty++;
            }
        }

        // #workaround - force age_ranges update (since it cannot be done in update())
        this.instance.age_range_assignments_ids = values.age_range_assignments_ids;

        // refresh the lists of available rental units for all SPM
        if(this.bookingServicesBookingGroupAccomodationComponents && typeof this.bookingServicesBookingGroupAccomodationComponents[Symbol.iterator] === 'function') {
            for(let spm of this.bookingServicesBookingGroupAccomodationComponents) {
                spm.refreshAvailableRentalUnits();
            }
        }
    }

    public calcRateClass() {
        return this.instance.rate_class_id.name + ' - ' + this.instance.rate_class_id.description;
    }

    public calcPack(pack:any): string {
        return (pack) ? pack.name: '';
    }

    public async oncreateMealPref() {
        try {
            const new_pref:any = await this.api.create("sale\\booking\\MealPreference", {
                booking_line_group_id: this.instance.id
            });

            this.instance.meal_preferences_ids.push(new BookingMealPref(new_pref.id));
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async ondeleteMealPref(pref_id:number) {
        try {
            // #todo #refresh - remove direct MealPreference
            await this.api.update(this.instance.entity, [this.instance.id], {meal_preferences_ids: [-pref_id]});
            this.instance.meal_preferences_ids.splice(this.instance.meal_preferences_ids.findIndex((e:any)=>e.id == pref_id),1);
            // no relay to parent
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async onupdateMealPref() {
        // relay to parent
        // this.updated.emit();
    }

    public async onupdateAgeRange() {
        // relay to parent
        this.updated.emit();
    }

    public async onupdatingAgeRange(loading: boolean) {
        this.loading = loading;
    }

    public async oncreateLine() {
        try {
            const new_line:any = await this.api.create("sale\\booking\\BookingLine", {
                order: this.instance.booking_lines_ids.length + 1,
                booking_id: this.booking.id,
                booking_line_group_id: this.instance.id
            });

            this.instance.booking_lines_ids.push(new BookingLine(new_line.id));
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async ondeleteActivity(activity_id: number) {
        this.loading = true;
        setTimeout( async () => {
            try {
                await this.api.remove('sale\\booking\\BookingActivity', [activity_id]);
                // relay to parent
                this.updated.emit();
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
            this.loading = false;
        });
    }

    public async ondeleteLine(line_id:number) {
        try {
            if(this.instance.has_pack) {
                const dialog = this.dialog.open(SbDialogConfirmDialog, {
                    width: '33vw',
                    data: {
                        title: "Supression produit",
                        message: "Ce produit est peut-être lié à un <b>pack</b>, êtes-vous certains de vouloir le supprimer ?",
                        yes: 'Oui',
                        no: 'Non'
                    }
                });

                await new Promise( async(resolve, reject) => {
                    dialog.afterClosed().subscribe( async (result) => (result) ? resolve(true) : reject() );
                });
            }

            this.loading = true;
            setTimeout( async () => {
                try {
                    // #todo #refresh - this triggers onupdateBookingLinesIds, which triggers _resetPrices
                    await this.api.update(this.instance.entity, [this.instance.id], {booking_lines_ids: [-line_id]});
                    // relay to parent
                    this.updated.emit();
                }
                catch(response) {
                    this.api.errorFeedback(response);
                }
                this.loading = false;
            });
        }
        catch(e) {
            // user discarded the dialog (selected 'no')
        }
    }

    public onupdateLine() {
        // relay to parent
        this.updated.emit();
    }

    public onupdateActivity() {
        // relay to parent
        this.updated.emit();
    }

    public onupdateMeal() {
        // relay to parent
        this.updated.emit();
    }

    public async onupdateAccomodation() {
        // relay to parent
        this.updated.emit();
        // #memo - the lists of available rental units for all SPM will be refreshed when self::update() is triggered back
    }

    public fold() {
        this.folded = true;
    }

    public sectionUnfold(key: string) {
        if(!this.displaySettings.store_folded_settings) {
            return;
        }

        this.storeFolded(key, false);
    }

    public sectionFold(key: string) {
        if(!this.displaySettings.store_folded_settings) {
            return;
        }

        this.storeFolded(key, true);
    }

    private storeFolded(key: string, folded: boolean) {
        let stored_map_bookings_booked_services_settings: string | null = localStorage.getItem('map_bookings_booked_services_settings');
        if(stored_map_bookings_booked_services_settings === null) {
            stored_map_bookings_booked_services_settings = '{}';
        }

        const map_bookings_booked_services_settings: {[key: number]: BookedServicesDisplaySettings} = JSON.parse(stored_map_bookings_booked_services_settings);
        if(!map_bookings_booked_services_settings[this.booking.id]) {
            map_bookings_booked_services_settings[this.booking.id] = JSON.parse(JSON.stringify(this.displaySettings));
        }

        map_bookings_booked_services_settings[this.booking.id][`${key}_folded` as keyof BookedServicesDisplaySettings] = folded;

        localStorage.setItem('map_bookings_booked_services_settings', JSON.stringify(map_bookings_booked_services_settings));
    }

    public toggleFold() {
        this.folded = !this.folded;
        this.toggle.emit(this.folded);
    }

    private async filterRateClasses(name: string) {
        let filtered:any[] = [];
        try {
            let data:any[] = await this.api.collect("sale\\customer\\RateClass", [["name", "ilike", '%'+name+'%']], ["id", "name", "description"], 'name', 'asc', 0, 25);
            filtered = data;
        }
        catch(response) {
            console.log(response);
        }
        return filtered;
    }

    public async onchangeTimeFrom() {
        if(this.instance.time_from.substring(0, 5) != this.vm.timerange.checkin.formControl.value) {
            console.log('BookingEditCustomerComponent::onchangeTimeFrom', this.vm.timerange.checkin.formControl.value);
            try {
                await this.api.update(this.instance.entity, [this.instance.id], {time_from: this.vm.timerange.checkin.formControl.value});
                // do not relay change to parent component
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
        }
    }

    public async onchangeTimeTo() {
        if(this.instance.time_to.substring(0, 5) != this.vm.timerange.checkout.formControl.value) {
            console.log('BookingEditCustomerComponent::onchangeTimeTo');
            try {
                await this.api.update(this.instance.entity, [this.instance.id], {time_to: this.vm.timerange.checkout.formControl.value});
                // do not relay change to parent component
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
        }
    }

    public async onchangeNbPers() {
        console.log('BookingEditCustomerComponent::nbPersChange');
        if(this.vm.participants_count.formControl.value != this.instance.nb_pers) {
            this.loading = true;
            try {
                await this.api.fetch('?do=sale_booking_update-sojourn-nbpers', {
                        id: this.instance.id,
                        nb_pers: this.vm.participants_count.formControl.value
                    });

                // relay change to parent component
                this.updated.emit();

            }
            catch(response) {
                console.log(response);
                // restore value
                this.vm.participants_count.formControl.setValue(this.instance.nb_pers);
                // display error
                // this.api.errorSnack('nb_pers', "Le nombre de personnes ne correspond pas aux tranches d'âge");
                this.api.errorFeedback(response);
            }
            this.loading = false;
        }
    }

    public async onchangeName() {
        console.log('BookingEditCustomerComponent::nameChange');
        try {
            // update group
            await this.api.update(this.instance.entity, [this.instance.id], {name: this.vm.name.value});
            // do not relay change to parent component
            this.instance.name = this.vm.name.value;
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public onchangeDateRange() {
        this.groupDatesOpen = false;

        let start = this.vm.daterange.start.formControl.value;
        let end = this.vm.daterange.end.formControl.value;

        if(!start || !end) return;

        if(typeof start == 'string') {
            start = new Date(start);
        }

        if(typeof end == 'string') {
            end = new Date(end);
        }

        let diff = Math.round((Date.parse(end.toString()) - Date.parse(start.toString())) / (60*60*24*1000));

        if(diff >= 0) {
            this.vm.daterange.nights_count = (diff < 0)?0:diff;
            // relay change to parent component
            if((start.getTime() != this.instance.date_from.getTime() || end.getTime() != this.instance.date_to.getTime())) {
                this.loading = true;
                setTimeout( async () => {
                    // make dates UTC @ 00:00:00
                    let timestamp, offset_tz;
                    timestamp = start.getTime();
                    offset_tz = start.getTimezoneOffset()*60*1000;
                    let date_from = (new Date(timestamp-offset_tz)).toISOString().substring(0, 10)+'T00:00:00Z';
                    timestamp = end.getTime();
                    offset_tz = end.getTimezoneOffset()*60*1000;
                    let date_to = (new Date(timestamp-offset_tz)).toISOString().substring(0, 10)+'T00:00:00Z';

                    try {
                        await this.api.fetch('?do=sale_booking_update-sojourn-dates', {
                                id: this.instance.id,
                                date_from: date_from,
                                date_to: date_to
                            });
                        this.updated.emit();
                    }
                    catch(response) {
                        this.api.errorFeedback(response);
                        // #todo - improve to rollback non-updatable fields only
                        // force refresh
                        this.updated.emit();
                    }
                    this.loading = false;
                });
            }
            // update VM values until refresh
            // this.instance.date_from = start;
            // this.instance.date_to = end;
        }
    }

    public async onchangeHasPack(has_pack: any) {
        if(this.instance.has_pack != has_pack) {
            let fields: any = {has_pack: has_pack};
            this.loading = true;

            try {
                if(has_pack === false) {
                    await this.api.fetch('?do=sale_booking_update-sojourn-pack-remove', {id: this.instance.id});
                }
                else {
                    await this.api.update(this.instance.entity, [this.instance.id], {has_pack: true})
                }
                // relay change to parent component
                this.updated.emit();
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
            this.loading = false;
        }
    }

    public async onchangePackId(pack: any) {
        if(!this.instance.pack_id || this.instance.pack_id.id != pack.id) {
            this.vm.pack.name = pack.name;
            this.loading = true;
            try {
                await this.api.fetch('?do=sale_booking_update-sojourn-pack-set', {id: this.instance.id, pack_id: pack.id});
                // relay change to parent component
                this.updated.emit();
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
            this.loading = false;
        }
    }

    public async onchangeIsLocked(locked: any) {
        if(this.instance.is_locked != locked) {
            this.vm.pack.is_locked = locked;
            try {
                await this.api.update(this.instance.entity, [this.instance.id], {is_locked: locked});
                // relay change to parent component
                this.updated.emit();
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
        }
    }

    public async onchangeHasLockedRentalUnits(event: any) {
        let locked = event.checked;
        if(this.instance.has_locked_rental_units != locked) {
            try {
                await this.api.update(this.instance.entity, [this.instance.id], {has_locked_rental_units: locked});
                // relay change to parent component
                this.updated.emit();
            }
            catch(response) {
                // restore value
                event.source.checked = !event.source.checked;
                this.api.errorFeedback(response);
            }
        }
    }

    private packInputChange(event:any) {
        this.vm.pack.inputClue.next(event.target.value);
    }

    private packFocus() {
        this.vm.pack.inputClue.next("");
    }

    private packReset() {
        setTimeout( () => {
            this.vm.pack.name = '';
        }, 100);
    }

    private packRestore() {
        if(this.vm.pack.name == '') {
            if(this.instance.pack_id && Object.keys(this.instance.pack_id).length) {
                this.vm.pack.name = this.instance.pack_id.name;
            }
            else {
                this.vm.pack.name = '';
            }
        }
    }

    public async oncreateAgeRange() {
        try {
            const new_range_assignment:any = await this.api.fetch('?do=sale_booking_update-sojourn-agerange-add', {
                        id: this.instance.id,
                    });

            this.instance.age_range_assignments_ids.push(new BookingAgeRangeAssignment(new_range_assignment.id));
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async oneditParticipantsOptions() {
        const dialogRef = this.dialog.open(BookingServicesBookingGroupDialogParticipantsOptionsComponent, {
            width: '33vw',
            data: {
                has_person_with_disability: this.instance.has_person_with_disability,
                person_disability_description: this.instance.person_disability_description
            }
        });

        dialogRef.afterClosed().subscribe(async (result) => {
            if(result) {
                if(this.instance.has_person_with_disability != result.has_person_with_disability || this.instance.person_disability_description != result.person_disability_description) {
                    this.loading = true;

                    try {
                        await this.api.update('sale\\booking\\BookingLineGroup', [this.instance.id], {
                            has_person_with_disability: result.has_person_with_disability,
                            person_disability_description: result.person_disability_description
                        });

                        // relay change to parent component
                        this.updated.emit();
                    }
                    catch(response) {
                        this.api.errorFeedback(response);
                    }

                    this.loading = false;
                }
            }
        });
    }

    public async ondeleteAgeRange(age_range_assignment_id:number) {
        this.loading = true;
        setTimeout( async () => {
            try {
                await this.api.fetch('?do=sale_booking_update-sojourn-agerange-remove', {
                        id: this.instance.id,
                        age_range_assignment_id: age_range_assignment_id,
                    });
                this.instance.age_range_assignments_ids.splice(this.instance.age_range_assignments_ids.findIndex((e:any) => e.id == age_range_assignment_id),1);
                // relay to parent
                this.updated.emit();
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
            this.loading = false;
        });
    }

    public async onchangeSojournType(event:any) {
        this.vm.sojourn_type.value = event.value;
        // update model
        try {
            await this.api.update(this.instance.entity, [this.instance.id], {sojourn_type_id: (event.value=='GA')?1:2});
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async onchangeRateClass(event:any) {
        console.log('BookingEditCustomerComponent::rateClassChange', event)

        // from MatAutocomplete
        let rate_class = event.option.value;
        if(rate_class && rate_class.hasOwnProperty('id') && rate_class.id) {
            this.vm.rate_class.name = rate_class.name + ' - ' + rate_class.description;
            try {
                await this.api.update(this.instance.entity, [this.instance.id], {rate_class_id: rate_class.id});
                // relay change to parent component
                this.updated.emit();
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
        }
    }

    private rateClassInputChange(event:any) {
        this.vm.rate_class.inputClue.next(event.target.value);
    }

    private rateClassFocus() {
        this.vm.rate_class.inputClue.next("");
    }

    private rateClassDisplay(rate_class:any): string {
        return rate_class ? (rate_class.name + ' - ' + rate_class.description): '';
    }

    private rateClassReset() {
        setTimeout( () => {
            this.vm.rate_class.name = '';
        }, 100);
    }

    private rateClassRestore() {
        if(Object.keys(this.instance.rate_class_id).length > 0) {
            this.vm.rate_class.name = this.instance.rate_class_id.name + ' - ' + this.instance.rate_class_id.description;
        }
        else {
            this.vm.rate_class.name = '';
        }
    }

    private lineDrop(event:CdkDragDrop<any>) {
        moveItemInArray(this.instance.booking_lines_ids, event.previousIndex, event.currentIndex);
        for(let i = Math.min(event.previousIndex, event.currentIndex), n = Math.max(event.previousIndex, event.currentIndex); i <= n; ++i) {
            this.api.update((new BookingLine()).entity, [this.instance.booking_lines_ids[i].id], {order: i+1})
            .catch(response => this.api.errorFeedback(response));
        }
    }

    private async filterPacks(name: string) {
        let filtered:any[] = [];
        try {
            let domain = [
                ['is_pack', '=', true]
            ];

            if(name && name.length) {
                domain.push(['name', 'ilike', '%'+name+'%']);
            }

            const data:any[] = await this.api.fetch('?get=sale_catalog_product_collect', {
                    center_id: this.booking.center_id.id,
                    domain: JSON.stringify(domain),
                    date_from: this.booking.date_from.toISOString(),
                    date_to: this.booking.date_to.toISOString()
                });
            filtered = data;
        }
        catch(response) {
            console.log(response);
        }
        return filtered;
    }

    public onclickGroupSummary() {
        this.groupSummaryOpen = true;
    }

    public onclickGroupDates() {
        this.groupDatesOpen = true;
    }

    public async selectedGroupSummaryProduct(product:any) {
        let prev_product_name = this.instance.name;
        // immediate view update (before refresh)
        this.groupSummaryOpen = false;
        this.instance.name = product.name;
        this.loadStart.emit();

        this.api.fetch('/?do=sale_booking_update-sojourn-product', {
            id: this.instance.id,
            product_id: product.id
        })
        .then( () => {
            this.loadEnd.emit();
            // relay change to parent component
            this.updated.emit();
        })
        .catch(response => {
            // rollback
            this.instance.name = prev_product_name;
            this.loadEnd.emit();
            this.api.errorFeedback(response);
        });
    }

    public onblurGroupSummarySelect() {
        this.groupSummaryOpen = false;
    }

    public onclickGroupType() {
        this.groupTypeOpen = true;
    }

    public onblurGroupType() {
        this.groupTypeOpen = false;
    }

    public onchangeGroupType(value: any) {
        this.groupTypeOpen = false;

        let prev_group_type = this.instance.group_type;

        this.instance.group_type = value;
        this.loading = true;

        setTimeout( async () => {
            try {
                await this.api.fetch('?do=sale_booking_update-sojourn-type', {id: this.instance.id, group_type: this.instance.group_type});
                // relay change to parent component
                this.updated.emit();
            }
            catch(response) {
                // rollback
                this.instance.group_type = prev_group_type;
                this.api.errorFeedback(response);
            }
            this.loading = false;
        });
    }

    public onclickGroupNbPers() {
        if(this.instance.age_range_assignments_ids?.length <= 1) {
            this.groupNbPersOpen = true;
        }
    }

    public onblurGroupNbPers() {
        this.groupNbPersOpen = false;
        this.onchangeNbPers();
        // queue view update before refresh
        setTimeout( () => {this.instance.nb_pers = this.vm.participants_count.formControl.value;} );
    }

    public async onclickCreateConsumptions() {

        const dialog = this.dialog.open(SbDialogConfirmDialog, {
            width: '33vw',
            data: {
                title: "Génération des consommations",
                message: "Ceci générera les Consommations supplémentaires pour les services planifiables présents dans ce groupe de suppléments. \
                Cette action est irréversible et ne peut être effectuée qu'une seule fois. Si le groupe de suppléments est en cours de création, terminez d'abord la configuration avant d'effectuer cette action. \
                <br /><br />Confirmer cette action ?",
                yes: 'Oui',
                no: 'Non'
            }
        });

        try {
            await new Promise( async(resolve, reject) => {
                dialog.afterClosed().subscribe( async (result) => (result) ? resolve(true) : reject() );
            });
            this.action_in_progress = true;
            try {
                await this.api.call('/?do=sale_booking_sojourn_create-extra-consumptions', {
                    id: this.instance.id
                });
                this.action_in_progress = false;
                this.mode = 'view';
                // snack OK
                this.updated.emit();
            }
            catch(response) {
                this.api.errorFeedback(response);
                this.action_in_progress = false;
            }

        }
        catch(error) {
            // user discarded the dialog (selected 'no')
        }

    }

    public getDayOfWeek(date:Date) {
        const days_of_week = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
        return days_of_week[ date.getDay() ];
    }

    public onCloseActivity(activityId: number) {
        const activityIdIndex = this.openedActivityIds.indexOf(activityId);
        if(activityIdIndex !== undefined) {
            this.openedActivityIds.splice(activityIdIndex, 1);
        }
    }

    public onOpenActivity(activityId: number) {
        this.openedActivityIds.push(activityId);
    }
}
