import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { ChildPreRegistrationComponent } from './pre-registration/pre-registration.component';


const routes: Routes = [
    {
        path: 'preregistration',
        component: ChildPreRegistrationComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class ChildRoutingModule {}
