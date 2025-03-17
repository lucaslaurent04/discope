import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';


import { SharedLibModule, CustomDateAdapter } from 'sb-shared-lib';

import { PlanningRoutingModule } from './planning-routing.module';

import { PlanningComponent } from './planning.component';
import { PlanningLegendDialogComponent } from './_components/legend.dialog/legend.component';
import { PlanningPreferencesDialogComponent } from './_components/preferences.dialog/preferences.component';
import { PlanningCalendarComponent } from './_components/planning.calendar/planning.calendar.component';
import { PlanningCalendarBookingComponent } from './_components/planning.calendar/_components/planning.calendar.booking/planning.calendar.booking.component';
import { PlanningCalendarNavbarComponent } from './_components/planning.calendar/_components/planning.calendar.navbar/planning.calendar.navbar.component';

import { ConsumptionCreationDialog } from './_components/planning.calendar/_components/consumption.dialog/consumption.component';


import { PlanningEmployeesComponent } from './employees/employees.component';
import { PlanningEmployeesLegendDialogComponent } from './employees/_components/legend.dialog/legend.component';
import { PlanningEmployeesPreferencesDialogComponent } from './employees/_components/preferences.dialog/preferences.component';
import { PlanningEmployeesCalendarComponent } from './employees/_components/employees.calendar/employees.calendar.component';
import { PlanningEmployeesCalendarNavbarComponent } from './employees/_components/employees.calendar/_components/employees.calendar.navbar/employees.calendar.navbar.component';
import { PlanningEmployeesCalendarActivityComponent } from './employees/_components/employees.calendar/_components/employees.calendar.activity/employees.calendar.activity.component';

import { LayoutModule } from '@angular/cdk/layout';
import { OverlayModule } from '@angular/cdk/overlay';

@NgModule({
  imports: [
    SharedLibModule,
    PlanningRoutingModule,
    LayoutModule,
    OverlayModule
  ],
  declarations: [
    PlanningComponent,
    PlanningCalendarComponent,
    PlanningCalendarBookingComponent,
    PlanningCalendarNavbarComponent,
    ConsumptionCreationDialog,
    PlanningLegendDialogComponent,
    PlanningPreferencesDialogComponent,
    PlanningEmployeesComponent,
    PlanningEmployeesLegendDialogComponent,
    PlanningEmployeesPreferencesDialogComponent,
    PlanningEmployeesCalendarComponent,
    PlanningEmployeesCalendarNavbarComponent,
    PlanningEmployeesCalendarActivityComponent
  ],
  providers: [
    { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
  ]
})
export class AppInPlanningModule { }
