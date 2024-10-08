# Discope - Logiciel PMS hautement personnalisable
[![Build Status](https://circleci.com/gh/discope-pms/discope.svg?style=shield)](https://circleci.com/gh/discope-pms/discope)
[![License: AGPL v3](https://img.shields.io/badge/license-AGPL--v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
![GitHub commit activity](https://img.shields.io/github/commit-activity/m/discope-pms/discope)

## Présentation
Discope est un système de gestion de propriété (PMS) hautement personnalisable, initialement développée pour Kaleo ASBL, une organisation spécialisée dans la gestion de gîtes et auberges en Belgique. 

L'objectif de Discope est de centraliser et simplifier toutes les opérations liées à la gestion des hébergements, des réservations et des services associés, tout en assurant une interface intuitive pour ses utilisateurs. 

Discope est conçu pour répondre aux besoins spécifiques des centres d’hébergement en permettant une gestion simplifiée et centralisée, adaptée à la diversité des types de clients, des offres proposées et des types d'hébergements. 


### Fonctionnalités principales

- **Gestion des réservations** :
  - Prise en charge des réservations pour différents types de centres d’hébergement (gîtes de groupes, auberges).
  - Réservation par bâtiment, chambre, lit, ou unité locative.
  - Gestion des séjours scolaires et des packs de réservation (forfaits avec plusieurs services inclus).
  - Réservation anticipée avec tarification à confirmer pour des dates futures.

- **Gestion des tarifs et réductions** :
  - Tarification dynamique en fonction des saisons, de la durée du séjour, du nombre de personnes, et de la catégorie du client (écoles, associations, grand public).
  - Gestion des réductions automatiques en fonction de critères comme la durée du séjour, la fidélité du client, ou la période de l'année.
  - Application de réductions manuelles ou automatiques sur les produits et services réservés.

- **Comptabilité et facturation** :
  - Génération automatique des factures, gestion des acomptes et des paiements partiels.
  - Intégration des taxes locales, y compris la taxe de séjour.
  - Gestion des commissions pour les tour-opérateurs et les partenaires en ligne comme Booking.com ou Expedia.

- **Gestion des produits et services** :
  - Association de services et de consommables aux réservations (repas, animations, location de salles, etc.).
  - Suivi des consommations par unité locative (chambres, bâtiments) et par personne.
  - Gestion des stocks pour les consommables (ex. boissons, nourriture).

- **Packs et offres spéciales** :
  - Création et gestion de packs regroupant des services (hébergement + repas + animations) à des tarifs forfaitaires.
  - Packs spécifiques pour les chèques-cadeaux (Vivabox, Wonderbox) et les offres promotionnelles.

- **Gestion des ressources** :
  - Suivi des ressources intérieures et extérieures des centres (chambres, salles, réfectoires, etc.).
  - Réservation des ressources partagées (salles, équipements) entre plusieurs gîtes.

- **Rôles et utilisateurs** :
  - Gestion des utilisateurs avec différents rôles (administrateurs, gestionnaires de gîtes, personnel externe).
  - Contrôle des accès et des autorisations pour chaque utilisateur en fonction de son rôle.

- **Statistiques et rapports** :
  - Génération de statistiques sur les réservations, les catégories de clients, les ressources utilisées.
  - Suivi des performances financières des centres d’hébergement.

- **Intégration avec des agences de voyages en ligne (OTA)** :
  - Gestion des réservations effectuées via des plateformes telles que Booking.com ou Expedia avec calcul des commissions.



## Development guidelines

### Testing

To avoid side effects (test that works at the time of test creation but stops working later), when tests involve bookings, the bookings must be standardized, meaning they must follow the application logic:

* A booking must always be assigned a status.
* A booking must always be attributed to a client.
* There must be links to expected objects according to the status.
* A booking must always contain at least one service group.
* A service group must always contain at least one service line.

As a general rule, every booking created as part of a test must:  
* Pass the controller check corresponding to its status.
* Be viewable and editable via the front-end (when the rollback is commented out).