<?php
declare(strict_types=1);

/**
 * Authentification : inscription, connexion, sessions, rôles.
 *
 * Principe : après une connexion réussie, on garde en SESSION uniquement l'identifiant
 * de l'utilisateur. À chaque page, on relit ses informations (et son rôle) dans la base :
 * si un administrateur est rétrogradé ou supprimé, c'est pris en compte immédiatement.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

/**
 * Hachage d'un mot de passe bidon. On le vérifie quand l'adresse e-mail n'existe pas,
 * pour que la réponse prenne le même temps : on ne révèle pas quelles adresses ont un compte.
 */
const HASH_FACTICE = '$2y$10$b/DUEEdyAIZeD2vnuWiemORsuBSpjGSMpyjftmUUpBeCdRLXezVwe';

/** Utilisateur connecté (tableau id, nom, email, role) ou null. */
function utilisateurCourant(): ?array
{
    static $charge = false;
    static $utilisateur = null;

    if ($charge) {
        return $utilisateur;
    }
    $charge = true;

    // Pas de cookie de session : visiteur anonyme, inutile d'ouvrir la session ni la base
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!isset($_COOKIE[session_name()])) {
            return null;
        }
        demarrerSession();
    }

    $id = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }

    try {
        $requete = getPdo()->prepare('SELECT id, nom, email, role FROM utilisateurs WHERE id = :id');
        $requete->execute([':id' => $id]);
        $ligne = $requete->fetch();
    } catch (PDOException $exception) {
        error_log('[Agenda] ' . $exception->getMessage());
        return null;
    }

    if ($ligne === false) {
        unset($_SESSION['utilisateur_id']);   // le compte n'existe plus
        return null;
    }

    $utilisateur = $ligne;

    return $utilisateur;
}

function estConnecte(): bool
{
    return utilisateurCourant() !== null;
}

function estAdmin(): bool
{
    $utilisateur = utilisateurCourant();

    return $utilisateur !== null && $utilisateur['role'] === 'admin';
}

/** Ouvre la session de l'utilisateur après vérification de son mot de passe. */
function connecterUtilisateur(int $id): void
{
    demarrerSession();
    session_regenerate_id(true);       // nouvel identifiant de session : empêche la "fixation de session"
    $_SESSION['utilisateur_id'] = $id;
    unset($_SESSION['csrf']);          // nouveau jeton CSRF pour la nouvelle session
}

function deconnecterUtilisateur(): void
{
    demarrerSession();
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Règles de mot de passe. Retourne un message d'erreur, ou null si le mot de passe convient. */
function validerMotDePasse(string $motDePasse): ?string
{
    if ($motDePasse === '') {
        return 'Choisissez un mot de passe.';
    }
    if (mb_strlen($motDePasse) < 10) {
        return 'Votre mot de passe doit contenir au moins 10 caractères.';
    }
    // bcrypt (utilisé par PASSWORD_DEFAULT) ignore tout ce qui dépasse 72 octets
    if (strlen($motDePasse) > 72) {
        return 'Votre mot de passe ne doit pas dépasser 72 caractères.';
    }

    return null;
}

/** Affiche une page d'erreur complète (403, 404...) et arrête le script. */
function pageErreur(
    int $code,
    string $titre,
    string $message,
    string $racine = '',
    string $lienRetour = 'index.php',
    string $libelleRetour = 'Retour à l\'agenda'
): never {
    http_response_code($code);
    $pageTitle   = $titre;
    $currentPage = '';
    require __DIR__ . '/header.php';
    echo '<h1>' . e($titre) . '</h1>';
    echo '<p>' . e($message) . '</p>';
    echo '<p><a href="' . e($racine . $lienRetour) . '">' . e($libelleRetour) . '</a></p>';
    require __DIR__ . '/footer.php';
    exit;
}

/** Réserve une page aux personnes connectées. $racine = chemin vers la racine du site ('' ou '../'). */
function exigerConnexion(string $racine = ''): void
{
    if (!estConnecte()) {
        ajouterFlash('Connectez-vous pour accéder à cette page.', 'erreur');
        header('Location: ' . $racine . 'connexion.php');
        exit;
    }
}

/** Réserve une page aux administrateurs (403 pour les simples membres). */
function exigerAdmin(string $racine = ''): void
{
    exigerConnexion($racine);

    if (!estAdmin()) {
        pageErreur(403, 'Accès refusé', 'Cette page est réservée aux administrateurs du site.', $racine);
    }
}

/** Menu secondaire de l'espace administrateur. $actif : 'evenements', 'nouveau' ou 'messages'. */
function afficherNavAdmin(string $actif): void
{
    $liens = [
        'evenements' => ['index.php', 'Événements'],
        'nouveau'    => ['evenement.php', 'Nouvel événement'],
        'messages'   => ['messages.php', 'Messages reçus'],
    ];

    echo '<nav class="admin-nav" aria-label="Administration"><ul>';
    foreach ($liens as $cle => [$url, $libelle]) {
        echo '<li><a href="' . e($url) . '"' . ($cle === $actif ? ' aria-current="page"' : '') . '>' . e($libelle) . '</a></li>';
    }
    echo '</ul></nav>';
}
