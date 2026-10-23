<?php
declare(strict_types=1);

$racine = '../';
require_once __DIR__ . '/../includes/auth.php';

exigerAdmin($racine);

$sujets   = sujetsContact();
$messages = [];
$erreurBdd = false;

try {
    $messages = getPdo()->query(
        'SELECT id, nom, email, sujet, message, created_at
         FROM messages
         ORDER BY created_at DESC, id DESC
         LIMIT 100'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log('[Agenda] ' . $exception->getMessage());
    $erreurBdd = true;
    http_response_code(500);
}

$pageTitle   = 'Messages reçus';
$currentPage = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<h1>Messages reçus</h1>

<?php afficherNavAdmin('messages'); ?>

<?php if ($erreurBdd): ?>
    <p class="alert alert-error" role="alert">Les messages ne peuvent pas être affichés pour le moment.</p>
<?php elseif ($messages === []): ?>
    <p>Aucun message reçu pour le moment.</p>
<?php else: ?>
    <p><?= e(count($messages)) ?> message<?= count($messages) > 1 ? 's' : '' ?> (les 100 plus récents), du plus récent au plus ancien.</p>

    <ul class="stack">
        <?php foreach ($messages as $m): ?>
            <li>
                <article class="card">
                    <h2><?= e($sujets[$m['sujet']] ?? $m['sujet']) ?></h2>
                    <p class="card-meta">
                        De <?= e($m['nom']) ?> (<a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>)<br>
                        Reçu le <?= e(dateLongueFr($m['created_at'])) ?> à <?= e(heureFr($m['created_at'])) ?>
                    </p>
                    <p><?= nl2br(e($m['message'])) ?></p>
                </article>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
