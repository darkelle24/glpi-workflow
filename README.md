# Workflow Builder pour GLPI 12

Workflow Builder permet de construire des procédures génériques depuis GLPI : formulaire de lancement, étapes avec champs, conditions visuelles, dépendances, documentation et historique. Les définitions sont enregistrées dans MariaDB. Chaque lancement conserve sa version publiée.

Version courante : **0.3.0**. Nom technique et dossier d’installation : **workflowbuilder**. Cible déclarée : **GLPI 12.0.x**, PHP **8.3 minimum**, MariaDB avec InnoDB. Les points d’extension ont été contrôlés dans les sources officielles de GLPI **12.0.0 et 12.0.1**. La validation de votre déploiement Docker reste nécessaire ; consulter le [rapport de validation](docs/validation.md).

## Démarrage

1. Copier le contenu de ce dépôt dans le dossier workflowbuilder du répertoire des plugins, ou monter ce dossier vers **/var/glpi/marketplace/workflowbuilder** dans le conteneur existant.
2. Dans GLPI, ouvrir la gestion des plugins, installer Workflow Builder puis l’activer.
3. Configurer les droits dans l’onglet **Workflow Builder** des profils GLPI.
4. Ouvrir **Assistance → Workflows**. Créer un modèle ou importer l’un des cinq exemples.
5. Ajouter les champs, étapes, conditions, responsables et articles de connaissance. Enregistrer le brouillon, tester la prévisualisation, puis publier.
6. Utiliser **Lancer un workflow** pour créer un ticket, ou **Ajouter un workflow** dans l’onglet Workflow d’un ticket existant.

Aucune modification du cœur GLPI n’est nécessaire. Le serveur n’a besoin ni de Composer ni de Node.js pour utiliser le plugin.

## Fonctionnalités du socle

- Éditeur à huit onglets : général, formulaire, étapes, conditions, permissions, paramètres, document PDF, prévisualisation.
- Ajout, duplication, suppression et réorganisation des champs et étapes.
- Champs texte, texte long, nombre, dates, case à cocher, listes, radio, choix multiples, oui/non, email, URL, utilisateurs, groupes, entités, ordinateurs et documents de ticket.
- Conditions ET/OU, visibilité des champs et étapes, activation et blocage ; rejet des dépendances circulaires.
- Création de ticket avec demandeurs, intervenants attribués, observateurs, utilisateurs et groupes multiples, type, urgence, impact et échéance ; association aux tickets existants.
- États persistants, validation côté serveur, responsables, dates, commentaires et progression.
- Suivis internes automatiques par étape, récapitulatif final configurable activé par défaut, équipe choisie au lancement et liens directs ; les actions de l’onglet Workflow restent dans le ticket.
- Prise en charge nominative : « Je prends en charge », nom du technicien visible sur l’étape et libération pour passer le relais.
- Sélecteur de fiches GLPI avec aperçus, filtres combinables, création de procédures structurées et accès direct depuis les étapes du ticket ; droits natifs vérifiés.
- Deux modes PDF : composition automatique par groupes avec aperçu et bibliothèque administrable de modèles préremplis, ou placement libre conservé.
- Modèle PDF facultatif : pages A4 blanches ou images PNG/JPEG, zones déplaçables, réponses du lancement et des étapes, polices, taille, couleur, aperçu et téléchargement final avec récapitulatif complet optionnel.
- Brouillons, publications immuables, archivage, duplication, historique des versions et import/export JSON validé.
- Droits de profils, restrictions par entité, utilisateur et groupe, droits du ticket et contrôles sur chaque endpoint.
- Historique sans copie des anciennes et nouvelles valeurs des champs.
- Clé de lancement unique, révisions optimistes, transactions et verrous par ticket.
- Contrôle avant résolution ou clôture via le modèle GLPI lorsque le plugin est actif.
- Liste des instances et indicateurs d’avancement, limités aux tickets accessibles.

La [liste précise des limites](docs/status.md) fait partie de la livraison. Les scénarios de notifications dédiés hors suivis de ticket, approbations, bibliothèque centrale d’étapes et synchronisation des tâches natives ne sont pas réalisés dans cette version.

## Documentation

- [Installation, Docker, sauvegarde et mise à jour](docs/installation.md)
- [Guide utilisateur et administrateur](docs/user-guide.md)
- [Architecture et modèle de données](docs/architecture.md)
- [Compatibilité et points d’extension vérifiés](docs/compatibility.md)
- [Tests exécutés et recette manuelle](docs/validation.md)
- [Fonctionnalités et limites](docs/status.md)

Le [guide illustré](docs/user-guide.md) est la source unique de la page **Guide utilisateur** accessible dans les pages du plugin et dans l’onglet Workflow des tickets. GLPI lit directement ce Markdown et affiche les dix captures livrées dans le plugin. Modifier ce fichier puis déployer les fichiers actualise le guide intégré ; aucune récupération distante depuis GitHub n’est nécessaire. Voir la [maintenance du guide](docs/guide-maintenance.md).

## Organisation

~~~text
setup.php / hook.php        déclaration, hooks, installation et désinstallation
bootstrap.php               autoload des classes du plugin
src/                        moteur, validations, services, droits et rendu
inc/                        adaptateurs des menus et onglets GLPI
front/                      pages et endpoints protégés
public/js/ / public/css/     éditeur et interface d’exécution
examples/                   cinq définitions importables
locales/                    domaine de traduction GLPI
tests/                      tests moteur, services, contrats, SQL et navigateur
deploy/                     exemple de montage Docker
tools/                      création d’une archive installable
docs/                       documentation
~~~

## Tests de développement

~~~sh
php tests/run.php
php tests/services.php
php tests/pdf-groups.php # composition automatique et modèles de base
php tests/pdf.php # validations et droits ; fournir un vendor TCPDF isolé pour le rendu réel
php tests/contracts.php /chemin/vers/les/sources/glpi
npm ci
npm run test:conditions
npx playwright install chromium
npm run test:ui
~~~

Les tests de services emploient des doublures GLPI. Les tests navigateur emploient une page de test autonome ; ils ne constituent pas une recette du conteneur GLPI. Les tests SQL utilisent un vrai serveur MariaDB dédié : instructions dans le [rapport](docs/validation.md).

Pour générer une archive contenant le dossier technique attendu :

~~~powershell
powershell -File tools/package.ps1
~~~

Licence : [GPL-3.0-or-later](LICENSE).
