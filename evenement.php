<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$options = optionsAcces();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

/* ---------- 1. Inscription / désinscription (formulaires POST) ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_int($id)) {
    demarrerSession();
    $retour = 'Location: evenement.php?id=' . $id;

    if (!csrfValide($_POST['csrf'] ?? null)) {
        ajouterFlash('Votre session a expiré. Merci de réessayer.', 'erreur');
        header($retour);
        exit;
    }

    $membre = utilisateurCourant();
    if ($membre === null) {
        ajouterFlash('Connectez-vous pour vous inscrire à un événement.', 'erreur');
        header('Location: connexion.php');
        exit;
    }

    $action = postTexte('action');

    try {
        $pdo = getPdo();
        $requete = $pdo->prepare('SELECT titre, date_debut, publie FROM evenements WHERE id = :id');
        $requete->execute([':id' => $id]);
        $cible = $requete->fetch();

        if ($cible === false || (int) $cible['publie'] !== 1) {
            ajouterFlash('Cet événement n\'est pas disponible.', 'erreur');
        } elseif ($action === 'inscrire') {
            if (new DateTimeImmutable($cible['date_debut']) < new DateTimeImmutable('now')) {
                ajouterFlash('Cet événement est terminé : les inscriptions sont closes.', 'erreur');
            } else {
                // INSERT IGNORE : si la personne est déjà inscrite, la clé primaire refuse le doublon sans erreur
                $pdo->prepare('INSERT IGNORE INTO inscriptions (utilisateur_id, evenement_id) VALUES (:u, :e)')
                    ->execute([':u' => $membre['id'], ':e' => $id]);
                ajouterFlash('Votre inscription à « ' . $cible['titre'] . ' » est enregistrée.');
            }
        } elseif ($action === 'desinscrire') {
            $pdo->prepare('DELETE FROM inscriptions WHERE utilisateur_id = :u AND evenement_id = :e')
                ->execute([':u' => $membre['id'], ':e' => $id]);
            ajouterFlash('Votre inscription à « ' . $cible['titre'] . ' » est annulée.');
        }
    } catch (PDOException $exception) {
        error_log('[Agenda] ' . $exception->getMessage());
        ajouterFlash('Une erreur est survenue. Merci de réessayer plus tard.', 'erreur');
    }

    // Redirection après un POST : évite de renvoyer le formulaire si on actualise la page
    header($retour);
    exit;
}

/* ---------- 2. Lecture de l'événement ---------- */

$evenement = null;
$erreurBdd = false;
$inscrit   = false;
$membre    = utilisateurCourant();

if (is_int($id)) {
    try {
        $pdo = getPdo();

        $sql = 'SELECT e.*, c.nom AS categorie
                FROM evenements e
                INNER JOIN categories c ON c.id = e.categorie_id
                WHERE e.id = :id';
        // Un brouillon n'est visible que des administrateurs
        if (!estAdmin()) {
            $sql .= ' AND e.publie = 1';
        }

        $requete = $pdo->prepare($sql);
        $requete->execute([':id' => $id]);
        $resultat = $requete->fetch();
        $evenement = $resultat === false ? null : $resultat;

        if ($evenement !== null && $membre !== null) {
            $verif = $pdo->prepare('SELECT 1 FROM inscriptions WHERE utilisateur_id = :u AND evenement_id = :e');
            $verif->execute([':u' => $membre['id'], ':e' => $id]);
            $inscrit = $verif->fetchColumn() !== false;
        }
    } catch (PDOException $exception) {
        error_log('[Agenda] ' . $exception->getMessage());
        $erreurBdd = true;
        http_response_code(500);
    }
}

/* ---------- 3. Événement introuvable : vraie page 404 ---------- */

if ($evenement === null) {
    if ($erreurBdd) {
        pageErreur(500, 'Une erreur est survenue', 'La fiche ne peut pas être affichée pour le moment. Merci de réessayer plus tard.');
    }
    pageErreur(404, 'Événement introuvable', 'Cet événement n\'existe pas ou n\'existe plus.');
}

/* ---------- 4. Affichage de la fiche ---------- */

$termine         = new DateTimeImmutable($evenement['date_debut']) < new DateTimeImmutable('now');
$estPublie       = (int) $evenement['publie'] === 1;
$pageTitle       = $evenement['titre'];
$pageDescription = mb_strimwidth((string) $evenement['description'], 0, 150, '…');
$currentPage     = 'accueil';
require __DIR__ . '/includes/header.php';
?>

<p><a href="index.php">← Retour à l'agenda</a></p>

<?php afficherFlash(); ?>

<?php if (!$estPublie): ?>
    <p class="alert alert-info">
        <strong>Brouillon.</strong> Cet événement n'est pas publié : seuls les administrateurs peuvent le voir.
    </p>
<?php endif; ?>

<article class="event">
    <p class="card-category"><?= e($evenement['categorie']) ?></p>
    <h1><?= e($evenement['titre']) ?></h1>

    <?php if (estAdmin()): ?>
        <p><a href="admin/evenement.php?id=<?= e($evenement['id']) ?>">Modifier cet événement</a></p>
    <?php endif; ?>

    <h2>Informations pratiques</h2>
    <dl class="details">
        <div>
            <dt>Date</dt>
            <dd>
                <time datetime="<?= e((new DateTimeImmutable($evenement['date_debut']))->format('Y-m-d\TH:i')) ?>">
                    <?= e(dateLongueFr($evenement['date_debut'])) ?> à <?= e(heureFr($evenement['date_debut'])) ?>
                </time>
            </dd>
        </div>
        <div>
            <dt>Durée</dt>
            <dd><?= e(dureeFr((int) $evenement['duree_minutes'])) ?></dd>
        </div>
        <div>
            <dt>Lieu</dt>
            <dd><?= e($evenement['lieu']) ?></dd>
        </div>
        <div>
            <dt>Adresse</dt>
            <dd><?= e($evenement['adresse']) ?></dd>
        </div>
        <div>
            <dt>Tarif</dt>
            <dd><?= e(prixFr($evenement['prix'])) ?></dd>
        </div>
    </dl>

    <h2>Accessibilité</h2>
    <!-- Le texte « disponible / non disponible » porte l'information : jamais la couleur seule -->
    <ul class="access-list">
        <?php foreach ($options as $option): ?>
            <?php $disponible = (int) $evenement[$option['colonne']] === 1; ?>
            <li>
                <?= e($option['long']) ?> :
                <strong class="<?= $disponible ? 'status-yes' : 'status-no' ?>">
                    <?= $disponible ? 'disponible' : 'non disponible' ?>
                </strong>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!empty($evenement['infos_acces'])): ?>
        <h3>Précisions sur l'accès</h3>
        <p><?= nl2br(e($evenement['infos_acces'])) ?></p>
    <?php endif; ?>

    <h2>Description</h2>
    <p><?= nl2br(e($evenement['description'])) ?></p>

    <h2>Participation</h2>
    <?php if (!$estPublie): ?>
        <p>Les inscriptions ouvriront à la publication de l'événement.</p>
    <?php elseif ($termine): ?>
        <p>Cet événement est terminé.</p>
    <?php elseif ($membre === null): ?>
        <p>
            Pour vous inscrire à cet événement, <a href="connexion.php">connectez-vous</a>
            ou <a href="inscription.php">créez un compte</a>.
        </p>
    <?php elseif ($inscrit): ?>
        <p>Vous êtes inscrit(e) à cet événement.</p>
        <form action="evenement.php?id=<?= e($evenement['id']) ?>" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="desinscrire">
            <button type="submit" class="button button-secondary">Annuler mon inscription</button>
        </form>
    <?php else: ?>
        <form action="evenement.php?id=<?= e($evenement['id']) ?>" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="inscrire">
            <button type="submit" class="button">Je m'inscris à cet événement</button>
        </form>
    <?php endif; ?>

    <p>
        Une question sur l'accessibilité de cet événement ?
        <a href="contact.php">Contactez-nous</a>.
    </p>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
