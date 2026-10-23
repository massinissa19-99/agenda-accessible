<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

exigerConnexion();
$utilisateur = utilisateurCourant();

$inscriptions = [];
$erreurBdd    = false;

try {
    $requete = getPdo()->prepare(
        'SELECT e.id, e.titre, e.date_debut, e.lieu
         FROM inscriptions i
         INNER JOIN evenements e ON e.id = i.evenement_id
         WHERE i.utilisateur_id = :id AND e.publie = 1
         ORDER BY e.date_debut ASC'
    );
    $requete->execute([':id' => $utilisateur['id']]);
    $inscriptions = $requete->fetchAll();
} catch (PDOException $exception) {
    error_log('[Agenda] ' . $exception->getMessage());
    $erreurBdd = true;
    http_response_code(500);
}

$maintenant = new DateTimeImmutable('now');

$pageTitle       = 'Mon compte';
$pageDescription = 'Vos informations et vos inscriptions aux événements.';
$currentPage     = 'compte';
require __DIR__ . '/includes/header.php';
?>

<h1>Mon compte</h1>

<?php afficherFlash(); ?>

<h2>Mes informations</h2>
<dl class="details">
    <div>
        <dt>Nom</dt>
        <dd><?= e($utilisateur['nom']) ?></dd>
    </div>
    <div>
        <dt>Adresse e-mail</dt>
        <dd><?= e($utilisateur['email']) ?></dd>
    </div>
    <div>
        <dt>Statut</dt>
        <dd><?= $utilisateur['role'] === 'admin' ? 'Administrateur' : 'Membre' ?></dd>
    </div>
</dl>

<p class="page-actions">
    <a href="mot-de-passe.php">Changer mon mot de passe</a>
    <?php if ($utilisateur['role'] === 'admin'): ?>
        <a href="admin/index.php">Aller à l'administration</a>
    <?php endif; ?>
</p>

<h2>Mes inscriptions</h2>

<?php if ($erreurBdd): ?>
    <p class="alert alert-error" role="alert">Vos inscriptions ne peuvent pas être affichées pour le moment. Merci de réessayer plus tard.</p>
<?php elseif ($inscriptions === []): ?>
    <p>Vous n'êtes inscrit(e) à aucun événement pour le moment. <a href="index.php">Découvrir l'agenda</a>.</p>
<?php else: ?>
    <ul class="stack">
        <?php foreach ($inscriptions as $ev): ?>
            <?php $termine = new DateTimeImmutable($ev['date_debut']) < $maintenant; ?>
            <li>
                <article class="card">
                    <h3><a href="evenement.php?id=<?= e($ev['id']) ?>"><?= e($ev['titre']) ?></a></h3>
                    <p class="card-meta">
                        <time datetime="<?= e((new DateTimeImmutable($ev['date_debut']))->format('Y-m-d\TH:i')) ?>">
                            <?= e(dateLongueFr($ev['date_debut'])) ?> à <?= e(heureFr($ev['date_debut'])) ?>
                        </time><br>
                        <?= e($ev['lieu']) ?><br>
                        <strong><?= $termine ? 'Événement terminé' : 'À venir' ?></strong>
                    </p>
                </article>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
