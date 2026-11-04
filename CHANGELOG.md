# Historique des versions

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
