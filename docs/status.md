# État des fonctionnalités et limites

## Implémenté dans 0.1.2

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
- Schéma : installation versionnée et rejouable ; migrations 1 vers 2 et 2 vers 3 fournies. La migration de prise en charge préserve les données existantes.
- Langues : interface en français ; domaine GLPI présent pour les menus et droits. La traduction exhaustive des textes PHP et JavaScript reste à compléter.
- Administration : désactivation/archivage et duplication ; suppression définitive d’un modèle non proposée pour préserver les versions utilisées.
- Aucun mécanisme de décision ne remplace les actions réelles dans Microsoft 365, MAGIS, UNI.T NG ou l’inventaire.

## Non réalisé

- Bibliothèque centrale d’étapes réutilisables avec gestion de leurs versions.
- Notifications natives d’attribution d’étapes.
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
