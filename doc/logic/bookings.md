## Client

Une réservation est toujours imputée à un client.

La recherche d'un client parmi les clients existants se fait sur base
des valeurs pour les champs nom, email, téléphone (avec une tolérance
sur la casse et les accents).

Note : L'identification se fait sur base du client qui prend la
réservation (et non sur le paiement). D'un point de vue logique le
client est l'organisation avec laquelle l'organisation est en contact
pour une réservation, indépendamment de l'organisation qui règlera le
montant des prestations qui en résultent.

Chaque client est rattaché à une identité qui contient ses informations
de contact.

-   Fiche identité : possibilité de renseigner l'organisation parente
    (identité parente) - ne fonctionne qu'avec les personnes morales

-   Lorsqu'on crée une réservation, on précise toujours l'identité qui
    doit être le client ; si cette identité est liée à une identité
    parente, le client est l'identité parente

-   Lors de la génération de documents, si l'identité du client ne
    correspond pas à l'identité de la réservation, cette dernière est
    ajoutée dans le champ ATTN

#### Identification des doublons

Une identité présente un risque de doublon si elle partage le même nom &
prénom ou nom légal et pays qu'une identité existante plus ancienne.

Dans ce cas, un marqueur "risque de doublon" est activé, et la
première identité parmi les doublons possibles est renseignée dans le
champ "Doublon possible".

Les tests sont faits indépendamment de la casse (minuscules/majuscules)
et des accents. Par contre, il n'y a pas de tests phonétiques (Dupond
n'est pas une correspondance pour Dupont).

Le possible doublon permettre également de vérifier si la création
d'une nouvelle identité est nécessaire ou non.

Tant que le marqueur "risque de doublon" est activé, il n'est pas
possible de sauvegarder la nouvelle identité.

-   S'il s'agit d'un faux-positif (c'est-à-dire si cette identité
    n'est pas un doublon), l'utilisateur doit simplement décocher le
    marqueur "risque de doublon" avant de sauvegarder.

-   S'il s'agit effectivement d'un doublon, l'utilisateur sait alors
    quelle identité sélectionner : il faut alors fermer la vue de
    création (en ignorant les changements) et sélectionner l'identité
    correspondante.

Sur la fiche d'une identité, il est possible de visualiser la liste de
toutes les identités identifiées comme doublon possible.

Pour la gestion des doublons "historiques", l'approche recommandée
est la suivante :

-   Dans le cas de faux-positifs (noms très proches) : forcer une
    différence firstname, lastname, legalname, address

-   Dans le cas de réels doublons : archiver la ou les identités les
    plus anciennes qui n'ont pas de réservation en cours

## Contacts

Exemple : L'organisation des camps scouts implique souvent beaucoup de
personnes différentes (par exemple 4 adresses emails et 4 numéros de
téléphone).

Un rôle peut être assigné à chaque contact :

-   Réservation : contact pour les échanges liés à la réservation (ex.
    prof d'une école)
-   Factures : contact à qui envoyer les factures (ex. secrétariat de
    l'école)
-   Contrats : contact à qui envoyer les contrats (ex. directeur de
    l'école)
-   Séjour : personne qui fera partie des participants au séjour

Pour les contacts additionnels, seuls les emails sont obligatoires
(numéro de téléphone souhaités).

Il y a une distinction entre les contacts liés à un client (identité) et
les contacts liés à une réservation. La logique est la suivante :

-   Les contacts peuvent être créés dans la fiche identité du client
-   Lorsqu'on crée une réservation, les contacts du client sont
    automatiquement importés
-   On peut ajouter des contacts arbitraires spécifiques à une
    réservation (qui ne seront pas visibles dans la liste des contacts
    du client)
-   Lors de la création d'une réservation, il y a toujours au minimum
    un contact qui correspond à l'identité du client et qui est assigné
    au type 'réservation'.

Les adresses email des contacts sont utilisées comme destinataires pour
l'envoi des messages emails (et de leurs pièce jointes) :

-   Les contacts de type 'contrat' sont automatiquement ajoutés comme
    destinataires des emails de contrats.
-   Les contacts de type 'facture' sont automatiquement ajoutés comme
    destinataires des emails de facture.

## Séjour

La base organisationnelle de toute transaction est le "séjour" : un
client réserve une ou plusieurs ressources et services dans un Centre
donné, au sein d'une période prédéfinie.

Une réservation consiste en 3 listes distinctes, complémentaires, et en
cascade :

-   La liste de produits et services (séjours et suppléments) qui sont
    réservés avec les quantités et tarifs associés ;

-   La liste des consommations qui en découle, ventilée par jour, date
    et quantité (utilisée pour l'organisation et la communication :
    partenaires pour les animations, organisation des repas, planning
    des ménages, disponibilités des unités locatives)

-   La liste des personnes bénéficiaires de ces services
    ("composition" : utilisée pour le calcul des produits à la
    personne ; pour les éventuelles taxes ; pour les statistiques ;
    nécessaire d'un point de vue légal)

Chacune de ces listes peut être générée semi-automatiquement, mais peut
être modifiée manuellement.

Conséquences :

-   Les ressources disponibles d'un centre doivent être décrites de
    manière exhaustive (par unité locative), avec leur capacité
    respective.

-   Lors d'une réservation, chaque ligne de service de type "nuitée"
    (service planifiable) doit être liée à une unité locative (la
    réconciliation est automatique et peut être ajustée manuellement)

-   Lors de la réservation d'une unité locative, la capacité de
    l'unité est utilisée pour générer un nombre de personnes par défaut

-   La liste des services est générée automatiquement sur base du nombre
    de personnes renseignées

Lors de la création d'une réservation, il est possible d'ajouter :

-   Soit des produits individuels (1 nuitée, 1 repas) ;

-   Soit des séjours : pour ajouter un séjour, il est possible soit de
    sélectionner un type de séjour existant (pack) soit de le composer
    manuellement.

Il est possible de définir les valeurs globales pour une réservation
(qui serviront de valeurs par défaut lors de l'ajout d'éléments à la
réservation) :

-   Nombre de personnes

-   Date début

-   Date de fin

## Services et groupes de services

Sur les devis, contrats et factures, pour grouper plusieurs prestations
sur une seule ligne (dont le libellé est modifiable), il est possible de
créer des regroupements.

Les regroupements sont établis lors de l'édition d'une réservation et
dupliqués par la suite, lors de la création des contrats et des
factures, mais peuvent également être modifiés manuellement.

Lorsque des regroupements sont définis, il reste possible de présenter
ces documents de manière détailles (en utilisant des templates ne tenant
pas compte des regroupements).

Le total d'une ligne de regroupement correspond à la somme des totaux
des produits regroupés, selon la formule :

-   Prix * réduction * (qté - gratuités)

-   Les réservations sont organisées en séjours

-   Toute demande est toujours nécessairement liée à un séjour

-   Un séjour est nécessairement sollicité par un contact, de la part
    d'un client : le client est l'entité légale qui s'engage dans la
    réservation du séjour et qui est responsable du paiement des
    prestations qui en découlent.

-   Les types de séjours sont 

À un séjour sont associées des prestations :

-   Une prestation est un produit associé à une quantité

-   Certaines prestations sont ajoutées lors d'un premier contact (par
    exemple demande de devis) pour demande de réservation

-   Une première liste de prestations est finalisée lors d'une demande
    ferme de réservation qui aboutit à un contrat

-   Des prestations peuvent être ajoutées à la réservation, même si un
    contrat a déjà été signé (il est possible mais pas indispensable de
    générer un nouveau contrat pour signature)

-   Des prestations peuvent être ajoutées en cours de séjour

-   Certaines prestations impliquent la réservation d'unités locatives
    et donnent lieu à des lignes de planning (qui ne sont pas
    nécessairement reprises dans le contrat)

Le suivi d'un séjour est toujours est réalisé au niveau des prestations
(desquelles découlent le planning et la facturation). Et toutes les
prestations doivent être prises en charge dans le flux de paiement (même
si elles sont offertes) :

-   Chaque prestation doit conserver les informations

    -   De statut de paiement

    -   Un lien vers la ligne de facturation correspondante

-   Le total des lignes de facturation (indépendamment du nombre de
    factures) doit correspondre au total des prestations non-offertes
    associées au séjour (qui peut regrouper plusieurs réservations)

-   Un tableau permet de voir quelles sont les prestations qui n'ont
    pas encore été facturées pour un séjour donné (et comment les
    prestations déjà facturées l'ont été : quelle facture, quelle
    date).

## Calcul de la quantité

Pour les produits comptabilisés au logement qui peuvent induire une
répétition implicite (capacité inférieure au nombre de personnes), et
qui sont ou non des unités locatives (ex. nettoyage chambre) :

-   Si le produit n'est pas répétable

    -   Si une capacité est définie, la quantité assignée correspond à
        ceil(\$nb_pers / \$capacity)

    -   Sinon la quantité est à 1

-   Si le produit est répétable

    -   Si une capacité est définie, la quantité assignée correspond à
        ceil(\$nb_pers / \$capacity) multiplié par le nombre de
        répétitions (nombre de nuits ou de jours)

    -   Sinon la quantité est au nombre de répétitions (nombre de nuits
        ou de jours)

Tableau récapitulatif du mode de comptabilisation des quantités en
fonction des différentes situations possibles :

<table>
<colgroup>
<col style="width: 13%" />
<col style="width: 10%" />
<col style="width: 11%" />
<col style="width: 9%" />
<col style="width: 4%" />
<col style="width: 2%" />
<col style="width: 2%" />
<col style="width: 18%" />
<col style="width: 18%" />
<col style="width: 8%" />
</colgroup>
<thead>
<tr class="header">
<th><blockquote>
<p><strong>Ligne de pack avec qté spécifique ?</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Produit avec durée ?</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Séjour ?</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Événement ?</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>(ni séjour ni événement)</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Répétable ?</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Capacité ?</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Calcul qté si<br />
"à la personne"</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Calcul qté si<br />
"au logement"</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Calcul qté si<br />
"à l'unité"</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td>facteur = qté spécifique</td>
<td>[/]</td>
<td>[/]</td>
<td>[/]</td>
<td>[/]</td>
<td>[/]</td>
<td>[/]</td>
<td>facteur</td>
<td>facteur</td>
<td>facteur</td>
</tr>
<tr class="even">
<td>non</td>
<td>facteur = durée</td>
<td>[/]</td>
<td>[/]</td>
<td>[/]</td>
<td>non</td>
<td>[/]</td>
<td>nb_pers</td>
<td>facteur</td>
<td>facteur</td>
</tr>
<tr class="odd">
<td>non</td>
<td>facteur = durée</td>
<td>[/]</td>
<td>[/]</td>
<td>[/]</td>
<td>oui</td>
<td>non</td>
<td>facteur x nb_pers</td>
<td>facteur</td>
<td>facteur</td>
</tr>
<tr class="even">
<td>non</td>
<td>facteur = durée</td>
<td>[/]</td>
<td>[/]</td>
<td>[/]</td>
<td>oui</td>
<td>oui</td>
<td>facteur x ceil(nb_pers/capacité)</td>
<td>facteur</td>
<td>facteur</td>
</tr>
<tr class="odd">
<td>non</td>
<td>non</td>
<td>facteur = nb_nuits</td>
<td>[/]</td>
<td>[/]</td>
<td>non</td>
<td>oui</td>
<td>nb_pers</td>
<td>ceil(nb_pers/capacité)</td>
<td>1</td>
</tr>
<tr class="even">
<td>non</td>
<td>non</td>
<td>facteur = nb_nuits</td>
<td>[/]</td>
<td>[/]</td>
<td>oui</td>
<td>non</td>
<td>facteur x nb_pers</td>
<td>facteur</td>
<td>facteur</td>
</tr>
<tr class="odd">
<td>non</td>
<td>non</td>
<td>facteur = nb_nuits</td>
<td>[/]</td>
<td>[/]</td>
<td>oui</td>
<td>oui</td>
<td>facteur x ceil(nb_pers/capacité)</td>
<td>facteur x ceil(nb_pers/capacité)</td>
<td>facteur</td>
</tr>
<tr class="even">
<td>non</td>
<td>non</td>
<td>[/]</td>
<td>facteur =<br />
nb_nuits+1</td>
<td>[/]</td>
<td>non</td>
<td>[/]</td>
<td>nb_pers</td>
<td>[/]</td>
<td>1</td>
</tr>
<tr class="odd">
<td>non</td>
<td>non</td>
<td>[/]</td>
<td>facteur =<br />
nb_nuits+1</td>
<td>[/]</td>
<td>oui</td>
<td>non</td>
<td>facteur x nb_pers</td>
<td>facteur</td>
<td>facteur</td>
</tr>
<tr class="even">
<td>non</td>
<td>non</td>
<td>[/]</td>
<td>facteur =<br />
nb_nuits+1</td>
<td>[/]</td>
<td>oui</td>
<td>oui</td>
<td>facteur x ceil(nb_pers/capacité)</td>
<td>facteur</td>
<td>facteur</td>
</tr>
<tr class="odd">
<td>non</td>
<td>non</td>
<td>non</td>
<td>non</td>
<td>facteur = 1</td>
<td>[/]</td>
<td>[/]</td>
<td>facteur x nb_pers</td>
<td>facteur</td>
<td>facteur</td>
</tr>
</tbody>
</table>

Les consommations qui se répètent chaque jour du séjour sont : les
**unités locatives**, les **logements**, les **repas** ainsi que les
produits dont le modèle est marqué comme « **répétable** ».

#### Variation des quantités

Certains services planifiables sont répétés automatiquement pour une
certaine durée et un certain nombre de personnes. Il est parfois
nécessaire de modifier le nombre de services planifiés pour un jour
spécifique, indépendamment de la durée du séjour et du nombre de
personnes.

Deux situations :

1.  À la création du devis

2.  Une fois que le contrat a été validé (avant ou pendant le séjour)

**Notes concernant le stockage des variations de quantités (`qty_vars`) :**

Les utilisateurs ont la possibilité, pour chaque jour du séjour, de
préciser une quantité spécifique du produit concerné, indépendamment du
nombre de personnes du séjour et de la quantité calculée.

En cas de modification du nombre de personnes assigné au séjour :

-   Si une quantité a été forcée manuellement pour un jour et un service
    donnés, elle est conservée telle quelle.

-   Dans les autres cas, les variations sont recalculées automatiquement
    en fonction du nouveau nombre de personnes.

Par exemple, si on a 4 personnes pour deux jours et qu'on a forcé les
quantités d'une ligne à 2 personnes le premier jour et 0 le second, en
augmentant le nombre de personnes à 5, on aura toujours 2 personnes pour
le premier jour et 0 pour le second.

#### Limitations

-   Ce mécanisme est basé sur la logique des lignes de réservation, et
    est appliqué lors des modifications du nombre de personnes assignées
    à un séjour / groupe de services.

-   Les variations ne sont possibles que pour les services comptabilisés
    à la personne.

-   Note: lorsqu'il y a plusieurs lignes d'assignation de tranche
    d'âge, la modification directe du nombre de personnes du séjour est
    désactivée (et passe nécessairement par la modification du nombre de
    personnes par tranche d'âge).

## Calcul du prix

Le calcul du prix d’une réservation ou d’une facture doit suivre des règles précises afin d’assurer l’exactitude des montants pour le client et la comptabilité.
Chaque produit ou service est facturé selon son prix unitaire, sa quantité et le taux de TVA applicable.
Les montants HTVA et TTC sont calculés avec des règles d’arrondi spécifiques pour éviter les écarts.
Cette section décrit la structure des lignes de réservation/facture, le calcul des totaux, et la méthode pour appliquer correctement la TVA par taux.

### Structure

Une réservation ou facture est composée de plusieurs **lignes**, chaque ligne représentant un produit ou un service.  
Chaque ligne contient les informations suivantes :

- **Produit** : nom ou référence du produit/service.
- **Prix unitaire** : prix HTVA d'une unité.
- **Quantité** : nombre d’unités commandées.
- **Taux de TVA** : pourcentage de TVA applicable à la ligne.
- **Total HTVA** : prix total hors taxes pour la ligne.
- **Total TTC** : prix total toutes taxes comprises (à titre indicatif).

### Calculs

#### Calcul du prix HTVA d’une ligne

- Le **prix unitaire** d’un produit peut contenir jusqu’à **4 décimales**.
- Le **total HTVA** d’une ligne est calculé ainsi :

    `Total HTVA = Prix unitaire × Quantité`

- Le total HTVA est ensuite **arrondi à 2 décimales**.
- Le **total TTC** d’une ligne est calculé à partir du total HTVA et du taux de TVA, mais est fourni **uniquement à titre indicatif**.

#### Calcul de la TVA

- La TVA est calculée **par taux de TVA**, pour garantir une ventilation correcte des montants par taux.
- Le total TVA d'un taux est **arrondi à 2 décimales**.
- Pour chaque taux de TVA, le montant de TVA est calculé ainsi : 

    `TVA (par taux) = Somme des totaux HTVA des lignes avec ce taux × Taux de TVA`

- Cette méthode permet d’obtenir le montant exact de TVA à appliquer pour chaque catégorie de produit ou service.

#### Calcul des totaux de la facture

- Le **total HTVA de la facture** correspond à la somme des totaux HTVA de toutes les lignes.
- Le **total TTC de la facture** est calculé en additionnant le total HTVA et la TVA totale (agrégée par taux).

### Exemple : simple

Supposons une facture avec deux lignes :

| Produit   | Prix unitaire | Quantité | Taux TVA | Total HTVA | Total TTC |
|-----------|---------------|----------|----------|------------|-----------|
| Produit A | 10,1234 €     | 2        | 20%      | 20,25 €    | 24,30 €   |
| Produit B | 5,5678 €      | 3        | 10%      | 16,70 €    | 18,37 €   |

- **Total HTVA facture** = 20,25 + 16,70 = 36,95 €
- **TVA 20%** = 20,25 × 0.20 = 4,05 €
- **TVA 10%** = 16,70 × 0.10 = 1,67 €
- **Total TTC facture** = 36,95 + 4,05 + 1,67 = 42,67 €

Cette organisation permet de calculer correctement les totaux tout en conservant la précision sur les lignes individuelles.

### Exemple : différence entre somme des lignes TTC et calcul global

Supposons une facture avec **3 lignes**, chaque ligne ayant un prix et un taux de TVA différents :

| Produit   | Prix unitaire | Quantité | Taux TVA | Total HTVA | Total TTC |
|-----------|---------------|----------|----------|------------|-----------|
| Produit A | 0,99 €        | 1        | 20%      | 0,99 €     | 1,19 €    |
| Produit B | 1,49 €        | 1        | 10%      | 1,49 €     | 1,64 €    |
| Produit C | 2,33 €        | 1        | 20%      | 2,33 €     | 2,80 €    |

#### Somme des lignes TTC

1,19 + 1,64 + 2,80 = **5,63 €**

#### Calcul global par taux de TVA

1. Somme HTVA par taux :

    - TVA 20% : 0,99 + 2,33 = 3,32 €
    - TVA 10% : 1,49 €

2. Montant TVA par taux :

    - TVA 20% : 3,32 × 0.20 = 0,664 → arrondi à 0,66 €
    - TVA 10% : 1,49 × 0.10 = 0,149 → arrondi à 0,15 €

3. Total TTC global :

    - Total TTC = Somme HTVA + Somme TVA = (0,99 + 1,49 + 2,33) + (0,66 + 0,15) = 4,81 + 0,81 = **5,62 €**

#### Conclusion

- **Somme des lignes TTC arrondies** : 5,63 €
- **Calcul global TTC par taux de TVA** : 5,62 €

> La différence vient de l’arrondi appliqué sur chaque ligne TTC vs. l’arrondi appliqué après la somme par taux.  
> C’est un comportement normal dans la comptabilité et c’est pour cela que la somme des lignes TTC est **à titre indicatif**.


## Consommations : Informations complémentaires

Des descriptions et commentaires peuvent être appliqués soit au niveau
de la réservation, soit au niveau des consommations.

Exemples :

-   Pour les repas : nombre de repas 2 services, 3 services nombre de
    repas spécial sans allergène
-   Assignable globalement (tous les repas), par type de produit (repas
    matin) ou par jour

#### Prolongation d'un séjour

Il y a deux situations pour prolonger un séjour :

1.  **Avant checkin :** Pour faire des modifications avant le checkin il
    faut obligatoirement repasser en devis.

2.  **Après checkin** (en cours de séjour) : On peut ajouter n'importe
    quel type de service, en ajoutant un groupe de service
    supplémentaire. Les groupes de services supplémentaires peuvent
    également être des séjours avec des services qui impliquent des
    consommations (repas et logement).

#### Compositions

Dans le cadre de réservation de groupes, on ne connait pas toujours le
nombre de personne à l'avance : réception de la composition à l'arrivée
des personnes.

-   Une composition sommaire est établie par chambre (note: certaines
    écoles réservent 1-2 ans à l'avance)

-   Possibilité de modifier la composition après la réservation

-   Répartition dans les chambres (par celui qui fait la réservation),
    par exemple filles / garçons

-   La composition est affinée lors des échanges avec le groupe (pour
    bloquer le nombre de personnes nécessaires par unité locative)

Un support est prévu pour :

-   La gestion de groupes répartis dans plusieurs unités locatives

-   Les groupes dont une partie est en chambre collective et d'autres
    en chambre privée (ex. 180 personnes à répartir dans plusieurs
    unités locatives)

#### Consommations (Planning)

Lors d'une réservation, il faut établir :

-   La liste des produits

-   Renseigner le nombre de personnes qui séjournent (légalement, lors
    du check-in, la signalétique minimum est : prénom nom, date de
    naissance -- le système permet les encodages partiels)

-   Les dates du séjour

-   La liste des repas qu'elles prendront (avec les heures si possible)

-   La liste des services auxquels elles souscrivent et des activités
    auxquelles elles prendront part (avec les dates et heures, qui ne
    sont pas nécessairement celles du séjour)

Lors de l'ajout de produits de type services planifiables (nécessitant
des dates), ce sont les dates du séjour qui sont utilisées par défaut.

Les dates renseignées sont ensuite utilisées pour générer le planning
des services associés.

Pour les produits forfaits, la prestation est découpée en une série de
consommations. Les consommations permettent de **planifier** la
réservation et la disponibilité des ressources (salles, logements,
animations, ...). Une ligne de consommation correspond toujours à un
intervalle de temps (généralement celui de la location du bien ou du
service considéré).

Cette liste de consommations est toujours rattachée aux prestations,
même lorsque le client renonce à l'une ou l'autre des consommations.

Dans le cas d'un produit comptabilisé au logement, un champ `nb_pers`
permet de générer une liste de services et une composition par défaut
(la valeur peut être définie manuellement, et est retrouvée sur base de
la capacité associée au produit).

Il y a deux types de produits (/ packs) vendus au logement :

1)  Les produits relatifs à une unité locative spécifique

2)  Les produits relatifs à une catégorie d'unités locatives

Dans le premier cas, l'unité locative est directement associée au
produit. Dans le second cas, la première unité locative correspondante
disponible est assignée à la réservation ; et l'opérateur peut
effectuer manuellement l'assignation à une autre unité locative
disponible (sur base du produit sélectionné).

Le nombre de nuit correspond à l'intervalle fermé à gauche et ouvert à
droite, ayant pour bornes la date d'arrivée et la date de départ (=
date d'arrivée + dates du séjour). Le nombre de jours inclus également
la date de départ.

Les arrivées se font en principe après 12h00 mais il est possible de
modifier l'heure d'arrive pour permettre l'accueil d'une réservation
plus tôt dans la journée, même si la chambre ou les lits ne sont pas
encore disponibles.

Si le départ se fait après 12h00, un jour de plus est comptabilisé
(nuitée + repas de midi \[si applicable\] + repas soir \[si
applicable\])

#### Assignation des unités locatives

Récapitulatif du fonctionnement des modifications automatiques par type
d'action :

**1.- Modification au niveau d'une réservation**

-   Modification des dates

    -   La modification des dates d'une réservation est seulement
        possible lors de la création. Par la suite, les dates et heures
        de la réservation sont déterminées par les groupes de services
        réservés.

**2.- Modification au niveau d'un séjour**

-   Modification des heures d'arrivée/départ

    -   Les heures d'arrivées/départ de la réservation sont mise à jour

-   Modification du pack (modification)

    -   Les lignes de produits qui ne font pas partie du nouveau pack
        sont conservées, les autres sont réinitialisées
    -   Toutes les assignations d'unités locatives sont réinitialisées
        (sauf si elles sont verrouillées)
    -   Si le pack se rapporte à une tranche d'âge spécifique, les
        tranches d'âge sont réinitialisées et le nombre de participants
        du séjour est assigné à la tranche d'âge spécifiée
    -   Si le pack se rapporte à un modèle de produit avec une capacité
        spécifique, le nombre de personne du séjour est mis à jour

-   Modification du nombre de personnes du séjour

    -   S'il n'y a qu'une tranche d'âge, la tranche d'âge est
        assignée au nouveau nombre de participants
    -   S'il y a plusieurs tranches d'âge et que le total des
        participants par tranche d'âge ne correspond pas au nouveau
        nombre de personnes du séjour, la modification est refusée (dans
        ce cas, il faut soit adapter le nombre de participants via les
        tranches d'âges, soit supprimer certaines tranches d'âge pour
        n'en laisser qu'une)
    -   Toutes les assignations d'unités locatives sont réinitialisées
        (sauf si elles sont verrouillées)
    -   Les lignes de produits sont adaptées en fonction des tranches
        d'âges auxquelles elles se rapportent
    -   Le nombre de participants total de la réservation est mis à jour

-   Modification du nombre de personnes d'une tranche d'âge

    -   Le nombre de personnes du séjour est mis à jour en conséquence
    -   Toutes les assignations d'unités locatives sont réinitialisées
        (sauf si elles sont verrouillées)

**3.- Les assignations automatiques des unités locatives**

-   Se font :

    -   En cas de changement de pack =\> reset complet sur base des
        tranches d'âge
    -   En cas de modification du nb_pers du groupe (direct ou via les
        tranches d'âge) =\> mise à jour en fonction du nb_pers (pas des
        tranches d'âge)
    -   En cas d'ajout ou de suppression d'un linge réserve

-   Ne se font pas

    -   En cas de changement manuel de la qty d'une ligne ou des
        qty_vars
    -   Si on a activé le verrou des unités locatives
    -   Si l'équipe de gestion est configurée pour une assignation
        manuelle

Dans le cas où l'assignation auto des UL est désactivée, un bouton
permet de demander (manuellement) l'allocation automatique des unités
locatives.

Tableau récap de cette proposition :

| équipe de gestion | switch | bouton visible |
|-------------------|--------|----------------|
| auto              | auto   | non            |
| auto              | manuel | oui            |
| manuel            | /      | oui            |

**4.- Réassignations des unités locatives pour un séjour confirmé**

La réassignation des unités locatives pour une réservation déjà
confirmée (ou éventuellement en cours de séjour) est une opération qui
impacte directement le planning (donc la disponibilité des unités
locatives), et qui est réalisé quasiment sans possibilité de contrôle
(le changement est fait en une seule étape).

#### Planification des repas

Le système de **planification des repas** permet d'organiser les repas
proposés ou prévus dans le cadre d'une réservation, en tenant compte de
la date, du créneau horaire, du lieu et du type de repas. Chaque repas
est **rattaché à un groupe de service de type séjour** (*sojourn*), ce
qui permet d'en gérer plusieurs en parallèle dans une même réservation.

**1. Déclenchement automatique à la création d'un repas**

Lorsqu'une **ligne de réservation** (BookingLine) est créée avec le
flag `is_meal = true`, dans un groupe de service de type `sojourn`, le
système vérifie pour chaque date du séjour s'il existe déjà un objet
`BookingMeal` correspondant :

-   même **réservation** (`booking_id`)

-   même **groupe** (`booking_line_group_id`)

-   même **créneau horaire** (`time_slot_id`)

-   même **date**

Si aucun `BookingMeal` n'est trouvé, **il est automatiquement créé**. La
ligne de réservation est ensuite **rattachée** à ce `BookingMeal`.

> 🔒 Les objets `BookingMeal` ne peuvent pas être créés manuellement via
l'interface utilisateur, mais ils peuvent être modifiés.

**2. Structure d'un BookingMeal**

Chaque objet `BookingMeal` regroupe les informations suivantes :

| Champ                 |
|-----------------------|
| booking_id            |
| booking_line_group_id |
| booking_lines_ids     |
| date                  |
| time_slot_id          |
| meal_type_id          |
| meal_place            |


**3. Affichage dans l'interface**

Les repas apparaissent dans une section dédiée appelée **"Repas"**,
propre à chaque groupe de type séjour. Cette section permet de
visualiser tous les repas planifiés sur la durée du séjour.

Dans le **planning**, les repas sont affichés avec des indications
précises :

-   **Quoi** : type de repas (normal, pique-nique, médiéval, BBQ, etc.)

-   **Qui organise** :

    -   Si aucun produit de type "repas" n'est réservé pour la date et
        > le créneau : `Repas amené par vos soins`

    -   Sinon : `Repas organisé par notre équipe`

-   **Où** : lieu du repas (réfectoire, extérieur, etc.)

L'option **"Afficher les repas"** permet de rendre ces informations
visibles dans le planning des activités.

**Exemples de repas**

| Type de repas        | Description                              |
|----------------------|------------------------------------------|
| Normal               | Pris au centre, dans un espace prévu     |
| Pique-nique          | Amené par les participants eux-mêmes     |
| Pique-nique + Goûter | Double distribution, à emporter          |
| BBQ encadré          | Préparé et animé par les encadrants      |
| Médiéval             | Service à thème selon l'ambiance choisie |


## Facture

Les factures reprennent toujours la liste des prestations
(éventuellement regroupées) avec le montant total.

Le cas échant, sur le contrat, est précisé l'échéancier avec les dates
pour lesquelles les différents acomptes sont attendus. Sur la facture de
solde, est reprise la liste complète des prestations avec :

-   Le montant total de la facture

-   Le montant total déjà payé

-   Le montant restant à payer (solde)

#### Acomptes et prépaiements

Il y a une distinction entre acomptes et prépaiements.

-   Un prépaiement est un paiement anticipé de la facture de solde
    (repris sur la facture de solde si payé)

-   Un acompte est une facture émise pour payer une partie d'un bien ou
    service (toujours repris sur la facture de solde)

Par défaut on demande des prépaiements.

Les prépaiements (financements) peuvent être convertis en facture
d'acompte. Soit de manière arbitraire, soit à la demande du client.

Pour convertir un financement en facture, il faut sélectionner le
financement qui doit être converti : sur la fiche de réservation, sous
l'onglet « financement ». S'il n'existe pas encore de financement, un
nouveau financement peut être créé manuellement. Ensuite, sur la fiche
du financement, dans le panneau latéral droit, sous l'onglet
« actions », il faut cliquer sur « Créer une facture ». Les conditions
de paiement peuvent éventuellement être modifiées ainsi que l'identité à
laquelle la facture sera adressée (si différent du client de la
réservation). Le bouton « convertir en facture » permet de créer une
nouvelle facture.

Par défaut, les factures créées sont toujours en proforma (« brouillon
de facture ») et il est nécessaire, après avoir vérifié que tout est
bien correct, d'émettre la facture pour qu'un numéro lui soit assigné
(attention cette opération est irréversible et, en cas d'erreur, il n'y
a pas d'autre solution que d'émettre une note de crédit).

#### Annulation

Lorsqu'une réservation est annulée, les financements qui ne sont pas
(du tout) payés sont supprimés, la réservation est marquée comme annulée
(visible sur la fiche) et son état passe à 'checkedout' (terminée).

De plus, les séjours sont forcés à 'is_extra' (pour simuler des
services supplémentaires), ce qui permet à l'utilisateur de modifier la
liste des services pour supprimer ceux qui n'ont pas été consommés et
ajouter d'éventuels frais d'annulation (le contrat n'est par contre
pas modifié, ce qui permet de garder une trace de la réservation
initiale).

En principe, une réservation peut à présent être annulée en cours de
séjour.

C'est donc l'utilisateur qui a la charge de clôturer la réservation
(facturation finale) en fonction de chaque situation particulière.

#### Calcul du prix

Le prix d'un produit se calcule sur base de la quantité réservée, qui
est toujours soit le nombre de jours (ou nuitées), soit le nombre de
produits.

Le prix d'un produit est toujours le produit du prix unitaire (PU) et
de la quantité (qte) : `prix = PU x qte`

#### Modification du prix

Le prix unitaire et le taux de TVA peuvent être définis manuellement.
Pour les éditer, il faut cliquer sur "P.U." ou "TVA" sur la ligne de
service correspondante. Une boite de dialogue s'ouvre alors et présente
les informations suivantes :

-   Le prix original (s'il y en a un - avec lien vers l'objet Price)
-   Le taux de TVA original
-   Le taux de TVA appliqué (éditable)
-   Le prix HTVA (éditable)
-   Le prix TVAC (éditable)

Lorsqu'on modifie un des prix (TVAC ou HTVA), l'autre se met à jour
automatiquement sur bas du taux de TVA renseigné. Ce mécanisme permet à
la fois de modifier les colonnes "prix unitaire" et "TVA".

Note : les valeurs ne peuvent être modifiées que lorsque l'état de la
réservation le permet (devis) ou qu'il s'agit de lignes de
suppléments.

#### Comptabilisation des produits

La comptabilisation des produits (quantité) se fait toujours selon 3
comptages possibles :

-   À la personne qte = nb_pers (La durée du service est fixe)

-   Au logement qte = nb_nuit (Le nombre de personnes n'influence pas
    le prix \[dans la limite de la capacité d'unité locative associée
    au produit\])

-   À l'unité qte = nb_art (ex. suppléments individuels)

Il est toujours possible d'ajouter des produits de manière
individuelle. L'interface permet tour à tour de présenter une liste
complète ou dissociée des produits et des séjours.

##### Comptabilisation des unités locatives

La comptabilisation des unités locatives se fait toujours selon 3
comptages possibles :

1.  À la personne (la quantité dépend du nombre de personnes, du nombre
    de nuits et de la capacité du logement)
2.  Au logement (la quantité varie uniquement en fonction du nombre de
    nuits)
3.  À l'unité (la quantité est arbitraire représente le nombre de fois
    que le produit est vendu)

Les produits de type logement (accomodation) sont toujours comptabilisés
soit à la personne, soit au logement ; et doivent renseigner une
capacité.

Le mode de comptabilisation est le suivant :

-   Au logement : qté = nb_nuits
-   À la personne : qté = nb_nuits x ceil(nb_pers / capacité)

Notes : Les références à nb_pers correspondent au nombre de personnes
pour chacune des tranches d'âges applicables.

##### Proposition pour la nomenclature

1 "nuitée" = une nuit passée par 1 personne (client) dans un Centre\
1 "nuit" = l'utilisation d'un logement pour une nuit (quelle que
soit la capacité du logement)

-   Les produits "nuitées" sont toujours comptabilisés à la personne
    et ont une capacité de 1

-   Les produits "nuit" sont comptabilisés à la personne mais peuvent
    avoir une capacité supérieure à 1 (plusieurs logements peuvent être
    comptabilisés)

-   Les produits "logement" sont comptabilisés au logement et ont
    généralement une capacité supérieure à 1 (un seul logement est
    comptabilisé)

##### Tableau récapitulatif

| Produit                                     | Qté si nb_pers <= capacité                                   | Qté si nb_pers > capacité                                                                              |
|---------------------------------------------|--------------------------------------------------------------|--------------------------------------------------------------------------------------------------------|
| Nuité dortoir <br> nuitée en chambre x pers | `nb_nuits` <br> (exemple de calcul : `nb_nuits * ceil(1/1)`) | `nb_nuits * nb_pers` <br> (exemple 11p: `nb_nuits * ceil(11/1) = nb_nuits * 11`)                       |
| Logement chambre x Pers                     | `nb_nuits` <br> (exemple de calcul : `nb_nuits * ceil(2/3)`) | (Situation impossible)                                                                                 |
| Nuit chambre x Pers                         | `nb_nuits` <br> (exemple de calcul : `nb_nuits * ceil(2/3)`) | `nb_nuits * ceil(nb_pers/capacité)` <br> (exemple 61p en ch3: `nb_nuits * ceil(61/3) = nb_nuits * 21`) |


#### Blocages d'unités locatives

Il est possible de rendre une unité locative indisponible durant une
période donnée. Dans ce cas, l'agenda des disponibilités est mis à jour et un
descriptif du motif (travaux, nettoyage, réparation, ...) y est ajouté.

Les blocages sont des lignes de consommations d'un type particulier :
`hors_service (ooo : out-of-order)`. Ceci permet de faire le suivi des
disponibilités de la même manière que pour les réservations.

Au moment de créer un blocage pour une unité locative via le
planificateur, une vérification est effectuée pour s'assurer qu'aucun
blocage ou réservation préalable n'existe, garantissant ainsi
l'intégrité du processus. En cas d'impossibilité de créer le blocage,
un message d'erreur est affiché.

Cette précaution permet de garantir l'absence de blocages pouvant
interférer avec la même unité locative. De plus, elle assure qu'il
n'est pas possible de créer un blocage s'il existe déjà une
réservation associée.

De la même manière, dans `[Réservation > Réparations & Maintenance]`,
une vérification est faite au moment de la modification du blocage pour
vérifier l'absence de consommation existante.

La vérification s'effectue lorsque le centre, la date de début, la date
de fin ou les unités locatives sont modifiés. En cas d'erreur, un
message d'erreur indique qu'il n'est pas possible d'effectuer la
modification.

#### Modification des dates

Il est possible de modifier les dates d'un groupe de service contenant des activités, à condition que la durée (nombre de jours) du groupe reste identique.

- Les activités et les liens avec les lignes de services sont adaptées automatiquement

- De même, la planification des repas est ajustée en cas de modification des dates du groupe.

Dans le cas où la durée du séjour doit être modifiée, il faut : `supprimer les activités → modifier les dates → recréer les activités`.

#### Notifications et alertes

Des notifications peuvent apparaître dans le menu latérale droit, sous
l'onglet vérifications (icone "check").

Les notifications correspondent à des tests qui peuvent être asynchrones
et sont là pour éviter les actions erronées ou signaler une erreur
(incohérence).

Sous le descriptif de la notification, il y a toujours un bouton
d'action : "réessayer" ou "ignorer".

Certaines notifications sont liées à un test qui peut être réessayé
manuellement, et d'autres notifications sont des alertes à destination
des utilisateurs mais qui peuvent être supprimées une fois qu'elles ont
été lues.

Les notifications liées à des tests sont annulées automatiquement
lorsque le test est réalisé à nouveau et qu'il ne détecte plus
d'erreur.

Les notifications de type alerte ne sont jamais supprimées, et leur
suppression est laissée à l'appréciation de l'utilisateur (en cliquant
sur "ignorer").

Donc, si rien n'est bloquant ou si le problème a disparu entretemps, la
réservation peut poursuivre sa progression malgré le fait qu'une
notification soit toujours affichée.

**Suppressions automatiques :**

La tâche planifiée 'lodging.booking.batch.update.checks' vérifie tous
les jours si les alertes sont encore pertinentes.

Les alertes encore pertinentes sont conservées, et les alertes obsolètes
sont supprimées.

Lorsqu'une réservation est passée à clôturée, toutes les alertes s'y
rapportant sont supprimées.

## Workflow - diagramme de flux des réservations

Un récapitulatif visuel du workflow est disponible dans le document
annexe :

« Discope - Schema - Workflow Réservations.docx ».

Les différents états par lesquels passe une réservation sont décrit dans
le chapitre « Etats d'une réservation ».

### Schéma

<center><img src="/_assets/img/booking-workflow.png" /></center>

### Schéma détaillé

<center><img src="/_assets/img/booking-workflow-detailed.png" /></center>

### Etats d'une réservation

Les réservations doivent nécessairement passer par chacun de ces états
selon les transitions présentées dans le diagramme ci-dessus :

-   Devis
-   Option
-   Confirmée
-   Validée
-   Checked-in
-   Checked-out
-   Pro forma
-   Solde débiteur
-   Solde créditeur
-   Clôture
-   Annulée

Lorsqu'une réservation passe en 'option', les consommations sont créées
et il n'est plus possible de modifier le détail des services.

Pour pouvoir modifier la réservation, il faut repasser en devis,
modifier l'assignation des UL, et passer à nouveau en option (pendant
cette opération, les unités locatives restent assignées à la
réservation, sauf indication contraire par l'utilisateur).

Les règles d'échéance, les rappels et les notifications client sont détaillés dans
[Options : rappels et expiration](options.md), avec leurs paramètres de configuration.

La plupart des transitions se font via la fiche de réservation, mais il
y a des cas particuliers :

### Option : Création d'une option via le planning

La procédure à suivre est la suivante :

-   Sélection de la période (date_from, date_to) et de la UL
    (rental_unit_id)

-   Choix d'un client =\> proposer les identities puis assigner au
    booking customer_identity_id

-   Créer une réservation, ajouter une ligne de service de type nuitée
    avec un nombre de personnes arbitraire (celui de la capacité de
    l'UL), et la mettre en option (génération des consommations et
    blocage du planning)

### Confirmation

Lors de la confirmation, un plan de financement (ou « plan de
paiement ») est attribué automatiquement sur base de 3 critères :

-   rate_class_id (classe tarifaire : T1-T7);

-   booking_type_id (type de réservation : tout public, groupe scolaire,
    stage);

-   sojourn_type_id

Il peut y avoir plusieurs plans dont les critères correspondent, dans de
telles situation, c'est le plan de paiement avec le plus de critères
correspondants qui est sélectionné.

Il existe des exceptions pour l'attribution du plan de financement
(pour les petits montants, pour des clients spécifiques ou pour des
réservations tardives) pour lesquelles on souhaite ne pas utiliser de
plan de financement, mais demander un paiement « instantané ».

Afin de pouvoir gérer les exceptions manuellement, un switch est
disponible au moment de la confirmation de la réservation, qui permet la
demande manuelle d'un financement instantané.

Le statut de la réservation peut passer automatiquement à « validée » si
le contrat a été marqué comme signé et que le financement d'acompte a
été payé.

Note 1 : il existe également un champ « statut paiement » qui permet de
voir rapidement si une réservation donnée est en ordre de paiement
vis-à) vis de ses financements (champ visible dans la liste générale des
réservations).

Note 2 : Le statut de la réservation est mis à jour tous les jours
durant le nuit, pour forcer une mise à jour du statut du paiement
(puisque ce statut dépend de la date d'échéance des financements).

### Modification des unités locatives en cours de séjour

Lors des passages successifs aux différents états, des actions
automatiques sont exécutées. En ce qui concerne le planning : ce sont
les consommations qui déterminent les occupations des unités locatives,
il s'agit donc d'éléments très sensibles qui ne doivent être modifiés
avec prudence. Les consommations sont créées lors de la mise en option
(ou de la confirmation en cas de confirmation immédiate).

Ces consommations sont basées sur les détails de chaque séjour (groupe
de services de type "séjour") au sein d'une réservation, ainsi que
sur les assignations définies (lien entre un modèle de produit et une
unité locative).

Les consommations ne sont générées automatiquement qu'une seule fois.
Ceci permet de valider la cohérence de planning au moment de la
confirmation d'une réservation.

### Ajout de services supplémentaires (en cours de séjour ou après le séjour)

Des suppléments peuvent être ajoutés en cours de séjour. Dans le cas où
des services planifiables sont ajoutés, un indicateur est présent dans
le groupe de services supplémentaires concerné. Dans cette situation, il
y a une particularité : dans le cas où des services planifiables sont
présents, la création des consommations doit être demandée manuellement
(via l'action "créer les consommations").

Une fois les consommations créées pour un groupe de suppléments, ce
groupe ne peut plus être modifié. Si une modification est nécessaire, il
faut soit ajouter un autre groupe avec d'autres suppléments, soit
supprimer ce groupe et en recréer un autre.

### Liste de réservations

Dans l'App Réservations, plusieurs vues permettent, pour un centre
donné, de consulter la situation des arrivées sur base du statut des
réservations et d'une date donnée. Par défaut, c'est la date du jour
qui est utilisée.

`[Planning > Arrivées > Prévues]` (date = date et statut =
'confirmée', 'validée', ou 'en cours') `[Planning > Arrivées > En
attente]` (date_from = date et statut = 'confirmée' ou 'validée')

`[Planning > Départs > Prévus]` (date_to = date et statut = 'en
cours', 'terminée' ou 'facturée') `[Planning > Départs > En attente]`
(date_to = date et statut = 'en cours')

La vue `[Planning > Résidents > Réservations en cours]` reprend
également toutes les réservations pour lesquelles un séjour est en
cours.

Important : La vue `[Planning > Arrivées > Prévues]` est également
consacrée à l'export du listing des arrivées (précédemment
`[Réservations > Planning > Arrivées]`).

### Annulation

#### Annulation sans frais

##### Comment procéder :

1. Ouvrir la fiche de réservation et cliquer sur **"Annuler"**.
2. Sélectionner n'importe quel **motif d'annulation**.
3. Laisser le champ **"Frais d'annulation"** à 0.
4. Cliquer sur **"Exécuter"**.

##### Conséquences :

- Le statut de la réservation passe à **"Annulée"** et *is_cancelled* est défini à **TRUE**.
- Les financements non payés sont supprimés.
- Les montants des financements restants sont ajustés au montant déjà payé.
- Un financement négatif est créé pour le remboursement du client.

#### Annulation avec frais

##### Comment procéder :

1. Ouvrir la fiche de réservation et cliquer sur **"Annuler"**.
2. Sélectionner n'importe quel **motif d'annulation**.
3. Indiquer un montant supérieur à 0 dans le champ **"Frais d'annulation"**.
4. Cliquer sur **"Exécuter"**.

##### Conséquences :

- Si la réservation est encore au stade **Devis**, elle le reste. Si elle a déjà dépassé le stade **Devis**, son statut passe à **"Terminée"**.
- Le champ *is_cancelled* de la réservation est défini à **TRUE**.
- Les financements non payés sont supprimés.
- Les groupes sont requalifiés en **"Extra"** afin de pouvoir être modifiés.
- Un groupe supplémentaire est ajouté, contenant le produit d'annulation au tarif saisi dans les **"Frais d'annulation"**.

##### Étapes suivantes :

- Les groupes **"Extra"** devenus inutiles peuvent être supprimés.
- Les frais d'annulation sont facturés.
- La réservation suit ensuite le processus habituel jusqu'au statut **"Clôturée"**.

#### Annulation OTA avec frais

##### Comment procéder :

1. Annuler la réservation dans Cubilis.

##### Conséquences :

- Le statut de la réservation passe à **"Terminée"** et *is_cancelled* est défini à **TRUE**.
- Les financements non payés sont supprimés.
- Les groupes sont requalifiés en **"Extra"** afin de pouvoir être modifiés.
- Un groupe supplémentaire est ajouté, contenant le produit d'annulation au tarif de 0 €.

##### Étapes suivantes :

- Les groupes **"Extra"** devenus inutiles peuvent être supprimés.
- Le montant des frais d'annulation doit être modifié de 0 € vers la somme demandée.
- Les frais d'annulation sont facturés.
- La réservation suit ensuite le processus habituel jusqu'au statut **"Clôturée"**.

#### Annulation OTA sans frais

##### Comment procéder :

1. Annuler la réservation dans Cubilis.

##### Conséquences :

- Le statut de la réservation passe à **"Terminée"** et *is_cancelled* est défini à **TRUE**.
- Les financements non payés sont supprimés.
- Les groupes sont requalifiés en **"Extra"** afin de pouvoir être modifiés.
- Un groupe supplémentaire est ajouté, contenant le produit d'annulation au tarif de 0 €.

##### Étapes suivantes :

- Utiliser l'action **"Annuler sans frais"** dans la fiche de réservation.
- Le statut de la réservation passe à **"Annulée"**.
- Les montants des financements restants sont ajustés au montant déjà payé.
- Un financement négatif est créé pour le remboursement du client.

## Système d'alertes

> **A propos des alertes**

> 1\) les alertes bloquantes : une vérification est faite et empêche une
action tant que le problème n'a pas été résolu. Dans le menu de droite,
sous ces alertes il y a un bouton "réessayer" qui permet de relancer
le test afin de voir si le problème a effectivement été corrigé. Si les
tests sont déclenchés lors d'une action (par exemple check-in) et que
l'action est à nouveau exécutée, cette fois avec succès, les alertes de
ce type sont automatiquement supprimées.

> 2\) les alertes non-bloquantes : ce sont des alertes qui ont été
générées à un moment donné, suite à l'exécution automatique d'un test.
Ces alertes sont des notifications dont il est utile de prendre
connaissance. Ces notifications ne sont pas supprimées automatiquement
et il est nécessaire d'utiliser le bouton "ignorer" pour confirmer
qu'on en a bien pris connaissance et qu'elles ne doivent plus être
affichées. Si une nouvelle erreur de ce type est générée ultérieurement,
elle sera à nouveau affichée.

Les alertes permettent aux utilisateurs de minimiser les erreurs liées à
l'encodage ou à des incohérences par rapport à la logique applicative,
en les informant afin qu'ils puissent, si nécessaire, effectuer des
adaptations.

Les alertes sont classées en trois niveaux : notice, warning et
important.

-   Les alertes considérées comme des "notice" sont de couleur
    violette avec l'option "Ignorer".
-   Les alertes considérées comme des "warning" sont des alertes de
    précaution. Elles visent à prévenir l'utilisateur sans l'empêcher
    de poursuivre la réservation.
-   Les alertes considérées comme "importante" sont des alertes que
    l'utilisateur doit traiter le plus tôt possible. Ce type d'alerte
    bloque l'utilisateur dans la poursuite de la réservation et propose
    un bouton "Réessayer" pour relancer les vérifications de
    validation.

Sur la page d'accueil de l'application Réservations, est reprise la
liste d'alertes en cours nécessitant l'intervention d'un utilisateur
(sur base des équipes de gestion auxquelles est attaché l'utilisateur).

Dans cette vue, il est possible de sélectionner les alertes et de les
mettre à jour en cliquant sur le bouton "Mettre à jour l'alerte",
permettant à l'utilisateur de relancer les vérifications sans entrer
dans la réservation.

Dans les listes des réservations, il y a un champ "suivi" qui permet à
l'utilisateur de savoir si la réservation comporte une alerte ou non.
Il est possible d'effectuer une recherche avancée par la réservation et
le centre de gestion.

Lors des passages de statut ou lorsqu'une réservation est annulée ou
clôturée, certaines alertes deviennent non pertinentes et sont
automatiquement supprimées.



## Type

Il est possible de définir différents **types de réservation** afin d’améliorer l’analyse statistique des réservations effectuées.

> 💡 **Astuce** : Le type de réservation peut également être utilisé pour appliquer un plan de paiement spécifique.

Une réservation peut se voir attribuer un type de plusieurs manières :

- à partir du **type de réservation associé au modèle de produit** d’un pack vendu ;
- via la **correspondance avec une règle d’assignation** de type de réservation.

### 1) Type défini par le modèle de produit d’un pack

Si le modèle de produit d’un pack vendu dans la réservation possède un type de réservation configuré, ce type sera automatiquement attribué à la réservation.

### 2) Type défini par une règle d’assignation

Des **règles d’assignation** peuvent être paramétrées pour déterminer le type de réservation en fonction de différents critères :

- **Conditions** : nombre de personnes, nombre d’enfants, nombre d’adultes, channel manager, durée ou caractéristiques du séjour
- **Type de séjour** : gîte auberge ou gîte groupe
- **Catégories tarifaires** : T1, T2, T3, T4, etc.

Il est également possible de créer une **règle d’assignation par défaut**, sans conditions, sans type de séjour et sans catégorie tarifaire, afin d’attribuer un type de réservation lorsque aucun autre critère ne s’applique.

Ce mécanisme offre une grande **flexibilité** dans l’attribution automatique des types de réservation.
