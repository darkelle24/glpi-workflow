# Historique des versions

## 0.8.1 — 19 novembre 2026

- Icônes natives GLPI dans les onglets du ticket : checklist pour Workflow et liste cochée pour Mes étapes à traiter, en cohérence avec le menu latéral.
- Rendu via createTabEntry, libellés accessibles conservés et icônes décoratives ; identifiants d’onglets, liens et droits inchangés.

## 0.8.0 — 19 novembre 2026

- Échéance de résolution actuelle de chaque ticket affichée dans la liste Mes étapes à traiter et dans son onglet personnel, avec le format de date GLPI et un repère En retard lorsque l’échéance est dépassée.
- Tri par défaut de l’échéance la plus proche : tickets en retard en tête, puis échéances futures ; tickets sans échéance en dernier. Choix du tri inverse ou des tickets les plus récents, conservé avec les filtres et la pagination.
- Dates relues depuis le ticket, y compris après une modification GLPI ; dates absentes ou invalides présentées Sans échéance. Classement appliqué avant pagination et ordre stable en cas d’égalité.
- Tests des retards, des tris, des échéances modifiées, de la pagination et des changements d’heure ; schéma SQL 5 et format JSON schema 1 conservés.

## 0.7.0 — 19 novembre 2026

- Vue Mes étapes à traiter regroupée par ticket, même avec plusieurs workflows : étapes terminées par l’utilisateur connecté / total personnel, étapes restantes, actions disponibles immédiatement et étapes en attente ; vingt tickets par page.
- Clic sur un ticket vers son onglet Mes étapes à traiter, avec toutes ses étapes personnelles restantes en détail : descriptions, procédures, responsables, champs, commentaires et boutons d’action selon les droits et l’état.
- Les étapes en attente ou bloquées restent visibles dans les tâches restantes ; les tickets sans travail personnel restant disparaissent. Les validations des collègues ne sont pas comptées comme un travail réalisé par l’utilisateur connecté.
- Retour dans le même onglet après les actions, conservation des valeurs en cas d’erreur et ancres distinctes pour l’onglet Tout. Les filtres de la liste conservent les compteurs complets de chaque ticket.
- Schéma SQL 5 et format JSON schema 1 inchangés.

## 0.6.0 — 19 novembre 2026

- Nouvelle entrée Assistance → Mes étapes à traiter : étapes personnelles disponibles, en cours ou à débloquer, recherche par numéro ou titre de ticket, filtre par état et pagination.
- Nouvel onglet Mes étapes à traiter sur chaque ticket, limité aux étapes de ce ticket ; liens directs vers le ticket, son workflow et l’étape sélectionnée. L’onglet Workflow complet reste disponible.
- Affectations utilisateur/groupe, équipe du workflow, prise en charge, droits natifs et restrictions du modèle respectés ; tickets supprimés, inaccessibles, résolus ou clos exclus. Les droits d’administration n’ajoutent pas les étapes affectées aux autres responsables à la vue personnelle.
- Schéma SQL 5 et format JSON schema 1 inchangés.

## 0.5.1 — 19 novembre 2026

- Liens d’onglets simplifiés sans suffixe `$1` dans la barre d’adresse : `forcetab=Change_Ticket`, `forcetab=TicketValidation`, etc., sans configuration serveur supplémentaire.
- Résolution de l’onglet demandé à l’ouverture, conservation des anciens liens complets et des ancres Workflow ; les autres numéros de sous-onglets restent inchangés.

## 0.5.0 — 19 novembre 2026

- URL partageable pour chaque onglet de ticket : synchronisation de la barre d’adresse, retour/précédent du navigateur et liens ouvrables dans un nouvel onglet, y compris le sélecteur mobile et Tout.
- URL courtes optionnelles `/front/ticket/11/statistiques`, `/workflow`, `/historique`, etc. ; règles Nginx/Apache fournies et activation explicite après configuration du serveur.
- Résolution des onglets selon les droits natifs du ticket et les onglets réellement disponibles ; noms stables pour les onglets tiers et conservation des ancres des workflows.
- Liens GLPI historiques conservés ; schéma SQL 5 et format JSON schema 1 inchangés.

## 0.4.0 — 5 novembre 2026

- Tutoriel PDF séparé et détaillé avec huit illustrations : composition automatique, associations, modèles administrables, placement libre et résultats PDF réels sur données fictives.
- Accès Tutoriel PDF dans GLPI, lien depuis le guide utilisateur et navigation entre les deux guides ; rendu des liens internes et téléchargement Markdown par source autorisée.
- Téléchargement protégé des deux exemples JSON depuis le tutoriel intégré ; fond décoratif PNG inclus dans l’exemple en placement libre et disponible séparément.
- Bouton principal Ouvrir le ticket sur les workflows lancés, en conservant le lien ciblant leur onglet Workflow.
- Scripts reproductibles de démonstration et de captures, tests des guides, des exemples, des droits et de la navigation web. Schéma SQL 5 et format JSON schema 1 conservés.

## 0.3.0 — 5 novembre 2026

- Choix entre composition automatique par groupes et placement libre ; les deux configurations sont conservées dans le workflow.
- Interface de composition avec titre, description, logo, groupes réorganisables, champs du workflow ou textes préremplis, largeurs pleine ligne/moitié/tiers, palettes et aperçu immédiat.
- Mise en page PDF automatique avec hauteurs adaptées au texte, changements de page, répétition des en-têtes, continuation des réponses longues et pied de page.
- Onglet Administration PDF pour créer et modifier les modèles de base par entité, les désactiver et les réutiliser par copie indépendante dans les workflows ; contrôle des droits et des révisions concurrentes.
- Deux bases intégrées (fiche salarié et document sobre), nouvel exemple de workflow automatique, guide et tests actualisés. Les PDF et versions existants restent en placement libre.
- Schéma SQL 5 : ajout rejouable de la table pdfpresets, sans modification des instances existantes. Format JSON schema 1 conservé.

## 0.2.0 — 5 novembre 2026

- Onglet Document PDF facultatif dans les workflows : pages A4 blanches ou images PNG/JPEG intégrées, orientation, réorganisation et duplication des pages.
- Zones déplaçables à la souris et au clavier, coordonnées et dimensions en millimètres, texte et variables de lancement ou d’étapes, trois familles de polices, taille, couleur, gras et alignement.
- Aperçu PDF du brouillon et téléchargement après la fin du workflow avec TCPDF fourni par GLPI ; récapitulatif complet optionnel des réponses, états, opérateurs, dates et commentaires.
- Modèles PDF figés dans les versions publiées et inclus dans l’import/export JSON ; contrôles des droits du ticket, des variables, des images et des dimensions.
- Exemple importable Fiche de connexion salarié, guide et tests PDF/navigateur. Les anciens modèles gardent le PDF désactivé.
- Limite JSON portée à 16 Mio images comprises, 10 pages et 200 zones ; images de 2 Mio maximum. Schéma SQL 4 et format JSON schema 1 conservés.

## 0.1.5 — 5 novembre 2026

- Lien direct par workflow et retour dans l’onglet Workflow du ticket après chaque action, avec ciblage de l’instance et de l’étape.
- Création du ticket avec utilisateurs et groupes multiples pour les demandeurs, attribués et observateurs, type, sévérité, impact et échéance de résolution ; respect des droits et règles GLPI.
- Choix multiples affichés sous forme de listes de cases à cocher.
- Option de récapitulatif final activée par défaut : données du lancement et des étapes, états, opérateurs, dates et commentaires, dans un suivi interne transactionnel.
- Conservation des paramètres initiaux de création du ticket dans les valeurs de l’instance ; aucun changement de schéma SQL.

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
