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
import { BookingActivitiesComponent } from './activities/activities.component';
import { BookingActivitiesBookingComponent } from './activities/_components/booking/booking.component';
import { BookingActivitiesBookingGroupComponent } from './activities/_components/booking/_components/group/group.component';
import { BookingActivitiesBookingGroupLineComponent } from './activities/_components/booking/_components/group/_components/line/line.component';

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
    BookingServicesBookingGroupAccomodationAssignmentsEditorComponent,
    BookingServicesBookingGroupAccomodationAssignmentsEditorAssignmentComponent,
    BookingServicesBookingGroupMealPrefComponent, BookingServicesBookingGroupAgeRangeComponent,
    BookingServicesBookingGroupLineDiscountComponent,
    BookingServicesBookingGroupLinePriceDialogComponent,
    BookingServicesBookingGroupLinePriceadapterComponent,
    BookingActivitiesComponent, BookingActivitiesBookingComponent,
    BookingActivitiesBookingGroupComponent, BookingActivitiesBookingGroupLineComponent,
    BookingCompositionComponent, BookingCompositionDialogConfirm,
    BookingCompositionInviteComponent,
    BookingCompositionLinesComponent,
    BookingQuoteComponent,
    BookingInvoiceComponent,
    BookingOptionComponent
  ],
  providers: [
    DatePipe,
    { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
  ]
})
export class AppInBookingModule { }
