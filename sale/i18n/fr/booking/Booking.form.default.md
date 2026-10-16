# Fiche de réservation

La fiche de réservation centralise toutes les informations d’un dossier client : les séjours, les services vendus, les personnes concernées, les ressources réservées, les documents et les paiements. Les informations et actions disponibles évoluent avec le statut de la réservation.

### Informations générales

Le haut de la fiche reprend les informations principales :

- le **libellé** est la référence attribuée automatiquement à la réservation ;
- le **statut** indique l’étape actuelle du dossier ;
- le **type** est attribué automatiquement à partir des packs vendus ou des règles d’assignation configurées ;
- le **nombre de participants** sert de valeur de référence pour les services comptabilisés à la personne ou au logement ;
- le **prix TTC** correspond à la somme des groupes de services et est recalculé lorsque ceux-ci sont modifiés ;
- les dates et heures de **début** et de **fin** proviennent du premier et du dernier séjour de la réservation.

Les indicateurs **À confirmer** signalent que certains prix doivent encore être validés. **Pas d’expiration** indique qu’une option ne doit pas expirer automatiquement.

### Description

Lors de la création, la description peut être initialisée à partir des informations du client. Elle sert ensuite à transmettre des remarques internes aux personnes qui prennent en charge la réservation. Une modification de cette description ne modifie pas la fiche du client.

### Services réservés et séjours

Le panneau **Services réservés** permet de constituer la réservation à partir de produits individuels ou de packs. Les services sont organisés en groupes, notamment les séjours et les suppléments. Les dates, le nombre de participants et les unités locatives assignées déterminent les quantités, les prix et les prestations à planifier.

Le passage en **Option** réserve les unités locatives et génère les consommations des services planifiables. À partir de cette étape, le détail des services ne se modifie normalement plus directement : il faut repasser en **Devis**, effectuer les changements, puis remettre la réservation en option. Pendant ou après le séjour, des services supplémentaires peuvent être ajoutés dans un groupe distinct ; la génération de leurs consommations planifiables doit alors être demandée explicitement.

### Contacts

L’onglet **Contacts** reprend les personnes associées à la réservation. Les contacts du client sont importés lors de la création, avec au minimum un contact de type **Réservation**. L’action **Importer les contacts** permet de reprendre ultérieurement les contacts ajoutés sur la fiche du client. Des contacts propres à la réservation peuvent aussi être ajoutés sans modifier ceux du client.

Le rôle détermine l’usage du contact : **Réservation** pour le suivi général, **Contrat** ou **Facture** pour les destinataires des documents correspondants, et **Séjour** pour une personne participant au séjour.

### Consommations et composition

L’onglet **Consommations** reprend les prestations planifiées issues des services réservés : logements, salles, repas, activités ou autres ressources. Chaque consommation correspond à une date, une plage horaire et une quantité, avec éventuellement une unité locative. Elles alimentent les plannings et déterminent l’occupation des ressources ; toute modification doit donc être faite avec attention.

L’onglet **Composition** détaille les personnes bénéficiaires des services et leur répartition dans les unités locatives. Cette composition est utilisée pour les prestations à la personne, certaines taxes, les statistiques et les obligations d’identification. L’écran **Composition** du panneau latéral permet également d’importer une liste de participants.

### Contrats, financements et factures

Lors de la confirmation, un contrat est généré et un plan de financement est sélectionné selon la classe tarifaire, le type de réservation et le type de séjour. Les financements représentent les montants attendus et leurs échéances ; ils sont marqués comme payés manuellement ou lors de la réconciliation d’un paiement. Une demande de paiement instantané peut remplacer le plan habituel dans certains cas.

L’onglet **Contrats** conserve l’historique des contrats. Lorsqu’une réservation repasse en devis, le contrat en cours est annulé ; un contrat verrouillé peut empêcher ce retour. Les onglets **Financements** et **Factures** permettent de suivre les montants attendus, les paiements, les proformas et les factures liés au dossier.

### Statut et alertes

Une réservation suit généralement les étapes **Devis**, **Option**, **Confirmée**, **Validée**, **Checked-in**, **Checked-out**, puis les étapes de facturation et de clôture. Les actions proposées en haut de la fiche permettent d’effectuer les transitions autorisées, d’annuler le dossier, d’importer les contacts ou de mettre à jour son état financier.

Les alertes attirent l’attention sur une incohérence ou un contrôle à effectuer. Une alerte bloquante doit être corrigée avant de poursuivre et peut être vérifiée de nouveau avec **Réessayer**. Une alerte informative peut être retirée avec **Ignorer** après lecture. Le statut de paiement, recalculé selon les financements et leurs échéances, permet de voir rapidement si le dossier est en ordre.
