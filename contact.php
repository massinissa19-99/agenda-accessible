<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

demarrerSession();

$sujets = sujetsContact();

$erreurs = [];
$valeurs = ['nom' => '', 'email' => '', 'sujet' => '', 'message' => ''];

/* ---------- 1. Traitement du formulaire (POST) ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valeurs as $champ => $_) {
        $valeurs[$champ] = (isset($_POST[$champ]) && is_string($_POST[$champ])) ? trim($_POST[$champ]) : '';
    }

    // Protection CSRF
    if (!csrfValide($_POST['csrf'] ?? null)) {
        $erreurs['global'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    }

    // Validation côté serveur (le seul contrôle vraiment fiable)
    if ($valeurs['nom'] === '') {
        $erreurs['nom'] = 'Indiquez votre nom.';
    } elseif (mb_strlen($valeurs['nom']) > 100) {
        $erreurs['nom'] = 'Votre nom ne doit pas dépasser 100 caractères.';
    }

    if ($valeurs['email'] === '') {
        $erreurs['email'] = 'Indiquez votre adresse e-mail.';
    } elseif (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($valeurs['email']) > 150) {
        $erreurs['email'] = 'Indiquez une adresse e-mail valide, par exemple prenom.nom@exemple.fr.';
    }

    if (!array_key_exists($valeurs['sujet'], $sujets)) {
        $erreurs['sujet'] = 'Choisissez un sujet dans la liste.';
    }

    $longueur = mb_strlen($valeurs['message']);
    if ($longueur < 10) {
        $erreurs['message'] = 'Votre message doit contenir au moins 10 caractères.';
    } elseif ($longueur > 2000) {
        $erreurs['message'] = 'Votre message ne doit pas dépasser 2000 caractères.';
    }

    // Enregistrement en base
    if ($erreurs === []) {
        try {
            $requete = getPdo()->prepare(
                'INSERT INTO messages (nom, email, sujet, message)
                 VALUES (:nom, :email, :sujet, :message)'
            );
            $requete->execute([
                ':nom'     => $valeurs['nom'],
                ':email'   => $valeurs['email'],
                ':sujet'   => $valeurs['sujet'],
                ':message' => $valeurs['message'],
            ]);

            // Pattern POST/Redirect/GET : évite le double envoi si on actualise la page
            $_SESSION['flash_succes'] = true;
            header('Location: contact.php');
            exit;
        } catch (PDOException $exception) {
            error_log('[Agenda] ' . $exception->getMessage());
            $erreurs['global'] = 'Votre message n\'a pas pu être enregistré. Merci de réessayer plus tard.';
            http_response_code(500);
        }
    }
}

// Message de succès affiché une seule fois
$succes = !empty($_SESSION['flash_succes']);
unset($_SESSION['flash_succes']);

/* ---------- 2. Affichage ---------- */

$pageTitle       = ($erreurs !== [] ? 'Erreur dans le formulaire – ' : '') . 'Contact';
$pageDescription = 'Contactez l\'équipe de l\'Agenda Accessible pour une question ou un signalement.';
$currentPage     = 'contact';
require __DIR__ . '/includes/header.php';
?>

<h1>Nous contacter</h1>

<?php if ($succes): ?>
    <!-- autofocus + tabindex="-1" : le focus se place sur le message de confirmation -->
    <div class="alert alert-success" role="status" tabindex="-1" autofocus>
        <h2>Message envoyé</h2>
        <p>Merci, votre message a bien été enregistré. Nous vous répondrons dès que possible.</p>
    </div>
<?php endif; ?>

<?php if ($erreurs !== []): ?>
    <!-- Résumé des erreurs : placé avant le formulaire, reçoit le focus, chaque message est un lien vers son champ -->
    <div class="alert alert-error" role="alert" tabindex="-1" autofocus aria-labelledby="titre-erreurs">
        <h2 id="titre-erreurs">
            <?= count($erreurs) > 1 ? 'Le formulaire contient ' . count($erreurs) . ' erreurs' : 'Le formulaire contient une erreur' ?>
        </h2>
        <ul>
            <?php foreach ($erreurs as $champ => $message): ?>
                <li>
                    <?php if ($champ === 'global'): ?>
                        <?= e($message) ?>
                    <?php else: ?>
                        <a href="#<?= e($champ) ?>"><?= e($message) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<p>Les champs marqués « obligatoire » doivent être remplis.</p>

<!-- novalidate : on laisse notre validation PHP afficher des messages clairs et identiques partout -->
<form class="contact-form" action="contact.php" method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <div class="field<?= isset($erreurs['nom']) ? ' has-error' : '' ?>">
        <label for="nom">Nom <span class="required">(obligatoire)</span></label>
        <?php if (isset($erreurs['nom'])): ?>
            <p class="error-message" id="nom-erreur"><span class="visually-hidden">Erreur : </span><?= e($erreurs['nom']) ?></p>
        <?php endif; ?>
        <input type="text" id="nom" name="nom" value="<?= e($valeurs['nom']) ?>" maxlength="100" autocomplete="name" required<?= attributsChamp('nom', $erreurs) ?>>
    </div>

    <div class="field<?= isset($erreurs['email']) ? ' has-error' : '' ?>">
        <label for="email">Adresse e-mail <span class="required">(obligatoire)</span></label>
        <p class="hint" id="email-aide">Exemple : prenom.nom@exemple.fr</p>
        <?php if (isset($erreurs['email'])): ?>
            <p class="error-message" id="email-erreur"><span class="visually-hidden">Erreur : </span><?= e($erreurs['email']) ?></p>
        <?php endif; ?>
        <input type="email" id="email" name="email" value="<?= e($valeurs['email']) ?>" maxlength="150" autocomplete="email" required<?= attributsChamp('email', $erreurs, true) ?>>
    </div>

    <div class="field<?= isset($erreurs['sujet']) ? ' has-error' : '' ?>">
        <label for="sujet">Sujet <span class="required">(obligatoire)</span></label>
        <?php if (isset($erreurs['sujet'])): ?>
            <p class="error-message" id="sujet-erreur"><span class="visually-hidden">Erreur : </span><?= e($erreurs['sujet']) ?></p>
        <?php endif; ?>
        <select id="sujet" name="sujet" required<?= attributsChamp('sujet', $erreurs) ?>>
            <option value="">Choisir un sujet</option>
            <?php foreach ($sujets as $cle => $libelle): ?>
                <option value="<?= e($cle) ?>"<?= attrSi($valeurs['sujet'] === $cle, 'selected') ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field<?= isset($erreurs['message']) ? ' has-error' : '' ?>">
        <label for="message">Message <span class="required">(obligatoire)</span></label>
        <p class="hint" id="message-aide">Entre 10 et 2000 caractères.</p>
        <?php if (isset($erreurs['message'])): ?>
            <p class="error-message" id="message-erreur"><span class="visually-hidden">Erreur : </span><?= e($erreurs['message']) ?></p>
        <?php endif; ?>
        <textarea id="message" name="message" rows="7" maxlength="2000" required<?= attributsChamp('message', $erreurs, true) ?>><?= e($valeurs['message']) ?></textarea>
    </div>

    <button type="submit" class="button">Envoyer le message</button>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
