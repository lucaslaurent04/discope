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
