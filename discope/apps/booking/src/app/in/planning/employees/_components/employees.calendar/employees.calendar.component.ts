import { Component, ChangeDetectionStrategy, ChangeDetectorRef, Output, EventEmitter, ViewChild, OnInit, OnChanges, ViewChildren, QueryList, ElementRef, AfterViewChecked, Input, SimpleChanges } from '@angular/core';

import { ChangeReservationArg } from 'src/app/model/changereservationarg';
import { HeaderDays } from 'src/app/model/headerdays';


import { ApiService, EnvService } from 'sb-shared-lib';
import { PlanningEmployeesCalendarParamService } from '../../_services/employees.calendar.param.service';
import { MatSnackBar } from '@angular/material/snack-bar';

import { CdkDragDrop } from '@angular/cdk/drag-drop';

class Partner {
    constructor(
        public id: number = 0,
        public name: string = '',
        public relationship: 'employee'|'provider' = 'employee',
        public is_active: boolean = true
    ) {}
}

class Employee extends Partner {
    constructor(
        public id: number = 0,
        public name: string = '',
        public is_active: boolean = true,
        public activity_product_models_ids: any[] = []
    ) {
        super(id, name, 'employee', is_active);
    }
}

class Provider extends Partner {
    constructor(
        public id: number = 0,
        public name: string = '',
        public is_active: boolean = true
    ) {
        super(id, name, 'provider');
    }
}

export class ProductModelCategory {
    constructor(
        public id: number = 0,
        public name: string = ''
    ) {}
}

export class ProductModel {
    constructor(
        public id: number = 0,
        public name: string = '',
        public categories_ids: number[] = [],
        public has_transport_required: boolean = false
    ) {}
}

@Component({
    selector: 'planning-employees-calendar',
    templateUrl: './employees.calendar.component.html',
    styleUrls: ['./employees.calendar.component.scss'],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class PlanningEmployeesCalendarComponent implements OnInit, OnChanges, AfterViewChecked {
    @Input() rowsHeight: number;
    @Output() filters = new EventEmitter<ChangeReservationArg>();
    @Output() showBooking = new EventEmitter();
    @Output() showCamp = new EventEmitter();
    @Output() showPartner = new EventEmitter();
    @Output() showPartnerEvent = new EventEmitter();

    @Output() openLegendDialog = new EventEmitter();
    @Output() openPrefDialog = new EventEmitter();
    @Output() fullScreen = new EventEmitter();

    // attach DOM element to compute the cells width
    @ViewChild('calTable') calTable: any;
    @ViewChild('actTable') actTable: any;
    @ViewChild('calTableRefColumn') calTableRefColumn: any;
    @ViewChild('selector') selector: any;

    @ViewChildren("calTableHeadCells") calTableHeadCells: QueryList<ElementRef>;

    public loading: boolean = false;
    public headers: any;

    public headerdays: HeaderDays;

    public tableRect: DOMRect;
    public cellsWidth: number;

    public activities: any = [];
    public partners: any = [];
    public holidays: any = [];
    // count of rental units taken under account (not necessarily equal to `rental_units.length`)
    public count_rental_units: number = 0;

    public hovered_activity: any;
    private hoveredActivityTimeout: any = null;

    public hovered_partner: any;
    public hovered_holidays: any;

    public hover_row_index = -1;

    // selection of contiguous cells for creating new assignments
    public selection =  {
        is_active: false,
        left: 0,
        top: 0,
        width: 0,
        height: 0,
        cell_from: {
            left: 0,
            width: 0,
            employee: {},
            date: {}
        },
        cell_to: {
            date: new Date()
        }
    };

    public currentDraggedActivity: any = null;

    private mousedownTimeout: any;
    private environment: any;

    // duration history as hint for refreshing cell width
    private previous_duration: number;

    public emptyEmployee = new Employee();

    public productModelCategories: ProductModelCategory[] = [];
    public productModels: ProductModel[] = [];

    constructor(
        private params: PlanningEmployeesCalendarParamService,
        private api: ApiService,
        private env: EnvService,
        private snack: MatSnackBar,
        private elementRef: ElementRef,
        private cd: ChangeDetectorRef
    ) {
        this.headers = {};
        this.partners = [];
        this.previous_duration = 0;
    }

    public ngOnChanges(changes: SimpleChanges): void {
        if(changes.rowsHeight)     {
            this.elementRef.nativeElement.style.setProperty('--rows_height', this.rowsHeight + 'px');
        }
     }

    public async ngOnInit() {
        this.params.getObservable().subscribe( () => {
            console.log('PlanningEmployeesCalendarComponent cal params change', this.params);
            this.onRefresh();
        });

        this.elementRef.nativeElement.style.setProperty('--rows_height', this.rowsHeight + 'px');

        this.productModelCategories = [
            { id: 0, name: 'TOUTES' },
            ...await this.api.collect(
                'sale\\catalog\\Category',
                [],
                Object.getOwnPropertyNames(new ProductModelCategory()),
                'name', 'asc', 0, 500
            )
        ];

        this.productModels = await this.api.collect(
            'sale\\catalog\\ProductModel',
            [['can_sell', '=', true], ['is_activity', '=', true]],
            Object.getOwnPropertyNames(new ProductModel()),
            'name', 'asc', 0, 500
        );

        this.environment = await this.env.getEnv();
    }

    /**
     * After refreshing the view with new content, adapt header and relay new cell_width, if changed
     */
    public async ngAfterViewChecked() {

        this.tableRect = this.calTable?.nativeElement.getBoundingClientRect();

        if(this.calTableHeadCells) {
            for(let cell of this.calTableHeadCells) {
                this.cellsWidth = cell.nativeElement.offsetWidth;
                break;
            }
        }


        // make sure ngOnChanges is triggered on sub-components
        this.cd.detectChanges();
    }

    public onRefresh(full: boolean = true) {
        console.log('onrefresh');

        if(this.currentDraggedActivity) {
            console.log('skip refresh because moving activity');
            return;
        }

        this.cd.detectChanges();

        if(full) {
            this.loading = true;

            // refresh the view, then run onchange
            setTimeout( async () => {
                await this.onFiltersChange();

                this.cd.reattach();
                this.loading = false;
                this.cd.detectChanges();

            }, 1000);
        }
    }

    private calcDateIndex(day: Date): string {
        let timestamp = day.getTime();
        let offset = day.getTimezoneOffset()*60*1000;
        let moment = new Date(timestamp-offset);
        return moment.toISOString().substring(0, 10);
    }

    public isWeekEnd(day:Date) {
        return (day.getDay() == 0 || day.getDay() == 6);
    }

    public hasActivity(partner: Partner, day_index: string, time_slot: string, ignore_partner_events = false): boolean {
        const activities = this.activities[partner.id]?.[day_index]?.[time_slot] ?? [];
        if(!ignore_partner_events) {
            return activities.length > 0;
        }

        for(let activity of activities) {
            if(!activity?.is_partner_event) {
                return true;
            }
        }
        return false;
    }

    public getActivities(partner: Partner, day: Date, time_slot: string): any {
        if(this.activities[partner.id] ?? false) {
            let date_index = this.calcDateIndex(day);
            return this.activities[partner.id]?.[date_index]?.[time_slot] ?? [];
        }
        return [];
    }

    public getDescription(activity: any): string {
        if(activity.booking_id) {
            let group_details = `<dt>Groupe ${activity.group_num}`;
            if(activity.age_range_assignments_ids.length === 1) {
                const assign = activity.age_range_assignments_ids[0];
                group_details += `, ${assign.qty} personne${assign.qty > 1 ? 's' : ''} (${assign.age_from} - ${assign.age_to})</dt>`;
            }
            else if(activity.age_range_assignments_ids.length > 1) {
                group_details += ':</dt>';
                for(let assign of activity.age_range_assignments_ids) {
                    group_details += `<dd>${assign.qty} personne${assign.qty > 1 ? 's' : ''} (${assign.age_from} - ${assign.age_to})</dd>`;
                }
            }

            return '<dl>' +
                `<dt>${activity.customer_id.name}</dt>` +
                (activity.partner_identity_id?.address_city ? `<dt>${activity.partner_identity_id?.address_city}</dt>` : '') +
                group_details +
                `<dt>Handicap : <b>${activity.booking_line_group_id.has_person_with_disability ? 'oui' : 'non'}</b></dt>` +
                `<dt>Séjour du ${activity.booking_id.date_from} au ${activity.booking_id.date_to}</dt>` +
                `<dt>${activity.booking_id.nb_pers} personnes</dt>` +
                `<br />` +
                `<dt>Activité ${activity.name} <b>${activity.counter}/${activity.counter_total}</b></dt>` +
                '</dl>';
        }

        if(activity.camp_id) {
            return '<dl>' +
                `<dt>${activity.camp_id.short_name}</dt>` +
                `<dt>Groupe ${activity.group_num}, ${activity.camp_id.enrollments_qty} personne${activity.camp_id.enrollments_qty > 1 ? 's' : ''} (${activity.camp_id.min_age} - ${activity.camp_id.max_age})</dt>` +
                `<dt>Camp du ${activity.camp_id.date_from} au ${activity.camp_id.date_to}</dt>` +
                `<br />` +
                `<dt>Activité ${activity.name} <b>${activity.counter}/${activity.counter_total}</b></dt>` +
                '</dl>';
        }

        return `<dt>${activity.name}</dt>` +
            `<br />` +
            (activity.description ? `<dt>${activity.description}</dt>` : '');
    }

    private async onFiltersChange() {
        this.createHeaderDays();

        try {
            const employees_domain = [
                ['relationship', '=', 'employee'],
                ['id', 'in', this.params.partners_ids]
            ];

            const employees = await this.api.collect(
                'hr\\employee\\Employee',
                employees_domain,
                Object.getOwnPropertyNames(new Employee()),
                'name', 'asc', 0, 500
            );

            const providers_domain = [
                ['relationship', '=', 'provider'],
                ['id', 'in', this.params.partners_ids]
            ];

            const providers = await this.api.collect(
                'sale\\provider\\Provider',
                providers_domain,
                Object.getOwnPropertyNames(new Provider()),
                'name', 'asc', 0, 500
            );

            this.partners = [...employees, ...providers];
        }
        catch(response) {
            console.warn('unable to fetch partners', response);
        }

        try {
            this.activities = await this.api.fetch('?get=sale_booking_activity_map', {
                // #memo - all dates are considered UTC
                date_from: this.calcDateIndex(this.params.date_from),
                date_to: this.calcDateIndex(this.params.date_to),
                partners_ids: JSON.stringify(this.params.partners_ids),
                product_model_ids: JSON.stringify(this.params.product_model_ids)
            });
        }
        catch(response: any ) {
            console.warn('unable to fetch activities', response);
            // if a 403 response is received, we assume that the user is not identified: redirect to /auth
            if(response.status == 403) {
                window.location.href = '/auth';
            }
        }
    }

    /**
     * Recompute content of the header.
     *
     * Convert to following structure :
     *
     * headers.months:
     *    months[]
     *        {
     *            month:
     *            days:
     *        }
     *
     * headers.days: date[]
     */
    private createHeaderDays() {

        if(this.previous_duration != this.params.duration) {
            // temporarily reset cellsWidth to an arbitrary low value
            this.cellsWidth = 12;
        }

        this.previous_duration = this.params.duration;

        // reset headers
        this.headers = {
            months: [],
            days: [],
            days_indexes: []
        };

        let months:any = {};
        // pass-1 assign dates

        for (let i = 0; i < this.params.duration; i++) {
            let date = new Date(this.params.date_from.getTime());
            date.setDate(date.getDate() + i);
            this.headers.days.push(date);
            this.headers.days_indexes.push(this.calcDateIndex(date))
            let month_index = date.getFullYear()*100+date.getMonth();
            if(!months.hasOwnProperty(month_index)) {
                months[month_index] = [];
            }
            months[month_index].push(date);
        }

        // pass-2 assign months (in order)
        let months_array = Object.keys(months).sort( (a: any, b: any) => (a - b) );
        for(let month of months_array) {
            this.headers.months.push(
                {
                    date: months[month][0],
                    month: month,
                    days: months[month]
                }
            );
        }

    }

    /**
     * During a dragging operation, makes the background highlighted if the drop is permitted for the hovered cell.
     * This callback is set on the calTable td cells.
     */
    public onmouseenterTableCell(event: Event | MouseEvent, index: number, employee: Employee, date_index: string, time_slot: string) {
        this.hover_row_index = index;
        if(this.currentDraggedActivity) {
            const element = event.target as HTMLElement;
            if(this.isDroppable(this.currentDraggedActivity, employee, date_index, time_slot)) {
                element.style.setProperty('background-color', '#ff4081', 'important');
            }
        }
    }

    public onmouseleaveTableCell(event: Event | MouseEvent) {
        this.hover_row_index = -1;
        const element = event.target as HTMLElement;
        element.style.setProperty('background-color', '');
    }

    public onhoverActivity(activity: any) {
        if(this.hoveredActivityTimeout === null && activity) {
            this.hovered_activity = activity;
        }
        else {
            clearTimeout(this.hoveredActivityTimeout);
            this.hoveredActivityTimeout = setTimeout(() => {
                this.hovered_activity = activity;
                this.hoveredActivityTimeout = null;
                this.cd.detectChanges();
            }, 100);
        }
    }

    public onOpenLegendDialog() {
        this.openLegendDialog.emit();
    }

    public onOpenPrefDialog() {
        this.openPrefDialog.emit();
    }

    public onFullScreen() {
        this.fullScreen.emit();
        setTimeout( () => {
            this.tableRect = this.calTable?.nativeElement.getBoundingClientRect();
        }, 500);
    }

    public onSelectedActivity(activity: any) {
        clearTimeout(this.mousedownTimeout);
        if(activity.is_partner_event) {
            this.showPartnerEvent.emit(activity);
        }
        else if(activity.booking_id) {
            this.showBooking.emit(activity);
        }
        else if(activity.camp_id) {
            this.showCamp.emit(activity);
        }
    }

    public onSelectedPartner(partner: any) {
        clearTimeout(this.mousedownTimeout);
        this.showPartner.emit(partner);
    }

    public onhoverDay(employee: any, day:Date) {
        this.hovered_partner = employee;

        if(day) {
            let date_index:string = this.calcDateIndex(day);
            if(this.holidays.hasOwnProperty(date_index) && this.holidays[date_index].length) {
                this.hovered_holidays = this.holidays[date_index];
            }
        }
        else {
            this.hovered_holidays = undefined;
        }
    }

    public onhoverPartner(employee: any) {
        this.hovered_partner = employee;
    }

    public preventDrag($event: any = null) {
        if($event && typeof $event.preventDefault === 'function') {
            $event.preventDefault();
        }
        return false;
    }

    private isDroppable(activity: any, employee: Employee, date_index: string, time_slot: string) {
        if(employee.relationship !== 'employee') {
            return false;
        }

        const activity_date_index = this.calcDateIndex(new Date(activity.activity_date));

        // Check drop and activity moment match
        if(date_index !== activity_date_index || time_slot !== activity.time_slot) {
            return false;
        }

        // Check employee can handle activity
        if(this.environment.hasOwnProperty('sale.features.employee.activity_filter') && this.environment['sale.features.employee.activity_filter']) {
            if(!employee.activity_product_models_ids.map(id => +id).includes(activity.product_model_id.id)) {
                return false;
            }
        }

        // Check that the employee hasn't been assigned an activity yet
        if(this.hasActivity(employee, date_index, time_slot, true)) {
            return false;
        }
        return true;
    }

    public onDragStart(activity: any) {
        this.currentDraggedActivity = activity;
    }

    public onDragEnd() {
        // leave a delay to allow handling of onDrop and isDroppable
        setTimeout( () => {
            this.currentDraggedActivity = null;
        }, 500);
    }

    public async onDropUnassign(event: Event | CdkDragDrop<any, any>) {
        if(this.currentDraggedActivity) {
            const dropEvent = event as CdkDragDrop<any, any>;
            const element = dropEvent.container.element.nativeElement as HTMLElement;
            console.log('dropped', this.currentDraggedActivity);

            let time_slot = this.currentDraggedActivity.time_slot;
            let date_index = this.calcDateIndex(new Date(this.currentDraggedActivity.activity_date));


            // #todo - (?) tenir compte du type (event_type)
            let old_employee_id = this.currentDraggedActivity.employee_id ?? 0;

            // remove from this.activities[0][date_index][time_slot]
            this.activities[old_employee_id][date_index][time_slot] = this.activities[old_employee_id][date_index][time_slot].filter( (activity: any) => activity.id !== this.currentDraggedActivity.id);

            // add to unassigned activities
            if(!(this.activities[0] ?? false)) {
                this.activities[0] = {};
            }
            if(!(this.activities[0][date_index] ?? false)) {
                this.activities[0][date_index] = {};
            }
            if(!(this.activities[0][date_index][time_slot] ?? false)) {
                this.activities[0][date_index][time_slot] = [];
            }

            this.currentDraggedActivity.partner_id = null;
            this.currentDraggedActivity.employee_id = null;
            this.activities[0][date_index][time_slot].push(this.currentDraggedActivity);

            // update back-end
            try {
                await this.api.call('?do=model_update', {
                    entity: 'sale\\booking\\BookingActivity',
                    id: this.currentDraggedActivity.id,
                    fields: {
                        employee_id: null
                    }
                });

                this.onRefresh(false);
            }
            catch(response) {
                this.api.errorFeedback(response);
            }

            this.currentDraggedActivity = null;
        }
    }

    public async onDrop(event: Event | CdkDragDrop<any, any>, index: number, employee: Employee, date_index: string, time_slot: string) {
        if(this.currentDraggedActivity) {
            if(!this.isDroppable(this.currentDraggedActivity, employee, date_index, time_slot)) {
                if(employee.relationship !== 'employee') {
                    this.snack.open('Cette activité ne peut pas être assignée à un prestataire.', 'ERREUR');
                }
                else if(this.currentDraggedActivity.employee_id !== employee.id) {
                    this.snack.open('Cette activité ne peut pas être assignée à cet animateur ou à cette plage horaire.', 'ERREUR');
                }
            }
            else {
                const dropEvent = event as CdkDragDrop<any, any>;

                const element = dropEvent.container.element.nativeElement as HTMLElement;
                element.style.setProperty('background-color', '');

                // #todo - (?) tenir compte du type (event_type)
                let old_employee_id = this.currentDraggedActivity.employee_id ?? 0;

                // remove from this.activities[0][date_index][time_slot]
                this.activities[old_employee_id][date_index][time_slot] = this.activities[old_employee_id][date_index][time_slot].filter( (activity: any) => activity.id !== this.currentDraggedActivity.id);

                // add to this.activities[employee.id][date_index][time_slot]
                if(!(this.activities[employee.id] ?? false)) {
                    this.activities[employee.id] = {};
                }
                if(!(this.activities[employee.id][date_index] ?? false)) {
                    this.activities[employee.id][date_index] = {};
                }
                if(!(this.activities[employee.id][date_index][time_slot] ?? false)) {
                    this.activities[employee.id][date_index][time_slot] = [];
                }

                this.currentDraggedActivity.partner_id = employee;
                this.currentDraggedActivity.employee_id = employee.id;
                if(this.currentDraggedActivity?.is_partner_event) {
                    this.activities[employee.id][date_index][time_slot].push(this.currentDraggedActivity);
                }
                else {
                    this.activities[employee.id][date_index][time_slot].unshift(this.currentDraggedActivity);
                }

                // this.headers.days = this.headers.days.slice();

                // update back-end
                try {
                    await this.api.call('?do=model_update', {
                        entity: 'sale\\booking\\BookingActivity',
                        id: this.currentDraggedActivity.id,
                        fields: {
                            employee_id: employee.id
                        }
                    });

                    this.onRefresh(false);
                }
                catch(response) {
                    this.api.errorFeedback(response);

                    this.activities[employee.id][date_index][time_slot] = this.activities[employee.id][date_index][time_slot].filter( (activity: any) => activity.id !== this.currentDraggedActivity.id);
                    this.activities[old_employee_id][date_index][time_slot].unshift(this.currentDraggedActivity);
                }
            }
            this.currentDraggedActivity = null;
        }

    }

    public trackByActivity(index: number, activity: any): string {
        return activity.id; // Assurez-vous que chaque activité a un ID unique
    }

    public getProductModelName(productModelId: string) {
        const productModel = this.productModels.find(p => p.id === +productModelId);

        return productModel?.name ?? '';
    }
}
