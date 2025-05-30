import { NgModule } from '@angular/core';
import { DateAdapter, MAT_DATE_LOCALE } from '@angular/material/core';
import { Platform } from '@angular/cdk/platform';

import { SharedLibModule, AuthInterceptorService, CustomDateAdapter } from 'sb-shared-lib';

import { CampsRoutingModule } from './camps-routing.module';

import { CampsComponent } from './camps.component';


@NgModule({
  imports: [
    SharedLibModule,
    CampsRoutingModule
  ],
  declarations: [
    CampsComponent
  ],
  providers: [
    { provide: DateAdapter, useClass: CustomDateAdapter, deps: [MAT_DATE_LOCALE, Platform] }
  ]
})
export class AppInCampsModule { }
