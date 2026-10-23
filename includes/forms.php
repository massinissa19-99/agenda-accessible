<?php
declare(strict_types=1);

/**
 * Aides pour construire des formulaires accessibles sans répéter le même HTML.
 * Chaque champ obtient : un <label> lié, une aide éventuelle, un message d'erreur en texte,
 * et les attributs ARIA (aria-describedby, aria-invalid).
 */

require_once __DIR__ . '/functions.php';

/**
 * Résumé des erreurs, à placer juste avant le formulaire.
 * Il reçoit le focus (autofocus), et chaque erreur est un lien vers son champ.
 * La clé 'global' sert aux erreurs qui ne concernent pas un champ précis.
 */
function resumeErreurs(array $erreurs): void
{
    if ($erreurs === []) {
        return;
    }

    $nombre = count($erreurs);

    echo '<div class="alert alert-error" role="alert" tabindex="-1" autofocus aria-labelledby="titre-erreurs">';
    echo '<h2 id="titre-erreurs">'
        . ($nombre > 1 ? 'Le formulaire contient ' . $nombre . ' erreurs' : 'Le formulaire contient une erreur')
        . '</h2><ul>';

    foreach ($erreurs as $champ => $message) {
        echo '<li>';
        if ($champ === 'global') {
            echo e($message);
        } else {
            echo '<a href="#' . e($champ) . '">' . e($message) . '</a>';
        }
        echo '</li>';
    }

    echo '</ul></div>';
}

/**
 * Affiche un champ de formulaire complet.
 *
 * Paramètres de $p :
 *   id (obligatoire), label (obligatoire), type (text, email, password, date, time, textarea, select...),
 *   valeur, aide, obligatoire (true par défaut), name (= id par défaut),
 *   autocomplete, maxlength, min, max, step, rows, inputmode,
 *   options + option_vide (pour select)
 */
function champ(array $p, array $erreurs): void
{
    $id          = (string) $p['id'];
    $type        = (string) ($p['type'] ?? 'text');
    $valeur      = (string) ($p['valeur'] ?? '');
    $aide        = $p['aide'] ?? null;
    $obligatoire = (bool) ($p['obligatoire'] ?? true);
    $erreur      = $erreurs[$id] ?? null;

    $attributs = '';
    foreach (['autocomplete', 'maxlength', 'min', 'max', 'step', 'rows', 'inputmode'] as $nom) {
        if (isset($p[$nom])) {
            $attributs .= ' ' . $nom . '="' . e($p[$nom]) . '"';
        }
    }

    $communs = ' id="' . e($id) . '" name="' . e($p['name'] ?? $id) . '"'
        . $attributs
        . ($obligatoire ? ' required' : '')
        . attributsChamp($id, $erreurs, $aide !== null);

    echo '<div class="field' . ($erreur !== null ? ' has-error' : '') . '">';

    echo '<label for="' . e($id) . '">' . e($p['label'])
        . ' <span class="required">(' . ($obligatoire ? 'obligatoire' : 'facultatif') . ')</span></label>';

    if ($aide !== null) {
        echo '<p class="hint" id="' . e($id) . '-aide">' . e($aide) . '</p>';
    }

    if ($erreur !== null) {
        echo '<p class="error-message" id="' . e($id) . '-erreur">'
            . '<span class="visually-hidden">Erreur : </span>' . e($erreur) . '</p>';
    }

    if ($type === 'textarea') {
        echo '<textarea' . $communs . '>' . e($valeur) . '</textarea>';
    } elseif ($type === 'select') {
        echo '<select' . $communs . '>';
        echo '<option value="">' . e($p['option_vide'] ?? 'Choisir') . '</option>';
        foreach (($p['options'] ?? []) as $cle => $libelle) {
            echo '<option value="' . e($cle) . '"' . attrSi((string) $cle === $valeur, 'selected') . '>'
                . e($libelle) . '</option>';
        }
        echo '</select>';
    } else {
        // Un mot de passe n'est jamais réaffiché dans la page
        $valeurHtml = $type === 'password' ? '' : ' value="' . e($valeur) . '"';
        echo '<input type="' . e($type) . '"' . $communs . $valeurHtml . '>';
    }

    echo '</div>';
}
