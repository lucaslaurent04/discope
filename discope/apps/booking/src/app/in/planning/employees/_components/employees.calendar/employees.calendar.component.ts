import { Component, ChangeDetectionStrategy, ChangeDetectorRef, Output, EventEmitter, ViewChild, OnInit, OnChanges, AfterViewInit, ViewChildren, QueryList, ElementRef, AfterViewChecked, Input, SimpleChanges, ViewRef } from '@angular/core';

import { ChangeReservationArg } from 'src/app/model/changereservationarg';
import { HeaderDays } from 'src/app/model/headerdays';


import { ApiService } from 'sb-shared-lib';
import { PlanningEmployeesCalendarParamService } from '../../_services/employees.calendar.param.service';
import { MatSnackBar } from '@angular/material/snack-bar';

import { CdkDragDrop } from '@angular/cdk/drag-drop';


class Employee {
    constructor(
        public id: number = 0,
        public name: string = '',
        public is_active: boolean = true,
        public activity_product_models_ids: any[] = []
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
    @Output() showEmployee = new EventEmitter();

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
    public employees: any = [];
    public holidays: any = [];
    // count of rental units taken under account (not necessarily equal to `rental_units.length`)
    public count_rental_units: number = 0;

    public hovered_activity: any;
    private hoveredActivityTimeout: any = null;

    public hovered_employee: any;
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

    public mapStats: any = {
        'occupied': {},
        'capacity': {},
        'blocked': {},
        'occupancy': {},
        'arrivals_expected': {},
        'arrivals_confirmed': {},
        'departures_expected': {},
        'departures_confirmed': {}
    };

    private mousedownTimeout: any;

    // duration history as hint for refreshing cell width
    private previous_duration: number;

    private show_parents: boolean = false;
    private show_children: boolean = false;
    private today: Date;
    private today_index: string;

    public emptyEmployee = new Employee();

    constructor(
        private params: PlanningEmployeesCalendarParamService,
        private api: ApiService,
        private snack: MatSnackBar,
        private elementRef: ElementRef,
        private cd: ChangeDetectorRef) {
            this.headers = {};
            this.employees = [];
            this.previous_duration = 0;
            this.show_parents = (localStorage.getItem('planning_show_parents') === 'true');
            this.show_children = (localStorage.getItem('planning_show_children') === 'true');
            if(!this.show_parents && !this.show_children) {
                this.show_parents = true;
                this.show_children = true;
            }
            this.today = new Date();
            this.today_index = this.calcDateIndex(this.today);
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

            this.show_parents = (localStorage.getItem('planning_show_parents') === 'true');
            this.show_children = (localStorage.getItem('planning_show_children') === 'true');
            if(!this.show_parents && !this.show_children) {
                this.show_parents = true;
                this.show_children = true;
            }
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

    public isToday(day:Date) {
        return (day.getDate() == this.today.getDate() && day.getMonth() == this.today.getMonth() && day.getFullYear() == this.today.getFullYear());
    }
/*
    public isTodayIndex(day_index:string) {
        return (this.today_index == day_index);
    }
*/
    public hasActivity(employee: Employee, day_index: string, time_slot: string): boolean {
        return (this.activities[employee.id]?.[day_index]?.[time_slot] ?? []).length > 0;
    }

    public getActivities(employee:Employee, day: Date, time_slot: string): any {
        if(this.activities[employee.id] ?? false) {
            let date_index:string = this.calcDateIndex(day);
            return this.activities[employee.id]?.[date_index]?.[time_slot] ?? {};
        }
        return {};
    }

    public getDescription(activity: any): string {
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
            `<dt>Handicap : <b>${activity.booking_line_group_id.has_person_with_disability ? 'oui' : 'non'}</b></dt>` + // TODO: handle disabled people (yes/no)
            `<dt>Séjour du ${activity.booking_id.date_from} au ${activity.booking_id.date_to}</dt>` +
            `<dt>${activity.booking_id.nb_pers} personnes</dt>` +
            `<br />` +
            `<dt>Activité ${activity.name} <b>${activity.counter}/${activity.counter_total}</b></dt>` +
            '</dl>';
    }

    private async onFiltersChange() {
        this.createHeaderDays();

        try {
            const domain: any[] = ['relationship', '=', 'employee'];
            /*
            const domain: any[] = JSON.parse(JSON.stringify(this.params.rental_units_filter));
            if(!domain.length) {
                domain.push([['can_rent', '=', true], ["center_id", "in", this.params.centers_ids]]);
            }
            else {
                for(let i = 0, n = domain.length; i < n; ++i) {
                    domain[i].push(["center_id", "in",  this.params.centers_ids]);
                }
            }
            */
            const employees = await this.api.collect(
                "hr\\employee\\Employee",
                domain,
                Object.getOwnPropertyNames(new Employee()),
                'name', 'asc', 0, 500
            );
            if(employees) {
                this.employees = employees;
            }
        }
        catch(response) {
            console.warn('unable to fetch employees', response);
        }

        try {
            this.activities = await this.api.fetch('?get=sale_booking_activity_map', {
                // #memo - all dates are considered UTC
                date_from: this.calcDateIndex(this.params.date_from),
                date_to: this.calcDateIndex(this.params.date_to),
                // #todo - #memo - we need to allow filtering employees based on various criterias
                // employees_ids: JSON.stringify([15, 16, 17, 18, 19])
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
            }, 300);
        }
    }

    public onhoverDate(day: Date) {
        let result;
        if(day) {
            let date_index: string = this.calcDateIndex(day);
            if(this.holidays.hasOwnProperty(date_index) && this.holidays[date_index].length) {
                result = this.holidays[date_index];
            }
        }
        this.hovered_holidays = result;
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

    public onSelectedBooking(event: any) {
        clearTimeout(this.mousedownTimeout);
        this.showBooking.emit(event);
    }

    public onSelectedEmployee(employee: any) {
        clearTimeout(this.mousedownTimeout);
        this.showEmployee.emit(employee);
    }

    public onhoverDay(employee: any, day:Date) {
        this.hovered_employee = employee;

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

    public onhoverEmployee(employee: any) {
        this.hovered_employee = employee;
    }

    public onmouseleaveTable() {
        clearTimeout(this.mousedownTimeout);
        this.selection.is_active = false;
        this.selection.width = 0;
    }

    public onmouseup() {
        clearTimeout(this.mousedownTimeout);

        if(this.selection.is_active) {
            console.log('is active');
            // make from and to right
            let rental_unit:any = this.selection.cell_from.employee;
            let from:any = this.selection.cell_from;
            let to:any = this.selection.cell_to;
            if(this.selection.cell_to.date < this.selection.cell_from.date) {
                from = this.selection.cell_to;
                to = this.selection.cell_from;
            }
            // check selection for existing consumption
            let valid = true;
            let diff = (<Date>this.selection.cell_to.date).getTime() - (<Date>this.selection.cell_from.date).getTime();
            let days = Math.abs(Math.floor(diff / (60*60*24*1000)))+1;
            // do not check last day : overlaps is allowed if checkout is before checkin
            for (let i = 0; i < days-1; i++) {
                let currdate = new Date(from.date.getTime());
                currdate.setDate(currdate.getDate() + i);
                // #todo
                if(this.hasActivity(rental_unit, this.calcDateIndex(currdate), 'AM')) {
                    valid = false;
                    break;
                }
            }
            if(!valid || !from.employee) {
                this.selection.is_active = false;
                this.selection.width = 0;
                return;
            }
            else {
                // open dialog for requesting action dd
/*
                const dialogRef = this.dialog.open(ConsumptionCreationDialog, {
                    width: '50vw',
                    data: {
                        employee: from.employee.name,
                        employee_id: from.employee.id,
                        date_from: from.date,
                        date_to: to.date
                    }
                });

                dialogRef.afterClosed().subscribe( async (values) => {
                    if(values) {
                        if(values.type && values.type == 'book') {
                            try {
                                // let date_from = new Date(values.date_from.getTime()-values.date_from.getTimezoneOffset()*60*1000);
                                let date_from = (new Date(values.date_from.getTime()));
                                let date_to = (new Date(values.date_to.getTime()));
                                date_from.setHours(0,-values.date_from.getTimezoneOffset(),0,0);
                                date_to.setHours(0,-values.date_to.getTimezoneOffset(),0,0);
                                await this.api.call('?do=sale_booking_plan-option', {
                                    date_from: date_from.toISOString(),
                                    date_to: date_to.toISOString(),
                                    rental_unit_id: values.rental_unit_id,
                                    customer_identity_id: values.customer_identity_id,
                                    no_expiry: values.no_expiry,
                                    free_rental_units: values.free_rental_units
                                });

                                this.onRefresh();
                            }
                            catch(response) {
                                this.api.errorFeedback(response);
                            }
                        }
                        else if(values.type && values.type == 'ooo') {
                            try {
                                let date_from = (new Date(values.date_from.getTime()));
                                let date_to = (new Date(values.date_to.getTime()));
                                date_from.setHours(0,-values.date_from.getTimezoneOffset(),0,0);
                                date_to.setHours(0,-values.date_to.getTimezoneOffset(),0,0);
                                await this.api.call('?do=sale_booking_plan-repair', {
                                    date_from: date_from.toISOString(),
                                    date_to: date_to.toISOString(),
                                    rental_unit_id: values.rental_unit_id,
                                    description: (values.description.length)?values.description:'Blocage via planning'
                                });

                                this.onRefresh();
                            }
                            catch(response) {
                                this.snack.open('Ce blocage est en conflit avec des consommations existantes.', 'ERREUR');
                                // this.api.errorFeedback(response);
                            }
                        }

                    }
                });
*/
            }
        }

        this.selection.is_active = false;
        this.selection.width = 0;

    }

    public onmousedown($event: any, employee: any, day: any) {
        // start selection with a 100ms delay to avoid confusion with booking selection
        this.mousedownTimeout = setTimeout( () => {
            let table = this.calTable?.nativeElement.getBoundingClientRect();
            let cell = $event.target;

            while (cell && !cell.classList.contains('cell-AM')) {
                cell = cell.previousElementSibling;
            }

            if(!cell) {
                return;
            }

            let cellRect = cell.getBoundingClientRect();

            this.selection.top = cellRect.top - table.top;
            this.selection.left = cellRect.left - table.left + this.calTable.nativeElement.offsetLeft;

            this.selection.width = this.cellsWidth * 3;
            this.selection.height = cellRect.height;

            this.selection.cell_from.left = this.selection.left;
            this.selection.cell_from.width = this.cellsWidth;
            this.selection.cell_from.date = day;
            this.selection.cell_from.employee = employee;

            this.selection.is_active = true;
        }, 100);
    }

    public onmouseover($event: any, day: any) {
        if(this.selection.is_active) {
            if(day < this.selection.cell_from.date) {
                return;
            }
            // selection between start and currently hovered cell
            let table = this.calTable?.nativeElement.getBoundingClientRect();
            let cell = $event.target;
            while (cell && !cell.classList.contains('cell-EV')) {
                cell = cell.nextElementSibling;
            }

            if(!cell) {
                return;
            }

            let cellRect = cell.getBoundingClientRect();

            this.selection.cell_to.date = day;

            // diff between two dates
            let diff = (<Date>this.selection.cell_to.date).getTime() - (<Date>this.selection.cell_from.date).getTime();
            let nb_days = Math.abs(Math.floor(diff / (60*60*24*1000))) + 1;

            this.selection.width = Math.ceil(this.cellsWidth * nb_days * 3) + nb_days;

        }
    }

    public preventDrag($event: any = null) {
        if($event && typeof $event.preventDefault === 'function') {
            $event.preventDefault();
        }
        return false;
    }

    private isDroppable(activity: any, employee: Employee, date_index: string, time_slot: string) {
        const activity_date_index = this.calcDateIndex(new Date(activity.activity_date));

               // Check drop and activity moment match
        return date_index === activity_date_index && time_slot == activity.time_slot

               // Check employee can handle activity
               && employee.activity_product_models_ids.map(id => +id).includes(activity.product_model_id.id)

               // Check employee is free during moment
               && !this.hasActivity(employee, date_index, time_slot);
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
            let old_employee_id = this.currentDraggedActivity.employee_id ? this.currentDraggedActivity.employee_id.id : this.currentDraggedActivity.employee_id;

console.log(time_slot, date_index, old_employee_id);

            console.log(JSON.stringify(this.activities[old_employee_id][date_index][time_slot]))
            // remove from this.activities[0][date_index][time_slot]
            this.activities[old_employee_id][date_index][time_slot] = this.activities[old_employee_id][date_index][time_slot].filter( (activity: any) => activity.id !== this.currentDraggedActivity.id);

            console.log(JSON.stringify(this.activities[old_employee_id][date_index][time_slot]))

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

            this.currentDraggedActivity.employee_id = this.emptyEmployee;
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
                this.snack.open('Cette activité ne peut pas être assignée à cet animateur ou à cette plage horaire.', 'ERREUR');
            }
            else {
                const dropEvent = event as CdkDragDrop<any, any>;

                const element = dropEvent.container.element.nativeElement as HTMLElement;
                element.style.setProperty('background-color', '');

                // #todo - (?) tenir compte du type (event_type)
                let old_employee_id = this.currentDraggedActivity.employee_id ? this.currentDraggedActivity.employee_id.id : this.currentDraggedActivity.employee_id;

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

                this.currentDraggedActivity.employee_id = employee;
                this.activities[employee.id][date_index][time_slot].push(this.currentDraggedActivity);

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
                }
            }
            this.currentDraggedActivity = null;
        }

    }

    public trackByActivity(index: number, activity: any): string {
        return activity.id; // Assurez-vous que chaque activité a un ID unique
    }
}
