Au sein des différents workflows, des documents officiels sont générés
et envoyés automatiquement ou semi-automatiquement : devis, contrats,
factures.

Chaque centre dispose de ses propres contraintes concernant la mise en
page des templates.

En outre, il est nécessaire de pouvoir communiquer des informations
officielles avec des clients parlant différentes langues (`FR`, `EN` et `NL`).

Chaque centre dispose d'une signature email commune aux utilisateurs
qui réalisent des échanges (les utilisateurs disposent également de leur
propre signature individuelle).

Les templates (destinés aux emails ou aux documents) sont regroupés par
catégories. A chaque centre, il est possible d'assigner une catégorie
de templates.

## Rappels et expiration des options

Les modèles de type `option`, codes `reminder` et `expired`, distinguent le rappel avant échéance de la notification d'expiration effective. Ils comprennent les parties `subject` et `body` et utilisent les variables `{booking}`, `{center}`, `{date_from}`, `{date_to}` et `{date_expiry}`.

Le contenu du rappel doit annoncer une expiration prochaine. Les conditions d'envoi, les settings et le choix des destinataires sont décrits dans [Options : rappels et expiration](options.md).
