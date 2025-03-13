import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { PlanningComponent } from './planning.component';
import { PlanningEmployeesComponent } from './employees/employees.component';


const routes: Routes = [
    {
        path: '',
        component: PlanningComponent
    },
    {
        path: 'employees',
        component: PlanningEmployeesComponent
    }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class PlanningRoutingModule {}
