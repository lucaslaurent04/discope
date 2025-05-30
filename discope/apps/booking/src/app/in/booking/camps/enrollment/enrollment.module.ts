import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';

import { SharedLibModule, CustomDateAdapter } from 'sb-shared-lib';

import { BookingCampsEnrollmentRoutingModule } from './enrollment-routing.module';

import { BookingCampsEnrollmentConfirmationComponent } from './confirmation/confirmation.component';

@NgModule({
    imports: [
        SharedLibModule,
        BookingCampsEnrollmentRoutingModule
    ],
    declarations: [
        BookingCampsEnrollmentConfirmationComponent
    ],
    providers: [
        { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
    ]
})
export class AppInBookingCampsEnrollmentModule {}
