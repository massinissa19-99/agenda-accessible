<?php
declare(strict_types=1);

// Heure locale du site (utilisée pour savoir si un événement est passé)
date_default_timezone_set('Europe/Paris');

/**
 * Échappe une valeur avant de l'afficher en HTML (protection contre les failles XSS).
 * À utiliser pour TOUTE donnée venant de la base ou de l'utilisateur.
 */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Options d'accessibilité d'un événement.
 * La clé sert dans l'URL (?acces[]=pmr), "colonne" est le nom dans la base.
 * Seules ces clés sont acceptées : c'est une liste blanche.
 */
function optionsAcces(): array
{
    return [
        'pmr' => [
            'colonne' => 'acces_pmr',
            'court'   => 'Accès PMR',
            'long'    => 'Accès pour personnes à mobilité réduite',
        ],
        'lsf' => [
            'colonne' => 'lsf',
            'court'   => 'LSF',
            'long'    => 'Interprétation en langue des signes française (LSF)',
        ],
        'boucle' => [
            'colonne' => 'boucle_magnetique',
            'court'   => 'Boucle magnétique',
            'long'    => 'Boucle magnétique pour les personnes appareillées',
        ],
        'audio' => [
            'colonne' => 'audiodescription',
            'court'   => 'Audiodescription',
            'long'    => 'Audiodescription pour les personnes aveugles ou malvoyantes',
        ],
    ];
}

/** Date en français : « samedi 14 novembre 2026 ». */
function dateLongueFr(string $dateHeure): string
{
    static $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    static $mois  = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
                     'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    $d = new DateTimeImmutable($dateHeure);

    return sprintf(
        '%s %d %s %d',
        $jours[(int) $d->format('w')],
        (int) $d->format('j'),
        $mois[(int) $d->format('n')],
        (int) $d->format('Y')
    );
}

/** Heure en français : « 20 h 30 » ou « 20 h ». */
function heureFr(string $dateHeure): string
{
    $d = new DateTimeImmutable($dateHeure);
    $minutes = $d->format('i');

    return $d->format('G') . ' h' . ($minutes === '00' ? '' : ' ' . $minutes);
}

/** Durée en français : 95 → « 1 h 35 », 45 → « 45 min ». */
function dureeFr(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' min';
    }

    $h = intdiv($minutes, 60);
    $m = $minutes % 60;

    return $h . ' h' . ($m > 0 ? ' ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
}

/** Prix en français : « Gratuit » ou « 8,00 € ». */
function prixFr(float|string $prix): string
{
    $prix = (float) $prix;

    return $prix > 0
        ? number_format($prix, 2, ',', ' ') . "\u{00A0}€"
        : 'Gratuit';
}

/** Valeur d'un attribut HTML booléen (checked, selected...) si la condition est vraie. */
function attrSi(bool $condition, string $attribut): string
{
    return $condition ? ' ' . $attribut : '';
}

/** Démarre la session une seule fois (cookies plus sûrs). */
function demarrerSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** Jeton CSRF : prouve que le formulaire vient bien de notre site. */
function csrfToken(): string
{
    demarrerSession();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrfValide(mixed $jeton): bool
{
    demarrerSession();

    return is_string($jeton)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $jeton);
}

/**
 * Attributs ARIA d'un champ de formulaire :
 *  - aria-describedby relie le champ à son aide et/ou à son message d'erreur ;
 *  - aria-invalid="true" signale l'erreur aux lecteurs d'écran.
 * Le résultat est du HTML de confiance (construit ici, avec e()).
 */
function attributsChamp(string $id, array $erreurs, bool $avecAide = false): string
{
    $ids = [];
    if ($avecAide) {
        $ids[] = $id . '-aide';
    }
    if (isset($erreurs[$id])) {
        $ids[] = $id . '-erreur';
    }

    $html = '';
    if ($ids !== []) {
        $html .= ' aria-describedby="' . e(implode(' ', $ids)) . '"';
    }
    if (isset($erreurs[$id])) {
        $html .= ' aria-invalid="true"';
    }

    return $html;
}

/** Sujets proposés dans le formulaire de contact (clé enregistrée en base => libellé). */
function sujetsContact(): array
{
    return [
        'question'    => 'Question générale',
        'signalement' => 'Signaler un problème d\'accessibilité',
        'evenement'   => 'Proposer un événement',
    ];
}

/** Lit un champ texte envoyé en POST, sans les espaces autour ('' s'il est absent). */
function postTexte(string $cle): string
{
    return (isset($_POST[$cle]) && is_string($_POST[$cle])) ? trim($_POST[$cle]) : '';
}

/** Lit un champ POST tel quel (mots de passe : les espaces comptent). */
function postBrut(string $cle): string
{
    return (isset($_POST[$cle]) && is_string($_POST[$cle])) ? $_POST[$cle] : '';
}

/**
 * Message "flash" : affiché une seule fois, à la page suivante (après une redirection).
 * Type : 'succes' ou 'erreur'.
 */
function ajouterFlash(string $message, string $type = 'succes'): void
{
    demarrerSession();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Affiche (puis efface) les messages flash. À appeler juste après le <h1> de la page. */
function afficherFlash(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!isset($_COOKIE[session_name()])) {
            return;
        }
        demarrerSession();
    }

    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    foreach ($messages as $m) {
        $erreur = $m['type'] === 'erreur';
        // role="status" : annonce discrète ; role="alert" : annonce immédiate (erreurs)
        echo '<div class="alert ' . ($erreur ? 'alert-error' : 'alert-success') . '"'
            . ' role="' . ($erreur ? 'alert' : 'status') . '" tabindex="-1" autofocus>'
            . '<p>' . e($m['message']) . '</p></div>';
    }
}
