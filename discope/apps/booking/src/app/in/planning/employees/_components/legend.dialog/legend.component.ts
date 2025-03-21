import { Component } from '@angular/core';
import { MatDialogRef } from '@angular/material/dialog';

@Component({
    selector: 'planning-employees-legend-dialog',
    templateUrl: './legend.component.html',
    styleUrls: ['./legend.component.scss']
})
export class PlanningEmployeesLegendDialogComponent {
    constructor(
        public dialogRef: MatDialogRef<PlanningEmployeesLegendDialogComponent>
    ) {}

    public onClose(): void {
        this.dialogRef.close();
    }
}
