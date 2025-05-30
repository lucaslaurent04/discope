import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { EnrollmentConfirmationComponent } from './confirmation/confirmation.component';


const routes: Routes = [
    {
        path: 'confirmation',
        component: EnrollmentConfirmationComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class EnrollmentRoutingModule {}
