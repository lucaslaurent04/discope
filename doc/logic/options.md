# Options : rappels et expiration

Une réservation au statut `option` peut avoir une date d'expiration (`date_expiry`) ou être sans échéance (`is_noexpiry`). Le rappel avant échéance et la notification d'expiration sont deux traitements distincts. L'envoi d'un devis au statut `quote` ne programme aucun rappel automatique.

## Paramètres

Ces paramètres (settings) se configurent dans **Configuration > Paramètres**, avec le package `sale` et la section `features`. Les noms complets sont indiqués ci-dessous.

| Setting | Valeur par défaut | Rôle |
| --- | --- | --- |
| `sale.features.option.validity_delay` | `10` | Délai de validité proposé à la mise en option, en jours. Le type de réservation ou la valeur saisie peut le modifier ; le délai est limité par le temps restant avant le séjour. |
| `sale.features.option.remind_auto` | `false` | Active la programmation d'un rappel lors de l'envoi d'une option. |
| `sale.features.option.remind_delay` | `5` | Nombre de jours calendaires avant `date_expiry` pour programmer le rappel. |
| `sale.features.option.expired_notify_auto` | `false` | Active la notification client lors du traitement de l'expiration effective. Indépendant du rappel. |
| `sale.features.booking.expired_option.revert_to_quote` | `true` | Détermine si l'option expirée repasse en devis. Si `false`, le statut reste `option` et une alerte interne signale l'expiration. |

Les anciens paramètres `sale.features.quote.remind_auto` et `sale.features.quote.remind_delay` sont conservés pour compatibilité, mais ne sont plus utilisés pour ces envois.

## Rappel avant échéance

Après l'envoi du document d'une réservation au statut `option`, un rappel est programmé si `option.remind_auto` est actif et si l'option a une échéance. La seule mise en option ne programme pas ce rappel.

La date de rappel est calculée à **minuit, N jours calendaires avant `date_expiry`**, où N correspond à `option.remind_delay`. L'exécution dépend du passage du planificateur de tâches. Par exemple, pour une échéance au 25 octobre et un délai de 5 jours, le rappel est prévu le 20 octobre à minuit. Si cet instant est déjà passé au moment de l'envoi du document, aucun rappel rétroactif n'est créé.

Chaque nouvel envoi remplace le rappel en attente : une réservation ne possède qu'une tâche de rappel. Celle-ci conserve le destinataire principal et la langue de l'envoi initial.

Avant l'envoi du rappel, le système vérifie que la réservation est toujours en `option`, que `is_noexpiry` est désactivé, que l'échéance n'a pas changé et n'est pas atteinte, et que `option.remind_auto` est toujours actif.

## Expiration effective

La mise en option programme séparément son traitement d'expiration. Lors de son exécution, le système vérifie le statut `option`, l'absence du marqueur `is_noexpiry`, l'échéance atteinte et sa correspondance avec l'échéance attendue par la tâche.

Le rappel en attente est alors supprimé. Si `option.expired_notify_auto` est actif, une notification est préparée pour le premier contact de la réservation disposant d'un e-mail, dans la langue de son identité (ou `DEFAULT_LANG` à défaut).

L'absence de destinataire ou de modèle est journalisée. Un échec de préparation ou de mise en file de l'e-mail ne bloque pas le traitement de l'expiration : les alertes internes et le retour éventuel en devis restent indépendants de cet envoi. En cas de retour en devis, le paramètre `free_rental_units` de la mise en option détermine si les unités locatives sont libérées.

## Modèles d'e-mails

Les deux modèles appartiennent à la catégorie assignée au centre (`template_category_id`). Chacun utilise le type `option` et comprend les parties `subject` (objet) et `body` (corps HTML).

| Modèle (type / code) | Contenu attendu |
| --- | --- |
| `option/reminder` | L'option est en attente de confirmation et expire prochainement le `{date_expiry}`. |
| `option/expired` | L'option a effectivement expiré le `{date_expiry}`. |

Les variables `{booking}`, `{center}`, `{date_from}`, `{date_to}` et `{date_expiry}` sont remplacées dans l'objet et le corps ; les dates sont affichées au format `jj/mm/aaaa`. La signature du centre est ajoutée au corps si elle est disponible. L'adresse de réponse et la copie cachée (`email_bcc`) proviennent de l'équipe de gestion du centre.

Le contenu de `option/reminder` doit annoncer une échéance future, et non une expiration déjà effective. Voir aussi [Modèles et e-mails](templates-and-emails.md).

## Tâches et changements de statut

Le rappel `booking.option.reminder.{id}` est annulé lors d'une nouvelle mise en option, d'un retour en devis, d'une confirmation (avec ou sans plan de paiement), d'une annulation ou du traitement effectif de l'expiration. Une nouvelle mise en option supprime aussi l'ancienne tâche `booking.option.deprecation.{id}` avant de la remplacer et annule les anciens rappels `booking.quote.reminder.{id}`.

Les contrôleurs `sale_booking_remind-option` et `sale_booking_update-booking` comparent l'échéance courante au paramètre `expected_date_expiry` enregistré dans leur tâche. Une modification d'échéance invalide donc une tâche existante, sans la reprogrammer automatiquement. Pour recréer le rappel, il faut renvoyer l'option ; la tâche d'expiration est recréée lors d'une nouvelle mise en option. Les anciennes tâches d'expiration sans ce paramètre restent compatibles, mais vérifient toujours que l'échéance courante est atteinte.

Le contrôleur `sale_booking_notify-option-expired` est appelé par le traitement d'expiration. L'ancien contrôleur `sale_booking_remind-quote` reste exécutable sans envoyer d'e-mail, afin de neutraliser les tâches historiques.

## Initialisation Kaleo

L'update `20261025090000_setup-option-reminders.php` prévoit `option.expired_notify_auto = true` si aucune valeur explicite n'existe, et complète les settings de rappel manquantes avec les valeurs `false` et `5`. Elle supprime les tâches historiques `booking.quote.reminder.*` et crée ou complète les deux modèles en français, anglais et néerlandais pour les catégories utilisées par les centres. Les valeurs et contenus déjà renseignés sont conservés ; l'update est rejouable.
