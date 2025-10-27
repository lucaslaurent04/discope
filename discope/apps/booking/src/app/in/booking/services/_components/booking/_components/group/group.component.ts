import { Component, OnInit, AfterViewInit, Input, Output, EventEmitter, ChangeDetectorRef, ViewChildren, QueryList, ViewChild, OnChanges } from '@angular/core';
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
import { from, Observable, ReplaySubject } from 'rxjs';
import { debounceTime, filter, map, switchMap } from 'rxjs/operators';
import { BookingMealPref } from '../../_models/booking_mealpref.model';
import { BookingAgeRangeAssignment } from '../../_models/booking_agerange_assignment.model';
import { MatAutocomplete } from '@angular/material/autocomplete';
import { MatDialog } from '@angular/material/dialog';
import { BookingActivityDay } from './_components/day-activities/day-activities.component';
import { BookingActivity } from '../../_models/booking_activity.model';

import { BookingMealDay } from './_components/day-meals/day-meals.component';
import { BookingMeal } from '../../_models/booking_meal.model';

import { BookedServicesDisplaySettings, RentalUnitsSettings } from '../../../../services.component';

import { BookingServicesBookingGroupDialogParticipantsOptionsComponent } from './_components/dialog-participants-options/dialog-participants-options.component';
import { BookingServicesBookingGroupDialogMealsOptionsComponent } from './_components/dialog-meals-options/dialog-meals-options.component';


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
export class BookingServicesBookingGroupComponent
    extends TreeComponent<BookingLineGroup, BookingLineGroupComponentsMap>
    implements OnInit, OnChanges, AfterViewInit  {

    // server-model relayed by parent
    @Input() set model(values: any) { this._model = values; this.is_update_pending = true; }
    @Input() booking: Booking;
    @Input() timeSlots: { id: number, name: string, code: 'B'|'AM'|'L'|'PM'|'D'|'EV' }[];
    @Input() sojournTypes: { id: number, name: 'GA'|'GG' }[] = [];
    @Input() mealTypes: { id: number, name: string, code: string }[] = [];
    @Input() mealPlaces: { id: number, name: string, code: string }[] = [];
    @Input() displaySettings: BookedServicesDisplaySettings;
    @Input() rentalUnitsSettings: RentalUnitsSettings;

    @Output() loadStart = new EventEmitter();
    // #deprecated
    @Output() loadEnd = new EventEmitter();
    @Output() updated = new EventEmitter();
    @Output() toggle  = new EventEmitter();

    private _model: any;
    // flag signaling that an update was requested while the component was not ready
    private is_update_pending: boolean = false;

    public bookingActivitiesDays: BookingActivityDay[];
    public bookingMealsDays: BookingMealDay[];

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

    // By convention, `ready` is set to true once the component has completed its
    // initial lifecycle phase: constructor + first ngOnChanges (if any) + ngOnInit + ngAfterViewInit,
    // At this point, the view has been initialized and all @Input values are available and the component is rendered in the DOM.
    public ready: boolean = false;

    // #memo - not for displaying the loader but for knowing if a change is in progress
    public loading: boolean = false;

    private packRequestCounter = 0;
    private rateClassRequestCounter = 0;

    public vm: vmModel;

    constructor(
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

    public ngOnChanges() {
        if(!this.ready) {
            return;
        }

        if(this.is_update_pending) {
            this.update(this._model);
        }
    }

    public ngAfterViewInit() {
        console.debug('BookingServicesBookingGroupComponent::ngAfterViewInit');
        // init local componentsMap
        this.componentsMap = {
            booking_lines_ids: this.bookingServicesBookingLineComponents,
            meal_preferences_ids: this.bookingServicesBookingGroupMealPrefComponents,
            age_range_assignments_ids: this.bookingServicesBookingGroupAgeRangeComponents,
            sojourn_product_models_ids: this.bookingServicesBookingGroupAccomodationComponents
        } as BookingLineGroupComponentsMap;

        this.ready = true;

        if(this.is_update_pending) {
            this.update(this._model);
        }

    }

    public ngOnInit() {
        console.debug('BookingServicesBookingGroupComponent::ngOnInit');
        if(this.booking.status == 'quote' || (this.instance.is_extra && !this.instance.has_consumptions)) {
            this.mode = 'edit';
        }

        this.auth.getObservable().subscribe( async (user: UserClass) => {
            this.user = user;
        });

        this.vm.pack.filteredList = this.vm.pack.inputClue.pipe(
            debounceTime(300),
            map( (value:any) => (typeof value === 'string' ? value : (value == null)?'':value.name) ),
            switchMap( (name: string) => {
                const currentRequest = ++this.packRequestCounter;
                return from(this.filterPacks(name)).pipe(
                    filter(() => currentRequest === this.packRequestCounter)
                )
            })
        );

        this.vm.rate_class.filteredList = this.vm.rate_class.inputClue.pipe(
            debounceTime(300),
            map( (value:any) => (typeof value === 'string' ? value : (value == null)?'':value.name) ),
            switchMap( (name: string) => {
                const currentRequest = ++this.rateClassRequestCounter;
                return from(this.filterRateClasses(name)).pipe(
                    filter(() => currentRequest === this.rateClassRequestCounter)
                )
            })
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

    }

    private initBookingActivitiesDays() {

        this.bookingActivitiesDays = [];
        let date = new Date(this.instance.date_from);
        const dateTo = new Date(this.instance.date_to);
        while(date <= dateTo) {
            const bookingActivityDay: BookingActivityDay = {
                date: new Date(date),
                AM: null,
                PM: null,
                EV: null
            };

            for(let bookingActivity of this.instance.booking_activities_ids as BookingActivity[]) {
                let activityDate = new Date(bookingActivity.activity_date).toISOString().split('T')[0];
                if(activityDate !== date.toISOString().split('T')[0]) {
                    continue;
                }

                let activityBookingLine: BookingLine | undefined = this.instance.booking_lines_ids.find(
                    (bookingLine: BookingLine) => bookingLine.id === bookingActivity.activity_booking_line_id
                );

                if(activityBookingLine && !activityBookingLine.service_date) {
                    continue;
                }

                const timeSlot = this.timeSlots.find((timeSlot: any) => timeSlot.id === bookingActivity.time_slot_id);
                if(!timeSlot || !['AM', 'PM', 'EV'].includes(timeSlot.code)) {
                    continue;
                }

                bookingActivityDay[timeSlot.code as 'AM'|'PM'|'EV'] = {
                    ...bookingActivity,
                    entity: 'sale\\booking\\BookingActivity',
                    activity_booking_line_id: activityBookingLine ?? null,
                    transports_booking_lines_ids: this.instance.booking_lines_ids.filter(
                        (bookingLine: BookingLine) => bookingActivity.transports_booking_lines_ids.map(Number).includes(bookingLine.id)
                    ),
                    supplies_booking_lines_ids: this.instance.booking_lines_ids.filter(
                        (bookingLine: BookingLine) => bookingActivity.supplies_booking_lines_ids.map(Number).includes(bookingLine.id)
                    )
                };
            }

            this.bookingActivitiesDays.push(bookingActivityDay);

            date.setDate(date.getDate() + 1);
        }
    }

    private initBookingMealsDays() {
        this.bookingMealsDays = [];
        let date = new Date(this.instance.date_from);
        const dateTo = new Date(this.instance.date_to);
        while(date <= dateTo) {
            const bookingMealDay: BookingMealDay = {
                date: new Date(date),
                B: null,
                AM: null,
                L: null,
                PM: null,
                D: null
            };

            for(let bookingMeal of this.instance.booking_meals_ids as BookingMeal[]) {
                let mealDate = new Date(bookingMeal.date).toISOString().split('T')[0];
                if(mealDate !== date.toISOString().split('T')[0]) {
                    continue;
                }

                const timeSlot = this.timeSlots.find((timeSlot: any) => timeSlot.id === bookingMeal.time_slot_id);
                if(!timeSlot || !['B', 'AM', 'L', 'PM', 'D'].includes(timeSlot.code)) {
                    continue;
                }

                bookingMealDay[timeSlot.code as 'B'|'AM'|'L'|'PM'|'D'] = bookingMeal;
            }

            this.bookingMealsDays.push(bookingMealDay);

            date.setDate(date.getDate() + 1);
        }
    }

    public async oneditMealsOptions() {
        const dialogRef = this.dialog.open(BookingServicesBookingGroupDialogMealsOptionsComponent, {
            width: '33vw',
            data: {
                meal_prefs_description: this.instance.meal_prefs_description
            }
        });

        console.log('INSTANCE', this.instance);

        dialogRef.afterClosed().subscribe(async (result) => {
            if(result) {
                if(this.instance.meal_prefs_description != result.meal_prefs_description) {

                    try {
                        this.loading = true;
                        await this.api.update('sale\\booking\\BookingLineGroup', [this.instance.id], {
                            meal_prefs_description: result.meal_prefs_description
                        });

                        // relay change to parent component
                        this.updated.emit();
                    }
                    catch(response) {
                        this.api.errorFeedback(response);
                    }
                    finally {
                        this.loading = false;
                    }
                }
            }
        });
    }

    public update(values: any) {
        console.debug('BookingServicesBookingGroupComponent::update');
        this.is_update_pending = false;
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

        // #memo - do not manually update child TreeComponents since it would break the silent refresh performed through super.update()
        // this.instance.age_range_assignments_ids = values.age_range_assignments_ids;
        // this.instance.booking_lines_ids = values.booking_lines_ids;

        // force activities/meals update (since it is not done in update() - these are no TreeComponent)
        this.instance.booking_activities_ids = values.booking_activities_ids;
        this.instance.booking_meals_ids = values.booking_meals_ids;

        this.initBookingActivitiesDays();
        this.initBookingMealsDays();

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
        if(this.loading) {
            return;
        }

        // optimistic UI - remove activity
        const activity: BookingActivity = this.instance.booking_activities_ids.find(
            (e: any) => e.id === activity_id
        );

        const timeSlot = this.timeSlots.find((timeSlot: any) => timeSlot.id === activity?.time_slot_id);
        if(!timeSlot || !['AM', 'PM', 'EV'].includes(timeSlot.code)) {
            return;
        }

        this.bookingActivitiesDays.forEach( (bookingActivitiesDay: BookingActivityDay) => {
            const targetActivity: BookingActivity = bookingActivitiesDay[timeSlot.code as 'AM'|'PM'|'EV'];
            if(targetActivity && targetActivity.id == activity_id) {
                bookingActivitiesDay[timeSlot.code as 'AM'|'PM'|'EV'] = null;
            }
        });

        try {
            this.loading = true;
            await this.api.remove('sale\\booking\\BookingActivity', [activity_id]);
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
        finally {
            this.loading = false;
            // #memo - activity might be linked to a booking line, with a price : full reload is necessary
            this.updated.emit();
        }
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

            this.instance.booking_lines_ids = this.instance.booking_lines_ids.filter((l: BookingLine) => l.id !== line_id);

            try {
                this.loading = true;
                await this.api.update(this.instance.entity, [this.instance.id], {booking_lines_ids: [-line_id]});
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
            finally {
                this.loading = false;

                // re-load line
                this.updated.emit();
            }

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
            try {
                this.loading = true;
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
            finally {
                this.loading = false;
            }
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

    public async onchangeDateRange() {
        this.groupDatesOpen = false;

        let start = this.vm.daterange.start.formControl.value;
        let end = this.vm.daterange.end.formControl.value;

        if(!start || !end) {
            return;
        }

        if(typeof start == 'string') {
            start = new Date(start);
        }

        if(typeof end == 'string') {
            end = new Date(end);
        }

        let diff = Math.round((Date.parse(end.toString()) - Date.parse(start.toString())) / (60*60*24*1000));

        if(diff >= 0) {
            this.vm.daterange.nights_count = (diff < 0) ? 0 : diff;
            // relay change to parent component
            if((start.getTime() != this.instance.date_from.getTime() || end.getTime() != this.instance.date_to.getTime())) {
                // make dates UTC @ 00:00:00
                let timestamp, offset_tz;
                timestamp = start.getTime();
                offset_tz = start.getTimezoneOffset()*60*1000;
                let date_from = (new Date(timestamp-offset_tz)).toISOString().substring(0, 10)+'T00:00:00Z';
                timestamp = end.getTime();
                offset_tz = end.getTimezoneOffset()*60*1000;
                let date_to = (new Date(timestamp-offset_tz)).toISOString().substring(0, 10)+'T00:00:00Z';

                try {
                    this.loading = true;
                    this.loadStart.emit();
                    await this.api.fetch('?do=sale_booking_update-sojourn-dates', {
                            id: this.instance.id,
                            date_from: date_from,
                            date_to: date_to
                        });
                }
                catch(response) {
                    this.api.errorFeedback(response);
                    // #todo - improve to rollback non-updatable fields only
                }
                finally {
                    this.loading = false;
                    this.updated.emit();
                }

            }
            // update VM values until refresh
            // this.instance.date_from = start;
            // this.instance.date_to = end;
        }
    }

    public async onchangeHasPack(has_pack: any) {
        if(this.instance.has_pack != has_pack) {
            let fields: any = {has_pack: has_pack};

            try {
                this.loading = true;
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
            finally {
                this.loading = false;
            }
        }
    }

    public async onchangePackId(pack: any) {
        if(!this.instance.pack_id || this.instance.pack_id.id != pack.id) {
            this.vm.pack.name = pack.name;

            try {
                this.loading = true;
                this.loadStart.emit();
                await this.api.fetch('?do=sale_booking_update-sojourn-pack-set', {id: this.instance.id, pack_id: pack.id});
                // relay change to parent component
                this.updated.emit();
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
            finally {
                this.loading = false;
            }
        }
    }

    public async onchangeIsLocked(locked: any) {
        if(this.instance.is_locked != locked) {
            this.vm.pack.is_locked = locked;
            try {
                this.loading = true;
                await this.api.update(this.instance.entity, [this.instance.id], {is_locked: locked});
                // relay change to parent component
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
            finally {
                this.updated.emit();
                this.loading = false;
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

                    try {
                        this.loading = true;
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
                    finally {
                        this.loading = false;
                    }
                }
            }
        });
    }

    public async ondeleteAgeRange(age_range_assignment_id:number) {

        try {
            this.loading = true;
            this.loadStart.emit();
            await this.api.fetch('?do=sale_booking_update-sojourn-agerange-remove', {
                    id: this.instance.id,
                    age_range_assignment_id: age_range_assignment_id,
                });
            this.instance.age_range_assignments_ids.splice(this.instance.age_range_assignments_ids.findIndex((e:any) => e.id == age_range_assignment_id),1);
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
        finally {
            // relay to parent
            this.updated.emit();
            this.loading = false;
        }

    }

    public async onchangeSojournType(event: any) {
        this.vm.sojourn_type.value = event.value;
        // update model
        try {
            this.loading = true;
            await this.api.update(this.instance.entity, [this.instance.id], {sojourn_type_id: (event.value=='GA')?1:2});
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
        finally {
            this.updated.emit();
            this.loading = false;
        }
    }

    public async onchangeRateClass(event:any) {
        console.log('BookingEditCustomerComponent::rateClassChange', event)

        // from MatAutocomplete
        let rate_class = event.option.value;
        if(rate_class && rate_class.hasOwnProperty('id') && rate_class.id) {
            this.vm.rate_class.name = rate_class.name + ' - ' + rate_class.description;
            try {
                this.loading = true;
                await this.api.update(this.instance.entity, [this.instance.id], {rate_class_id: rate_class.id});
            }
            catch(response) {
                this.api.errorFeedback(response);
            }
            finally {
                this.updated.emit();
                this.loading = true;
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
                [
                    ['rate_class_id', '=', this.instance.rate_class_id.id]
                ],
                [
                    ['rate_class_id', 'is', null]
                ]
            ];
            if(name && name.length) {
                domain[0].push(['name', 'ilike', '%'+name+'%']);
                domain[1].push(['name', 'ilike', '%'+name+'%']);
            }

            const data:any[] = await this.api.fetch('?get=sale_catalog_product_collect-pack', {
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
        // apply commit-rollback logic

        // save previous values
        let prev_product_name = this.instance.name;

        // optimistic UI - immediate view update (before refresh)
        this.groupSummaryOpen = false;
        this.instance.name = product.name;

        try {
            this.loading = true;
            await this.api.fetch('/?do=sale_booking_update-sojourn-product', {
                id: this.instance.id,
                product_id: product.id
            });
        }
        catch(response) {
            // rollback
            this.instance.name = prev_product_name;
            this.api.errorFeedback(response);
        }
        finally {
            this.updated.emit();
            this.loading = false;
        }
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

    public async onchangeGroupType(value: any) {
        this.groupTypeOpen = false;

        let prev_group_type = this.instance.group_type;
        this.instance.group_type = value;

        try {
            this.loading = true;
            await this.api.fetch('?do=sale_booking_update-sojourn-type', {id: this.instance.id, group_type: this.instance.group_type});
        }
        catch(response) {
            // rollback
            this.instance.group_type = prev_group_type;
            this.api.errorFeedback(response);
        }
        finally {
            // relay change to parent component
            this.updated.emit();
            this.loading = false;
        }

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
        if(activityIdIndex >= 0) {
            this.openedActivityIds.splice(activityIdIndex, 1);
        }
    }

    public onOpenActivity(activityId: number) {
        this.openedActivityIds.push(activityId);
    }
}
