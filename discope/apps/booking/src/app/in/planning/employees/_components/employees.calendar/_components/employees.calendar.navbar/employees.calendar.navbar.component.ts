import { Component, Input, Output, EventEmitter, OnInit, ViewChild } from '@angular/core';

import { PlanningEmployeesCalendarParamService } from '../../../../_services/employees.calendar.param.service';

import { ChangeReservationArg } from 'src/app/model/changereservationarg';
import { ApiService, AuthService } from 'sb-shared-lib';
import { FormControl, FormGroup } from '@angular/forms';
import { MatSelect } from '@angular/material/select';
import { MatOption } from '@angular/material/core';

@Component({
  selector: 'planning-employees-calendar-navbar',
  templateUrl: './employees.calendar.navbar.component.html',
  styleUrls: ['./employees.calendar.navbar.component.scss']
})
export class PlanningEmployeesCalendarNavbarComponent implements OnInit {
    @Input() activity: any;
    @Input() partner: any;
    @Input() holidays: any;
    @Output() changedays = new EventEmitter<ChangeReservationArg>();
    @Output() refresh = new EventEmitter<Boolean>();
    @ViewChild('centerSelector') partnerSelector: MatSelect;

    @Output() openLegendDialog = new EventEmitter();
    @Output() openPrefDialog = new EventEmitter();
    @Output() fullScreen = new EventEmitter();

    dateFrom: Date;
    dateTo: Date;
    duration: number;

    partners: any[] = [];
    selected_partners_ids: any[] = [];

    vm: any = {
        duration:   '31',
        date_range: new FormGroup({
            date_from: new FormControl(),
            date_to: new FormControl()
        })
    };

    constructor(
        private api: ApiService,
        private auth: AuthService,
        private params: PlanningEmployeesCalendarParamService
    ) {}

    public ngOnInit() {

        /*
            Setup events listeners
        */

        this.params.getObservable()
            .subscribe( async () => {
                console.log('received change from params');
                // update local vars according to service new values
                this.dateFrom = new Date(this.params.date_from.getTime())
                this.dateTo = new Date(this.params.date_to.getTime())

                this.duration = this.params.duration;
                this.vm.duration = this.duration.toString();
                this.vm.date_range.get("date_from").setValue(this.dateFrom);
                this.vm.date_range.get("date_to").setValue(this.dateTo);
            });

        // by default set the first center of current user
        this.auth.getObservable()
            .subscribe( async (user:any) => {
                if(!user.hasOwnProperty('centers_ids') || !user.centers_ids.length) {
                    return;
                }

                try {
                    const employees = await this.api.collect(
                        'hr\\employee\\Employee',
                        ['center_id', 'in', user.centers_ids],
                        ['id'],
                        'name', 'asc', 0, 500
                    );

                    if(employees.length === 0) {
                        return;
                    }

                    const partners_domain = [
                        [['id', 'in', employees.map((e: any) => e.id)]],
                        [['relationship', '=', 'provider']]
                    ];
                    const partners = await this.api.collect(
                        'identity\\Partner',
                        partners_domain,
                        ['id', 'name', 'relationship'],
                        'name', 'asc', 0, 500
                    );

                    if(partners.length === 0) {
                        return;
                    }

                    // value stored in local storage prevails
                    let stored = localStorage.getItem('partners_ids');
                    if(stored) {
                        this.selected_partners_ids = JSON.parse(stored);
                    }
                    else {
                        this.selected_partners_ids = partners.map( (e:any) => e.id );
                    }

                    this.params.partners_ids = this.selected_partners_ids;
                    this.partners = partners.sort((a: any, b: any) => {
                        if (a.relationship !== b.relationship) {
                            return a.relationship < b.relationship ? -1 : 1;
                        }
                        return a.name.localeCompare(b.name);
                    });
                }
                catch(err) {
                    console.warn(err) ;
                }
            });
    }

    public onOpenLegendDialog() {
        this.openLegendDialog.emit();
    }

    public onOpenPrefDialog() {
        this.openPrefDialog.emit();
    }

    public onFullScreen() {
        this.fullScreen.emit();
    }

    public async onchangeDateRange() {
        let start = this.vm.date_range.get("date_from").value;
        let end = this.vm.date_range.get("date_to").value;

        if(!start || !end) return;

        if(typeof start == 'string') {
            start = new Date(start);
        }

        if(typeof end == 'string') {
            end = new Date(end);
        }

        if(start <= end) {

            // relay change to parent component
            if((start.getTime() != this.dateFrom.getTime() || end.getTime() != this.dateTo.getTime())) {
                //  update local members and relay to params service
                this.dateFrom = this.vm.date_range.get("date_from").value;
                this.dateTo = this.vm.date_range.get("date_to").value;
                this.params.date_from = this.dateFrom;
                this.params.date_to = this.dateTo;
            }
        }
    }

    public onDurationChange(event: any) {
        console.log('onDurationChange');
        // update local values
        this.duration = parseInt(event.value, 10);
        this.dateTo = new Date(this.dateFrom.getTime());
        this.dateTo.setDate(this.dateTo.getDate() + this.duration);
        this.vm.date_range.get("date_to").setValue(this.dateTo);

        this.params.date_from = this.dateFrom;
        this.params.date_to = this.dateTo;
    }

    public onToday() {
        this.dateFrom = new Date();
        this.dateTo = new Date(this.dateFrom.getTime());
        this.dateTo.setDate(this.dateTo.getDate() + this.params.duration);
        this.vm.date_range.get("date_from").setValue(this.dateFrom);
        this.vm.date_range.get("date_to").setValue(this.dateTo);

        this.params.date_from = this.dateFrom;
        this.params.date_to = this.dateTo;
    }

    public onPrev(duration: number) {
        this.dateFrom.setDate(this.dateFrom.getDate() - duration);
        this.dateTo.setDate(this.dateTo.getDate() - duration);
        this.vm.date_range.get("date_from").setValue(this.dateFrom);
        this.vm.date_range.get("date_to").setValue(this.dateTo);

        this.params.date_from = this.dateFrom;
        this.params.date_to = this.dateTo;
    }

    public onNext(duration: number) {
        this.dateFrom.setDate(this.dateFrom.getDate() + duration);
        this.dateTo.setDate(this.dateTo.getDate() + duration);
        this.vm.date_range.get("date_from").setValue(this.dateFrom);
        this.vm.date_range.get("date_to").setValue(this.dateTo);

        this.params.date_from = this.dateFrom;
        this.params.date_to = this.dateTo;
    }

    public onRefresh() {
        this.refresh.emit(true);
    }

    public onchangeSelectedPartners() {
        console.log('::onchangeSelectedEmployees');
        this.params.partners_ids = this.selected_partners_ids;
        localStorage.setItem('partners_ids', JSON.stringify(this.selected_partners_ids));
    }

    public onclickUnselectAllPartners() {
        this.partnerSelector.options.forEach((item: MatOption) => item.deselect());
    }

    public onclickSelectAllPartners() {
        this.partnerSelector.options.forEach((item: MatOption) => item.select());
    }

    public onclickSelectInternal() {
        this.partnerSelector.options.forEach((item: MatOption) => {
            const partner = this.partners.find(p => p.id == item.value);
            if(partner.relationship === 'employee') {
                item.select();
            }
            else {
                item.deselect();
            }
        });
    }

    public onclickSelectExternal() {
        this.partnerSelector.options.forEach((item: MatOption) => {
            const partner = this.partners.find(p => p.id == item.value);
            if(partner.relationship === 'provider') {
                item.select();
            }
            else {
                item.deselect();
            }
        });
    }

    public calcHolidays() {
        return this.holidays.map( (a:any) => a.name ).join(', ');
    }
}
