import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';
import { DatePipe } from '@angular/common';

import { SharedLibModule, CustomDateAdapter } from 'sb-shared-lib';

import { BookingCampsRoutingModule } from './camps-routing.module';

@NgModule({
    imports: [
        SharedLibModule,
        BookingCampsRoutingModule
    ],
    declarations: [],
    providers: [
        DatePipe,
        { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
    ]
})
export class AppInBookingCampsModule { }
