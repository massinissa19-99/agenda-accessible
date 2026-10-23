<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Cette page ne sert qu'à traiter le bouton « Se déconnecter » (méthode POST).
// Si on l'ouvre directement dans le navigateur, on retourne simplement à l'accueil.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    demarrerSession();

    if (csrfValide($_POST['csrf'] ?? null)) {
        deconnecterUtilisateur();
        ajouterFlash('Vous êtes déconnecté(e). À bientôt !');
    }
}

header('Location: index.php');
exit;
