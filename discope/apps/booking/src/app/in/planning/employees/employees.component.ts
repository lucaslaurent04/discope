import { Component, ChangeDetectorRef, OnInit, AfterViewInit, ViewChild, ElementRef, OnDestroy } from '@angular/core';

import { ContextService } from 'sb-shared-lib';
import { PlanningEmployeesCalendarParamService } from './_services/employees.calendar.param.service';
import { PlanningEmployeesCalendarComponent } from './_components/employees.calendar/employees.calendar.component';
import { MatDialog } from '@angular/material/dialog';

import * as screenfull from 'screenfull';
import { PlanningEmployeesLegendDialogComponent } from './_components/legend.dialog/legend.component';
import { PlanningEmployeesPreferencesDialogComponent } from './_components/preferences.dialog/preferences.component';

interface DateRange {
  from: Date,
  to: Date
}

@Component({
    selector: 'planning-employees',
    templateUrl: './employees.component.html',
    styleUrls: ['./employees.component.scss']
})
export class PlanningEmployeesComponent implements OnInit, AfterViewInit, OnDestroy {
    @ViewChild('planningBody') planningBody: ElementRef;
    @ViewChild('planningCalendar') planningCalendar: PlanningEmployeesCalendarComponent;

    public centers_ids: number[];
    public rowsHeight: number = 30;
    public date_range: DateRange = <DateRange>{};
    public fullscreen: boolean = false;

    // timeout for storing rowsHeight in local storage
    private wheelTimeout: any = null;

    // interval for refreshing the data
    private refreshTimeout: any;

    constructor(
        private context: ContextService,
        private params: PlanningEmployeesCalendarParamService,
        private cd: ChangeDetectorRef,
        public dialog: MatDialog
    ) {
        this.centers_ids = [];
    }

    ngOnDestroy() {
        clearInterval(this.refreshTimeout);
    }

    ngOnInit() {
        // (re)init params service
        this.params.init();

        if (screenfull.isEnabled) {
            screenfull.on('change', () => {
                this.fullscreen = screenfull.isFullscreen;
            });
        }

        // #memo - we need to put this on global window to support fullscreen

        window.addEventListener('wheel', (event:any) => {
            if(event.shiftKey) {
                if(event.deltaY > 0) {
                    this.rowsHeight -= (this.rowsHeight/10) * Math.abs(event.deltaY)/100;
                }
                else if(event.deltaY < 0) {
                    this.rowsHeight += (this.rowsHeight/10) * Math.abs(event.deltaY)/100;
                }
                if(this.rowsHeight < 10) {
                    this.rowsHeight = 10;
                }
                else if(this.rowsHeight > 50) {
                    this.rowsHeight = 50;
                }

                if(this.wheelTimeout) {
                    clearTimeout(this.wheelTimeout);
                }
                this.wheelTimeout = setTimeout( () => {
                    // store new rowsHeight in local storage
                    localStorage.setItem('planning_rows_height', this.rowsHeight.toString());
                }, 1000);
            }
        }, true);

        // retrieve rowsHeight from local storage
        let rows_height = localStorage.getItem('planning_rows_height');
        if(rows_height) {
            this.rowsHeight = parseInt(rows_height, 10);
        }
        this.retrieveSettings();

        this.refreshTimeout = setInterval(() => {
                this.planningCalendar.onRefresh();
            },
            // refresh every 5 minutes
            5*60*1000);
    }

    private retrieveSettings() {
        console.log('applying settings');
        let rows_height = localStorage.getItem('planning_rows_height');
        if(rows_height) {
            this.rowsHeight = parseInt(rows_height, 10);
        }
    }

    // apply updated settings from localStorage
    private applySettings() {
        this.retrieveSettings();
        this.planningCalendar.onRefresh();
    }

    /**
     * Set up callbacks when component DOM is ready.
     */
    public ngAfterViewInit() {
    }

    public async onFullScreen() {
        if(screenfull.isEnabled) {
            this.cd.detach();
            await screenfull.request(this.planningBody.nativeElement);
            this.cd.reattach();
        }
        else {
            console.log('screenfull not enabled');
        }
    }

    public onOpenLegendDialog(){
        this.dialog.open(PlanningEmployeesLegendDialogComponent, {});
    }

    public onOpenPrefDialog() {
        const dialogRef = this.dialog.open(PlanningEmployeesPreferencesDialogComponent, {
                width: '500px',
                height: '500px'
            });

        dialogRef.afterClosed().subscribe(result => {
            if(result) {
                localStorage.setItem('planning_rows_height', result.rows_height.toString());
                this.applySettings();
            }
        });
    }

    public onShowBooking(consumption: any) {
        let descriptor:any

        // switch depending on object type (booking or ooo)
        if(consumption.type == 'ooo') {
            descriptor = {
                context_silent: true, // do not update sidebar
                context: {
                    entity: 'sale\\booking\\Repairing',
                    type: 'form',
                    name: 'default',
                    domain: ['id', '=', consumption.repairing_id.id],
                    mode: 'view',
                    purpose: 'view',
                    display_mode: 'popup',
                    callback: (data:any) => {
                        // restart angular lifecycles
                        this.cd.reattach();
                    }
                }
            };
        }
        // 'book' or similar
        else {
            descriptor = {
                context_silent: true, // do not update sidebar
                context: {
                    entity: 'sale\\booking\\Booking',
                    type: 'form',
                    name: 'default',
                    domain: ['id', '=', consumption.booking_id.id],
                    mode: 'view',
                    purpose: 'view',
                    display_mode: 'popup',
                    callback: (data:any) => {
                        // restart angular lifecycles
                        this.cd.reattach();
                        // force a refresh
                        this.planningCalendar.onRefresh();
                    }
                }
            };
        }

        if(this.fullscreen) {
            descriptor.context['dom_container'] = '.planning-body';
        }
        // prevent angular lifecycles while a context is open
        this.cd.detach();
        this.context.change(descriptor);
    }

    public onShowPartner(partner: any) {
        let descriptor:any = {
            context_silent: true, // do not update sidebar
            context: {
                entity: partner.relationship === 'employee' ? 'hr\\employee\\Employee' : 'sale\\provider\\Provider',
                type: 'form',
                name: 'default',
                domain: ['id', '=', partner.id],
                mode: 'view',
                purpose: 'view',
                display_mode: 'popup',
                callback: (data:any) => {
                    // restart angular lifecycles
                    this.cd.reattach();
                    // force a refresh
                    this.planningCalendar.onRefresh();
                }
            }
        };

        if(this.fullscreen) {
            descriptor.context['dom_container'] = '.planning-body';
        }
        // prevent angular lifecycles while a context is open
        this.cd.detach();
        this.context.change(descriptor);
    }
}
