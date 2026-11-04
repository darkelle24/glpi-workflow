# Historique des versions

## 0.1.4 — 4 novembre 2026

- Trois options de suivi interne par étape, activées par défaut : disponibilité, prise en charge et fin.
- Suivis GLPI avec lien vers l’étape, intervenants et progression ; la fin d’étape reprend les réponses visibles et le commentaire enregistré.
- Protection contre les suivis de prise en charge répétés lors des simples enregistrements ; écriture dans la transaction de l’action et contrôle du droit natif d’ajout de suivi.
- Sélection de plusieurs utilisateurs et groupes au lancement du workflow ; l’équipe s’applique aux étapes sans responsable explicite.
- Présentation structurée à l’ouverture du ticket, avec identification du workflow, version, responsables, données de lancement et bouton d’accès au workflow. Ajout sous forme de suivi interne pour un ticket existant.
- Schéma SQL 4 : colonnes assigned_users et assigned_groups ; migration 3 vers 4 rejouable préservant les instances. Format JSON conservé en schema 1.


## 0.1.3 — 4 novembre 2026

- Sélecteur de fiches de connaissance GLPI avec titres, aperçus, pagination et filtres combinables : titre/contenu, FAQ/interne, fiches de l’auteur connecté et date de modification.
- Création et association d’une fiche depuis chaque étape, avec un modèle à six rubriques à compléter dans GLPI et une visibilité personnelle ou limitée à l’entité du workflow.
- Remplacement du champ Instructions par les fiches de connaissance ; reprise des anciens textes lors de la création d’une fiche.
- Accès direct aux procédures associées depuis les étapes du ticket, avec respect des droits natifs GLPI.
- Tests du sélecteur, des permissions, de la création native et du parcours complet jusqu’au ticket ; documentation mise à jour.
- Schéma SQL inchangé : version 3. Format JSON des modèles inchangé : schema 1.

## 0.1.2 — 4 novembre 2026

- Guide utilisateur réécrit avec dix captures GLPI réelles sur des données fictives, sommaire, parcours technicien et administration, permissions et dépannage.
- Accès Guide utilisateur dans la navigation du plugin et dans l’onglet Workflow des tickets.
- Une source Markdown unique, lue directement dans le plugin et téléchargeable à l’identique ; aucune copie HTML à maintenir.
- Rendu CommonMark avec tableaux, ancres françaises, images locales agrandissables et présentation adaptée aux petits écrans.
- Contrôle des droits de lecture et filtrage du HTML, des liens et des images dans le guide.
- Correction de la présentation des cases à cocher et du contraste du nom de l’opérateur.
- Scripts de reproduction des captures et tests du rendu et du parcours natif.
- Schéma SQL inchangé : version 3. Aucun changement du format JSON des modèles (schema 1).


## 0.1.1 — 4 novembre 2026

- Prise en charge nominative des étapes avec le bouton « Je prends en charge ».
- Affichage du technicien et de la date directement dans le résumé de l’étape.
- Protection contre la prise en charge ou la modification par un autre opérateur.
- Libération volontaire et reprise par un collègue autorisé, sans supprimer les valeurs enregistrées.
- Réaffectation administrative explicite avec motif pour les étapes abandonnées.
- Historique des prises en charge et libérations ; noms de participants affichés par le mécanisme natif GLPI, avec son anonymisation.
- Schéma SQL 3 : ajout du champ nullable started_by, migration 2 vers 3 rejouable.
- Les anciennes étapes en cours restent sans opérateur confirmé jusqu’à une prise en charge explicite ou un enregistrement autorisé.

## 0.1.0 — 4 novembre 2026

Première livraison du moteur générique, éditeur, intégration aux tickets, versions, permissions, exemples et tests. Schéma SQL 2.
