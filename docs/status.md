# État des fonctionnalités et limites

## Ajouts en cours — navigation et lancement complet

Chaque instance possède un lien direct. Les actions lancées depuis l’onglet Workflow reviennent au ticket, au workflow et à l’étape concernés, y compris en cas de saisie refusée. Les identifiants des étapes sont distincts lorsque plusieurs workflows partagent le ticket.

Le lancement d’un nouveau ticket propose ses demandeurs, personnes et groupes attribués, observateurs, type Incident/Demande, urgence, impact et échéance de résolution. Chaque rôle accepte plusieurs utilisateurs et groupes. Les droits natifs d’attribution GLPI s’appliquent ; la priorité est calculée par GLPI. Ces paramètres ne modifient pas un ticket existant lors d’une association.

Les champs Choix multiples sont présentés comme des listes de cases à cocher. Une option de modèle activée par défaut envoie un récapitulatif interne à la fin des étapes obligatoires applicables : réponses du lancement, paramètres de création, réponses et états de toutes les étapes, opérateurs, dates et commentaires. Les paramètres de création choisis restent conservés dans les valeurs de l’instance, même si le ticket est modifié ensuite. Le schéma SQL reste à 4.

## Ajouts de la version 0.1.4 — messages et équipe

Trois suivis internes par étape sont configurables et activés par défaut : disponibilité, prise en charge et fin. Ils incluent un lien vers le workflow, les intervenants et la progression ; la fin reprend les réponses visibles et le commentaire. Le lancement permet de sélectionner une équipe de plusieurs utilisateurs et groupes pour les étapes sans responsable explicite. L’ouverture du ticket affiche une présentation du workflow et un bouton d’accès. Le schéma SQL 4 conserve l’équipe ; la migration préserve les instances existantes.

## Ajouts de la version 0.1.3 — procédures GLPI

Les étapes disposent d’un sélecteur de fiches avec aperçus, recherche titre/contenu, filtres FAQ/interne, auteur connecté, modification depuis une date et tri par création. Une fiche structurée peut être créée et associée depuis l’étape, puis complétée dans GLPI. Le ticket propose un bouton direct vers chaque fiche. Les droits natifs s’appliquent aux recherches, aux titres rechargés, à la création et à la consultation. Les textes libres historiques restent conservés pour conversion ; le champ Instructions n’est plus éditable.

## Socle conservé dans 0.1.4

Le code livré couvre un socle générique : administration, champs et étapes, conditions ET/OU, dépendances, prévisualisation, publication de versions figées, lancement, tickets existants et création dédiée, checklist, valeurs persistantes, progression, affectations, commentaires, historique, droits, import/export, trois exemples et tableau de bord simple.

Les recherches de connaissance et de documents utilisent des références natives et revérifient leurs droits. Les mises à jour de l’instance ont un contrôle de révision, une transaction et une clé de lancement unique. Chaque étape peut être prise en charge nominativement ; son opérateur et sa date apparaissent dans le résumé. Un autre technicien doit attendre la libération ou une réaffectation administrative explicite pour la modifier.

Le contrôle de résolution/clôture utilise le hook public POST_PREPAREUPDATE. Son résultat est validé avec le véritable Ticket::update sur GLPI 12.0.0 et 12.0.1, avant et après complétion ; le comportement réel de chaque API et plugin de votre installation doit être vérifié en recette.

Les tests, leurs environnements et leurs résultats sont détaillés dans le [rapport](validation.md). La présence du code n’est pas présentée comme une certification en production.

Le guide illustré est conservé dans un seul [fichier Markdown](user-guide.md), lu directement par la page Guide utilisateur dans GLPI. Le téléchargement fournit exactement ce fichier. Dix captures de démonstration sont incluses ; les liens du sommaire, le rendu mobile et les droits de lecture ont été testés.

## Couverture partielle ou choix de première version

- Formulaires de tickets : page de lancement dédiée ; pas de sélecteur injecté dans l’écran natif de création.
- Pièces jointes : sélection des documents déjà ajoutés au ticket par GLPI, sans téléversement direct dans le formulaire de lancement. Utiliser les champs Documents dans les étapes pour le mode création.
- Matériel : sélection des ordinateurs ; les autres types de matériels restent à ajouter.
- Sections : titres séquentiels ; pas de sections répétables ni de mise en page multicolonne.
- Conditions : basées sur les valeurs figées du lancement ; visibilité locale basée aussi sur les champs précédents. Aucune réévaluation métier basée sur une sortie d’une autre étape.
- Règles de validation : type, obligation, choix, bornes numériques et longueur ; pas de script ni de règles personnalisées arbitraires.
- Responsables : sélection visuelle dans l’éditeur ; réaffectation en exécution par identifiants GLPI, réservée à l’administrateur.
- Tableau de bord : compteurs et liste de progression ; pas de graphiques, échéances ou analyse par catégorie.
- Pagination : affichage par 20, avec filtrage des droits avant affichage ; le chargement des grandes listes reste à optimiser pour un usage massif.
- Historique : toutes les opérations de l’instance sont tracées ; pas de journal détaillé des différences éditoriales des brouillons.
- Versionnement : historique publié et définitions conservées ; aucune migration d’instances existantes vers une autre version.
- Schéma : installation versionnée et rejouable ; migrations 1 vers 2, 2 vers 3 et 3 vers 4 fournies. La migration de prise en charge préserve les données existantes.
- Langues : interface en français ; domaine GLPI présent pour les menus et droits. La traduction exhaustive des textes PHP et JavaScript reste à compléter.
- Administration : désactivation/archivage et duplication ; suppression définitive d’un modèle non proposée pour préserver les versions utilisées.
- Aucun mécanisme de décision ne remplace les actions réelles dans Microsoft 365, MAGIS, UNI.T NG ou l’inventaire.

## Non réalisé

- Bibliothèque centrale d’étapes réutilisables avec gestion de leurs versions.
- Scénarios de notifications dédiés au workflow en dehors des suivis de ticket.
- Approbations distinctes des droits de validation d’une étape.
- Synchronisation avec les tâches natives GLPI.
- Affectation automatique des acteurs du ticket depuis les champs de formulaire.
- Migration contrôlée d’une instance déjà lancée.
- Test de charge, audit de sécurité externe et validation sur TrueNAS SCALE.

## Limites de garantie

Le plugin doit être actif et ses hooks exécutés pour empêcher la clôture. Une écriture SQL directe ou une désactivation ne peut pas être interceptée sans modification du cœur.

Les tickets ayant des contraintes natives supplémentaires peuvent nécessiter une création dans GLPI avant association du workflow. Les effets externes d’autres plugins pendant la création d’un ticket ne sont pas annulables par une transaction SQL.

Les champs libres peuvent recevoir des données sensibles malgré l’interdiction de concevoir des champs de secrets. Les valeurs sont enregistrées dans la base GLPI ; utiliser les contrôles d’accès, sauvegardes et règles de conservation de l’organisation.

Le montage et les interactions avec vos plugins, modèles de tickets, règles métier et API doivent être validés sur une copie de votre environnement avant exploitation.
