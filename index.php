<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$options = optionsAcces();

/* ---------- 1. Lecture et validation des paramètres de recherche (GET) ---------- */

$q = (isset($_GET['q']) && is_string($_GET['q']))
    ? mb_substr(trim($_GET['q']), 0, 100)
    : '';

$categorieId = filter_input(INPUT_GET, 'categorie', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($categorieId)) {
    $categorieId = null;
}

// Liste blanche : on ne garde que les clés connues (pmr, lsf, boucle, audio)
$accesDemandes = [];
if (isset($_GET['acces']) && is_array($_GET['acces'])) {
    $accesDemandes = array_values(array_intersect(
        array_keys($options),
        array_filter($_GET['acces'], 'is_string')
    ));
}

$filtresActifs = $q !== '' || $categorieId !== null || $accesDemandes !== [];

/* ---------- 2. Requêtes SQL (préparées) ---------- */

$categories = [];
$evenements = [];
$erreurBdd  = false;

try {
    $pdo = getPdo();

    $categories = $pdo->query('SELECT id, nom FROM categories ORDER BY nom')->fetchAll();

    $sql = 'SELECT e.id, e.titre, e.date_debut, e.lieu, e.prix,
                   e.acces_pmr, e.lsf, e.boucle_magnetique, e.audiodescription,
                   c.nom AS categorie
            FROM evenements e
            INNER JOIN categories c ON c.id = e.categorie_id';

    // Le public ne voit que les événements publiés (les brouillons restent dans l'administration)
    $conditions = ['e.publie = 1'];
    $parametres = [];

    if ($q !== '') {
        $conditions[] = '(e.titre LIKE :q1 OR e.description LIKE :q2 OR e.lieu LIKE :q3)';
        $motif = '%' . addcslashes($q, '%_\\') . '%';
        $parametres[':q1'] = $motif;
        $parametres[':q2'] = $motif;
        $parametres[':q3'] = $motif;
    }

    if ($categorieId !== null) {
        $conditions[] = 'e.categorie_id = :categorie';
        $parametres[':categorie'] = $categorieId;
    }

    // Les noms de colonnes viennent de NOTRE liste blanche, jamais de l'utilisateur
    foreach ($accesDemandes as $cle) {
        $conditions[] = 'e.' . $options[$cle]['colonne'] . ' = 1';
    }

    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY e.date_debut ASC';

    $requete = $pdo->prepare($sql);
    $requete->execute($parametres);
    $evenements = $requete->fetchAll();
} catch (PDOException $exception) {
    error_log('[Agenda] ' . $exception->getMessage());
    $erreurBdd = true;
    http_response_code(500);
}

$nombre = count($evenements);

/* ---------- 3. Affichage ---------- */

$pageTitle       = 'Agenda des événements';
$pageDescription = 'Recherchez des événements culturels et filtrez-les selon leurs options d\'accessibilité.';
$currentPage     = 'accueil';
require __DIR__ . '/includes/header.php';
?>

<h1>Agenda culturel accessible</h1>

<?php afficherFlash(); ?>
<p class="intro">
    Retrouvez les événements près de chez vous, avec pour chacun des informations
    précises sur l'accessibilité : accès en fauteuil roulant, langue des signes,
    boucle magnétique, audiodescription.
</p>

<!-- L'action se termine par #resultats : après l'envoi, le navigateur place le lecteur sur les résultats -->
<form class="search-form" action="index.php#resultats" method="get" role="search" aria-labelledby="titre-recherche">
    <h2 id="titre-recherche">Rechercher un événement</h2>

    <div class="field">
        <label for="q">Mot-clé</label>
        <p class="hint" id="q-aide">Titre, lieu ou description de l'événement.</p>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" maxlength="100" autocomplete="off" aria-describedby="q-aide">
    </div>

    <div class="field">
        <label for="categorie">Catégorie</label>
        <select id="categorie" name="categorie">
            <option value="">Toutes les catégories</option>
            <?php foreach ($categories as $categorie): ?>
                <option value="<?= e($categorie['id']) ?>"<?= attrSi($categorieId === (int) $categorie['id'], 'selected') ?>>
                    <?= e($categorie['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <fieldset class="field">
        <legend>Options d'accessibilité souhaitées</legend>
        <ul class="checkbox-list">
            <?php foreach ($options as $cle => $option): ?>
                <li>
                    <input type="checkbox" id="acces-<?= e($cle) ?>" name="acces[]" value="<?= e($cle) ?>"<?= attrSi(in_array($cle, $accesDemandes, true), 'checked') ?>>
                    <label for="acces-<?= e($cle) ?>"><?= e($option['long']) ?></label>
                </li>
            <?php endforeach; ?>
        </ul>
    </fieldset>

    <div class="actions">
        <button type="submit" class="button">Rechercher</button>
        <?php if ($filtresActifs): ?>
            <a href="index.php">Réinitialiser la recherche</a>
        <?php endif; ?>
    </div>
</form>

<section aria-labelledby="resultats">
    <?php if ($erreurBdd): ?>
        <h2 id="resultats" tabindex="-1">Résultats</h2>
        <p class="alert alert-error" role="alert">
            Les événements ne peuvent pas être affichés pour le moment. Merci de réessayer plus tard.
        </p>
    <?php else: ?>
        <h2 id="resultats" tabindex="-1">
            <?php if ($nombre === 0): ?>
                Aucun événement trouvé
            <?php else: ?>
                <?= e($nombre) ?> événement<?= $nombre > 1 ? 's trouvés' : ' trouvé' ?>
            <?php endif; ?>
        </h2>

        <?php if ($nombre === 0): ?>
            <p>Aucun événement ne correspond à votre recherche. Essayez avec moins de critères ou <a href="index.php">affichez tous les événements</a>.</p>
        <?php else: ?>
            <ul class="cards">
                <?php foreach ($evenements as $ev): ?>
                    <li>
                        <article class="card">
                            <p class="card-category"><?= e($ev['categorie']) ?></p>
                            <h3><a href="evenement.php?id=<?= e($ev['id']) ?>"><?= e($ev['titre']) ?></a></h3>
                            <p class="card-meta">
                                <time datetime="<?= e((new DateTimeImmutable($ev['date_debut']))->format('Y-m-d\TH:i')) ?>">
                                    <?= e(dateLongueFr($ev['date_debut'])) ?> à <?= e(heureFr($ev['date_debut'])) ?>
                                </time><br>
                                <?= e($ev['lieu']) ?><br>
                                <?= e(prixFr($ev['prix'])) ?>
                            </p>

                            <?php
                            $presentes = array_filter($options, fn (array $o): bool => (int) $ev[$o['colonne']] === 1);
                            ?>
                            <?php if ($presentes !== []): ?>
                                <ul class="badges" aria-label="Accessibilité de l'événement">
                                    <?php foreach ($presentes as $option): ?>
                                        <li class="badge"><?= e($option['court']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
