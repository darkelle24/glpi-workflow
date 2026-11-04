# Installation et exploitation

## Prérequis et périmètre

- GLPI 12.0.x et PHP 8.3 ou supérieur, avec les extensions déjà exigées par GLPI.
- MariaDB avec InnoDB, utf8mb4 et disponibilité des verrous consultatifs GET_LOCK.
- Droits GLPI permettant l’installation de plugins et l’administration des profils.
- Accès aux fichiers de l’hôte TrueNAS ou au montage du conteneur.
- Un navigateur récent avec JavaScript pour l’éditeur et les interactions.

La compatibilité des API a été examinée sur GLPI 12.0.0 et 12.0.1. L’image glpi/glpi:latest peut évoluer : vérifier la version effectivement exécutée avant de mettre à jour. Les prérequis refusent une version hors 12.0.x. Épingler en exploitation un tag ou digest d’image vérifié.

## Installation par montage

Le répertoire technique doit s’appeler **workflowbuilder**, même si le dépôt Git s’appelle glpi-workflow. Dans le conteneur demandé :

- code du cœur : /var/www/glpi ;
- plugin : /var/glpi/marketplace/workflowbuilder.

Monter le répertoire racine du dépôt vers le second chemin. L’exemple [compose.override.example.yml](../deploy/compose.override.example.yml) ajoute uniquement un montage au service GLPI existant ; adapter son nom à votre stack.

Définir WORKFLOWBUILDER_SOURCE avec un chemin absolu de l’hôte TrueNAS. Il ne s’agit ni d’un chemin Windows Citrix ni d’un chemin interne au conteneur. Conserver les autres volumes de GLPI et de MariaDB existants. Ne pas masquer l’ensemble de /var/glpi/marketplace avec le seul dossier du plugin.

Les fichiers du plugin peuvent être montés en lecture seule. Le plugin utilise la base GLPI et les documents natifs ; il n’écrit pas dans son propre répertoire. Aucune reconstruction de l’image GLPI n’est requise.

Redéployer le service pour appliquer le montage, puis installer et activer le plugin depuis la gestion des plugins GLPI. L’activation est distincte de la copie des fichiers.

## Installation par archive ou copie

L’archive de livraison contient un dossier racine workflowbuilder. Extraire ce dossier vers le répertoire Marketplace, sans obtenir un niveau supplémentaire workflowbuilder/workflowbuilder.

Une copie directe dans le conteneur permet un essai :

~~~sh
docker cp ./workflowbuilder glpi:/var/glpi/marketplace/
~~~

Adapter le nom du conteneur. La persistance de cette copie dépend du volume Marketplace configuré : un montage de l’hôte est préférable pour conserver et versionner le code après remplacement du conteneur.

Le processus exécutant PHP doit pouvoir lire les fichiers et parcourir les répertoires. Ne pas appliquer de permissions universelles en écriture.

## Installation en ligne de commande

Les commandes suivantes correspondent aux commandes natives vérifiées dans GLPI 12.0.1 :

~~~sh
cd /var/www/glpi
php bin/console plugin:install --username=glpi workflowbuilder
php bin/console plugin:activate workflowbuilder
~~~

Adapter l’utilisateur GLPI au compte d’administration réel et utiliser l’utilisateur système attendu par votre image pour exécuter PHP.

L’option username fournit le contexte de l’administrateur pour l’attribution initiale des droits. Si aucun droit du plugin n’a encore été attribué, un administrateur possédant les droits natifs de configuration et de modification des profils peut amorcer les droits depuis l’onglet Workflow Builder d’un profil.

## Configuration des profils

Dans Administration → Profils, ouvrir le profil puis l’onglet Workflow Builder.

| Besoin | Lignes et cases nécessaires |
| --- | --- |
| Administrer les modèles | Modèles : Voir, Créer, Modifier |
| Publier / archiver | Publication : Modifier ; accès aux modèles et gestion du modèle |
| Lancer | Instances : Voir ; Lancement : Créer |
| Travailler sur les étapes | Instances : Voir ; Exécution : Modifier |
| Valider une étape | Validation : Modifier |
| Réouvrir ou débloquer | Réouverture : Modifier |
| Gérer les droits / affectations | Administration : Modifier ; droits natifs supplémentaires pour modifier un profil |

Un technicien doit aussi pouvoir lire et modifier le ticket. Créer un ticket exige le droit natif correspondant. GLPI 12 peut demander une confirmation d’identité avant la recherche ou la sélection d’utilisateurs : utiliser le lien Confirmer l’identité GLPI, ouvert dans un autre onglet, puis relancer la recherche. Les références utilisateurs, groupes et ordinateurs exigent leur accès natif ; les résultats non lisibles sont filtrés. L’accès aux articles et documents est vérifié séparément.

Les restrictions configurées dans les modèles s’ajoutent aux profils. Les restrictions d’exécution sont figées dans la version publiée ; les restrictions de gestion sont prises dans le brouillon courant.

## Premier lancement

Importer Préparation ordinateur depuis la liste des modèles. Adapter les instructions et sélectionner les articles de connaissance de votre installation. Enregistrer, prévisualiser, publier, puis lancer depuis le menu ou depuis un ticket existant.

Les exemples n’effectuent aucune opération dans Microsoft 365, MAGIS ou UNI.T NG. Ils structurent et tracent les actions du technicien. Les affectations d’ordinateur et les modifications d’inventaire restent réalisées dans les écrans natifs GLPI.

## Mise à jour

1. Sauvegarder la base GLPI, les documents et le code du plugin.
2. Tester la nouvelle version sur une copie représentative de GLPI.
3. Suspendre temporairement les modifications de tickets et workflows pendant l’intervention.
4. Remplacer l’ensemble du code du plugin par la nouvelle livraison.
5. Exécuter l’action de mise à jour dans GLPI si elle est proposée, ou la commande native plugin:install avec l’option force appropriée après examen de son aide.
6. Réactiver, reconnecter les utilisateurs et exécuter la recette courte.
7. Vérifier les journaux et les instances déjà lancées.

Le schéma courant est versionné 3. Une migration préserve les données du schéma de développement 1 et applique les conventions GLPI 12. La migration 2 vers 3 ajoute la prise en charge nominative, sans supprimer les valeurs ni les instances. L’installation et ces migrations sont rejouables. Ne pas diminuer la version du code face à un schéma plus récent.

### Passage de 0.1.0 à 0.1.1

Remplacer les fichiers par la nouvelle archive, puis déclencher Mettre à jour dans GLPI et réactiver le plugin. La migration du schéma 2 vers 3 est exécutée par cette opération, pas par la simple copie des fichiers. Les modèles JSON existants restent au format schema 1 et n’ont pas besoin d’être réimportés.

Les anciennes étapes en cours ne reçoivent pas un opérateur supposé : elles indiquent que la prise en charge reste à confirmer. Un technicien autorisé peut les prendre en charge pour enregistrer son identité.

## Sauvegarde et restauration

Sauvegarder l’ensemble de la base GLPI avec les outils MariaDB habituels, en conservant une cohérence transactionnelle, ainsi que le volume /var/glpi contenant les documents et la configuration adaptée à votre image. Inclure le code exact du plugin et la référence de l’image Docker.

Un export JSON de modèle ne sauvegarde pas les instances, les valeurs ou l’historique. Les documents sélectionnés sont stockés et sauvegardés par GLPI, pas dans le répertoire du plugin.

Restaurer ensemble la base, les fichiers et le code compatibles, d’abord sur une instance de test. Vérifier les liens aux tickets, les versions figées et les documents après restauration. Les identifiants référencés supposent la même base GLPI.

## Désactivation et désinstallation

La désactivation conserve les données. Elle retire les interfaces et suspend aussi le contrôle de clôture par le plugin. Ne pas considérer ce contrôle actif pendant une désactivation.

La désinstallation via GLPI supprime définitivement les dix tables du plugin, ses droits de profils et sa configuration. Elle conserve les tickets, utilisateurs, groupes, articles et documents GLPI. Sauvegarder auparavant les données nécessaires. Retirer ensuite le montage ou le répertoire de code.

## Diagnostic

Une page de [diagnostic](../front/diagnostics.php), réservée à l’administrateur du plugin, affiche la version GLPI/PHP, la version du schéma, la présence des tables et les droits du profil courant.

En cas de problème :

- plugin absent : vérifier le nom workflowbuilder, le niveau du dossier et les droits de lecture ;
- menu absent : vérifier activation, profil actif et droits du plugin, puis se reconnecter ;
- modèle absent au lancement : vérifier publication, état actif, entité, sous-entités, restrictions et mode de lancement ;
- ticket non créé : vérifier les champs obligatoires des modèles natifs et les règles métier ; essayer un ticket existant ;
- document absent : le joindre d’abord au ticket avec GLPI ;
- validation refusée : contrôler champs obligatoires, dépendances, affectation et fraîcheur de la page ;
- erreur technique : consulter les journaux PHP/SQL dans le volume GLPI, souvent sous /var/glpi/files/_log selon la configuration.

Ne pas exposer de mots de passe, jetons ni données personnelles en transmettant des journaux.
