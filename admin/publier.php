<?php
declare(strict_types=1);

$racine = '../';
require_once __DIR__ . '/../includes/auth.php';

exigerAdmin($racine);

// Cette page traite uniquement le bouton « Publier / Dépublier » de la liste (méthode POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = filter_var(postTexte('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $publie = postTexte('publie') === '1' ? 1 : 0;

    if (!csrfValide($_POST['csrf'] ?? null) || $id === false) {
        ajouterFlash('Action impossible : la page a expiré. Merci de réessayer.', 'erreur');
    } else {
        try {
            $requete = getPdo()->prepare('UPDATE evenements SET publie = :publie WHERE id = :id');
            $requete->execute([':publie' => $publie, ':id' => $id]);

            ajouterFlash($publie === 1
                ? 'L\'événement est publié : il est visible sur le site.'
                : 'L\'événement est dépublié : il redevient un brouillon.');
        } catch (PDOException $exception) {
            error_log('[Agenda] ' . $exception->getMessage());
            ajouterFlash('Une erreur est survenue. Merci de réessayer plus tard.', 'erreur');
        }
    }
}

header('Location: index.php');
exit;
