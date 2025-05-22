import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { BookingCampsEnrollmentPreRegistrationComponent } from './pre-registration/pre-registration.component';
import { BookingCampsEnrollmentConfirmationComponent } from './confirmation/confirmation.component';


const routes: Routes = [
    {
        path: 'preregistration',
        component: BookingCampsEnrollmentPreRegistrationComponent
    },
    {
        path: 'confirmation',
        component: BookingCampsEnrollmentConfirmationComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class BookingCampsEnrollmentRoutingModule {}
