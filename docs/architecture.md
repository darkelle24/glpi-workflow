# Architecture technique

## Modules

Le moteur pur regroupe Definition, Fields, Condition et Engine. Il ne dépend pas de GLPI : la même validation s’applique à la publication, à l’import, à la prévisualisation et à l’exécution.

La prise en charge effective est mémorisée par started_by et started_at dans stepinstances, indépendamment des responsables prévus users_id et groups_id. La migration 2 vers 3 ajoute started_by sans attribuer arbitrairement les anciennes étapes en cours.

Les services Templates et Instances orchestrent les écritures. Access centralise les droits ; Store encapsule la base native ; TicketGuard contrôle les transitions du ticket ; TicketMutex sérialise les écritures liées au ticket. Les adaptateurs dans inc ajoutent les menus et onglets. Http protège les endpoints. View, FormView et ProfileView assurent le rendu.

L’éditeur JavaScript manipule seulement une définition structurée. Le serveur refuse les propriétés inconnues, les valeurs malformées, les conditions invalides et les cycles. Aucun interpréteur de code utilisateur n’est présent.

## Tables

Toutes les tables sont préfixées glpi_plugin_workflowbuilder_ et utilisent InnoDB / utf8mb4.

| Table | Rôle |
| --- | --- |
| templates | Métadonnées, entité, récursivité, état, brouillon, version active, révision |
| versions | Numéro croissant, définition complète figée, empreinte SHA256, auteur, date |
| fields | Projection normalisée des champs par version et étape |
| steps | Projection ordonnée des étapes publiées |
| rules | Projection des règles publiées |
| dependencies | Arêtes du graphe par version |
| instances | Ticket, version, auteur du lancement, état, révision, dates, clé unique |
| stepinstances | États, responsables, dates et utilisateur de validation |
| values | Valeurs typées JSON, indexées par instance, étape et identifiant de champ |
| history | Acteur, action, ancien/nouvel état, noms des champs modifiés et commentaire |

La définition complète en base permet une lecture atomique de la version. Les tables de projection permettent les futurs outils de recherche et de reporting. Aucun workflow n’est conservé exclusivement dans un fichier : les JSON livrés sont des exemples et des échanges.

Les références GLPI ne possèdent pas de contrainte SQL en cascade. Supprimer un utilisateur ou un article ne supprime pas l’historique du workflow. Les références sont vérifiées à l’utilisation et l’interface signale les objets inaccessibles.

## Cycle de vie

Un modèle possède un brouillon modifiable. Publier crée une nouvelle version, ses projections, son numéro et son empreinte, dans une transaction. Le brouillon courant n’affecte pas la version active avant publication. Désactiver ou archiver interdit les nouveaux lancements.

Une instance référence uniquement une version figée. Les responsables d’une étape peuvent être réaffectés par un administrateur sans modifier la définition publiée ; l’action est tracée. L’entité du modèle devient immuable après première publication : dupliquer le modèle pour changer sa portée.

Les valeurs du lancement sont immuables. Les conditions de workflow s’appuient sur ces valeurs. Les conditions de champs d’étapes peuvent aussi utiliser les champs précédents de la même étape. Les références vers des champs futurs sont refusées.

## Flux de lancement

1. Vérifier session, POST, jeton de session et droits du plugin.
2. Sérialiser l’accès au ticket existant, puis verrouiller le modèle dans une transaction.
3. Rechercher la clé de lancement unique pour rendre la répétition idempotente.
4. Vérifier publication, version présentée au technicien, entité, restrictions et accès au ticket.
5. Valider les champs et les références GLPI.
6. Créer un ticket par Ticket::add ou récupérer le ticket existant.
7. Vérifier à nouveau l’entité et le statut après les règles métier.
8. Insérer instance, étapes, valeurs et historique.
9. Valider la transaction.

Une erreur SQL ou métier annule les écritures. Les effets externes déclenchés par d’autres plugins pendant Ticket::add ne peuvent pas être annulés par ce plugin.

## Exécution et concurrence

Une modification exige les droits de lecture et de modification du ticket ainsi que le droit spécifique de l’action. Les restrictions d’exécution et l’affectation utilisateur/groupe sont contrôlées ensuite. L’administrateur du plugin peut remplacer les affectations, mais conserve les contraintes natives du ticket.

L’instance est verrouillée et sa révision vérifiée. Une prise en charge existante interdit le démarrage, l’enregistrement, la validation ou le blocage par un autre opérateur, y compris s’il possède les droits d’administration du plugin. Les commentaires restent possibles avec les droits adaptés. Le propriétaire peut libérer l’étape ; un administrateur peut la réaffecter explicitement avec un motif, ce qui libère la prise en charge des étapes non terminées. Un premier enregistrement ou une validation directe identifie aussi son opérateur. Une réouverture identifie la personne qui reprend le travail. Une page obsolète est refusée. Le serveur recalcule les états et la progression à chaque action. La même transaction conserve les valeurs, les dates, les nouveaux états et l’historique.

Des verrous consultatifs MariaDB GET_LOCK identifiés par base et ticket évitent une concurrence entre clôture et écriture du plugin. Ils sont libérés à la fin de la requête. Une attente supérieure à cinq secondes produit un message de réessai. L’unicité de la clé de lancement est aussi garantie par un index SQL.

## États et progression

États : waiting, available, progress, done, blocked, na. Une dépendance peut bloquer le démarrage ou seulement la validation. Une étape non applicable satisfait une dépendance. Réouvrir une étape est refusé si une étape dépendante engagée serait invalidée.

Le dénominateur est le nombre d’étapes obligatoires applicables. La progression est le nombre de ces étapes terminées divisé par ce total. Avec zéro étape obligatoire applicable, elle vaut 100 %. Les étapes facultatives sont affichées à part et ne bloquent pas la fin.

La fin du workflow ne ferme pas automatiquement le ticket. Le contrôle de résolution/clôture recalcule les étapes de toutes les instances rattachées au ticket, y compris si l’appelant n’a aucun droit du plugin.

## Sécurité et données sensibles

Les requêtes de données utilisent le DBAL GLPI. Les rares SQL explicites ne reçoivent que des identifiants de table contrôlés et des nombres entiers ou noms de verrous générés. Tout texte affiché est échappé. Les instructions sont du texte, et non du HTML administrateur exécutable.

Les journaux n’enregistrent pas les anciennes et nouvelles valeurs. Ils enregistrent les identifiants des champs modifiés. Les formulaires interdisent les noms de champs de mots de passe, secrets et jetons. Les champs libres et commentaires ne constituent pas un coffre-fort ; un utilisateur peut toujours y coller une information sensible. L’avertissement fonctionnel et la politique d’utilisation doivent être respectés.

Le format d’échange possède son propre numéro schema 1, distinct du numéro de schéma SQL 3.

L’import est limité à 512 Kio et n’exécute aucun code. Les références locales de permissions, affectations et connaissance sont retirées à l’import pour être sélectionnées dans l’instance cible.

## Installation et évolutions

Le schéma courant porte le numéro 3, enregistré dans la configuration du plugin après création des tables. Les DDL sont rejouables, car MariaDB effectue des commits implicites sur les DDL. La désactivation conserve les données. La désinstallation supprime uniquement les tables et droits du plugin, pas les tickets ni documents natifs.

La migration 1 vers 2 conserve les données du schéma de développement : définitions longues et colonnes temporelles TIMESTAMP conformes aux conventions GLPI 12. Elle est rejouable avant enregistrement du numéro final. Les futures versions devront ajouter des migrations numérotées avant de relever Schema::VERSION ; une simple création IF NOT EXISTS ne suffit pas à modifier une table existante. Les changements du format de définition devront conserver un lecteur des versions déjà publiées.
