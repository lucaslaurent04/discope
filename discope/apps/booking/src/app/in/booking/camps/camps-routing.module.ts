import { RouterModule, Routes } from '@angular/router';
import { NgModule } from '@angular/core';

const routes: Routes = [
    {
        path: 'child/:child_id',
        loadChildren: () => import(`./child/child.module`).then(m => m.AppInBookingCampsChildModule)
    },
    {
        path: 'enrollment/:enrollment_id',
        loadChildren: () => import(`./enrollment/enrollment.module`).then(m => m.AppInBookingCampsEnrollmentModule)
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class BookingCampsRoutingModule {}
