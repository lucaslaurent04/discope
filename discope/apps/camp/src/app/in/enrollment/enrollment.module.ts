import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';

import { SharedLibModule, CustomDateAdapter } from 'sb-shared-lib';

import { EnrollmentRoutingModule } from './enrollment-routing.module';

import { EnrollmentConfirmationComponent } from './confirmation/confirmation.component';
import { EnrollmentComponent } from './enrollment.component';

@NgModule({
    imports: [
        SharedLibModule,
        EnrollmentRoutingModule
    ],
    declarations: [
        EnrollmentComponent,
        EnrollmentConfirmationComponent
    ],
    providers: [
        { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
    ]
})
export class AppInEnrollmentModule {}
