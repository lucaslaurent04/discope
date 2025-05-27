import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';

import { SharedLibModule, CustomDateAdapter } from 'sb-shared-lib';

import { BookingCampsChildRoutingModule } from './child-routing.module';

import { BookingCampsChildPreRegistrationComponent } from './pre-registration/pre-registration.component';
import { BookingCampsChildConfirmationComponent } from './confirmation/confirmation.component';

@NgModule({
    imports: [
        SharedLibModule,
        BookingCampsChildRoutingModule
    ],
    declarations: [
        BookingCampsChildPreRegistrationComponent,
        BookingCampsChildConfirmationComponent
    ],
    providers: [
        { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
    ]
})
export class AppInBookingCampsChildModule {}
