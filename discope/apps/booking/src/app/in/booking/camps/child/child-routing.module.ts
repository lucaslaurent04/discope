import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { BookingCampsChildPreRegistrationComponent } from './pre-registration/pre-registration.component';


const routes: Routes = [
    {
        path: 'preregistration',
        component: BookingCampsChildPreRegistrationComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class BookingCampsChildRoutingModule {}
