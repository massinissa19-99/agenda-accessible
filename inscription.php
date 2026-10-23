<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/forms.php';

demarrerSession();

// Déjà connecté : inutile de créer un compte
if (estConnecte()) {
    header('Location: mon-compte.php');
    exit;
}

$erreurs = [];
$valeurs = ['nom' => '', 'email' => ''];
$messageEmailPris = 'Un compte existe déjà avec cette adresse e-mail. Vous pouvez vous connecter.';

/* ---------- Traitement du formulaire ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeurs['nom']   = postTexte('nom');
    $valeurs['email'] = mb_strtolower(postTexte('email'));
    $motDePasse       = postBrut('mot_de_passe');
    $confirmation     = postBrut('confirmation');

    if (!csrfValide($_POST['csrf'] ?? null)) {
        $erreurs['global'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    }

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

    $erreurMotDePasse = validerMotDePasse($motDePasse);
    if ($erreurMotDePasse !== null) {
        $erreurs['mot_de_passe'] = $erreurMotDePasse;
    } elseif ($confirmation !== $motDePasse) {
        $erreurs['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
    }

    if ($erreurs === []) {
        try {
            $pdo = getPdo();

            $existe = $pdo->prepare('SELECT 1 FROM utilisateurs WHERE email = :email');
            $existe->execute([':email' => $valeurs['email']]);

            if ($existe->fetchColumn() !== false) {
                $erreurs['email'] = $messageEmailPris;
            } else {
                $insertion = $pdo->prepare(
                    'INSERT INTO utilisateurs (nom, email, mot_de_passe_hash)
                     VALUES (:nom, :email, :hash)'
                );
                $insertion->execute([
                    ':nom'   => $valeurs['nom'],
                    ':email' => $valeurs['email'],
                    ':hash'  => password_hash($motDePasse, PASSWORD_DEFAULT),   // jamais le mot de passe en clair
                ]);

                connecterUtilisateur((int) $pdo->lastInsertId());
                ajouterFlash('Bienvenue ' . $valeurs['nom'] . ' ! Votre compte est créé et vous êtes connecté(e).');
                header('Location: mon-compte.php');
                exit;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                // Deux inscriptions simultanées avec la même adresse : la base refuse la 2e (clé UNIQUE)
                $erreurs['email'] = $messageEmailPris;
            } else {
                error_log('[Agenda] ' . $exception->getMessage());
                $erreurs['global'] = 'Votre compte n\'a pas pu être créé. Merci de réessayer plus tard.';
                http_response_code(500);
            }
        }
    }
}

/* ---------- Affichage ---------- */

$pageTitle       = ($erreurs !== [] ? 'Erreur dans le formulaire – ' : '') . 'Inscription';
$pageDescription = 'Créez un compte pour vous inscrire aux événements de l\'agenda.';
$currentPage     = 'inscription';
require __DIR__ . '/includes/header.php';
?>

<h1>Créer un compte</h1>

<?php resumeErreurs($erreurs); ?>

<p>
    Un compte vous permet de vous inscrire aux événements et de retrouver vos inscriptions.
    Vous avez déjà un compte ? <a href="connexion.php">Connectez-vous</a>.
</p>

<form class="contact-form" action="inscription.php" method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <?php
    champ(['id' => 'nom', 'label' => 'Nom', 'valeur' => $valeurs['nom'], 'autocomplete' => 'name', 'maxlength' => 100], $erreurs);
    champ(['id' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'valeur' => $valeurs['email'], 'autocomplete' => 'email', 'maxlength' => 150, 'aide' => 'Exemple : prenom.nom@exemple.fr'], $erreurs);
    champ(['id' => 'mot_de_passe', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'aide' => 'Au moins 10 caractères. Une phrase facile à retenir est un bon mot de passe.'], $erreurs);
    champ(['id' => 'confirmation', 'label' => 'Confirmer le mot de passe', 'type' => 'password', 'autocomplete' => 'new-password'], $erreurs);
    ?>

    <button type="submit" class="button">Créer mon compte</button>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
