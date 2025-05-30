import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';

import { SharedLibModule, CustomDateAdapter } from 'sb-shared-lib';

import { BookingCampsChildRoutingModule } from './child-routing.module';

import { BookingCampsChildPreRegistrationComponent } from './pre-registration/pre-registration.component';

@NgModule({
    imports: [
        SharedLibModule,
        BookingCampsChildRoutingModule
    ],
    declarations: [
        BookingCampsChildPreRegistrationComponent
    ],
    providers: [
        { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
    ]
})
export class AppInBookingCampsChildModule {}
