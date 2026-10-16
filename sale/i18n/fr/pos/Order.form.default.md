# Fiche d’une commande

Une commande regroupe les produits vendus pendant une session de caisse. Elle peut concerner un client de passage, être rattachée à une réservation ou servir à encaisser un financement existant.

### Informations générales

Le nom identifie la commande. La fiche indique la session de caisse concernée ainsi que, le cas échéant, le financement, la réservation ou la facture auxquels la commande est liée.

### Statut

Une commande passe par trois états :

- **En cours (pending)** : les lignes peuvent encore être préparées ou modifiées ;
- **Paiement (payment)** : le règlement est en cours, par exemple via un terminal ou un serveur de paiement ;
- **Payée (paid)** : le montant attendu a été réglé et la vente est clôturée.

### Session

La session associe la commande à une caisse ouverte et à sa période d’activité. La recherche avancée permet de filtrer les sessions afin de retrouver plus facilement celle dans laquelle la vente doit être enregistrée.

### Réservation et financement

Lorsqu’une commande encaisse un financement de réservation, son paiement alimente ce financement. Lorsqu’elle vend de nouveaux produits pour une réservation, les lignes peuvent être ajoutées comme services complémentaires afin de conserver le lien entre la vente, le séjour et la facturation.

### Lignes de commande et paiements

L’onglet **Lignes de commande** reprend les produits et quantités vendus. L’onglet **Paiements** détaille la répartition des montants et des moyens de paiement ; une commande peut être ventilée entre plusieurs paiements et plusieurs encaissements.

### Ticket, facture et correction

Une fois la commande payée, le ticket est accessible depuis le panneau latéral. Le lien de facturation indique si la commande a été reprise dans une facture. Une commande clôturée peut encore être corrigée tant que la facture correspondante n’a pas été émise ; après émission, la correction doit suivre le workflow comptable prévu.
