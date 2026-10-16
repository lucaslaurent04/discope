# Fiche d’édition d’une facture

Cette fiche permet de consulter les informations d’une facture ou d’une proforma, ses lignes et les écritures comptables générées lors de son émission. Une facture est un document légal lié à une vente ; son contenu doit donc être vérifié avant de l’émettre.

### Informations générales

Le haut de la fiche reprend le client, l’organisation émettrice, la date, l’échéance, le type de document, le statut et la référence demandée par le client. L’indicateur **Payée** précise si le montant de la facture émise a été entièrement réglé. Pour une facture annulée, le lien vers la facture ou la note de crédit inverse permet de retrouver le document correspondant.

### Statut et type

Une **proforma** est un brouillon sans valeur comptable. Elle permet de contrôler les services, les prix, le client et la date avant l’émission. Une proforma ne doit pas être émise automatiquement ni systématiquement : il peut être nécessaire d’attendre la fin du séjour ou l’émission de factures antérieures.

Le statut **Facture** désigne un document définitif, numéroté et comptabilisé. Une facture émise ne peut plus être modifiée ou supprimée. Le type indique s’il s’agit d’une facture ordinaire ou d’une **note de crédit** destinée à annuler tout ou partie d’une facture précédente.

### Numéro et date d’émission

Le numéro définitif est attribué au moment de l’émission selon la séquence de l’équipe de gestion et l’année comptable en cours. Une facture ne peut pas être émise sur une année clôturée ni dans un ordre chronologique incompatible avec les factures déjà émises.

La date d’une proforma peut être adaptée avant l’émission. Si elle précède la facture la plus récente, la date de cette dernière est utilisée lors de l’émission. Si la date ne correspond pas à l’année comptable ouverte, le document reste en proforma.

### Lignes et montants

L’onglet **Lignes de facture** reprend les produits et services facturés, leurs quantités, prix et taux de TVA. Pour une facture de solde, les acomptes déjà facturés apparaissent avec des montants négatifs afin d’être déduits du total des services.

Les prix unitaires et les totaux intermédiaires conservent une précision suffisante pour les calculs, puis les montants affichés sont arrondis. Un léger écart peut donc exister entre la somme des montants TTC arrondis ligne par ligne et le total calculé globalement par taux de TVA.

### Écritures comptables

Les écritures comptables sont créées lors du passage de **Proforma** à **Facture** à partir des lignes et des règles comptables associées aux produits. Elles sont rattachées au journal concerné et leur total au débit doit correspondre à leur total au crédit. L’onglet **Écritures comptables** n’est donc visible que pour un document émis ou annulé.

### Paiement et correction

Les paiements sont normalement rattachés à une facture ou à un acompte par l’intermédiaire des financements. Ils peuvent être enregistrés par un opérateur, par la caisse ou lors de la réconciliation d’un extrait bancaire. La fiche indique si la facture est payée, mais le suivi détaillé du montant restant dû se fait à partir des financements et paiements associés.

Pour corriger une facture définitive erronée, il faut émettre une **note de crédit**. Celle-ci suit la même séquence de numérotation, génère les écritures inverses et lie les deux documents. La facture initiale passe alors au statut **Annulée**.
