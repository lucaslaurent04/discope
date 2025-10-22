# Doc Lathus

## Calendrier des Animateurs

Le **calendrier des animateurs** présente l’ensemble des activités liées aux **réservations** ou aux **camps**.

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

  - Si le champ **_Nécessite du personnel_** (`has_staff_required`) est activé → l’activité **doit être assignée** à un animateur.

  - Si le champ **_Exclusive_** (`is_exclusive`) est activé → l’activité **ne peut pas partager la même tranche horaire** avec une autre pour un même animateur.

---

### 🗓️ Événements

Les **événements** ajoutent des informations spécifiques à un moment donné pour un animateur (ex. congé, repos…).

#### Types d’événements :
- 🏖️ Congé → `leave`
- 🕓 Autre → `other`
- 😌 Repos → `rest`
- 🔁 Récupération → `time_off`
- 👨‍🏫 Formateur → `trainer`
- 🎓 Formation → `training`

#### Gestion :
- Les événements **ne peuvent pas être déplacés** par *drag and drop*.
- Ils sont modifiables depuis la **fiche événement** (accessible par clic sur l’événement).

#### Création :
- **Double-clic** sur une case du calendrier.
- **Depuis une fiche animateur/prestataire** `(Onglet) Événements` ou `(Onglet) Séries d’événements`.

> ⚡ Les **séries d’événements** permettent la création **rapide** d’événements récurrents entre deux dates.

---

### ⚙️ Paramètres

Deux paramètres permettent de configurer le calendrier :

#### 1. Activé le Calendrier Animateurs

Active ou désactive l’affichage du calendrier.

> sale.features.booking.employee_planning

#### 2. Filtrer les activités qui peuvent être assignées à un animateur/prestataire

Restreint l’assignation des activités aux animateurs et prestataires **habilités** à les accepter.

Configuration :
- **Animateurs (employés)** `Fiche employé → (Onglet) Modèle de produits`  
  → Configure les modèles d’activités qu’un animateur peut recevoir.
- **Prestataires** `Fiche prestataire → (Onglet) Modèle de produits`  
  → Configure les modèles d’activités qu’un prestataire peut recevoir.

> sale.features.employee.activity_filter

---

## Camps

L'application `Camps` permet la gestion des camps d'été du CPA Lathus.
Chaque camp a un thème et un tarif, des parents ou tuteurs peuvent y inscrire leurs enfants agés de 6 à 16 ans.

Les inscriptions peuvent être réalisées :
  - par les parents sur le site `www.cpa-lathus.asso.fr`
  - par les employés du CPA Lathus dans Discope (contact téléphone/mail avec un parent)

Il existe **deux types** de camps :

  - **Classique**
    - L'enfant est hébergé du dimanche soir au vendredi fin d'après-midi
    - L'enfant participe à des activités du lundi au vendredi


  - **Centre de vacances et de loisirs** (_CLSH_)
    - L'enfant n'est pas hébergé
    - L'enfant est inscrit par jour
    - Peut durer 4 à 5 jours, jamais durant le weekend

---

### Produits

Les produits de camps ne peuvent être utilisés que pour les inscriptions aux camps.

Une inscription liste les produits qui seront facturés, il existe 4 types de produits de camps :

  - **Classique**
    - L'inscription de l'enfant au camp `Camp complet`
      - Tarif séjour A
      - Tarif séjour B
      - Tarif séjour C
    - L'hébergement de l'enfant jusqu'au samedi matin `Samedi matin`
      - Fin séjour samedi matin
    - L'hébergement de l'enfant le weekend car il poursuit avec un camp la semaine suivante `Week-end`
      - Lier 2 séjours


  - **Centre de vacances et de loisirs** (_CLSH_)
    - L'inscription de l'enfant à une journée du camp `Camp à la journée`
      - Tarif CLSH journée

---

### Participants

Les **enfants** participent aux camps, il faut qu'un **tuteur principal** leur soit assigné et une **institution** peut être également assignée si besoin.

#### Enfants

La fiche d'un enfant permet de renseigné :
  - la liste de ses **compétences** (_nécéssaire à l'inscription à certains camps_)
  - s'il possède une **license de la fédération française d'équitation** (_nécéssaire à l'inscription à certains camps d'équitation_)
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
  - **Devoir** :
    - Doit fournir les documents nécessaires à l'inscription de l'enfant
    - Doit payer le montant de l'inscription


  - **Documents inscription** :
    - Recevra la confirmation de pré-inscription
    - Recevra la confirmation d'inscription


  - **Priorité contact** avec CPA Lathus, concernant `l'inscription` ou `l'enfant durant un camp` :
    - Son adresse est utilisée en priorité pour l'envoi de courier
    - Son adresse email est utilisée en priorité pour l'envoi de courier électronique
    - Son numéro de téléphone est utilisé en priorité

#### Tuteurs

Un tuteur est une personne avec une relation avec un enfant :
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