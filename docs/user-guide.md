# Guide utilisateur

## Construire une procédure différente des exemples

Dans Assistance → Workflows, choisir Nouveau modèle.

L’onglet Général contient le nom, la description, la catégorie et l’icône. La portée d’entité apparaît au-dessus des onglets. Après première publication, dupliquer le modèle pour changer cette portée.

Dans Formulaire, ajouter les champs du lancement. Chaque champ reçoit automatiquement un identifiant stable, visible dans son bloc. Choisir le type, le libellé, la description et le caractère obligatoire. Les listes possèdent un choix par ligne. Les sections sont des titres descriptifs regroupant les champs qui suivent.

Les flèches et le glisser-déposer modifient l’ordre. Une condition de champ ne peut utiliser que les champs précédents de ce formulaire, ce qui évite les cycles. Une réorganisation qui rend une référence future doit être corrigée avant enregistrement.

Dans Étapes, ajouter autant de blocs que nécessaire, puis définir titre, instructions, obligation, responsable et champs propres à l’étape. Les identifiants de champs sont locaux à leur formulaire ou étape. La duplication crée un nouvel identifiant de bloc.

Rechercher les articles de connaissance par leur titre et les sélectionner. La sélection conserve l’identifiant GLPI. Le technicien verra uniquement les articles auxquels il a effectivement accès. Les procédures sont ouvertes dans un autre onglet.

Les dépendances peuvent empêcher le démarrage ou seulement la validation. Le serveur refuse tout cycle.

Dans Conditions, choisir une étape, une variable du lancement, un opérateur et une valeur. Les conditions peuvent être groupées avec ET et OU. Les valeurs oui/non se comparent avec 1/0, ou les opérateurs Est vrai / Est faux.

- Afficher : l’étape est applicable seulement si la condition est vraie.
- Activer : l’étape reste visible mais est bloquée lorsque la condition est fausse.
- Bloquer : l’étape est bloquée lorsque la condition est vraie.

Les conditions sont évaluées sur les informations figées du lancement. Les champs d’une étape peuvent en plus dépendre de champs précédents de cette étape.

Dans Permissions et affectations, définir qui peut lancer, gérer ou exécuter le modèle. Une liste vide n’ajoute aucune restriction aux droits GLPI. Une sélection utilisateur ou groupe autorise ceux correspondant à l’un de ces critères. Une affectation d’étape restreint encore les personnes pouvant agir.

Dans Paramètres, autoriser la création de ticket et/ou l’association à un ticket existant. Définir le titre et la description du ticket avec les variables indiquées. Les variables remplacent du texte ; elles n’exécutent aucun code.

La prévisualisation ne crée aucun ticket. Renseigner les valeurs et choisir Tester les valeurs et conditions pour vérifier les étapes applicables et dépendances. Enregistrer ensuite le brouillon. Publier crée une nouvelle version immuable utilisable par les prochains lancements.

## Lancer et exécuter

Choisir Lancer un workflow pour créer un ticket, ou ouvrir l’onglet Workflow d’un ticket existant puis Ajouter un workflow.

Remplir les informations demandées. Le serveur valide les données et les références. En cas de double clic ou de répétition de la même requête, une instance unique est conservée.

La checklist affiche version, lancement, responsable, états et progression. Déplier une étape pour consulter ses instructions et sa documentation. Je prends en charge passe l’étape à En cours et mémorise automatiquement le technicien connecté ainsi que la date. Son nom est visible directement dans le résumé de l’étape, même repliée. Enregistrer conserve une saisie partielle. Terminer exige que tous les champs obligatoires applicables soient valides et que les dépendances soient satisfaites.

Pendant cette prise en charge, les autres techniciens voient qui travaille sur l’étape et peuvent commenter avec leurs droits habituels, mais ne peuvent pas écraser sa saisie. Le premier enregistrement ou une validation directe identifie aussi son opérateur.

Pour passer le relais, enregistrer les modifications puis cliquer sur Libérer l’étape. Les valeurs enregistrées sont conservées. Un collègue autorisé peut ensuite la prendre en charge à son nom. Une étape bloquée reste bloquée lorsqu’elle est libérée. La réaffectation administrative avec motif libère la prise en charge des étapes non terminées.

Une case à cocher obligatoire doit être cochée. Un champ Oui/Non obligatoire accepte aussi Non. Les champs masqués sont exclus de la validation et leurs valeurs sont ignorées.

Signaler un blocage exige un commentaire. Un utilisateur autorisé peut débloquer une étape ou la réouvrir. Une règle du modèle peut maintenir un blocage. La réouverture est refusée si des étapes dépendantes engagées seraient invalidées ; les traiter d’abord dans l’ordre inverse.

L’administrateur du plugin peut réaffecter une étape avec un motif. Les valeurs déjà enregistrées et l’historique sont conservés.

Après rechargement, les données sont relues en base. Si un autre utilisateur a modifié l’instance, une page obsolète est refusée : recharger avant de refaire l’action.

## Documents et références

Ajouter les fichiers dans le ticket avec les mécanismes GLPI, puis les rechercher dans un champ Document de l’étape. Pour un nouveau ticket, placer les demandes de documents dans les étapes, après lancement. Un document obligatoire dans le formulaire initial empêche actuellement le mode création de ticket.

Les champs Matériel sélectionnent actuellement des ordinateurs. Ils ne modifient pas automatiquement leur affectation dans l’inventaire.

Un utilisateur ou article supprimé reste identifiable par une référence historique, avec une indication d’indisponibilité. Les droits natifs sont contrôlés à chaque consultation ou nouvelle saisie. Un administrateur peut remplacer un responsable devenu indisponible.

## Progression et clôture

La progression compte uniquement les étapes obligatoires applicables. Les étapes facultatives restent disponibles mais n’empêchent pas la fin. Les étapes non applicables sont exclues et satisfont leurs dépendances. Avec zéro étape obligatoire applicable, la progression est de 100 %.

La fin du workflow ne résout pas le ticket automatiquement. Si l’option de contrôle est activée, toutes les étapes obligatoires de toutes les instances concernées doivent être terminées avant résolution ou clôture.

Après résolution/clôture, réouvrir le ticket par GLPI avant de modifier ses étapes.

## Historique, versions et confidentialité

L’historique indique les acteurs, dates, actions, changements d’état et noms de champs modifiés. Il ne conserve pas une copie des anciennes/nouvelles valeurs dans le journal. Les valeurs courantes restent visibles dans les formulaires.

Ne saisir aucun mot de passe, secret d’authentification ou jeton. Les commentaires et champs libres ne sont pas un coffre-fort.

Modifier puis republier un modèle ne transforme pas les tickets existants. Exporter une version publiée conserve sa définition exacte. L’import dans une autre installation crée un brouillon et retire les références locales de responsables, permissions et articles. Les sélectionner à nouveau avant publication.
