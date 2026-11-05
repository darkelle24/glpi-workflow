# Maintenance du guide illustré

## Une source unique

Le contenu se modifie dans [user-guide.md](user-guide.md). Le dépôt l’affiche comme un Markdown ordinaire. Dans GLPI, **Guide utilisateur** lit ce même fichier installé et le convertit au moment de la consultation. **Télécharger le Markdown** renvoie le fichier sans transformation.

Les captures sont dans [public/images/guide](../public/images/guide). Conserver les liens relatifs `../public/images/guide/nom.png` dans le Markdown : ils fonctionnent dans le dépôt et sont adaptés au chemin public de GLPI par le rendu. Le guide ne charge pas de ressources distantes. Pour modifier une image, garder un nom composé de lettres minuscules, chiffres et tirets, avec l’extension PNG.

Modifier le Markdown et les images, vérifier le rendu, puis déployer les fichiers correspondants dans le plugin. Une modification sur GitHub seule ne met pas à jour une installation GLPI : il faut déployer les fichiers. Ne pas maintenir une seconde version HTML.

## Reproduire les captures

Les images livrées proviennent d’un GLPI 12.0.1 local, fraîchement installé, avec un jeu de données fictif. Le [script de démonstration](../tests/guide-demo.php) crée les modèles, le ticket, l’article de connaissance et les états utilisés dans les captures.

1. Préparer une instance GLPI de test locale isolée avec une base nommée exactement selon le motif `workflowbuilder_glpi12_docs_[8 caractères hexadécimaux]`, par exemple `workflowbuilder_glpi12_docs_1234abcd`.
2. Installer et activer le plugin. Le jeu de démonstration attend le compte local initial `glpi` et son mot de passe initial, ainsi qu’aucun modèle de workflow. Il refuse une base dont le nom ne correspond pas au motif.
3. Depuis le dépôt, exécuter les commandes ci-dessous, en adaptant le chemin GLPI et le port de son serveur local.
4. Inspecter les captures produites, puis vérifier le Markdown dans le dépôt et dans GLPI.

Ne jamais préparer ces données dans l’environnement de production. Le script modifie le nom du compte de démonstration et crée des objets natifs.

~~~powershell
$env:WORKFLOWBUILDER_ALLOW_GUIDE_DEMO = '1'
php tests/guide-demo.php C:/tests/glpi
npm ci
npx playwright install chromium
$env:WB_GUIDE_BASE_URL = 'http://127.0.0.1:8766'
node tools/capture-guide.cjs
node tests/guide-browser.cjs
~~~

Les scripts navigateur n’acceptent que localhost ou 127.0.0.1 avec le drapeau explicite. Le script de capture suppose les identifiants d’objets d’une installation vierge ; si le jeu de données est adapté, ajuster ses destinations. Il masque uniquement la barre d’en-tête fixe pendant les captures de zones pour éviter qu’elle recouvre le contenu.

## Vérifier le rendu

~~~sh
php tests/guide.php /chemin/vers/glpi
php tests/services.php
~~~

Le premier test charge la bibliothèque CommonMark du GLPI indiqué et contrôle le Markdown unique, les tableaux, les ancres françaises, les images et le filtrage du HTML et des URL. Le second vérifie notamment les permissions du guide. Le test navigateur contrôle le téléchargement identique, les dix images, la navigation et l’absence de débordement sur mobile.

La [CI](../.github/workflows/tests.yml) exécute aussi les tests de rendu avec une installation Composer isolée pour les tests ; cette dépendance n’est pas livrée dans le plugin.

## Livrer

Exécuter [tools/package.ps1](../tools/package.ps1). L’archive doit contenir le guide, les captures et la page intégrée. La livraison 0.1.5 conserve le schéma SQL 4 ; lancer la mise à jour du plugin dans GLPI. Une installation antérieure à 0.1.4 applique également les migrations déjà fournies.
