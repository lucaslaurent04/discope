## Configuration d'une nouvelle instance de Discope

**Configuration de Sécurité :** Cette option permet d'établir les permissions et restrictions pour les utilisateurs assignés à un groupe. Elle se trouve dans l'option Base, puis dans Sécurité.

- **Utilisateurs :** Il est nécessaire de compléter les informations suivantes : sélectionner une identité existante, remplir l'email et le mot de passe, et assigner un nom d'utilisateur tout en choisissant la langue de préférence. Après la création d'un nouvel utilisateur, celui-ci doit être validé par un administrateur.
- **Groupes :** La configuration des groupes permet de gérer les accès des utilisateurs aux différentes applications. Chaque application peut contenir plusieurs groupes. Pour chaque groupe, il est essentiel de définir les utilisateurs. Par défaut, certains groupes sont créés lors de l'initialisation de l'application, mais de nouveaux groupes peuvent être ajoutés en indiquant un nom et une description.
- **Permissions :** Les permissions sont définies par l'identificateur du groupe et les droits qui lui sont attribués. Bien que les permissions soient généralement préétablies, l'administrateur a la possibilité de les ajuster si nécessaire.

**Configuration de l'organisation**

- **Organisation :** Par défaut, l'organisation est déjà créée avec l'identificateur 1. Il est nécessaire d'ajouter le nom, le type, l'email, le numéro de téléphone, le numéro d'entreprise, ainsi que l'adresse complète (pays, ville, rue, numéro et code postal).
- **Équipes de gestion :** L'équipe de gestion gère plusieurs centres et centralise la facturation ainsi que la comptabilité. Elle nécessite les informations suivantes : le libellé, qui correspond au nom du centre, le code de groupe, un identifiant unique du centre, et l'IBAN, soit le compte bancaire pour la facturation. Au moins une équipe de gestion doit être créée pour assurer la gestion financière.
- **Centres :** Le centre représente les lieux physiques où les réservations auront lieu. Il nécessite de compléter les informations suivantes : le nom du centre, l'abréviation du centre, l'équipe de gestion responsable, le type de séjour et l'organisation associée.
- **Unités locatives :**
  - **Unités locatives :** Cette configuration nécessite de compléter des informations précises pour chaque unité, telles que le libellé, le code unique, le statut (par exemple, "complètement occupé" ou "en ordre") et les actions requises (comme le nettoyage complet). D’autres détails incluent le type d'hébergement, le centre associé, la capacité d’accueil, ainsi que la possibilité de sous-louer ou non l’unité. Ces données garantissent une gestion optimale des ressources et de la logistique dans chaque centre.
  - **Catégorie d'unité locative :** La catégorie classe les types d'unités disponibles, comme les salles de réunion, les chambres ou les bâtiments. Chaque catégorie a un nom et une description définissant son utilisation, facilitant ainsi la gestion des unités locatives pour chaque catégorie.

**Configuration de la comptabilité** : Cela permet de définir le plan et les règles comptables. Cette section se trouve dans Finances/Compta.

- **Plan comptable** :Le plan comptable est associé à une organisation et regroupe toutes les règles comptables qui lui sont liées. Par défaut, un plan comptable est défini pour correspondre à l'organisation par défaut.
- **Règles de TVA** : Quatre règles comptables sont définies par défaut pour la TVA à zéro, six, douze et vingt-et-un pour cent. Il est possible de supprimer ou de créer de nouvelles règles. La configuration des règles de TVA peut être modifiée, mais il est nécessaire d'établir une règle comptable et de la définir correctement.
- **Règles comptables**: Il permet de définir et d'organiser les différentes catégories de transactions financières au sein de l'établissement. Chaque règle comptable est identifiée par un libellé et une description, correspondant à un type de vente spécifique. Cette classification favorise une gestion efficace des recettes et des dépenses, simplifiant ainsi le suivi financier et la création de rapports comptables précis

**Configuration de communication** 

* **Templates**: La configuration de template permet de créer des modèles d'emails à envoyer aux clients, comprenant une section pour l'objet et une pour le corps du message. Ces modèles sont associés à une catégorie spécifique et à un type, tel que devis, contrat ou facture. Par défaut, une catégorie de modèle est définie et les modèles peuvent inclure des fichiers joints spécifiés
* **Alertes**: La configuration de modèles d'alertes définit des alertes liées aux réservations, identifiées par un nom, un libellé, un type et une description.  Il est essentiel de compléter les informations du modèle, y compris le nom, le libellé affiché, le type d'alerte  et la description correspondante. Ces données garantissent une gestion efficace des réservations et informent rapidement les utilisateurs des actions nécessaires.
* **Emails (SMTP)**:  La configuration permet d'établir les paramètres nécessaires pour l'envoi d'emails via un serveur SMTP. Cette configuration inclut des éléments tels que l'hôte SMTP, le port, le nom d'affichage du compte, le nom d'utilisateur, le mot de passe et l'adresse email associée. De plus, il est important de fournir une adresse email pour signaler les abus.

**Configuration des paramètres :** Cette configuration se trouve dans l'option "Configuration", puis dans "Paramètres". Par défaut, certains paramètres sont déjà définis, tels que le format de date, le format d'affichage des prix, la présentation des numéros et les traductions dans différentes langues. Il est possible de modifier la valeur par défaut d'un paramètre directement dans la liste des valeurs. De plus, il est possible de créer un nouveau paramètre en spécifiant le package, le code, la section, le titre, le type, le contrôle de formulaire, ainsi que l'option de multilinguisme.

**Configuration de Ventes :** Cette option permet de définir les paramètres pour les réservations, les réductions et les produits automatiques.

- **Types de réservation :** Cette configuration permet d’établir différents types de réservations selon la clientèle ou l’organisation, ainsi que les délais d’expiration des options associées. Chaque type est identifié par un code spécifique et a un nombre de jours d’expiration unique pour l’option de réservation.
- **Clients :** Cette configuration gère les informations des clients, les catégories tarifaires et les tranches d’âge. En général, elle est établie par l’application Discope lors de l’initialisation, bien que des adaptations puissent être effectuées par la suite.
  - **Types de clients :** Cette classification permet de regrouper les clients selon leur statut juridique ou organisationnel, facilitant ainsi la gestion des relations commerciales et l’adaptation des services.
  - **Nature du client :** Elle permet de classer les clients en fonction de leur catégorie tarifaire et de leur type d’entité. Chaque type est identifié par un code et se voit attribuer une catégorie tarifaire spécifique, déterminant ainsi les tarifs applicables et les conditions de service.
  - **Catégories tarifaires :** Cette configuration définit des groupes distincts pour faciliter la gestion des tarifs et l’adaptation des services offerts, en fonction des réservations de chaque client ou groupe.
  - **Tranches d’âge :** Cette fonctionnalité permet de classer les participants d’une réservation selon leur tranche d’âge (Bébé 0-3, Maternelle 3-6, Primaire 6-12, Secondaire 12-26, Adulte 26-99) afin d’adapter les services et les tarifs proposés.

- **Réductions**

  - **Réduction** : La réduction permet d’offrir des avantages tarifaires aux clients selon des critères spécifiques. Chaque réduction se compose d’un libellé, d’une valeur, d’un type (pourcentage ou gratuité) et de conditions à remplir pour en bénéficier.

    **Liste de réductions** : Cette liste définit des remises pour différentes catégories tarifaires sur une période donnée. Chaque entrée inclut un libellé, une catégorie, des dates de validité, une classe tarifaire et des taux de remise minimum et maximum. De plus, la liste de réductions regroupe les différentes remises disponibles.

- **Produits automatiques :** La liste des produits est organisée par catégorie et par période précise, à partir d'une date de début et d'une date de fin. Elle inclut tous les produits devant être appliqués automatiquement à la réservation, si celle-ci correspond à la période spécifiée et aux conditions de chaque produit.

- **Saisons**: La saison est définie pour chaque année et catégorie. Dans une saison, chaque période est spécifiée avec une date de début et une date de fin, ainsi qu'un type de saison, qui peut être (Basse, Moyenne, Haute, Très Haute).  Les périodes doivent être créées de manière à s'étendre du 1er janvier au 31 décembre de chaque année, avec une durée d'un jour, commençant à 0 heures et se terminant à 23 heures. 

- **Plan de paiement**: Le plan de paiement définit les modalités de règlement pour les réservations. Pour chaque plan de paiement, il est nécessaire de préciser le type de réservation, la catégorie tarifaire et les échéances correspondantes.

**Configuration de produits**

* **Produits**: La configuration des produits permet d’associer chaque produit à un modèle spécifique, garantissant ainsi un SKU unique pour une identification précise. Chaque produit doit comporter un libellé descriptif, un SKU, un modèle de produit correspondant, ainsi que des informations clés telles que son statut de vente, sa possibilité d’être inclus dans un pack et la famille à laquelle il appartient. De plus, un produit peut regrouper plusieurs autres produits, formant ainsi un pack. Enfin, il peut avoir plusieurs prix selon différentes listes de prix.
* **Modelé de produit**: La configuration de Modèle de Produits permet de définir et organiser les types de produits dans l'organisation, en spécifiant leur libellé, type, famille et possibilité de vente. Elle inclut aussi des détails sur la comptabilisation, les statistiques de vente, et si le produit est un repas, collation ou logement. Un modèle peut avoir plusieurs variantes et être disponible pour plusieurs groupes.
* **Catégories**: La configuration de catégorie permet de regrouper les modèles de produits et les types de réservation. Elle inclut des familles telles que Séjours scolaires (CDV), Produits de caisse (POS) et SPORT (SPT). Chaque catégorie a une description spécifique pour faciliter la gestion
* **Familles**: La famille est le regroupement de produits présents dans tous les centres. Par défaut, une famille doit être créée dans laquelle seront regroupés tous les produits de l'organisation
* **Groupes**: Il est nécessaire de remplir les champs suivants : Nom du groupe de produits et Centre. La configuration de groupe permet d'organiser les modèles de produits et les produits.

**Configuration de prix**

* **Liste de prix** : La configuration de la liste de prix permet de gérer les tarifs applicables sur des périodes spécifiques et selon des catégories de services. La liste  doit être défini une fois par an.  Chaque entrée inclut un identifiant, un statut, des dates de validité et la durée, garantissant l'application des tarifs corrects pour  chaque catégorie de liste de prix. 

