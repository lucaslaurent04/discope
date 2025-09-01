# Doc Lathus


### planning des animateurs 


spécificités

* distinction employés et les prestataires
	les employés ont une liste de produits activités assignés
	les prestataitaires on une liste de modèles de produits assignés
 

* possiblité d'ajouter des évéenements non-activité
	au niveau des employés
	même logique : par jour et tranche horaire, description libre affichage en grisé

* basé sur BookingActivity,avec synchro sur product model
	- has_staff_required : seule les activités nécessitant du personnel sont affichées
	- is_exclusive : lorsqu'une activité est "exclusive", on ne peut en mettre qu'une par tranche horaire
