import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';
import { DatePipe } from '@angular/common';

import { SharedLibModule, CustomDateAdapter } from 'sb-shared-lib';

import { BookingRoutingModule } from './booking-routing.module';

import { BookingComponent } from './booking.component';
import { BookingServicesComponent } from './services/services.component';

import { BookingServicesBookingComponent } from './services/_components/booking/booking.component';
import { BookingServicesBookingGroupComponent } from './services/_components/booking/_components/group/group.component';
import { BookingServicesBookingGroupLineComponent } from './services/_components/booking/_components/group/_components/line/line.component';
import { BookingServicesBookingGroupAccomodationComponent } from './services/_components/booking/_components/group/_components/accomodation/accomodation.component';
import { BookingServicesBookingGroupAccomodationAssignmentComponent } from './services/_components/booking/_components/group/_components/accomodation/_components/assignment.component';
import { BookingServicesBookingGroupDayActivitiesComponent } from './services/_components/booking/_components/group/_components/day-activities/day-activities.component';
import { BookingServicesBookingGroupDayActivitiesActivityComponent } from './services/_components/booking/_components/group/_components/day-activities/_components/activity/activity.component';
import { BookingServicesBookingGroupDayActivitiesActivityLineComponent } from './services/_components/booking/_components/group/_components/day-activities/_components/activity/_components/line/line.component';
import { BookingServicesBookingGroupAccomodationAssignmentsEditorComponent } from './services/_components/booking/_components/group/_components/accomodation/_components/assignmentseditor/assignmentseditor.component';
import { BookingServicesBookingGroupAccomodationAssignmentsEditorAssignmentComponent } from './services/_components/booking/_components/group/_components/accomodation/_components/assignmentseditor/_components/assignment.component';
import { BookingServicesBookingGroupMealPrefComponent } from './services/_components/booking/_components/group/_components/mealpref/mealpref.component';
import { BookingServicesBookingGroupAgeRangeComponent } from './services/_components/booking/_components/group/_components/agerange/agerange.component';
import { BookingServicesBookingGroupLinePriceDialogComponent } from './services/_components/booking/_components/group/_components/line/_components/price.dialog/price.component';
import { BookingServicesBookingGroupLineDiscountComponent } from './services/_components/booking/_components/group/_components/line/_components/discount/discount.component';
import { BookingServicesBookingGroupLinePriceadapterComponent } from './services/_components/booking/_components/group/_components/line/_components/priceadapter/priceadapter.component';

import { BookingCompositionComponent, BookingCompositionDialogConfirm } from './composition/composition.component';
import { BookingCompositionInviteComponent } from './composition/invite/invite.component';
import { BookingCompositionLinesComponent } from './composition/_components/booking.composition.lines/booking.composition.lines.component';

import { BookingQuoteComponent } from './quote/quote.component';
import { BookingInvoiceComponent } from './invoice/invoice.component';
import { BookingOptionComponent } from './option/option.component';

import { BookingActivitiesPlanningComponent } from './activities-planning/activities-planning.component';
import { BookingActivitiesPlanningActivityDetailsComponent } from './activities-planning/_components/activity-details/activity-details.component';
import { BookingActivitiesPlanningBookingGroupDetailsComponent } from './activities-planning/_components/booking-group-details/booking-group-details.component';
import { BookingActivitiesPlanningBookingGroupDetailDialogParticipantsOptionsComponent } from './activities-planning/_components/booking-group-details/_components/dialog-participants-options/dialog-participants-options.component';
import { BookingActivitiesPlanningWeekActivitiesComponent } from './activities-planning/_components/week-activities/week-activities.component';
import { BookingActivitiesPlanningActivityScheduleComponent } from './activities-planning/_components/activity-schedule/activity-schedule.component';

import { BookingServicesBookingGroupDayMealsComponent } from './services/_components/booking/_components/group/_components/day-meals/day-meals.component';
import { BookingServicesBookingGroupDayMealsMealComponent } from './services/_components/booking/_components/group/_components/day-meals/_components/meal/meal.component';
import { BookingServicesBookingGroupDialogMealsOptionsComponent } from './services/_components/booking/_components/group/_components/dialog-meals-options/dialog-meals-options.component';
import { BookingServicesBookingGroupDayActivitiesActivityDetailsDialogComponent } from './services/_components/booking/_components/group/_components/day-activities/_components/activity/_components/details/details.component';
import { BookingServicesBookingGroupDialogParticipantsOptionsComponent } from './services/_components/booking/_components/group/_components/dialog-participants-options/dialog-participants-options.component';

@NgModule({
  imports: [
    SharedLibModule,
    BookingRoutingModule
  ],
  declarations: [
    BookingComponent, BookingServicesComponent,
    BookingServicesBookingComponent, BookingServicesBookingGroupComponent,
    BookingServicesBookingGroupLineComponent, BookingServicesBookingGroupAccomodationComponent,
    BookingServicesBookingGroupAccomodationAssignmentComponent,
    BookingServicesBookingGroupDayActivitiesComponent,
    BookingServicesBookingGroupDayActivitiesActivityComponent,
    BookingServicesBookingGroupDayActivitiesActivityLineComponent,
    BookingServicesBookingGroupDayActivitiesActivityDetailsDialogComponent,
    BookingServicesBookingGroupDialogParticipantsOptionsComponent,
    BookingServicesBookingGroupAccomodationAssignmentsEditorComponent,
    BookingServicesBookingGroupAccomodationAssignmentsEditorAssignmentComponent,
    BookingServicesBookingGroupMealPrefComponent, BookingServicesBookingGroupAgeRangeComponent,
    BookingServicesBookingGroupLineDiscountComponent,
    BookingServicesBookingGroupLinePriceDialogComponent,
    BookingServicesBookingGroupLinePriceadapterComponent,
    BookingCompositionComponent, BookingCompositionDialogConfirm,
    BookingCompositionInviteComponent,
    BookingCompositionLinesComponent,
    BookingQuoteComponent,
    BookingInvoiceComponent,
    BookingOptionComponent,
    BookingActivitiesPlanningComponent,
    BookingActivitiesPlanningActivityDetailsComponent,
    BookingActivitiesPlanningActivityScheduleComponent,
    BookingActivitiesPlanningBookingGroupDetailsComponent,
    BookingActivitiesPlanningBookingGroupDetailDialogParticipantsOptionsComponent,
    BookingActivitiesPlanningWeekActivitiesComponent,
    BookingServicesBookingGroupDayMealsComponent,
    BookingServicesBookingGroupDayMealsMealComponent,
    BookingServicesBookingGroupDialogMealsOptionsComponent
  ],
  providers: [
    DatePipe,
    { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
  ]
})
export class AppInBookingModule { }
