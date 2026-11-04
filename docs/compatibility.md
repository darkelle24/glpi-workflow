# Compatibilité et analyse des extensions GLPI

Analyse effectuée les 3 et 4 novembre 2026 sur les tags officiels 12.0.0 et 12.0.1. La branche main n’est pas utilisée comme référence de compatibilité.

## Mécanismes retenus

| Besoin | Mécanisme vérifié | Utilisation |
| --- | --- | --- |
| Découverte | plugin_version_workflowbuilder, plugin_init_workflowbuilder | Déclaration et initialisation standard |
| Installation | plugin_workflowbuilder_install / uninstall | Appels des fonctions du plugin par Plugin |
| Menu | Glpi\Plugin\Hooks::MENU_TOADD ; CommonGLPI::getMenuContent | Menu Assistance |
| Onglets | Plugin::registerClass avec addtabon ; getTabNameForItem et displayTabContentForItem | Ticket et Profile |
| Profils | ProfileRight::updateProfileRights et deleteProfileRights ; Session::haveRight | Habilitations du plugin |
| Entités | Session::haveAccessToEntity ; getAncestorsOf | Portée et sous-entités |
| Tickets | Ticket::can, add, getFormURLWithID | Droits et création par le modèle natif |
| Connaissance | KnowbaseItem::can avec READ ; getFormURLWithID | Permissions et lien vers l’article |
| Documents | Document::canViewFile ; associations glpi_documents_items | Sélection et consultation des documents du ticket |
| Persistance | DBmysql::request, insert, update, updateOrInsert, doQuery | Requêtes structurées et DDL |
| Transactions | DBmysql::beginTransaction, commit, rollBack | Atomicité des écritures |
| Clôture | Hooks::POST_PREPAREUPDATE pour Ticket | Contrôle après prepareInputForUpdate |
| Fichiers publics | répertoire public du plugin, URLs /plugins/workflowbuilder/... | CSS et JavaScript |

La méthode historique DBmysql::query n’existe plus dans la version étudiée. Les requêtes SQL explicites utilisent **doQuery**. Les insertions et mises à jour de données utilisent les méthodes structurées à paramètres du cœur.

## CSRF et routage en version 12

Session::getNewCSRFToken, validateCSRF et checkCSRF sont dépréciées. La validation ancienne ne protège plus une requête en version 12. Le noyau contrôle notamment Sec-Fetch-Site et Origin.

Html::redirect déclenche une exception de redirection prise en charge par le noyau. Les gestionnaires d’erreurs du plugin la laissent remonter, ainsi que les exceptions HTTP natives, afin de conserver redirections et réauthentification.

Les pages sont des contrôleurs PHP hérités dans le répertoire front, exécutés par le point d’entrée public GLPI. Elles ne chargent pas inc/includes.php, devenu obsolète. Elles exigent une session authentifiée et un plugin actif. Chaque mutation exige POST et un jeton propre au plugin, lié à la session, en complément de la protection du noyau.

Le répertoire public contient seulement les ressources exposables. Le code métier, les exemples, les tests et les documents ne sont pas servis directement par le routeur normal de GLPI.

Les noms des opérateurs et des acteurs de l’historique des tickets utilisent getUserName, comme les tâches ITIL natives. L’accès au ticket est vérifié auparavant et l’anonymisation de GLPI est conservée ; aucun lien vers la fiche administrative de l’utilisateur n’est exposé par cet affichage.

## Choix de l’intégration aux tickets

La première version utilise une page de lancement dédiée. Elle appelle Ticket::add et respecte les droits, les entités, les règles métier et les contraintes natives. Les champs supplémentaires obligatoires de vos modèles de tickets peuvent empêcher la création ; dans ce cas, créer le ticket dans GLPI puis lui associer le workflow.

Les étapes sont stockées par le plugin : les tâches natives ne couvrent pas leurs champs et conditions. Aucune seconde progression native n’est créée.

Le hook POST_PREPAREUPDATE peut annuler une mise à jour en mettant input à false. Il contrôle ici les statuts SOLVED et CLOSED sans dépendre des droits du plugin de l’appelant. Les écritures SQL directes, une suspension des hooks GLPI ou la désactivation du plugin échappent à ce mécanisme. Les autres plugins qui changeraient le statut après ce hook doivent faire l’objet d’une recette commune.

## Rendu du guide

Le rendu utilise les classes Environment, CommonMarkCoreExtension, GithubFlavoredMarkdownExtension et MarkdownConverter de League CommonMark, présentes dans les dépendances livrées avec les deux distributions GLPI testées. La configuration supprime le HTML brut et interdit les liens dangereux. DOMDocument, mbstring et libxml sont déjà des dépendances de l’environnement GLPI. Les chemins des captures passent par le routage public natif du plugin. Les tests du rendu ont été exécutés avec les bibliothèques des distributions 12.0.0 et 12.0.1 ; le parcours HTTP et mobile a été testé sur GLPI 12.0.1.

## Sources primaires

- [Hooks GLPI 12.0.0](https://github.com/glpi-project/glpi/blob/12.0.0/src/Glpi/Plugin/Hooks.php)
- [Hooks GLPI 12.0.1](https://github.com/glpi-project/glpi/blob/12.0.1/src/Glpi/Plugin/Hooks.php)
- [Chargement, installation et exécution des hooks](https://github.com/glpi-project/glpi/blob/12.0.0/src/Plugin.php)
- [Mise à jour et permissions des objets](https://github.com/glpi-project/glpi/blob/12.0.0/src/CommonDBTM.php)
- [Contrat des onglets et menus](https://github.com/glpi-project/glpi/blob/12.0.0/src/CommonGLPI.php)
- [Modèle Ticket](https://github.com/glpi-project/glpi/blob/12.0.0/src/Ticket.php)
- [Droits de profils](https://github.com/glpi-project/glpi/blob/12.0.0/src/ProfileRight.php)
- [Accès aux articles](https://github.com/glpi-project/glpi/blob/12.0.0/src/KnowbaseItem.php)
- [API DBmysql](https://github.com/glpi-project/glpi/blob/12.0.0/src/DBmysql.php)
- [Protection CSRF du noyau](https://github.com/glpi-project/glpi/blob/12.0.0/src/Glpi/Kernel/Listener/ControllerListener/CheckCsrfListener.php)
- [Routage des fichiers de plugins](https://github.com/glpi-project/glpi/blob/12.0.0/src/Glpi/Http/RequestRouterTrait.php)

Le test de contrats fourni vérifie la présence des méthodes et hooks utilisés. Il complète la lecture des sources ; il ne remplace pas les tests d’exécution.
