import { Component, ChangeDetectionStrategy, ChangeDetectorRef, Output, EventEmitter, ViewChild, OnInit, OnChanges, ViewChildren, QueryList, ElementRef, AfterViewInit, Input, SimpleChanges } from '@angular/core';

import { ChangeReservationArg } from 'src/app/model/changereservationarg';
import { HeaderDays } from 'src/app/model/headerdays';


import { ApiService, EnvService } from 'sb-shared-lib';
import { PlanningEmployeesCalendarParamService, Partner, Employee, Provider } from '../../_services/employees.calendar.param.service';
import { MatSnackBar } from '@angular/material/snack-bar';

import { CdkDragDrop } from '@angular/cdk/drag-drop';

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
export class PlanningEmployeesCalendarComponent implements OnInit, OnChanges, AfterViewInit {

    @Input() rowsHeight: number;
    @Input() mapTimeSlot: {[key: string]: {id: number, name: string, code: 'AM'|'PM'|'EV', schedule_from: string, schedule_to: string}};

    @Output() filters = new EventEmitter<ChangeReservationArg>();
    @Output() showActivity = new EventEmitter();
    @Output() showBooking = new EventEmitter();
    @Output() showCamp = new EventEmitter();
    @Output() showPartner = new EventEmitter();
    @Output() showPartnerEvent = new EventEmitter();
    @Output() createPartnerEvent = new EventEmitter();

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
    private hoveredPartnerTimeout: any = null;
    public hovered_activity_partner: any;

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

    // duration history as a hint for refreshing cell width
    private previous_duration: number;

    public emptyEmployee = new Employee();

    public productModelCategories: ProductModelCategory[] = [];
    public productModels: ProductModel[] = [];

    public dropZonePosition: 'left'|'center'|'right' = null;

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
            ...await this.api.fetch('?get=sale_booking_activity_collect-categories', {
                fields: Object.getOwnPropertyNames(new ProductModelCategory()),
                order: 'name',
                sort: 'asc',
                start: 0,
                limit: 500
            })
        ];

        this.productModels = await this.api.collect(
            'sale\\catalog\\ProductModel',
            [['can_sell', '=', true], ['is_activity', '=', true]],
            Object.getOwnPropertyNames(new ProductModel()),
            'name', 'asc', 0, 500
        );

        this.environment = await this.env.getEnv();
    }

    public ngAfterViewInit(): void {
        this.updateTableDimensions();

        const observer = new ResizeObserver(() => {
            this.updateTableDimensions();
            this.cd.markForCheck();
        });

        observer.observe(this.calTable.nativeElement);
    }

    private updateTableDimensions(): void {
        this.tableRect = this.calTable?.nativeElement.getBoundingClientRect();

        if(this.calTableHeadCells) {
            for(let cell of this.calTableHeadCells) {
                this.cellsWidth = cell.nativeElement.offsetWidth;
                break;
            }
        }
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

            }, 100);
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

    public hasActivity(partner: Partner, day_index: string, time_slot: string): boolean {
        const activities = this.activities[partner.id]?.[day_index]?.[time_slot] ?? [];

        for(let activity of activities) {
            if(!activity?.is_partner_event) {
                return true;
            }
        }
        return false;
    }

    public hasExclusiveActivity(employee: Employee, day_index: string, time_slot: string) {
        let activities = this.activities[employee.id]?.[day_index]?.[time_slot] ?? [];

        activities = activities.filter((a: any) => !a.is_partner_event);

        return activities.find((a: any) => a.is_exclusive) !== undefined;
    }

    public hasSpaceBefore(employee: Employee, day: Date, time_slot: 'AM'|'PM'|'EV') {
        let activities = this.getActivities(employee, day, time_slot);
        let timeSlot = this.mapTimeSlot[time_slot];

        return activities.length > 0 && activities[0].schedule_from > timeSlot.schedule_from;
    }

    public hasSpaceAfter(employee: Employee, day: Date, time_slot: 'AM'|'PM'|'EV') {
        let activities = this.getActivities(employee, day, time_slot);
        let timeSlot = this.mapTimeSlot[time_slot];

        return activities.length > 0 && activities[activities.length - 1].schedule_to < timeSlot.schedule_to;
    }

    public getActivities(partner: Partner, day: Date, time_slot: string): any[] {
        if(!this.activities?.[partner.id]) {
            return [];
        }

        let date_index = this.calcDateIndex(day);
        const allActivities = this.activities[partner.id]?.[date_index]?.[time_slot] ?? [];

        return allActivities
            .filter((a: any) => !a.is_partner_event)
            .sort((a: any, b: any) => {
                if(a.schedule_from < b.schedule_from) {
                    return -1;
                }
                if(a.schedule_from > b.schedule_from) {
                    return 1;
                }
                return 0;
            });
    }

    public hasPartnerEvent(partner: Partner, day_index: string, time_slot: string): boolean {
        const activities = this.activities[partner.id]?.[day_index]?.[time_slot] ?? [];

        for(let activity of activities) {
            if(activity?.is_partner_event) {
                return true;
            }
        }
        return false;
    }

    public getPartnerEvents(partner: Partner, day: Date, time_slot: string): any[] {
        if(!this.activities?.[partner.id]) {
            return [];
        }

        let date_index = this.calcDateIndex(day);
        const allActivities = this.activities[partner.id]?.[date_index]?.[time_slot] ?? [];

        return allActivities.filter((a: any) => a.is_partner_event);
    }

    public getActivityDescription(activity: any): string {
        if(activity.booking_id) {
            // booking activity
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
                (activity.booking_line_group_id.has_person_with_disability && activity.booking_line_group_id.person_disability_description && activity.booking_line_group_id.person_disability_description.length > 0 ? activity.booking_line_group_id.person_disability_description : '') +
                `<dt>Séjour du ${activity.booking_id.date_from} au ${activity.booking_id.date_to}</dt>` +
                `<dt>${activity.booking_id.nb_pers} personnes</dt>` +
                `<br>` +
                `<dt>${activity.name} <b>${activity.counter}/${activity.counter_total}</b></dt>` +
                `<br>` +
                `<dt>${activity.schedule_from} - ${activity.schedule_to}</dt>` +
                '</dl>';
        }
        else {
            // camp activity
            return '<dl>' +
                `<dt>${activity.camp_id.short_name}</dt>` +
                `<dt>Groupe ${activity.group_num}, ${activity.camp_id.enrollments_qty} personne${activity.camp_id.enrollments_qty > 1 ? 's' : ''} (${activity.camp_id.min_age} - ${activity.camp_id.max_age})</dt>` +
                `<dt>Camp du ${activity.camp_id.date_from} au ${activity.camp_id.date_to}</dt>` +
                `<br>` +
                `<dt>${activity.name} <b>${activity.counter}/${activity.counter_total}</b></dt>` +
                `<br>` +
                `<dt>${activity.schedule_from} - ${activity.schedule_to}</dt>` +
                '</dl>';
        }
    }

    private humanReadableSchedule(schedule: number) {
        const hours = Math.floor(schedule / 3600);
        const minutes = Math.floor((schedule % 3600) / 60);
        const seconds = schedule % 60;

        return (
            hours.toString().padStart(2, '0') + ':' +
            minutes.toString().padStart(2, '0') + ':' +
            seconds.toString().padStart(2, '0')
        );
    }

    public getPartnerEventDescription(partnerEvent: any): string {
        if(partnerEvent.camp_id) {
            // auto generated partner event because the partner is responsible for the camp group
            return '<dl>' +
                (partnerEvent.camp_id ? `<dt>${partnerEvent.camp_id.short_name}</dt>` : '') +
                (partnerEvent.camp_id ? `<dt>Groupe ${partnerEvent.group_num}, ${partnerEvent.camp_id.enrollments_qty} personne${partnerEvent.camp_id.enrollments_qty > 1 ? 's' : ''} (${partnerEvent.camp_id.min_age} - ${partnerEvent.camp_id.max_age})</dt>` : '') +
                (partnerEvent.camp_id ? `<dt>Camp du ${partnerEvent.camp_id.date_from} au ${partnerEvent.camp_id.date_to}</dt>` : '') +
                (partnerEvent.camp_id ? `<br>` : '') +
                `<dt>${partnerEvent.name}</dt>` +
                `<br>` +
                (partnerEvent.description ? `<dt>${partnerEvent.description}</dt>` : '') +
                '</dl>';
        }
        else {
            // partner event
            return '<dl>' +
                `<dt>${partnerEvent.name}</dt>` +
                `<br>` +
                (partnerEvent.description ? `<dt>${partnerEvent.description}</dt>` : '') +
                '</dl>';
        }
    }

    private async onFiltersChange() {
        this.createHeaderDays();

        this.partners = this.params.selected_partners;

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

        for(let i = 0; i < this.params.duration; i++) {
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
            if(this.isDroppable(this.currentDraggedActivity, employee, date_index, time_slot)) {
                const element = event.target as HTMLElement;
                element.classList.add('cell-droppable');
            }
        }
    }

    public onmouseleaveTableCell(event: Event | MouseEvent) {
        this.hover_row_index = -1;
        const element = event.target as HTMLElement;
        element.classList.remove('cell-droppable');
    }

    public onmouseenterDropZone(position: 'left'|'center'|'right') {
        if(this.currentDraggedActivity && this.currentDraggedActivity.is_exclusive) {
            this.dropZonePosition = 'center';
        }
        else {
            this.dropZonePosition = position;
        }
    }

    public onmouseleaveDropZone() {
        this.dropZonePosition = null;
    }

    public ondoubleclickTableCell(day: Date, partner: any, timeSlotCode: 'AM'|'PM'|'EV') {
        // remove timezone offset
        const eventDate = new Date();
        eventDate.setFullYear(day.getFullYear());
        eventDate.setMonth(day.getMonth());
        eventDate.setDate(day.getDate());

        this.createPartnerEvent.emit({
            eventDate,
            partnerId: partner.id,
            timeSlotCode
        });
    }

    public onhoverActivity(activity: any) {
        if(this.currentDraggedActivity) {
            this.hovered_activity = null;
            this.hoveredActivityTimeout = null;
            return;
        }

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
            // this.showBooking.emit(activity);
            this.showActivity.emit(activity);
        }
        else if(activity.camp_id) {
            // this.showCamp.emit(activity);
            this.showActivity.emit(activity);
        }
    }

    public onSelectedPartner(partner: any) {
        clearTimeout(this.mousedownTimeout);
        this.showPartner.emit(partner);
    }

    public onhoverDay(employee: any, day:Date) {
        this.hovered_activity_partner = employee;

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
        if(this.hoveredPartnerTimeout === null && employee) {
            this.hovered_partner = employee;
            this.hovered_activity_partner = employee;
        }
        else {
            clearTimeout(this.hoveredPartnerTimeout);
            this.hoveredPartnerTimeout = setTimeout(() => {
                this.hovered_partner = employee;
                this.hovered_activity_partner = employee;
                this.hoveredPartnerTimeout = null;
                this.cd.detectChanges();
            }, 100);
        }
    }

    public preventDrag($event: any = null) {
        if($event && typeof $event.preventDefault === 'function') {
            $event.preventDefault();
        }
        return false;
    }

    private isDroppable(activity: any, partner: Partner, date_index: string, time_slot: string) {
        if(activity.has_staff_required) {
            return this.isDroppableEmployee(activity, partner as Employee, date_index, time_slot);
        }
        else {
            return this.isDroppableProvider(activity, partner as Provider, date_index, time_slot);
        }
    }

    private isDroppableEmployee(activity: any, employee: Employee, date_index: string, time_slot: string) {
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

        // Check that activity is not already assigned to this employee
        let activities = this.activities[employee.id]?.[date_index]?.[time_slot] ?? [];
        for(let a of activities) {
            if(a.id === activity.id) {
                return false;
            }
        }

        // Check that the employee hasn't been assigned an exclusive activity yet
        return !((activity.is_exclusive && this.hasActivity(employee, date_index, time_slot)) || this.hasExclusiveActivity(employee, date_index, time_slot));
    }

    private isDroppableProvider(activity: any, provider: Provider, date_index: string, time_slot: string) {
        if(provider.relationship !== 'provider') {
            return false;
        }

        const activity_date_index = this.calcDateIndex(new Date(activity.activity_date));

        // Check drop and activity moment match
        if(date_index !== activity_date_index || time_slot !== activity.time_slot) {
            return false;
        }

        return activity.product_model_id.providers_ids.includes(provider.id);
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

    public async onDrop(event: Event | CdkDragDrop<any, any>, partner: Partner, date_index: string, time_slot: string) {
        if(!this.currentDraggedActivity) {
            return;
        }

        if(!this.isDroppable(this.currentDraggedActivity, partner, date_index, time_slot)) {
            if(partner.relationship === 'employee') {
                if(this.currentDraggedActivity.has_provider) {
                    this.snack.open('Cette activité doit être assignée à un prestataire.');
                }
                else if(this.currentDraggedActivity.employee_id !== partner.id) {
                    this.snack.open('Cette activité ne peut pas être assignée à cet animateur ou à cette plage horaire.', 'ERREUR');
                }
            }
            else {
                if(this.currentDraggedActivity.has_staff_required) {
                    this.snack.open('Cette activité doit être assignée à un employé.');
                }
                else {
                    this.snack.open('Cette activité ne peut pas être assignée à ce prestatire ou à cette plage horaire.', 'ERREUR');
                }
            }

            this.currentDraggedActivity = null;

            return;
        }

        const dropEvent = event as CdkDragDrop<any, any>;

        const element = dropEvent.container.element.nativeElement as HTMLElement;
        element.classList.remove('cell-droppable');

        if(partner.relationship === 'employee') {
            await this.onDropOnEmployee(partner, date_index, time_slot);
        }
        else {
            await this.onDropOnProvider(partner, date_index, time_slot);
        }
    }

    private async onDropOnEmployee(partner: Partner, date_index: string, time_slot: string) {
        // #todo - (?) tenir compte du type (event_type)
        let old_partner_id = this.currentDraggedActivity.partner_id ?? null;
        let old_employee_id = this.currentDraggedActivity.employee_id ?? 0;

        const old_index = this.activities[old_employee_id][date_index][time_slot].findIndex((activity: any) => activity.id === this.currentDraggedActivity.id);

        // remove from this.activities[0][date_index][time_slot]
        this.activities[old_employee_id][date_index][time_slot] = this.activities[old_employee_id][date_index][time_slot].filter((activity: any) => activity.id !== this.currentDraggedActivity.id);

        // add to this.activities[employee.id][date_index][time_slot]
        if(!(this.activities[partner.id] ?? false)) {
            this.activities[partner.id] = {};
        }
        if(!(this.activities[partner.id][date_index] ?? false)) {
            this.activities[partner.id][date_index] = {};
        }
        if(!(this.activities[partner.id][date_index][time_slot] ?? false)) {
            this.activities[partner.id][date_index][time_slot] = [];
        }

        this.currentDraggedActivity.partner_id = partner;
        this.currentDraggedActivity.employee_id = partner.id;

        if(this.dropZonePosition === 'left') {
            this.activities[partner.id][date_index][time_slot].unshift(this.currentDraggedActivity);
        }
        else {
            this.activities[partner.id][date_index][time_slot].push(this.currentDraggedActivity);
        }

        // this.headers.days = this.headers.days.slice();

        // update back-end
        try {
            const employeeActivities = this.activities[partner.id][date_index][time_slot].filter((a: any) => !a.is_partner_event);
            const timeSlot = this.mapTimeSlot[time_slot];
            if(employeeActivities.length === 1 && this.dropZonePosition === 'center') {
                // handle only one activity is assigned to employee's moment
                await this.api.call('?do=model_update', {
                    entity: 'sale\\booking\\BookingActivity',
                    id: this.currentDraggedActivity.id,
                    fields: {
                        employee_id: partner.id,
                        schedule_from: timeSlot.schedule_from,
                        schedule_to: timeSlot.schedule_to
                    }
                });
            }
            else {
                if(employeeActivities.length === 1) {
                    // handle one activity assigned to employee's moment and positioned left/right
                    const scheduleIntervals = this.splitSchedule(timeSlot.schedule_from, timeSlot.schedule_to, 2);
                    const interval = this.dropZonePosition === 'left' ? scheduleIntervals[0] : scheduleIntervals[1];

                    await this.api.call('?do=model_update', {
                        entity: 'sale\\booking\\BookingActivity',
                        id: employeeActivities[0].id,
                        fields: {
                            schedule_from: interval.from,
                            schedule_to: interval.to,
                            employee_id: partner.id
                        }
                    });
                }
                else {
                    // handle multiple activities assigned to employee's moment
                    const scheduleIntervals = this.splitSchedule(timeSlot.schedule_from, timeSlot.schedule_to, employeeActivities.length);

                    let index = 0;
                    for(let activity of employeeActivities) {
                        const interval = scheduleIntervals[index++];

                        const fields: any = {
                            schedule_from: interval.from,
                            schedule_to: interval.to
                        };
                        if(this.currentDraggedActivity.id === activity.id) {
                            fields.employee_id = partner.id
                        }

                        await this.api.call('?do=model_update', {
                            entity: 'sale\\booking\\BookingActivity',
                            id: activity.id,
                            fields
                        });
                    }
                }
            }

            this.currentDraggedActivity = null;

            // full refresh if multiple activities to get them correctly sorted
            this.onRefresh(employeeActivities.length > 1 || this.dropZonePosition !== 'center' || (old_employee_id > 0 && this.activities[old_employee_id][date_index][time_slot].length > 0));
        }
        catch(response) {
            this.api.errorFeedback(response);

            // move the activity to its previous position
            this.activities[partner.id][date_index][time_slot] = this.activities[partner.id][date_index][time_slot].filter((activity: any) => activity.id !== this.currentDraggedActivity.id);
            this.activities[old_employee_id][date_index][time_slot].splice(old_index, 0, this.currentDraggedActivity);

            // reset employee related data (needed because the object wasn't cloned and is modified above)
            this.currentDraggedActivity.partner_id = old_partner_id;
            this.currentDraggedActivity.employee_id = old_employee_id;

            // drag and drop finished
            this.currentDraggedActivity = null;
        }
    }

    private async onDropOnProvider(partner: Partner, date_index: string, time_slot: string) {
        let old_partner_id = this.currentDraggedActivity.partner_id ?? null;
        let old_providers_ids = this.currentDraggedActivity.providers_ids ?? [];

        const old_index = this.activities[old_partner_id?.id ?? 0][date_index][time_slot].findIndex((activity: any) => activity.id === this.currentDraggedActivity.id);
        this.activities[old_partner_id?.id ?? 0][date_index][time_slot] = this.activities[old_partner_id?.id ?? 0][date_index][time_slot].filter((activity: any) => activity.id !== this.currentDraggedActivity.id);

        const providersIds = [partner.id];
        if(old_partner_id) {
            providersIds.push(-old_partner_id.id);
        }

        try {
            await this.api.call('?do=model_update', {
                entity: 'sale\\booking\\BookingActivity',
                id: this.currentDraggedActivity.id,
                fields: {
                    providers_ids: providersIds
                }
            });

            this.currentDraggedActivity = null;

            // full refresh if multiple activities to get them correctly sorted
            this.onRefresh();
        }
        catch(response) {
            this.api.errorFeedback(response);

            // move the activity to its previous position
            this.activities[partner.id][date_index][time_slot] = this.activities[partner.id][date_index][time_slot].filter((activity: any) => activity.id !== this.currentDraggedActivity.id);
            this.activities[old_partner_id?.id ?? 0][date_index][time_slot].splice(old_index, 0, this.currentDraggedActivity);

            // reset employee related data (needed because the object wasn't cloned and is modified above)
            this.currentDraggedActivity.partner_id = old_partner_id;
            this.currentDraggedActivity.providers_ids = [old_providers_ids];

            // drag and drop finished
            this.currentDraggedActivity = null;
        }
    }

    private splitSchedule(schedule_from: string, schedule_to: string, qty: number) {
        const toSeconds = (time: string) => {
            const [hours, minutes, seconds] = time.split(':').map(Number);
            return hours * 3600 + minutes * 60 + seconds;
        };

        const toTime = (totalSeconds: number) => {
            const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
            const seconds = String(totalSeconds % 60).padStart(2, '0');
            return `${hours}:${minutes}:${seconds}`;
        };

        const start = toSeconds(schedule_from);
        const end = toSeconds(schedule_to);
        const interval = (end - start) / qty;

        const result = [];

        for(let i = 0; i < qty; i++) {
            const from = toTime(start + interval * i);
            const to = toTime(start + interval * (i + 1));
            result.push({ from, to });
        }

        return result;
    }

    public trackByActivity(index: number, activity: any): string {
        return activity.id; // Assurez-vous que chaque activité a un ID unique
    }

    public createProductModelsNames(productModelsIds: number[]) {
        const productModelsNames: string[] = [];
        for(let productModelId of productModelsIds) {
            const productModel = this.productModels.find(p => p.id === +productModelId);
            if(productModel !== undefined && !productModelsNames.includes(productModel.name)) {
                productModelsNames.push(productModel.name);
            }
        }

        return productModelsNames.sort();
    }
}
