<?php
declare(strict_types=1);

$racine = '../';
require_once __DIR__ . '/../includes/auth.php';

exigerAdmin($racine);

/*
 * Suppression en deux temps, sans JavaScript :
 *   1. (GET)  une page de confirmation explique ce qui va disparaître ;
 *   2. (POST) le bouton « Oui, supprimer » exécute la suppression.
 * Une action destructrice ne doit jamais partir d'un simple lien.
 */

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($id)) {
    $id = filter_var(postTexte('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}
if (!is_int($id)) {
    pageErreur(404, 'Événement introuvable', 'Cet événement n\'existe pas ou n\'existe plus.', $racine, 'admin/index.php', 'Retour à la liste');
}

try {
    $pdo = getPdo();

    $requete = $pdo->prepare(
        'SELECT e.id, e.titre,
                (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id) AS nb_inscrits
         FROM evenements e WHERE e.id = :id'
    );
    $requete->execute([':id' => $id]);
    $evenement = $requete->fetch();

    if ($evenement === false) {
        pageErreur(404, 'Événement introuvable', 'Cet événement n\'existe pas ou n\'existe plus.', $racine, 'admin/index.php', 'Retour à la liste');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfValide($_POST['csrf'] ?? null)) {
            ajouterFlash('Action impossible : la page a expiré. Merci de réessayer.', 'erreur');
        } else {
            // Les inscriptions liées sont supprimées automatiquement (ON DELETE CASCADE)
            $pdo->prepare('DELETE FROM evenements WHERE id = :id')->execute([':id' => $id]);
            ajouterFlash('L\'événement « ' . $evenement['titre'] . ' » a été supprimé.');
        }
        header('Location: index.php');
        exit;
    }
} catch (PDOException $exception) {
    error_log('[Agenda] ' . $exception->getMessage());
    pageErreur(500, 'Une erreur est survenue', 'La suppression n\'a pas pu être effectuée. Merci de réessayer plus tard.', $racine, 'admin/index.php', 'Retour à la liste');
}

$nbInscrits  = (int) $evenement['nb_inscrits'];
$pageTitle   = 'Supprimer l\'événement ?';
$currentPage = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<h1>Supprimer cet événement ?</h1>

<div class="alert alert-error">
    <p>
        Vous êtes sur le point de supprimer <strong>« <?= e($evenement['titre']) ?> »</strong>.
        <?php if ($nbInscrits > 0): ?>
            Les <?= e($nbInscrits) ?> inscription<?= $nbInscrits > 1 ? 's' : '' ?> à cet événement seront supprimée<?= $nbInscrits > 1 ? 's' : '' ?> aussi.
        <?php endif; ?>
    </p>
    <p><strong>Cette action est définitive.</strong> Pour simplement le retirer du site, vous pouvez le dépublier.</p>
</div>

<form action="supprimer.php" method="post" class="actions">
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= e($evenement['id']) ?>">
    <button type="submit" class="button button-danger">Oui, supprimer définitivement</button>
    <a href="index.php">Non, revenir à la liste</a>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
