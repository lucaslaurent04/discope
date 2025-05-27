import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { BookingCampsChildPreRegistrationComponent } from './pre-registration/pre-registration.component';
import { BookingCampsChildConfirmationComponent } from './confirmation/confirmation.component';


const routes: Routes = [
    {
        path: 'preregistration',
        component: BookingCampsChildPreRegistrationComponent
    },
    {
        path: 'confirmation',
        component: BookingCampsChildConfirmationComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class BookingCampsChildRoutingModule {}
