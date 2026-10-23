<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/forms.php';

demarrerSession();

if (estConnecte()) {
    header('Location: mon-compte.php');
    exit;
}

$erreurs = [];
$email   = '';

/* ---------- Traitement du formulaire ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = mb_strtolower(postTexte('email'));
    $motDePasse = postBrut('mot_de_passe');

    if (!csrfValide($_POST['csrf'] ?? null)) {
        $erreurs['global'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    }
    if ($email === '') {
        $erreurs['email'] = 'Indiquez votre adresse e-mail.';
    }
    if ($motDePasse === '') {
        $erreurs['mot_de_passe'] = 'Indiquez votre mot de passe.';
    }

    if ($erreurs === []) {
        try {
            $pdo = getPdo();
            $requete = $pdo->prepare('SELECT id, nom, role, mot_de_passe_hash FROM utilisateurs WHERE email = :email');
            $requete->execute([':email' => $email]);
            $compte = $requete->fetch();

            // Même durée de calcul que le compte existe ou non (voir HASH_FACTICE)
            $hash = $compte === false ? HASH_FACTICE : $compte['mot_de_passe_hash'];

            if ($compte !== false && password_verify($motDePasse, $hash)) {
                // Si l'algorithme de hachage a évolué depuis la création du compte, on met le hachage à jour
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $maj = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe_hash = :hash WHERE id = :id');
                    $maj->execute([':hash' => password_hash($motDePasse, PASSWORD_DEFAULT), ':id' => $compte['id']]);
                }

                connecterUtilisateur((int) $compte['id']);
                ajouterFlash('Bonjour ' . $compte['nom'] . ', vous êtes connecté(e).');
                header('Location: ' . ($compte['role'] === 'admin' ? 'admin/index.php' : 'mon-compte.php'));
                exit;
            }

            // Message volontairement identique dans les deux cas : on ne dit pas laquelle des deux informations est fausse
            $erreurs['global'] = 'Adresse e-mail ou mot de passe incorrect.';
        } catch (PDOException $exception) {
            error_log('[Agenda] ' . $exception->getMessage());
            $erreurs['global'] = 'La connexion est impossible pour le moment. Merci de réessayer plus tard.';
            http_response_code(500);
        }
    }
}

/* ---------- Affichage ---------- */

$pageTitle       = ($erreurs !== [] ? 'Erreur dans le formulaire – ' : '') . 'Connexion';
$pageDescription = 'Connectez-vous à votre compte Agenda Accessible.';
$currentPage     = 'connexion';
require __DIR__ . '/includes/header.php';
?>

<h1>Connexion</h1>

<?php afficherFlash(); ?>
<?php resumeErreurs($erreurs); ?>

<p>Pas encore de compte ? <a href="inscription.php">Créez-en un gratuitement</a>.</p>

<form class="contact-form" action="connexion.php" method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <?php
    champ(['id' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'valeur' => $email, 'autocomplete' => 'username', 'maxlength' => 150], $erreurs);
    champ(['id' => 'mot_de_passe', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'current-password'], $erreurs);
    ?>

    <button type="submit" class="button">Se connecter</button>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
