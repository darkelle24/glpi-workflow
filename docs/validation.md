# Rapport de validation — 4 novembre 2026

## Mise à jour 0.1.1 — prise en charge des étapes

Contrôles exécutés le 4 novembre 2026 sur le code 0.1.1 :

| Vérification | Résultat |
| --- | --- |
| Moteur, dont libération d’étape | 80 tests réussis |
| Services, dont prises concurrentes et permissions | 38 tests réussis |
| Schéma MariaDB, dont migration 2 vers 3 rejouée | 8 contrôles réussis |
| Intégration native GLPI 12.0.0 | 19 contrôles réussis |
| Intégration native GLPI 12.0.1 | 19 contrôles réussis |
| Contrats des extensions des deux versions GLPI | Réussite |
| Migration d’une installation GLPI 12.0.1 existante | Cinq instances présentes avant et après |
| Interface dans GLPI 12.0.1 | Nom et date visibles dans le résumé, libération et reprise réussies, valeurs conservées après rechargement |

Le démarrage, le premier enregistrement et la validation directe mémorisent l’utilisateur connecté. Une autre personne ne peut pas remplacer cet opérateur par une simple action d’exécution ; l’administration du plugin ne contourne pas cette protection. La réaffectation administrative explicite reste possible avec un motif. La libération conserve les valeurs enregistrées.

Les tests de services couvrent aussi les commentaires par un collègue, les étapes héritées sans opérateur connu, les droits du ticket, la réouverture et l’affichage des champs en lecture seule pour un autre opérateur.

La colonne started_by est ajoutée au schéma 3 sans inventer le nom d’un opérateur pour les anciennes étapes en cours. L’affichage des participants utilise le même mécanisme natif que les tâches ITIL, avec anonymisation GLPI conservée.

## Validation initiale 0.1.0 conservée pour référence

| Vérification | Environnement | Résultat |
| --- | --- | --- |
| Syntaxe PHP | PHP 8.3.36, tous les fichiers du projet | Réussite |
| Moteur et validation | PHP 8.3.36, sans GLPI | 77 tests réussis |
| Services et autorisations | PHP, doublures explicites de GLPI et DB | 26 tests réussis |
| Conditions côté navigateur | Node.js 26.13.0 | 18 cas réussis |
| Éditeur et widgets | Chromium, Playwright 1.64.1, page de test PHP | 3 tests réussis |
| Schéma réel | MariaDB 11.4.9, base temporaire isolée | 6 contrôles réussis |
| Contrats des extensions | Sources officielles GLPI 12.0.0 | Réussite |
| Contrats des extensions | Sources officielles GLPI 12.0.1 | Réussite |
| Installation et activation natives | GLPI 12.0.0 + MariaDB 11.4.9 | Réussite |
| Installation et activation natives | GLPI 12.0.1 + MariaDB 11.4.9 | Réussite |
| Parcours natif et contrôle du ticket | GLPI 12.0.0 | 14 contrôles réussis |
| Parcours natif et contrôle du ticket | GLPI 12.0.1 | 14 contrôles réussis |
| Migration 1 vers 2 | GLPI 12.0.1, modèle déjà enregistré | Réussite, modèle conservé |
| Désactivation / réactivation | GLPI 12.0.0 | Deux instances avant et après |
| Désinstallation native | GLPI 12.0.0 | Tables du plugin retirées ; ticket natif conservé |

Les deux GLPI et MariaDB ont été installés dans des répertoires et bases temporaires dédiés sur la machine de développement Windows. Les archives officielles ont été vérifiées par SHA256. Les tests n’ont utilisé ni le conteneur TrueNAS de l’UDAF24 ni sa base de données.

Les avertissements optionnels d’installation GLPI concernaient notamment les tables de fuseaux horaires non chargées dans ce MariaDB de test. Les dates techniques du plugin suivent l’horloge GLPI et ses colonnes sont des TIMESTAMP.

## Parcours effectué dans le véritable navigateur GLPI 12.0.1

1. Connexion au GLPI isolé et affichage du menu Assistance → Workflows.
2. Création depuis l’éditeur d’un modèle « Recette GLPI 12 — Préparation poste ».
3. Ajout de deux champs de lancement et de cinq étapes.
4. Ajout de champs d’étapes différents : texte, date, nombre, email et case à cocher obligatoire.
5. Ajout de dépendances et d’une condition basée sur le champ Oui/Non du lancement.
6. Recherche et association de l’article natif Accueil, servant ici de référence de connaissance accessible.
7. Enregistrement, prévisualisation native et publication de la version 1.
8. Lancement et création réelle du ticket GLPI.
9. Exclusion de l’étape conditionnelle : quatre étapes obligatoires applicables.
10. Sauvegarde d’une saisie partielle, rechargement et vérification de sa conservation.
11. Validation des étapes applicables : progression finale de 100 %.
12. Affichage de la même instance dans l’onglet Workflow du ticket et consultation des neuf événements d’historique de ce parcours.
13. Création puis publication d’un second modèle différent « Réservation de salle », uniquement par l’interface.
14. Vérification de la confirmation d’identité native GLPI avant une recherche de références utilisateurs.
15. Simulation d’une modification concurrente du modèle : refus de la page obsolète et maintien de sa révision, avec lien explicite pour recharger la version enregistrée.

La confirmation d’identité est celle du cœur GLPI 12 pour les objets sensibles. Le plugin la respecte et propose un accès au mécanisme natif ; il ne la contourne pas.

## Contrôles natifs automatisés

Le script [native.php](../tests/native.php) charge le noyau réel GLPI. Il utilise le DBAL réel et les modèles natifs. Il vérifie :

- publication et projections persistées ;
- création du ticket et association au workflow ;
- répétition idempotente du lancement ;
- refus de résoudre le ticket avant les étapes obligatoires ;
- association d’une seconde instance au même ticket ;
- rejet des champs obligatoires incomplets ;
- conservation des saisies après relecture de la base ;
- progression et déblocage des dépendances ;
- fin du workflow ;
- prise en compte de toutes les instances avant résolution ;
- conservation des définitions après nouvelle publication ;
- résolution puis clôture par le véritable Ticket::update ;
- historique et acteur persistés.

Le script exige WORKFLOWBUILDER_ALLOW_NATIVE_TESTS=1 et refuse toute base qui ne porte pas un nom explicitement réservé aux tests, commençant par workflowbuilder_glpi12_ ou workflowbuilder_test_. Il crée des données de recette et ne doit pas être lancé sur une base d’exploitation.

## Reproduire les tests rapides

~~~sh
php tests/run.php
php tests/services.php
php tests/contracts.php /chemin/vers/glpi
npm ci
npm run test:conditions
npx playwright install chromium
npm run test:ui
~~~

Le test navigateur démarre un serveur PHP local et utilise les fixtures de tests, pas une imitation déclarée du cœur GLPI. Les endpoints de fixtures ne sont pas exposés par le routeur normal du plugin.

## Reproduire les tests MariaDB

Sur un serveur MariaDB de test dédié, fournir WB_DB_HOST, WB_DB_PORT, WB_DB_USER et WB_DB_PASSWORD selon sa configuration :

~~~sh
WORKFLOWBUILDER_ALLOW_DB_TESTS=1 php tests/schema.php
~~~

Le script crée une base avec un nom aléatoire, vérifie tables, réinstallation, clé unique, transaction, UTF-8 et verrous, puis supprime uniquement cette base de test. Le compte de test doit pouvoir créer et supprimer cette base. Aucun identifiant de base fourni par l’utilisateur n’est utilisé pour une suppression.

## Reproduire le test GLPI natif

Installer GLPI 12.0.x avec une base dédiée portant le préfixe de test exigé. Installer et activer le plugin, puis exécuter :

~~~sh
WORKFLOWBUILDER_ALLOW_NATIVE_TESTS=1 php tests/native.php /chemin/vers/glpi
~~~

Le compte initial glpi/glpi est utilisé par défaut pour une installation neuve isolée. Pour un autre compte de recette, fournir WB_GLPI_TEST_USER et WB_GLPI_TEST_PASSWORD dans l’environnement, sans les enregistrer dans le dépôt. Les effets d’autres plugins ou notifications d’une copie de production doivent être neutralisés avant cette recette.

## Recette restante dans le conteneur UDAF24

Ces points ne sont pas déclarés vérifiés sur votre installation :

| Contrôle | Attendu |
| --- | --- |
| Montage TrueNAS / Docker | Plugin détecté après remplacement du conteneur |
| Modèles natifs de tickets | Respect de vos champs obligatoires et règles métier |
| Profils de techniciens réels | Droits minimaux suffisants, absence d’accès entre entités |
| Groupes et sous-entités | Restrictions et affectations représentatives de votre organisation |
| API GLPI utilisée chez vous | Refus effectif de résoudre/clôturer un ticket incomplet |
| Autres plugins | Aucun contournement ou changement de statut après le hook |
| Connaissance privée | Recherche, lecture, révocation et suppression d’articles |
| Documents | Téléversement natif, sélection, téléchargement et retrait des droits |
| Utilisateurs supprimés | Affichage historique puis réaffectation par un administrateur |
| Sauvegarde/restauration | Versions, valeurs, liens et documents conservés |
| Concurrence réelle | Deux techniciens et double envoi, sous votre serveur PHP |
| Charge et écrans réduits | Temps de réponse, grandes listes, thème et appareils de votre service |

Les contrôles négatifs sur les droits, références invalides et révisions obsolètes disposent aussi de tests de services. Une doublure ne remplace pas la matrice de profils, d’API et de plugins d’une installation réelle.

## Critère d’acceptation proposé

Rejouer le scénario du modèle à cinq étapes sur une copie Docker de votre installation, puis créer un deuxième processus sans modifier le code. Valider les cas interdits avec un profil disposant de moins de droits et une autre entité avant mise en exploitation.

Les fonctionnalités non développées et les limites fonctionnelles sont décrites dans [status.md](status.md).
