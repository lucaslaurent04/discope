# Doc Lathus

## Calendrier des Animateurs

Le **calendrier des animateurs** présente l'ensemble des activités liées aux **réservations** ou aux **camps**.

- **Axe vertical :** liste des **animateurs** et **prestataires** auxquels les activités peuvent être liées.
- **Axe horizontal :** les **dates** et **tranches horaires** (_matin, midi, soir_) correspondant aux moments des activités.

Le calendrier permet d'assigner des activités aux animateurs et prestataires, les activités pas encore assignées sont présentées sous le calendrier.

---

### 📍 Où le trouver ?

`Apps dashboard → Réservations → Calendrier Animateurs`

> 💡 **Astuce :** Le *Calendrier Animateurs* se trouve dans le **menu du haut**.

---

### 🎯 Activités

Une activité peut être liée à une **réservation** ou un **camp**.

**Règles :**
  - Si le champ **_Nécessite du personnel_** (`has_staff_required`) est activé → l'activité **doit être assignée** à un animateur.
  - Si le champ **_Exclusive_** (`is_exclusive`) est activé → l'activité **ne peut pas partager la même tranche horaire** avec une autre activité pour un même animateur.

---

### 🗓️ Événements

Les événements ajoutent des **informations spécifiques** à un moment donné pour un animateur (ex. congé, repos…).  
Ils peuvent également représenter **un groupe de camp** dont un animateur est responsable.

**Types d'événements :**
  - Info animateur :
    - 🏖️ Congé
    - 🕓 Autre
    - 😌 Repos
    - 🔁 Récupération
    - 👨‍🏫 Formateur
    - 🎓 Formation
  - Camp :
    - ⛺ Activité d'un camp (_l'animateur est responsable d'un groupe de camps durant la tranche horaire_)

Les événements **ne peuvent pas être déplacés** par *drag and drop*.

Ils sont modifiables depuis la **fiche événement** (accessible par clic sur l'événement).

**Création :**
  - **Double-clic** sur une case du calendrier.
  - **Depuis une fiche animateur/prestataire** `(Onglet) Événements` ou `(Onglet) Séries d'événements`.

> ⚡ Les **séries d'événements** permettent la création **rapide** d'événements récurrents entre deux dates.

---

### ⚙️ Paramètres

Deux paramètres permettent de configurer le calendrier :

#### 1. Activer le Calendrier Animateurs

Active ou désactive l'affichage du calendrier.

> sale.features.booking.employee_planning

#### 2. Filtrer les activités qui peuvent être assignées à un animateur/prestataire

Restreint l'assignation des activités aux animateurs et prestataires **habilités** à les accepter.

Configuration :
- **Animateurs (employés)** `Fiche employé → (Onglet) Modèle de produits`  
  → Configure les modèles d'activités qu'un animateur peut recevoir.
- **Prestataires** `Fiche prestataire → (Onglet) Modèle de produits`  
  → Configure les modèles d'activités qu'un prestataire peut recevoir.

> sale.features.employee.activity_filter

---

## Camps

L'application `Camps` permet la gestion des camps d'été du CPA Lathus.  
Chaque camp a un thème et un tarif, des parents ou tuteurs peuvent y inscrire leurs enfants âgés de 6 à 16 ans.

Les inscriptions peuvent être réalisées :
  - par les parents sur le site `www.cpa-lathus.asso.fr` (pour les camps classiques, pas les CLSH)
  - par les employés du CPA Lathus dans Discope (contact téléphone/mail avec un parent)

Il existe **deux types** de camps :

  - **Classique**
    - L'enfant est hébergé du dimanche soir au vendredi fin d'après-midi
    - L'enfant participe à des activités du lundi au vendredi

  - **Centre de vacances et de loisirs** (_CLSH_)
    - L'enfant n'est pas hébergé
    - L'enfant est inscrit par jour
    - Peut durer 4 à 5 jours, jamais durant le week-end

> **Notes** : Le nombre de places maximum dans un camp en égale à `Qté groupe * Max enfants`.
> Les inscriptions de status `Brouillon`, `Confirmée` et `Validée` sont prises en compte.

---

### Produits

Les produits de camps ne peuvent être utilisés que pour les inscriptions aux camps.

Une inscription liste les produits qui seront facturés. Il existe 4 types de produits de camps :

  - **Classique**
    - L'inscription de l'enfant au camp `Camp complet`
      - Tarif séjour A
      - Tarif séjour B
      - Tarif séjour C
    - L'hébergement de l'enfant jusqu'au samedi matin `Samedi matin`
      - Fin séjour samedi matin
    - L'hébergement de l'enfant le week-end, car il poursuit avec un camp la semaine suivante `Week-end`
      - Lier 2 séjours

  - **Centre de vacances et de loisirs** (_CLSH_)
    - L'inscription de l'enfant à une journée du camp `Camp à la journée`
      - Tarif CLSH journée

---

### Prix

Les prix des produits de camps peuvent être plus détaillés que les prix ordinaires, s'il s'agit d'un produit `Camp complet` ou `Camp à la journée`.

#### Camp complet

Ajout d'un champ `Classe de camp` qui permet d'appliquer un prix spécifique en fonction de la `Classe de camp` de l'inscription.

Classes de camp :
  - Autre
  - Habitants Vienne/Partenaires hors Vienne
  - Adhérents/Partenaires Vienne/Habitants des cantons

> **Note :** 3 prix sont donc nécessaires pour chaque produit `Camp complet`

#### Camp à la journée

Ajout d'un champ `Classe de camp` qui permet d'appliquer un prix spécifique en fonction de la `Classe de camp` de l'inscription.

Classes de camp :
  - Autre
  - Habitants Vienne/Partenaires hors Vienne

> **Note :** La classe `Adhérents/Partenaires Vienne/Habitants des cantons` n'est pas utilisée.
> Une inscription de cette classe utilise le prix de la classe la plus proche, donc `Habitants Vienne/Partenaires hors Vienne`.

Ajout des champs `Quotient familial min` et `Quotient familial max` qui permettent d'appliquer un prix spécifique en fonction du quotient familial de l'inscription.

Tranches quotient familial :
  - 0 - 700
  - 701 - 850
  - 851 - 1200
  - 1201 - 10000

> **Note :** 8 prix sont donc nécessaires pour chaque produit `Camp complet`

### Participants

Les **enfants** participent aux camps. Il faut qu'un **tuteur principal** leur soit assigné, et une **institution** peut également être assignée si besoin.

#### Enfants

La fiche d'un enfant permet de renseigner :
  - la liste de ses **compétences** (_nécessaires à l'inscription à certains camps_)
  - s'il possède une **licence de la Fédération Française d'Équitation** (_nécessaire à l'inscription à certains camps d'équitation_)
  - sa **classe de camp** (_permettant une réduction du prix d'inscription_)
    - `Autres` (prix de base)
    - `Habitants Vienne/Partenaires hors Vienne` (prix avantageux)
    - `Adhérents/Partenaires Vienne/Habitants des cantons` (prix le plus avantageux)
  - s'il est membre d'un **club CPA** (_permettant une réduction du prix d'inscription_)
    - Passe la classe de camp de `Autres` → `Habitants Vienne/Partenaires hors Vienne`
  - ses **tuteurs**, dont le principal
  - l'**institution** qui a sa charge

##### Tuteur principal

Le tuteur principal est celui qui prend en charge le contact avec le CPA Lathus concernant les inscriptions de l'enfant :
  - **Devoirs :**
    - Doit fournir les documents nécessaires à l'inscription de l'enfant
    - Doit payer le montant de l'inscription

  - **Documents inscription :**
    - Recevra la confirmation de préinscription
    - Recevra la confirmation d'inscription

  - **Priorité contact** avec le CPA Lathus, concernant `l'inscription` ou `l'enfant durant un camp` :
    - Son adresse est utilisée en priorité pour l'envoi de courrier
    - Son adresse email est utilisée en priorité pour l'envoi de courrier électronique
    - Son numéro de téléphone est utilisé en priorité

#### Tuteurs

Un tuteur est une personne ayant une relation avec un enfant :
  - Mère
  - Père
  - Tuteur légal
  - Membre famille
  - Responsable foyer
  - Conseil département
  - Garde d'enfants
  - Autre

Une fiche tuteur permet de consulter les informations de contact d'un tuteur d'un enfant.

#### Institutions

Une fiche d'institution permet de consulter les informations de contact d'une institution en charge d'un enfant.

---

### Modèles de camps

Base de configuration d'un camp :
  - **Produits :**
    - Camp classique :
      - Camp complet
      - Samedi matin
      - Liaison entre deux camps
    - Camp CLSH :
      - Journée camp
  - **Compétences requises :** compétences que l'enfant doit avoir pour s'inscrire au camp
  - **Documents requis :** documents nécessaires à l'inscription de l'enfant au camp
  - **Type de camp :**
    - Sport
    - Cirque
    - Culture
    - Environnement
    - Équitation
    - Accueil & Loisir
    - Autre
  - **Ratio employé :** nombre max d'enfants par groupe
  - **Quota ASE :** nombre max d'enfants de l'Aide Sociale à l'Enfance

---

### Camps

Un camp est créé à partir d'un modèle de camp qui peut être :
  - `classique` ou `CLSH`
  - d'un certain type (`Sport`, `Cirque`, …)
  - configuré avec certains produits
  - configuré avec certains documents requis
  - configuré avec certaines compétences requises

#### Workflow

Les statuts :
  - **Brouillon** : Le camp est encore en phase de configuration et tous ses champs peuvent être modifiés.
  - **Publié** : Le camp est publié, ses champs sont bloqués, mais de nouveaux groupes peuvent y être ajoutés pour augmenter le nombre de places disponibles.
  - **Annulé** : Le camp est annulé, ses inscriptions peuvent être annulées ou transférées vers un autre camp.

#### Groupes

Un groupe d'un camp peut accueillir une quantité maximale d'enfants et un animateur y est assigné comme responsable.  
Un groupe peut être ajouté tant que le camp n'a pas commencé, cela permet d'augmenter le nombre de places disponibles.

#### Activités

Les activités des groupes du camp sont générées à la création d'un groupe et sont décalées à la modification de la date de début du camp.

Pour chaque activité générée, il faut assigner un produit activité et, si requis, un animateur.  
Cela doit être fait avant le début du camp.

#### Repas

Les repas du camp sont générés quand le camp est publié et sont supprimés si le camp est annulé.

Une liste globale des repas se trouve dans `Apps dashboard → Camps → Repas`

---

### Inscriptions

Une inscription permet d'inscrire un enfant à un camp d'été.

#### Workflow

Les statuts :
  - **En attente** : En attente d'une place dans un camp.
  - **Brouillon** : En cours de création/modification, tous ses champs peuvent être modifiés.
  - **Confirmée** : Ses lignes et réductions/aides ne peuvent plus être modifiées, car son financement a été généré.
  - **Validée** : Les documents requis ont été reçus, mais pas nécessairement tous les paiements.
  - **Annulée** : L'inscription est annulée avec ou sans frais. Un financement positif ou négatif peut devoir être géré.

Flux normal : `Brouillon` (création) → `Confirmée` (récupération documents requis) → `Validée` (paiement avant début camp)

> 💡 **Astuce :** Une inscription confirmée peut être `Repasser en brouillon` afin de la modifier.

#### Restrictions

Un nombre d'inscriptions max par camp limite le nombre d'enfants acceptés. Un nouveau groupe de camp peut être créé pour ajouter des places.

Un nombre d'inscriptions ASE (Aide Sociale à l'Enfance) max par camp limite le nombre d'enfants ASE acceptés.

Pour être inscrit à un camp, un enfant doit respecter sa tranche d'âge à une année près. Donc un enfant de cinq ans peut être inscrit à un camp de 6 à 9 ans.

#### Lignes

Les lignes d'inscription listent les produits qui sont vendus. La ligne du produit du prix du camp "Tarif séjour X" ou "Tarif CLSH journée" est ajoutée directement à la création.

Pour une inscription à un `Camp classique`, la modification du champ "Week-end extra" affecte les lignes.  
Cela ajoute/retire/remplace les produits "Fin séjour samedi matin" et "Lier 2 séjours".

| Week-end extra                   | Présence ligne "Fin séjour samedi matin" | Présence ligne "Lier 2 séjours" |
|----------------------------------|:----------------------------------------:|:-------------------------------:|
| Aucun                            |                                          |                                 |
| Hébergement jusqu'à samedi matin |                    X                     |                                 |
| Hébergement entre 2 séjours      |                                          |                X                |

Pour une inscription à `Camp CLSH`, la modification des jours de présence affecte la quantité de la ligne du produit "Tarif CLSH journée".

#### Classe de camp

Une classe de camp est assignée à une inscription et permet de proposer un prix plus avantageux.

Classes de camp :
  - `Autre` (prix de base)
  - `Habitants Vienne/Partenaires hors Vienne` (prix avantageux)
  - `Adhérents/Partenaires Vienne/Habitants des cantons` (prix le plus avantageux)

Elle est récupérée de la `Classe de camp` de l'enfant concerné, mais peut être modifiée pour chaque inscription.

#### Quotient familial

Un quotient familial est assigné à une inscription et permet de proposer un prix plus avantageux.
Il est un indicateur de mesure des ressources mensuelles de la famille de l'enfant.

Le quotient familial est un **entier** d'une valeur de `0` à `5000`.

Il est définis manuellement et fourni par le tuteur principal de l'enfant.

#### Conseil d'entreprise

Un CE peut être assigné à une réservation, la classe de camp est alors améliorée de 1.

Si la classe de l'enfant est :
  - `Autre`, alors l'assignation d'un CE modifie la classe de camp à `Habitants Vienne/Partenaires hors Vienne`.
  - `Habitants Vienne/Partenaires hors Vienne`, alors l'assignation d'un CE modifie la classe de camp à `Adhérents/Partenaires Vienne/Habitants des cantons`.
  - `Adhérents/Partenaires Vienne/Habitants des cantons`, alors pas de changement, car il n'existe pas de meilleur classe de camp.

L'amélioration de la classe de camp de l'inscription va engendrer la selection d'un **tarif plus avantageux**.

#### Réductions & aides

Tant que l'inscription n'est pas confirmée, des réductions et aides peuvent être appliquées.

Type d'adaptateur de prix :
  - Autre `Réduction`
  - Réduction fidélité `Réduction`
  - Aide commune `Aide`
  - Aide communauté de communes `Aide`
  - Aide CAF `Aide`
  - Aide MSA `Aide`

**Réductions**

Les réductions "Autre" et "Réduction fidélité" affectent directement le prix de l'inscription en soustrayant un montant ou un pourcentage.  
Le **pourcentage** est seulement appliqué par rapport à la ligne de prix du camp et `ignore les lignes "Samedi matin" ou "Liaison 2 séjours"`.

**Aides**

Les aides "Aide commune", "Aide communauté de communes", "Aide CAF" et "Aide MSA" n'affectent pas directement le prix de l'inscription.  
Elles génèrent des paiements sur le financement créé lors du passage de l'état `Brouillon` → `Confirmé`, ce qui réduit le montant demandé aux parents de l'enfant.

> 📍 Les remboursements des aidants peuvent être demandés `Fiche aidant → Facturer/Facturer à l'année`

> 📍 Liste globale des aides fournies `Apps dashboard → Camps → Aides financières → Réductions aides`

#### Documents requis

La fiche d'inscription liste les documents **requis** afin de pouvoir **valider** l'inscription.  
Cette liste est créée en fonction de la configuration du **modèle de camp**.  
Il faut marquer les documents comme reçus quand ils le sont.

#### Inscription via site web

Une action Discope permet de récupérer les inscriptions depuis l'API du site web du CPA Lathus et les ajouter dans Discope.

Si le camp ciblé par une inscription a au moins une place libre, alors l'état de l'inscription est `Confirmée`.
Le champ "Week-end extra" peut être modifié pour une réservation confirmée si elle est externe.
Cela n'affectera pas les lignes de produit, mais bien les présences.
Il est possible de la `Repasser en brouillon` afin de la modifier si nécessaire.

Si le camp ciblé n'a pas de place libre, alors l'état de l'inscription est `En attente`.
Ensuite, les différentes possibilités :
  - L'inscription peut être `transférée`
  - Ou, l'inscription peut être `annulée`
  - Ou, un groupe supplémentaire peut être ajouté au camp afin de créer plus de places

Des messages d'alertes sont ajoutés à une inscription si des problèmes surviennent durant l'ajout de l'inscription ou si une incohérence est détectée.

Liste des alertes :
  - Week-end extra incohérent
    - Message: _Le week-end extra donné par l'API du site web www.cpa-lathus.asso.fr contient "Hébergement tout le weekend" et "Hébergement jusqu'à samedi matin"._
    - Code : `lodging.camp.pull_enrollments.weekend_extra_inconsistency`
  - Aidant non trouvé
    - Message : _L'aidant (commune) donné par l'API du site web www.cpa-lathus.asso.fr n'a pas été trouvé dans Discope._
    - (`lodging.camp.pull_enrollments.sponsor_not_found`)
  - CE non trouvé
    - Message: _Le CE (conseil d'entreprise) donné par l'API du site web www.cpa-lathus.asso.fr n'a pas été trouvé dans Discope._
    - Code : `lodging.camp.pull_enrollments.work_council_not_found`
  - CE mauvais code
    - Message: _Le code CE qui a été renseigné par le client ne correspond pas avec le code de conseil d'entreprise._
    - Code : `lodging.camp.pull_enrollments.work_council_wrong_code`
  - Prix incohérent
    - Message: _Le prix calculé par le site web est différent du prix calculé par Discope._
    - Code : `lodging.camp.pull_enrollments.price_mismatch`

> 💡 **Astuce :** Des informations supplémentaires sur les alertes peuvent avoir été ajoutées à la description de l'inscription.

#### Présences

Les présences de l'enfant sont générées quand l'inscription est `confirmée` et supprimées quand elle est annulée.

**Camp classique :**
  - Les présences sont générées du dimanche au vendredi
  - Une présence samedi est ajoutée si "Week-end extra" est `Hébergement jusqu'à samedi matin`
  - Des présences samedi et dimanche sont ajoutées si "Week-end extra" est `Hébergement tout le week-end`

> Note : Les présences supplémentaires pour samedi et dimanche concernent les jours suivant le camp, jamais les jours avant.

**Camp CLSH :**
  - Une présence est ajoutée pour chaque jour de présence de l'enfant
  - Une indication est ajoutée pour la garderie matin et/ou soir

> 📍 Liste globale des présences `Apps dashboard → Camps → Présences`

> 💡 **Astuce :** Si non-facturation d'un hébergement supplémentaire, modifier "Week-end extra" puis supprimer manuellement la ligne ajoutée automatiquement.

---

#### Envoi pré-inscription

Quand une inscription est à l'état `Confirmée`, il est possible d'envoyer la **pré-inscription** par e-mail au tuteur principal.

> 📍 Envoi pré-inscription `Apps dashboard → Camps → Inscriptions → Fiche inscription → Pré-inscription`

> 💡 **Astuce :** La *Pré-inscription* se trouve dans le **menu de droite**.

Le mail de pré-inscription comprend :
  - Le document PDF de pré-inscription
    - Liste les inscriptions (produits, prix) des enfants dont le tuteur principal est responsable, il est possible de ne sélectionner qu'un enfant specifique.
  - Le sujet du mail
  - Le contenu du mail
    - Demande de documents pour valider l'inscription :
      - la fiche sanitaire complétée et signée
      - la fiche renseignement complémentaire complétée et signée
      - la photocopie des vaccins de l'enfant
      - le test préalable aux pratiques des activités aquatique et nautique, seulement pour les séjours avec le logo de la vague
      - le règlement à l'ordre du CPA Lathus ou preuves
  - Les documents attachés :
    - Fiche sanitaire
    - Petit trousseau
    - Renseignement complémentaire
    - Test préalable pratique activité aquatique

Ce mail demande au tuteur principal de fournir les documents nécéssaire à l'inscription de l'enfant ainsi que le paiement.

> **Notes** : Une inscription peu être "Validée" même si tous les paiements n'ont pas encore été reçus.

#### Envoie confirmation

Quand une inscription est à l'état `Validée`, il est possible d'envoyer la **confirmation** par e-mail au tuteur principal.

> 📍 Envoi confirmation `Apps dashboard → Camps → Inscriptions → Fiche inscription → Confirmation`

> 💡 **Astuce :** La *Confirmation* se trouve dans le **menu de droite**.

Le mail de confirmation comprend :
  - Le document PDF de confirmation
    - Donne les informations précises sur l'inscription (prix, arrivée, départ _liaison avec autre séjour ou samedi matin_)
  - Le sujet du mail
  - Le contenu du mail
    - Donne les informations résumées sur l'inscription (nom enfant, numéro séjour, nom camp, dates)

Ce mail confirme au tuteur principal l'inscription de l'enfant au camp.

### Stats camps

#### Distribution enfants

Liste les camps entre les deux dates données et donne des informations sur les quantités d'enfants participants aux camps.

Informations :
  - Age (_list les âges, contient une valeur si `Par âge` activer_)
  - Qté garçons
  - Qté filles
  - Qté anciens
  - Qté nouveaux (_première inscription_)
  - Qté

> 📍 `Apps dashboard → Statistiques (Lathus) -> Stats Camps → Camps → Distribution enfants`

> 💡 **Astuce :** Il est possible de séparer `Par âge` pour avoir les informations séparées pour chaque âge des enfants.

> **Note :** Uniquement les inscriptions validées sont prises en compte.

#### Enfants par semaines

Liste les semaines entre les deux dates données et done des informations sur les quantités d'enfants participants aux camps.

Informations :
  - Qté semaine
  - Qté week-end (_si non CLSH et liaison entre 2 séjours_)

> 📍 `Apps dashboard → Statistiques (Lathus) -> Stats Camps → Enfants → Par semaines`

> **Note :** Uniquement les inscriptions validées sont prises en compte.

#### Inscriptions par régions

Liste les départements entre les deux dates données et donne des informations sur les quantités d'inscriptions aux camps.

Informations :
  - Qté

> 📍 `Apps dashboard → Statistiques (Lathus) -> Stats Camps → Inscriptions → Par régions`

> 💡 **Astuce :** Il est possible de séparer `Par commune (86, 87)` pour avoir les informations séparées pour chaque commune pour les départements 86xxx et 87xxx.

> **Note :** Uniquement les inscriptions validées sont prises en compte.

#### Inscriptions par tarifs

Liste les tarifs entre les deux dates données et donne des informations sur les quantités d'inscriptions aux camps.

Pour les camps `CLSH` la quantité de journées d'inscriptions est utilisées (_si 2 jours alors comptabilisé comme 2 inscriptions_).

Informations :
  - Qté

> 📍 `Apps dashboard → Statistiques (Lathus) -> Stats Camps → Inscriptions → Par tarifs`

#### Inscriptions par aides

Liste les aidants entre les deux dates données et donne des informations sur les quantités d'inscriptions aux camps et les montants accordés.

Informations :
  - Qté
  - Montant (_montant accordé par l'aidant_)

> 📍 `Apps dashboard → Statistiques (Lathus) -> Stats Camps → Inscriptions → Par aides`

#### Inscriptions par CEs

Liste les CEs entre les deux dates données et donne des informations sur les quantités d'inscriptions aux camps.

Informations :
  - Qté

> 📍 `Apps dashboard → Statistiques (Lathus) -> Stats Camps → Inscriptions → Par CEs`

#### Inscriptions par types de séjour

Liste les types de séjour entre les deux dates données et donne des informations sur les quantités d'inscriptions aux camps.

Types de séjour :
  - Camp
  - Camp CLSH (4 jours ou 5 jours)

Informations :
  - Qté

> 📍 `Apps dashboard → Statistiques (Lathus) -> Stats Camps → Inscriptions → Par types de séjour`

> 💡 **Astuce :** Il est possible de séparer `Par durée` pour avoir les informations séparées pour les camps CLSH 4 ou 5 jours.

#### Inscriptions par tranches d'âge

Liste les tranches d'âge entre les deux dates données et donne des informations sur les quantités d'inscriptions aux camps.

Tranches d'âges :
  - 6 - 9
  - 10 - 12
  - 13 - 16

Informations :
  - Qté

> 📍 `Apps dashboard → Statistiques (Lathus) -> Stats Camps → Inscriptions → Par tranches d'âge`

#### Inscriptions par mois

Liste les mois quand se déroule les camps entre deux dates données et donnes des informations sur les quantités d'inscriptions aux camps par status.

Informations :
  - Qté brouillon
  - Qté en attente
  - Qté confirmée
  - Qté validée
  - Qté annulée
  - Qté

> 📍 `Apps dashboard → Statistiques (Lathus) -> Stats Camps → Inscriptions → Par mois`

---
