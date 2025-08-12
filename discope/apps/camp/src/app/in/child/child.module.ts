import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';

import { SharedLibModule, CustomDateAdapter } from 'sb-shared-lib';

import { ChildRoutingModule } from './child-routing.module';

import { ChildPreRegistrationComponent } from './pre-registration/pre-registration.component';

@NgModule({
    imports: [
        SharedLibModule,
        ChildRoutingModule
    ],
    declarations: [
        ChildPreRegistrationComponent
    ],
    providers: [
        { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
    ]
})
export class AppInChildModule {}
