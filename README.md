# Agenda Accessible

Site web dynamique d'**agenda d'événements culturels** qui met en avant l'**accessibilité** de chaque événement (accès PMR, langue des signes, boucle magnétique, audiodescription). Le site lui-même est conçu pour être accessible à tous : navigation au clavier, lecteurs d'écran, zoom, mobile.

Projet pédagogique réalisé en **HTML, CSS, PHP (PDO) et MySQL**, sans framework ni JavaScript. Les lieux, événements et comptes de démonstration sont fictifs.

## Fonctionnalités

**Pour tous les visiteurs**

- Liste des événements avec **recherche par mot-clé**, **filtre par catégorie** et **filtres d'accessibilité**
- **Fiche détaillée** de chaque événement (date, lieu, tarif, options d'accessibilité, précisions sur l'accès)
- **Formulaire de contact** avec validation côté serveur et enregistrement en base
- **Déclaration d'accessibilité**

**Pour les membres (compte gratuit)**

- **Inscription** et **connexion** (mot de passe haché, session sécurisée)
- **Inscription / désinscription** aux événements
- Page **Mon compte** : informations, liste des inscriptions, changement de mot de passe

**Pour les administrateurs**

- **Créer, modifier, supprimer** des événements
- **Publier / dépublier** : un événement en brouillon n'est visible que des administrateurs
- Voir les **personnes inscrites** à chaque événement
- Lire les **messages reçus** par le formulaire de contact
- Suppression en deux temps (page de confirmation), sans JavaScript

## Comptes de démonstration

| Rôle           | E-mail              | Mot de passe         |
| -------------- | ------------------- | -------------------- |
| Administrateur | `admin@exemple.fr`  | `Admin-Agenda-2026`  |
| Membre         | `membre@exemple.fr` | `Membre-Agenda-2026` |

> **Important** : ces mots de passe sont publics (ils sont dans ce dépôt). Changez-les via **Mon compte → Changer mon mot de passe** dès la première connexion, et ne mettez jamais en ligne un site qui les utilise encore.

## Technologies

| Couche          | Choix                                          |
| --------------- | ---------------------------------------------- |
| Structure       | HTML5 sémantique                               |
| Style           | CSS3 (variables, grid, flexbox, media queries) |
| Serveur         | PHP 8.1 ou plus (PDO)                          |
| Base de données | MySQL 8 / MariaDB 10.4 ou plus                 |

## Installation

### Avec XAMPP (Windows)

1. Démarrer **Apache** et **MySQL** dans le XAMPP Control Panel.
2. Copier le dossier du projet dans `C:\xampp\htdocs\agenda-accessible`.
3. Ouvrir <http://localhost/phpmyadmin>, onglet **Importer**, choisir `sql/schema.sql` (jeu de caractères **utf-8**), puis **Importer**.
4. Copier `config/config.example.php` en `config/config.php` (les valeurs par défaut de XAMPP conviennent).
5. Ouvrir <http://localhost/agenda-accessible/>.

> Astuce Windows : activez _Affichage → Afficher → Extensions de nom de fichier_ pour ne pas créer par erreur `config.php.txt`.

### En ligne de commande

```bash
mysql -u root -p < sql/schema.sql
cp config/config.example.php config/config.php   # puis adapter les identifiants
php -S localhost:8000                            # http://localhost:8000
```

### Mettre à jour une base de la version 1

Si vous avez déjà importé la première version (sans comptes) et voulez **garder vos données** :

1. Importez `sql/migration_comptes.sql` (une seule fois) : elle ajoute la colonne `publie`, les tables `utilisateurs` et `inscriptions`, et les deux comptes de démonstration.
2. Remplacez les fichiers du projet par la nouvelle version.

Pour repartir de zéro, réimportez simplement `sql/schema.sql` (⚠️ cela efface les tables).

## Structure du projet

```
agenda-accessible/
├── index.php              Liste + recherche + filtres
├── evenement.php          Fiche d'un événement + inscription / désinscription
├── contact.php            Formulaire de contact
├── accessibilite.php      Déclaration d'accessibilité
├── inscription.php        Création de compte
├── connexion.php          Connexion
├── deconnexion.php        Déconnexion (POST + jeton CSRF)
├── mon-compte.php         Espace membre : infos et inscriptions
├── mot-de-passe.php       Changement de mot de passe
├── admin/                 Espace administrateur (réservé au rôle « admin »)
│   ├── index.php          Liste de tous les événements (publiés et brouillons)
│   ├── evenement.php      Créer / modifier un événement
│   ├── publier.php        Publier / dépublier (POST)
│   ├── supprimer.php      Confirmation puis suppression
│   └── messages.php       Messages reçus par le formulaire de contact
├── includes/
│   ├── header.php         Début de page commun (landmarks, menu selon le rôle, lien d'évitement)
│   ├── footer.php         Fin de page commune
│   ├── auth.php           Utilisateur courant, rôles, connexion, pages 403/404
│   ├── forms.php          Aides de formulaire accessibles (champ, résumé d'erreurs)
│   ├── functions.php      Fonctions utilitaires (échappement, dates, CSRF, messages flash)
│   └── db.php             Connexion PDO
├── config/
│   └── config.example.php Modèle de configuration (config.php est ignoré par Git)
├── assets/css/style.css   Feuille de style
└── sql/
    ├── schema.sql         Tables + données d'exemple (installation neuve)
    └── migration_comptes.sql  Mise à jour d'une base de la version 1
```

## Base de données

```
categories (id, nom)
     │ 1
     │
     │ N
evenements (id, titre, description, date_debut, duree_minutes, lieu, adresse,
            categorie_id, prix, acces_pmr, lsf, boucle_magnetique,
            audiodescription, infos_acces, publie)
     │ N
     │
     │ N        (table de liaison, clé primaire composée : pas de double inscription)
inscriptions (utilisateur_id, evenement_id, created_at)
     │ N
     │
     │ 1
utilisateurs (id, nom, email UNIQUE, mot_de_passe_hash, role « membre » | « admin », created_at)

messages (id, nom, email, sujet, message, created_at)
```

Les clés étrangères des inscriptions sont en `ON DELETE CASCADE` : supprimer un événement ou un compte supprime aussi ses inscriptions.

## Accessibilité : ce qui est fait et où le voir

| Principe                | Mise en œuvre                                                                        | Fichier                       |
| ----------------------- | ------------------------------------------------------------------------------------ | ----------------------------- |
| Langue et titre de page | `<html lang="fr">`, `<title>` unique par page                                        | `includes/header.php`         |
| Lien d'évitement        | « Aller au contenu principal »                                                       | `header.php`, `style.css`     |
| Repères de page         | `<header>`, `<nav aria-label>`, `<main>`, `<footer>`, `role="search"`                | `header.php`, `index.php`     |
| Navigation clavier      | Éléments natifs (liens, boutons, champs), ordre du DOM logique                       | partout                       |
| Focus visible           | Contour épais à deux couleurs sur `:focus-visible`                                   | `style.css`                   |
| Page courante           | `aria-current="page"`                                                                | `header.php`, `auth.php`      |
| Formulaires             | `<label for>`, `fieldset`/`legend`, `autocomplete`, aide liée par `aria-describedby` | `includes/forms.php`          |
| Erreurs                 | Résumé en haut avec liens vers les champs, `aria-invalid`, message texte             | `includes/forms.php`          |
| Messages de retour      | Confirmation après une action (flash), annoncée aux lecteurs d'écran                 | `functions.php`, `header.php` |
| Action destructrice     | Page de confirmation explicite, jamais un simple lien                                | `admin/supprimer.php`         |
| Pas de couleur seule    | « disponible / non disponible » en texte, liens soulignés                            | `evenement.php`, `style.css`  |
| Contrastes              | Au moins 4,5:1 pour le texte (thèmes clair et sombre)                                | `style.css`                   |
| Cibles tactiles         | Hauteur minimale de 44 px                                                            | `style.css`                   |
| Responsive              | Grille fluide, lisible dès 320 px, zoom jamais bloqué                                | `style.css`                   |
| Préférences système     | `prefers-color-scheme`, `prefers-reduced-motion`, `forced-colors`                    | `style.css`                   |

## Sécurité

- **Mots de passe** : jamais stockés en clair, hachage `password_hash()` / `password_verify()`.
- **Sessions** : identifiant régénéré à la connexion (contre la fixation de session), cookie `HttpOnly` et `SameSite`.
- **Rôles** : le rôle est relu en base à chaque page ; chaque page d'administration vérifie le rôle côté serveur (403 sinon), pas seulement en cachant un lien.
- **Connexion** : message d'erreur identique que l'e-mail existe ou non, et temps de réponse comparable.
- **Injection SQL** : requêtes préparées PDO ; les noms de colonnes dynamiques viennent d'une liste blanche.
- **XSS** : toute sortie passe par `e()` (`htmlspecialchars`).
- **CSRF** : jeton de session sur tous les formulaires qui modifient des données, y compris la déconnexion.
- **Validation** : toujours côté serveur, jamais seulement côté navigateur.
- **Secrets** : `config/config.php` n'est pas versionné.

### Limites connues (à traiter avant une vraie mise en ligne)

- Pas de limitation du nombre de tentatives de connexion.
- Pas de vérification de l'adresse e-mail ni de réinitialisation de mot de passe oublié.
- Pas de suppression de compte par l'utilisateur lui-même.
- Comptes de démonstration publics (voir plus haut).
- Utiliser HTTPS en production.

## Tests réalisés

Le projet a été testé de bout en bout sur une vraie base MariaDB : création de compte (erreurs, doublon, jeton CSRF), connexion, droits d'un simple membre face aux pages d'administration (403, aucune modification possible), inscription et désinscription, brouillons invisibles du public, création, modification, publication et suppression d'événements, cascade des inscriptions, tentative d'injection HTML et SQL, migration d'une base de la version 1.

Les tests d'accessibilité avec un vrai lecteur d'écran restent à faire (voir ci-dessous).

## Comment tester l'accessibilité

1. **Clavier seul** : débrancher la souris, parcourir tout le site avec Tab, Maj+Tab, Entrée, Espace. Le focus est-il toujours visible et dans un ordre logique ? Testez aussi les formulaires d'inscription, de connexion et d'administration.
2. **Zoom** : agrandir à 200 % puis 400 %. Aucun contenu coupé, pas de défilement horizontal (vérifier le tableau de l'administration).
3. **Mobile** : outils de développement du navigateur, largeur 320 px.
4. **Lecteur d'écran** : NVDA (Windows, gratuit), VoiceOver (macOS / iOS) ou TalkBack (Android).
5. **Outils automatiques** : Lighthouse (Chrome), extension axe DevTools, WAVE. Ils ne détectent qu'une partie des problèmes.
6. **Validateur HTML** : <https://validator.w3.org>.
7. **Préférences** : tester le mode sombre et « réduire les animations » dans les réglages du système.

## Problèmes fréquents

| Problème                                             | Solution                                                                                                                       |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| « Configuration manquante »                          | `config/config.php` n'existe pas : copier `config.example.php` (attention à l'extension cachée sous Windows)                   |
| Accents déformés (`Ã©`)                              | Réimporter `schema.sql` : il contient `SET NAMES utf8mb4`. Si les données sont déjà abîmées, supprimer la base puis réimporter |
| Page blanche ou erreur 500                           | Lire `C:\xampp\apache\logs\error.log` (XAMPP)                                                                                  |
| Connexion impossible avec le compte de démonstration | Vérifier que l'import de `schema.sql` (ou de la migration) s'est bien terminé                                                  |

## Pistes d'amélioration

- Limitation des tentatives de connexion, réinitialisation du mot de passe par e-mail
- Pagination des résultats et filtre par date
- Gestion des catégories dans l'administration
- Images avec texte alternatif saisi à l'ajout
- Nombre de places et liste d'attente
- Tests automatisés d'accessibilité avec pa11y ou axe-core dans GitHub Actions
- Export du calendrier (fichier .ics)
- Version en plusieurs langues

## Références

- [RGAA](https://accessibilite.numerique.gouv.fr/)
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- [MDN : Accessibilité](https://developer.mozilla.org/fr/docs/Web/Accessibility)
- [Documentation PHP : PDO](https://www.php.net/manual/fr/book.pdo.php)
- [Documentation PHP : password_hash](https://www.php.net/manual/fr/function.password-hash.php)

## Licence

MIT, à adapter selon vos besoins.

Projet réalisé par massinissa19-99, étudiant en master MIASHS.
