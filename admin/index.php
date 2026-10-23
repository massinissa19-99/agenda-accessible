<?php
declare(strict_types=1);

$racine = '../';   // cette page est dans le sous-dossier admin/
require_once __DIR__ . '/../includes/auth.php';

exigerAdmin($racine);

$evenements = [];
$erreurBdd  = false;

try {
    $evenements = getPdo()->query(
        'SELECT e.id, e.titre, e.date_debut, e.publie, c.nom AS categorie,
                (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id) AS nb_inscrits
         FROM evenements e
         INNER JOIN categories c ON c.id = e.categorie_id
         ORDER BY e.date_debut DESC'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log('[Agenda] ' . $exception->getMessage());
    $erreurBdd = true;
    http_response_code(500);
}

$pageTitle   = 'Administration des événements';
$currentPage = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<h1>Administration</h1>

<?php afficherNavAdmin('evenements'); ?>
<?php afficherFlash(); ?>

<h2 id="titre-liste">Tous les événements</h2>

<?php if ($erreurBdd): ?>
    <p class="alert alert-error" role="alert">La liste ne peut pas être affichée pour le moment.</p>
<?php elseif ($evenements === []): ?>
    <p>Aucun événement pour le moment. <a href="evenement.php">Créer le premier événement</a>.</p>
<?php else: ?>
    <p><?= e(count($evenements)) ?> événement<?= count($evenements) > 1 ? 's' : '' ?>, du plus récent au plus ancien.</p>

    <!-- Zone défilable au clavier (tabindex="0") et nommée, pour les petits écrans -->
    <div class="table-wrap" role="region" aria-labelledby="titre-liste" tabindex="0">
        <table aria-labelledby="titre-liste">
            <thead>
                <tr>
                    <th scope="col">Événement</th>
                    <th scope="col">Date</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Inscrits</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($evenements as $ev): ?>
                    <?php $publie = (int) $ev['publie'] === 1; ?>
                    <tr>
                        <th scope="row">
                            <?= e($ev['titre']) ?><br>
                            <span class="table-sub"><?= e($ev['categorie']) ?></span>
                        </th>
                        <td>
                            <time datetime="<?= e((new DateTimeImmutable($ev['date_debut']))->format('Y-m-d\TH:i')) ?>">
                                <?= e(dateLongueFr($ev['date_debut'])) ?><br>
                                <?= e(heureFr($ev['date_debut'])) ?>
                            </time>
                        </td>
                        <td>
                            <!-- Le statut est écrit en toutes lettres ; la bordure en pointillés est un indice visuel supplémentaire -->
                            <span class="badge<?= $publie ? '' : ' badge-draft' ?>"><?= $publie ? 'Publié' : 'Brouillon' ?></span>
                        </td>
                        <td><?= e($ev['nb_inscrits']) ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="evenement.php?id=<?= e($ev['id']) ?>">Modifier<span class="visually-hidden"> l'événement <?= e($ev['titre']) ?></span></a>

                                <form action="publier.php" method="post">
                                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($ev['id']) ?>">
                                    <input type="hidden" name="publie" value="<?= $publie ? '0' : '1' ?>">
                                    <button type="submit" class="button button-secondary button-small">
                                        <?= $publie ? 'Dépublier' : 'Publier' ?><span class="visually-hidden"> l'événement <?= e($ev['titre']) ?></span>
                                    </button>
                                </form>

                                <a href="supprimer.php?id=<?= e($ev['id']) ?>">Supprimer<span class="visually-hidden"> l'événement <?= e($ev['titre']) ?></span></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
