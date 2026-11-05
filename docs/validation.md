# Rapport de validation — 5 novembre 2026

## Mise à jour 0.3.0 — composition automatique et administration PDF

Contrôles locaux avec PHP 8.5.12, Chromium/Playwright 1.62.1 et des dépendances isolées TCPDF 7.0.0 et CommonMark 2.10.3 :

| Vérification | Résultat |
| --- | --- |
| Moteur et cinq exemples JSON | 82 tests réussis |
| Services existants avec doublures GLPI | 68 tests réussis |
| PDF libre, droits et rendu réel | 19 contrôles réussis |
| PDF automatique, bases, entités, révisions et rendu réel | 14 contrôles réussis |
| Éditeur, deux modes PDF et administration dans Chromium | 22 tests réussis |
| Guide intégré | 10 contrôles réussis |
| Conditions JavaScript | 18 cas réussis |
| Syntaxe PHP | 66 fichiers vérifiés |

Les parcours navigateur vérifient le maintien des pages libres lors des changements de mode, les associations aux champs, l’aperçu immédiat, la sélection des bases intégrées, l’ordre et la duplication des groupes, les palettes, le logo, les textes préremplis de l’administration, les coordonnées libres existantes, les erreurs d’aperçu et la lecture seule. Le petit écran et les erreurs JavaScript sont contrôlés dans les nouveaux parcours.

La fiche automatique courte a été générée en PDF, rasterisée avec Ghostscript et inspectée : ses six groupes et ses contacts tiennent sur une page A4. Un cas contenant une réponse de plusieurs milliers de caractères et une chaîne sans espaces a été généré sur plusieurs pages. La fin de cette réponse et le dernier contact ont été retrouvés par extraction texte ; les pages de continuation répètent les titres et les libellés. L’interface du workflow et celle de l’administration ont également été inspectées visuellement sur des données fictives.

Les services vérifient le droit admin UPDATE, les restrictions d’entité, le catalogue réservé aux lecteurs des modèles de workflow, le refus des références à un formulaire particulier dans une base, les conflits de révision et la conservation des copies publiées lorsqu’une base est modifiée, désactivée ou réactivée.

Le test SQL existant a été adapté à onze tables et inclut un contrôle de création/rejeu de la bibliothèque PDF avec conservation des instances. Ce contrôle SQL n’a pas été exécuté localement faute de serveur MariaDB de test configuré pour cette session ; il reste dans le job MariaDB de la CI. Le schéma passe à 5 et exige la mise à jour du plugin dans GLPI. Aucune instance GLPI ni base de production n’a été modifiée ; une recette après déploiement reste nécessaire sur votre installation.

~~~sh
php tests/pdf-groups.php
# Ajouter un vendor TCPDF isolé et ses polices pour le contrôle de rendu :
php tests/pdf-groups.php /chemin/vers/pdf-renderer/vendor
~~~

## Mise à jour 0.2.0 — modèles PDF et fiche de connexion

Vérifications locales sur Windows, avec PHP 8.5.12, Chromium et des dépendances temporaires isolées. Le rendu réel utilise TCPDF 7.0.0 (bibliothèque requise par GLPI 12), ses dépendances et les polices officielles DejaVu/core ; le serveur GLPI de production n’a pas été modifié.

| Vérification | Résultat |
| --- | --- |
| Moteur, validation et exemples JSON | 81 tests réussis |
| Services et permissions avec doublures GLPI | 68 tests réussis |
| PDF : validations, droits, versions, champs masqués et rendu réel | 19 contrôles réussis |
| Éditeur et formulaires dans Chromium, dont cinq parcours PDF | 17 tests réussis |
| Conditions JavaScript | 18 cas réussis |
| Guide intégré avec CommonMark 2.10.3 | 10 contrôles réussis |
| Syntaxe PHP | 61 fichiers sans erreur |

Les parcours PDF vérifient les styles, l’insertion de variables, les coordonnées, les déplacements souris/clavier, l’import de plusieurs images et le refus des formats incompatibles, les pages et zones dupliquées/supprimées, l’orientation, le petit écran, l’aperçu téléchargé avec jeton CSRF, les erreurs serveur et la lecture seule. Les erreurs JavaScript du parcours PDF font échouer le test.

Le rendu TCPDF vérifie des accents, une image de fond, des consignes longues, des pages A4 en paysage et un récapitulatif seul. Les cinq pages produites par le cas long ont été rasterisées avec Ghostscript et inspectées visuellement. L’état graphique du fond est isolé afin que les titres et pieds de page placés hors de l’image restent visibles. Le texte a également été relu depuis le PDF.

Les tests de droits vérifient le refus avant fin du workflow, le droit de lecture du plugin et du ticket, l’indisponibilité après réouverture, ainsi que le maintien du modèle PDF publié après modification/republication du brouillon. Les limites d’image, les variables inconnues, les zones hors page et la limite JSON totale sont contrôlées côté serveur.

Commandes supplémentaires :

~~~sh
php tests/pdf.php
# Pour les deux contrôles de rendu : vendor de dépendances TCPDF isolées avec polices préparées.
php tests/pdf.php /chemin/vers/pdf-renderer/vendor
~~~

La CI exécute les validations PDF et les droits sans dépendance supplémentaire ; les contrôles de rendu réel ci-dessus sont une validation locale séparée. Le schéma SQL reste à 4. Les nouveaux boutons et téléchargements doivent encore être recettés sur votre installation GLPI après déploiement ; les tests de services emploient des doublures et les tests navigateur une page autonome.

## Mise à jour 0.1.5 — navigation ticket, paramètres de création et récapitulatif final

Contrôles exécutés sur les sources modifiées, avec PHP 8.5.12, Chromium et une installation GLPI 12 locale reliée à une base MariaDB explicitement réservée aux tests :

| Vérification | Résultat |
| --- | --- |
| Moteur et validation des définitions | 80 tests réussis |
| Services et permissions avec doublures GLPI | 68 tests réussis |
| Éditeur, intervenants du ticket et choix multiples dans Chromium | 12 tests réussis |
| Création, acteurs et suivis dans GLPI natif | 18 contrôles réussis |
| Parcours complet dans le navigateur GLPI | 10 contrôles réussis |
| Conditions JavaScript | 18 cas réussis |
| Rendu du guide intégré | 10 contrôles réussis |
| Syntaxe PHP | 57 fichiers sans erreur |
| Contrats des extensions GLPI et diff Git | Réussite |

La recette native vérifie les demandeurs, attribués et observateurs dans les tables d’acteurs réelles de GLPI, y compris plusieurs groupes attribués. Le type, l’urgence, l’impact et l’échéance sont relus sur le ticket créé. Le récapitulatif final est un suivi interne contenant les réponses du lancement et des deux étapes.

Le navigateur crée un ticket, prend et termine une première étape, suit le lien de lancement, ouvre le workflow dans le ticket, prend la seconde étape, déclenche volontairement un refus de validation puis la termine. Les trois actions reviennent dans l’onglet Workflow du même ticket avec la bonne ancre ; l’étape terminée reste ouverte. La présence du récapitulatif complet dans le fil du ticket, le petit écran et l’absence d’erreurs JavaScript sont vérifiés. Le contrôle mobile attend la fin de la remise en page responsive de GLPI.

Les tests de services vérifient également les valeurs invalides avant création, le droit natif d’attribution, la conservation des paramètres initiaux, les instances multiples dans un ticket, les cases à choix multiples et leur désélection complète, l’option de récapitulatif héritée ou désactivée, l’absence de doublons, la réouverture et l’annulation transactionnelle si le suivi final échoue.

Schéma SQL inchangé : version 4. Aucune modification de GLPI de production.
## Mise à jour 0.1.4 — suivis de ticket et équipe du workflow

Contrôles exécutés le 4 novembre 2026 sur un GLPI local et une base MariaDB de test dédiée :

| Vérification | Résultat |
| --- | --- |
| Moteur et validations | 80 tests réussis |
| Services et autorisations | 57 tests réussis avec doublures |
| Éditeur et formulaires Chromium | 9 tests réussis |
| Schéma MariaDB et migrations rejouées | 9 contrôles réussis |
| Suivis et équipe dans GLPI natif | 12 contrôles réussis |
| Parcours navigateur GLPI complet | 6 contrôles réussis |
| Syntaxe PHP | 56 fichiers vérifiés |
| Conditions JavaScript | 18 cas réussis |
| Guide intégré | 10 contrôles réussis |

Les services vérifient les options activées par défaut et leur désactivation individuelle, le contenu des suivis, les réponses conditionnelles, l’échappement, la progression, l’absence de doublons, le refus d’un suivi non autorisé et l’annulation transactionnelle en cas d’échec. L’équipe est testée sur les utilisateurs, les groupes, la priorité des responsables d’étapes, les références invalides et le maintien des droits natifs du ticket.

Le test natif crée de vrais tickets et suivis internes, valide la copie des réponses à la fin d’étape et la progression jusqu’à 100 %. Le navigateur confirme l’identité GLPI, sélectionne un utilisateur par la recherche native, lance le workflow, prend et termine une étape, consulte le contenu du ticket et ses boutons, ouvre le workflow par la clé de lancement puis cible une étape terminée. Le rendu mobile et l’absence d’erreurs JavaScript sont contrôlés. La migration 3 vers 4 est rejouée en conservant les instances ; les anciennes affectations restent sans équipe imposée.

~~~sh
WORKFLOWBUILDER_ALLOW_NATIVE_TESTS=1 WB_MESSAGES_STATE_PATH=/tmp/messages-state.json php tests/messages-native.php /chemin/vers/glpi
WORKFLOWBUILDER_ALLOW_NATIVE_TESTS=1 WB_MESSAGES_STATE_PATH=/tmp/messages-state.json WB_MESSAGES_BASE_URL=http://127.0.0.1:8765 node tests/messages-browser.cjs
~~~

Ces scripts exigent un environnement local de test explicite et créent des données fictives. Aucun déploiement de production n’a été modifié.


## Mise à jour 0.1.3 — procédures dans la base de connaissances

Contrôles exécutés le 4 novembre 2026, sur un GLPI 12 isolé et sa base MariaDB de test :

| Vérification | Résultat |
| --- | --- |
| Syntaxe PHP | 51 fichiers vérifiés |
| Moteur et validations | 80 tests réussis |
| Services existants et permissions | 39 tests réussis avec doublures |
| Conditions JavaScript | 18 cas réussis |
| Éditeur, sélecteur et création | 7 tests Chromium réussis |
| Connaissances natives GLPI/MariaDB | 21 contrôles réussis |
| Parcours HTTP et navigateur GLPI | 7 contrôles réussis |
| Rendu du guide | 10 contrôles réussis |

Le sélecteur est testé sur la recherche combinée, les aperçus, la pagination, l’association/retrait, la reprise des titres après changement d’onglet, la création et l’erreur de permission, la conservation du texte historique en cas d’échec, la sérialisation et les réponses de recherche périmées. Le mobile est contrôlé à 390 pixels.

Les tests natifs vérifient la création d’un vrai KnowbaseItem et ses six rubriques, l’échappement HTML, la copie des anciens textes, les cibles de visibilité natives, les filtres SQL, l’hydratation sans fuite des fiches privées et les droits de création, d’entité et de gestion du modèle. Le navigateur crée une fiche depuis l’étape, l’ouvre dans GLPI, recharge l’association sauvegardée, publie le modèle, crée le ticket et vérifie son lien direct. Une requête de création sans jeton est refusée.

Reproduction dans un environnement de test dédié :

~~~sh
WORKFLOWBUILDER_ALLOW_NATIVE_TESTS=1 php tests/knowledge-native.php /chemin/vers/glpi
WORKFLOWBUILDER_ALLOW_NATIVE_TESTS=1 WB_KNOWLEDGE_BASE_URL=http://127.0.0.1:8765 node tests/knowledge-browser.cjs
npm run test:ui
~~~

Ces commandes ajoutent des fiches, modèles et tickets fictifs à une base de test. Le script PHP refuse une base dont le nom ne suit pas le motif workflowbuilder_glpi12_* ou workflowbuilder_test_* ; le navigateur exige une URL locale et le drapeau explicite. Utiliser WB_GLPI_TEST_USER et WB_GLPI_TEST_PASSWORD pour d’autres identifiants de test.

Aucun changement de schéma SQL. Les tests ont utilisé une installation locale isolée ; aucun déploiement de production n’a été modifié.


## Mise à jour 0.1.2 — guide illustré intégré

Contrôles exécutés le 4 novembre 2026 sur la livraison 0.1.2 :

| Vérification | Résultat |
| --- | --- |
| Syntaxe PHP | 48 fichiers vérifiés |
| Moteur et validations | 80 tests réussis |
| Services et permissions, dont accès au guide | 39 tests réussis avec doublures GLPI et DB |
| Rendu avec la bibliothèque livrée dans GLPI 12.0.0 | 10 contrôles réussis |
| Rendu avec la bibliothèque livrée dans GLPI 12.0.1 | 10 contrôles réussis |
| Installation et activation 0.1.2 | Réussite sur un GLPI 12.0.1 vierge isolé |
| Parcours HTTP et navigateur du guide sur GLPI 12.0.1 | 7 contrôles réussis |
| Captures | 10 PNG produits dans ce GLPI avec des données fictives |
| Syntaxe JavaScript des outils de guide | Réussite |

Le parcours natif vérifie le refus du téléchargement anonyme, les dix URL PNG et leur décodage dans le navigateur, l’identité octet pour octet entre Markdown téléchargé et source, les ancres françaises, le rejet d’un format tentant un accès arbitraire, les liens depuis la navigation et l’onglet ticket, et l’absence de débordement à 390 pixels après stabilisation de la mise en page. Les tests de rendu vérifient aussi le filtrage du HTML brut, des protocoles dangereux et des images distantes ou traversant les répertoires. Les captures ont été inspectées et recadrées pour éviter le recouvrement par l’en-tête fixe.

Le navigateur utilisé est Chromium via Playwright. Le schéma reste à 3 ; aucun nouveau test de migration SQL n’est revendiqué pour cette mise à jour documentaire. Les résultats natifs de la version 0.1.1 ci-dessous restent des résultats historiques. Le déploiement TrueNAS de production n’a pas été modifié.

Pour reproduire les captures et contrôles, consulter la [maintenance du guide](guide-maintenance.md). La CI du rendu est configurée ; son exécution distante n’a pas été déclenchée pendant cette livraison.

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
