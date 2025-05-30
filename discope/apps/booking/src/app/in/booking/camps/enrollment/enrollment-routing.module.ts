import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { BookingCampsEnrollmentConfirmationComponent } from './confirmation/confirmation.component';


const routes: Routes = [
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
