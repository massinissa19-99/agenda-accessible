<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/*
 * Variables attendues (définies par chaque page AVANT d'inclure ce fichier) :
 *   $pageTitle       : titre de la page (balise <title>), unique pour chaque page
 *   $pageDescription : description courte (optionnel)
 *   $currentPage     : identifiant de la page courante, pour aria-current
 *   $racine          : chemin vers la racine du site : '' (par défaut) ou '../' dans admin/
 */
$pageTitle       = $pageTitle ?? 'Agenda';
$pageDescription = $pageDescription ?? 'Agenda d\'événements culturels avec les informations d\'accessibilité de chaque lieu.';
$currentPage     = $currentPage ?? '';
$racine          = $racine ?? '';

$utilisateur = utilisateurCourant();

// Le menu change selon que l'on est visiteur, membre ou administrateur
$navigation = [
    'accueil'       => ['index.php', 'Agenda'],
    'accessibilite' => ['accessibilite.php', 'Accessibilité'],
    'contact'       => ['contact.php', 'Contact'],
];

if ($utilisateur === null) {
    $navigation['connexion']   = ['connexion.php', 'Connexion'];
    $navigation['inscription'] = ['inscription.php', 'Inscription'];
} else {
    $navigation['compte'] = ['mon-compte.php', 'Mon compte'];
    if ($utilisateur['role'] === 'admin') {
        $navigation['admin'] = ['admin/index.php', 'Administration'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <!-- Ne JAMAIS bloquer le zoom (pas de user-scalable=no) -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> – Agenda Accessible</title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="stylesheet" href="<?= e($racine) ?>assets/css/style.css">
</head>
<body>
    <!-- Lien d'évitement : premier élément atteint au clavier -->
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>

    <header class="site-header">
        <div class="container header-inner">
            <p class="site-title"><a href="<?= e($racine) ?>index.php">Agenda Accessible</a></p>

            <nav aria-label="Navigation principale">
                <ul class="nav-list">
                    <?php foreach ($navigation as $cle => [$url, $libelle]): ?>
                        <li>
                            <a href="<?= e($racine . $url) ?>"<?= $cle === $currentPage ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
                        </li>
                    <?php endforeach; ?>

                    <?php if ($utilisateur !== null): ?>
                        <li>
                            <!-- Déconnexion en POST + jeton CSRF : un simple lien pourrait être déclenché à votre insu -->
                            <form action="<?= e($racine) ?>deconnexion.php" method="post">
                                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                                <button type="submit" class="link-button">Se déconnecter</button>
                            </form>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- tabindex="-1" : permet à <main> de recevoir le focus quand on suit le lien d'évitement -->
    <main id="contenu" class="container" tabindex="-1">
