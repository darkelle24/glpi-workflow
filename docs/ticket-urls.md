# URL directes des onglets de tickets

Depuis la version 0.5.0, les liens des onglets des tickets sont partageables et la barre d’adresse suit l’onglet ouvert. Les boutons précédent/suivant du navigateur rechargent l’onglet correspondant. Le sélecteur mobile et l’onglet Tout sont aussi pris en charge.

Sans configuration serveur, les URL natives restent utilisées, par exemple :
`/front/ticket.form.php?id=11&forcetab=Ticket%244`.

## Activer le format court

Le plugin ne peut pas déclarer une route globale GLPI hors de son propre préfixe. Une règle du serveur web est nécessaire pour les adresses suivantes :

| Onglet | Adresse |
| --- | --- |
| Ticket | /front/ticket/11/ticket |
| Statistiques | /front/ticket/11/statistiques |
| Validations | /front/ticket/11/validations |
| Éléments | /front/ticket/11/elements |
| Coûts | /front/ticket/11/couts |
| Historique | /front/ticket/11/historique |
| Workflow | /front/ticket/11/workflow |
| Mes étapes à traiter | /front/ticket/11/mes-etapes |
| Tout | /front/ticket/11/tout |

Les autres onglets natifs disposent aussi de noms fixes. Les onglets ajoutés par d’autres plugins utilisent un nom `onglet-…` dérivé de leur identifiant technique, indépendant de la langue et du nombre affiché dans le badge.

1. Déployer le plugin 0.9.0 et effectuer sa mise à jour dans GLPI. Le schéma SQL reste à 5.
2. Ajouter la règle [Nginx](../deploy/ticket-urls.nginx.conf) ou [Apache](../deploy/ticket-urls.apache.conf) à la configuration existante, avant les règles génériques GLPI. Pour une installation sous `/glpi`, préfixer les chemins source et destination par `/glpi`. Avec Apache dans un sous-répertoire, adapter aussi le motif au contexte du VirtualHost ou du fichier .htaccess.
3. Vérifier la syntaxe et recharger le serveur web selon votre environnement.
4. Avant activation, essayer `https://glpi.udaf24.fr/front/ticket/11/statistiques` avec un ticket accessible : une redirection doit ouvrir son onglet Statistiques. Un onglet inconnu renvoie 404.
5. Fournir la variable d’environnement `WORKFLOWBUILDER_TICKET_URLS=1` au processus PHP qui exécute GLPI, puis le redémarrer/recharger selon votre environnement. Pour PHP-FPM, vérifier que cette variable est transmise au pool ; une variable du shell administrateur seule ne suffit pas. Dans Docker Compose, l’ajouter à `environment` du service GLPI et recréer ce service selon votre procédure.

Après activation, chaque onglet affiche une URL courte, y compris en venant d’un ancien lien GLPI. Les liens Workflow générés par le plugin utilisent aussi ce format et gardent les ancres d’instance/étape. Les anciens liens continuent de fonctionner.

La résolution passe brièvement par une redirection vers la page native avant que le navigateur rétablisse l’adresse courte. Cela conserve les contrôleurs, formulaires et chargements AJAX natifs de GLPI. JavaScript est nécessaire à la synchronisation de l’adresse.

## Droits et recette

Le contrôleur exige une session GLPI, un plugin actif et le droit de lire le ticket. Seuls les onglets effectivement disponibles pour ce ticket et ce profil sont résolus. Il ne donne aucun droit supplémentaire.

Vérifier avec un technicien puis un utilisateur à droits restreints : ouverture directe, changement d’onglet, rechargement, précédent/suivant, clic central/Ctrl+clic, sélecteur mobile, Tout, et lien Workflow avec ancre. Vérifier aussi un ticket inaccessible et un onglet inconnu.

Les exemples de configuration ne sont pas appliqués automatiquement à votre serveur. Désactiver la variable avant de retirer la règle, afin de revenir aux URL natives sans casser la navigation. Aucun changement du cœur GLPI n’est nécessaire.
## Liens simplifiés sans configuration serveur (0.5.1)

Les onglets dont l’identifiant interne se termine par `$1` utilisent maintenant le nom de leur classe seul dans l’adresse : `/front/ticket.form.php?id=11&forcetab=Change_Ticket` ou `forcetab=TicketValidation`. L’identifiant du ticket reste dynamique.

Le plugin sélectionne l’onglet disponible correspondant à l’ouverture du lien. Les anciens liens avec `%241` continuent de fonctionner et sont simplifiés à l’affichage. Les numéros distincts, comme `Ticket$4` pour Statistiques, et `Ticket$main` restent conservés. JavaScript et le plugin actif sont nécessaires à la résolution des liens simplifiés ; sans eux, GLPI peut afficher son onglet par défaut. Les appels internes de GLPI conservent leurs identifiants complets.