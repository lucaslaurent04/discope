# Discope


## Stratégies de paramétrage & personnalisations

* présence d'un paramètre de configuration (SettingValue) qu'il est possible de configurer
	=> c'est la solution à privilégier pour les fonctionnalités natives de Discope, mais dont l'utilisation dépend de l'organisation
	une alternative moins propre mais acceptable, est de conditionner l'affichage d'une action ou d'une vue à l'appartenance d'un utilisateur à un groupe et, en parallèle de ne créer les groupes cibles que sur les instances concernées


* surcharge d'une entité pour pouvoir assigner une vue spécifique (form), au sein d'un package dédié au client
	=> c'est la stratégie à mettre en œuvre lorsqu'il y a des vues spécifiques à un client (mise en page, filtre, actions, …)

* s'il s'agit d'un ensemble de fonctionnalités regroupées en une App qui est spécifique à un client, alors l'App concernée est placée dans un package dédié au client


* s'il s'agit d'une personnalisation mineure (état d'un contexte, ordre d'affichage, zones ouvertes, ...), les informations peuvent être stockées eclusivement dan le frontend via le local storage.