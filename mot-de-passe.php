<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/forms.php';

exigerConnexion();
$utilisateur = utilisateurCourant();

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actuel       = postBrut('actuel');
    $nouveau      = postBrut('nouveau');
    $confirmation = postBrut('confirmation');

    if (!csrfValide($_POST['csrf'] ?? null)) {
        $erreurs['global'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    }

    if ($actuel === '') {
        $erreurs['actuel'] = 'Indiquez votre mot de passe actuel.';
    }

    $erreurNouveau = validerMotDePasse($nouveau);
    if ($erreurNouveau !== null) {
        $erreurs['nouveau'] = $erreurNouveau;
    } elseif ($confirmation !== $nouveau) {
        $erreurs['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
    }

    if ($erreurs === []) {
        try {
            $pdo = getPdo();

            $requete = $pdo->prepare('SELECT mot_de_passe_hash FROM utilisateurs WHERE id = :id');
            $requete->execute([':id' => $utilisateur['id']]);
            $hash = (string) $requete->fetchColumn();

            if (!password_verify($actuel, $hash)) {
                $erreurs['actuel'] = 'Le mot de passe actuel est incorrect.';
            } else {
                $maj = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe_hash = :hash WHERE id = :id');
                $maj->execute([
                    ':hash' => password_hash($nouveau, PASSWORD_DEFAULT),
                    ':id'   => $utilisateur['id'],
                ]);

                session_regenerate_id(true);   // changement de mot de passe : on renouvelle aussi l'identifiant de session
                ajouterFlash('Votre mot de passe a été modifié.');
                header('Location: mon-compte.php');
                exit;
            }
        } catch (PDOException $exception) {
            error_log('[Agenda] ' . $exception->getMessage());
            $erreurs['global'] = 'Le mot de passe n\'a pas pu être modifié. Merci de réessayer plus tard.';
            http_response_code(500);
        }
    }
}

$pageTitle       = ($erreurs !== [] ? 'Erreur dans le formulaire – ' : '') . 'Changer mon mot de passe';
$currentPage     = 'compte';
require __DIR__ . '/includes/header.php';
?>

<p><a href="mon-compte.php">← Retour à mon compte</a></p>

<h1>Changer mon mot de passe</h1>

<?php resumeErreurs($erreurs); ?>

<form class="contact-form" action="mot-de-passe.php" method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <?php
    champ(['id' => 'actuel', 'label' => 'Mot de passe actuel', 'type' => 'password', 'autocomplete' => 'current-password'], $erreurs);
    champ(['id' => 'nouveau', 'label' => 'Nouveau mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'aide' => 'Au moins 10 caractères.'], $erreurs);
    champ(['id' => 'confirmation', 'label' => 'Confirmer le nouveau mot de passe', 'type' => 'password', 'autocomplete' => 'new-password'], $erreurs);
    ?>

    <button type="submit" class="button">Enregistrer le nouveau mot de passe</button>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
