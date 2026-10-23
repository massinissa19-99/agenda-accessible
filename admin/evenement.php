<?php
declare(strict_types=1);

$racine = '../';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/forms.php';

exigerAdmin($racine);

$options = optionsAcces();

// Avec ?id=... on modifie un événement existant ; sans id, on en crée un nouveau
$id           = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$modification = is_int($id);

$erreurs     = [];
$categories  = [];
$inscrits    = [];
$accesCoches = [];
$publie      = false;   // un nouvel événement démarre en brouillon : on choisit ensuite de le publier

// Les noms de ces clés sont aussi les noms des champs du formulaire
$valeurs = [
    'titre'         => '',
    'description'   => '',
    'date'          => '',
    'heure'         => '',
    'duree_minutes' => '60',
    'lieu'          => '',
    'adresse'       => '',
    'categorie_id'  => '',
    'prix'          => '0',
    'infos_acces'   => '',
];

try {
    $pdo = getPdo();
    $categories = $pdo->query('SELECT id, nom FROM categories ORDER BY nom')->fetchAll();

    /* ---------- Chargement de l'événement à modifier ---------- */
    if ($modification) {
        $requete = $pdo->prepare('SELECT * FROM evenements WHERE id = :id');
        $requete->execute([':id' => $id]);
        $existant = $requete->fetch();

        if ($existant === false) {
            pageErreur(404, 'Événement introuvable', 'Cet événement n\'existe pas ou n\'existe plus.', $racine, 'admin/index.php', 'Retour à la liste');
        }

        $debut = new DateTimeImmutable($existant['date_debut']);
        $valeurs = [
            'titre'         => $existant['titre'],
            'description'   => $existant['description'],
            'date'          => $debut->format('Y-m-d'),
            'heure'         => $debut->format('H:i'),
            'duree_minutes' => (string) $existant['duree_minutes'],
            'lieu'          => $existant['lieu'],
            'adresse'       => $existant['adresse'],
            'categorie_id'  => (string) $existant['categorie_id'],
            'prix'          => number_format((float) $existant['prix'], 2, ',', ''),
            'infos_acces'   => (string) $existant['infos_acces'],
        ];
        foreach ($options as $cle => $option) {
            if ((int) $existant[$option['colonne']] === 1) {
                $accesCoches[] = $cle;
            }
        }
        $publie = (int) $existant['publie'] === 1;

        $liste = $pdo->prepare(
            'SELECT u.nom, u.email, i.created_at
             FROM inscriptions i
             INNER JOIN utilisateurs u ON u.id = i.utilisateur_id
             WHERE i.evenement_id = :id
             ORDER BY i.created_at ASC'
        );
        $liste->execute([':id' => $id]);
        $inscrits = $liste->fetchAll();
    }

    /* ---------- Traitement du formulaire ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (array_keys($valeurs) as $cle) {
            $valeurs[$cle] = postTexte($cle);
        }

        // Liste blanche : seules les clés connues sont acceptées
        $accesCoches = [];
        if (isset($_POST['acces']) && is_array($_POST['acces'])) {
            $accesCoches = array_values(array_intersect(
                array_keys($options),
                array_filter($_POST['acces'], 'is_string')
            ));
        }
        $publie = isset($_POST['publie']);

        if (!csrfValide($_POST['csrf'] ?? null)) {
            $erreurs['global'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
        }

        if ($valeurs['titre'] === '') {
            $erreurs['titre'] = 'Indiquez le titre de l\'événement.';
        } elseif (mb_strlen($valeurs['titre']) > 150) {
            $erreurs['titre'] = 'Le titre ne doit pas dépasser 150 caractères.';
        }

        if ($valeurs['description'] === '') {
            $erreurs['description'] = 'Indiquez une description.';
        } elseif (mb_strlen($valeurs['description']) > 5000) {
            $erreurs['description'] = 'La description ne doit pas dépasser 5000 caractères.';
        }

        // On relit la date et l'heure avec leur format strict, puis on vérifie qu'elles ressortent identiques
        // (cela refuse par exemple le 31 février)
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $valeurs['date']);
        if ($date === false || $date->format('Y-m-d') !== $valeurs['date']) {
            $erreurs['date'] = 'Indiquez une date valide.';
        }

        $heure = DateTimeImmutable::createFromFormat('!H:i', $valeurs['heure']);
        if ($heure === false || $heure->format('H:i') !== $valeurs['heure']) {
            $erreurs['heure'] = 'Indiquez une heure valide, par exemple 20:30.';
        }

        $duree = filter_var($valeurs['duree_minutes'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1440]]);
        if ($duree === false) {
            $erreurs['duree_minutes'] = 'Indiquez une durée en minutes, entre 1 et 1440.';
        }

        if ($valeurs['lieu'] === '') {
            $erreurs['lieu'] = 'Indiquez le nom du lieu.';
        } elseif (mb_strlen($valeurs['lieu']) > 120) {
            $erreurs['lieu'] = 'Le nom du lieu ne doit pas dépasser 120 caractères.';
        }

        if ($valeurs['adresse'] === '') {
            $erreurs['adresse'] = 'Indiquez l\'adresse.';
        } elseif (mb_strlen($valeurs['adresse']) > 200) {
            $erreurs['adresse'] = 'L\'adresse ne doit pas dépasser 200 caractères.';
        }

        $categorieChoisie = filter_var($valeurs['categorie_id'], FILTER_VALIDATE_INT);
        $categoriesValides = array_map('intval', array_column($categories, 'id'));
        if ($categorieChoisie === false || !in_array($categorieChoisie, $categoriesValides, true)) {
            $erreurs['categorie_id'] = 'Choisissez une catégorie dans la liste.';
        }

        $prixTexte = str_replace(',', '.', $valeurs['prix']);
        if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $prixTexte)) {
            $erreurs['prix'] = 'Indiquez un prix en euros, par exemple 8,50. Indiquez 0 si l\'événement est gratuit.';
        }

        if (mb_strlen($valeurs['infos_acces']) > 2000) {
            $erreurs['infos_acces'] = 'Les précisions ne doivent pas dépasser 2000 caractères.';
        }

        /* ---------- Enregistrement ---------- */
        if ($erreurs === []) {
            $parametres = [
                ':titre'       => $valeurs['titre'],
                ':description' => $valeurs['description'],
                ':date_debut'  => $valeurs['date'] . ' ' . $valeurs['heure'] . ':00',
                ':duree'       => $duree,
                ':lieu'        => $valeurs['lieu'],
                ':adresse'     => $valeurs['adresse'],
                ':categorie'   => $categorieChoisie,
                ':prix'        => number_format((float) $prixTexte, 2, '.', ''),
                ':pmr'         => in_array('pmr', $accesCoches, true) ? 1 : 0,
                ':lsf'         => in_array('lsf', $accesCoches, true) ? 1 : 0,
                ':boucle'      => in_array('boucle', $accesCoches, true) ? 1 : 0,
                ':audio'       => in_array('audio', $accesCoches, true) ? 1 : 0,
                ':infos'       => $valeurs['infos_acces'] === '' ? null : $valeurs['infos_acces'],
                ':publie'      => $publie ? 1 : 0,
            ];

            if ($modification) {
                $sql = 'UPDATE evenements SET
                            titre = :titre, description = :description, date_debut = :date_debut,
                            duree_minutes = :duree, lieu = :lieu, adresse = :adresse,
                            categorie_id = :categorie, prix = :prix,
                            acces_pmr = :pmr, lsf = :lsf, boucle_magnetique = :boucle, audiodescription = :audio,
                            infos_acces = :infos, publie = :publie
                        WHERE id = :id';
                $parametres[':id'] = $id;
            } else {
                $sql = 'INSERT INTO evenements
                            (titre, description, date_debut, duree_minutes, lieu, adresse, categorie_id, prix,
                             acces_pmr, lsf, boucle_magnetique, audiodescription, infos_acces, publie)
                        VALUES
                            (:titre, :description, :date_debut, :duree, :lieu, :adresse, :categorie, :prix,
                             :pmr, :lsf, :boucle, :audio, :infos, :publie)';
            }

            $pdo->prepare($sql)->execute($parametres);

            ajouterFlash(
                'L\'événement « ' . $valeurs['titre'] . ' » a été '
                . ($modification ? 'modifié' : 'créé')
                . ($publie ? ' et il est publié.' : ' ; c\'est un brouillon, il n\'est pas visible sur le site.')
            );
            header('Location: index.php');
            exit;
        }
    }
} catch (PDOException $exception) {
    error_log('[Agenda] ' . $exception->getMessage());
    $erreurs['global'] = 'L\'opération a échoué. Merci de réessayer plus tard.';
    http_response_code(500);
}

/* ---------- Affichage ---------- */

$titrePage   = $modification ? 'Modifier l\'événement' : 'Créer un événement';
$pageTitle   = ($erreurs !== [] ? 'Erreur dans le formulaire – ' : '') . $titrePage;
$currentPage = 'admin';
require __DIR__ . '/../includes/header.php';

$optionsCategories = [];
foreach ($categories as $categorie) {
    $optionsCategories[$categorie['id']] = $categorie['nom'];
}
?>

<h1><?= e($titrePage) ?></h1>

<?php afficherNavAdmin($modification ? 'evenements' : 'nouveau'); ?>

<?php resumeErreurs($erreurs); ?>

<?php if ($modification): ?>
    <p><a href="../evenement.php?id=<?= e($id) ?>">Voir la fiche telle que les visiteurs la verront</a></p>
<?php endif; ?>

<form class="admin-form" action="evenement.php<?= $modification ? '?id=' . e($id) : '' ?>" method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <?php
    champ(['id' => 'titre', 'label' => 'Titre', 'valeur' => $valeurs['titre'], 'maxlength' => 150], $erreurs);
    champ(['id' => 'description', 'label' => 'Description', 'type' => 'textarea', 'valeur' => $valeurs['description'], 'rows' => 8, 'maxlength' => 5000], $erreurs);
    champ(['id' => 'date', 'label' => 'Date', 'type' => 'date', 'valeur' => $valeurs['date']], $erreurs);
    champ(['id' => 'heure', 'label' => 'Heure de début', 'type' => 'time', 'valeur' => $valeurs['heure']], $erreurs);
    champ(['id' => 'duree_minutes', 'label' => 'Durée (en minutes)', 'type' => 'text', 'inputmode' => 'numeric', 'valeur' => $valeurs['duree_minutes'], 'maxlength' => 4], $erreurs);
    champ(['id' => 'lieu', 'label' => 'Nom du lieu', 'valeur' => $valeurs['lieu'], 'maxlength' => 120], $erreurs);
    champ(['id' => 'adresse', 'label' => 'Adresse', 'valeur' => $valeurs['adresse'], 'maxlength' => 200], $erreurs);
    champ(['id' => 'categorie_id', 'label' => 'Catégorie', 'type' => 'select', 'valeur' => $valeurs['categorie_id'], 'options' => $optionsCategories, 'option_vide' => 'Choisir une catégorie'], $erreurs);
    champ(['id' => 'prix', 'label' => 'Prix (en euros)', 'inputmode' => 'decimal', 'valeur' => $valeurs['prix'], 'maxlength' => 7, 'aide' => 'Exemple : 8,50. Indiquez 0 si l\'événement est gratuit.'], $erreurs);
    ?>

    <fieldset class="field">
        <legend>Options d'accessibilité proposées</legend>
        <ul class="checkbox-list">
            <?php foreach ($options as $cle => $option): ?>
                <li>
                    <input type="checkbox" id="acces-<?= e($cle) ?>" name="acces[]" value="<?= e($cle) ?>"<?= attrSi(in_array($cle, $accesCoches, true), 'checked') ?>>
                    <label for="acces-<?= e($cle) ?>"><?= e($option['long']) ?></label>
                </li>
            <?php endforeach; ?>
        </ul>
    </fieldset>

    <?php
    champ(['id' => 'infos_acces', 'label' => 'Précisions sur l\'accès', 'type' => 'textarea', 'valeur' => $valeurs['infos_acces'], 'rows' => 5, 'maxlength' => 2000, 'obligatoire' => false,
           'aide' => 'Entrée, ascenseur, places réservées, toilettes adaptées, chiens guides, etc.'], $erreurs);
    ?>

    <fieldset class="field">
        <legend>Publication</legend>
        <ul class="checkbox-list">
            <li>
                <input type="checkbox" id="publie" name="publie" value="1"<?= attrSi($publie, 'checked') ?>>
                <label for="publie">Publier cet événement (le rendre visible sur le site)</label>
            </li>
        </ul>
        <p class="hint">Si la case n'est pas cochée, l'événement reste un brouillon visible des administrateurs seulement.</p>
    </fieldset>

    <div class="actions">
        <button type="submit" class="button">Enregistrer l'événement</button>
        <a href="index.php">Annuler</a>
    </div>
</form>

<?php if ($modification): ?>
    <h2>Personnes inscrites (<?= e(count($inscrits)) ?>)</h2>
    <?php if ($inscrits === []): ?>
        <p>Personne ne s'est encore inscrit à cet événement.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($inscrits as $inscrit): ?>
                <li>
                    <?= e($inscrit['nom']) ?> (<a href="mailto:<?= e($inscrit['email']) ?>"><?= e($inscrit['email']) ?></a>),
                    inscrit(e) le <?= e(dateLongueFr($inscrit['created_at'])) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
