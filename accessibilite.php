<?php
declare(strict_types=1);

$pageTitle       = 'Déclaration d\'accessibilité';
$pageDescription = 'État d\'accessibilité du site Agenda Accessible, mesures mises en œuvre et limites connues.';
$currentPage     = 'accessibilite';
require __DIR__ . '/includes/header.php';
?>

<h1>Déclaration d'accessibilité</h1>

<p>
    Ce site est un projet pédagogique. Il a été conçu pour être utilisable par le plus grand
    nombre, quels que soient le handicap, l'appareil ou la manière de naviguer.
    L'objectif visé est le niveau AA des WCAG 2.2, sur lequel s'appuie le RGAA.
</p>

<h2>État de conformité</h2>
<p>
    Le site n'a pas fait l'objet d'un audit réalisé par un tiers. Il s'agit d'une
    <strong>auto-évaluation</strong> : la conformité complète n'est donc ni garantie ni certifiée.
    Une déclaration officielle suppose un audit complet de l'ensemble des pages.
</p>

<h2>Ce qui est mis en œuvre</h2>
<ul>
    <li><strong>Navigation au clavier :</strong> tous les liens, champs et boutons sont atteignables avec la touche Tab et activables avec Entrée ou Espace, dans un ordre logique.</li>
    <li><strong>Focus visible :</strong> l'élément actif est entouré d'un contour épais et contrasté.</li>
    <li><strong>Lien d'évitement :</strong> le premier élément de chaque page permet de passer directement au contenu principal.</li>
    <li><strong>Structure :</strong> langue de la page déclarée, titre unique par page, hiérarchie de titres cohérente, zones repérables (en-tête, navigation, contenu principal, pied de page).</li>
    <li><strong>Formulaires :</strong> chaque champ a une étiquette visible, les erreurs sont décrites en texte, listées en début de page et reliées aux champs concernés.</li>
    <li><strong>Couleurs :</strong> contrastes élevés ; aucune information n'est transmise par la couleur seule.</li>
    <li><strong>Adaptation à l'affichage :</strong> mise en page fluide (responsive), lisible dès 320 pixels de large et avec un zoom jusqu'à 400 %. Les tailles de texte sont relatives, le zoom n'est jamais bloqué.</li>
    <li><strong>Préférences de l'utilisateur :</strong> thème clair ou sombre selon le système, réduction des animations, modes à contraste forcé pris en charge.</li>
    <li><strong>Fonctionnement sans JavaScript :</strong> le site n'a besoin d'aucun script pour fonctionner.</li>
</ul>

<h2>Limites connues</h2>
<ul>
    <li>Le site ne contient ni vidéo ni son : les critères sur les médias temporels ne sont pas concernés.</li>
    <li>Les événements et les informations d'accessibilité affichés sont des données d'exemple.</li>
    <li>Les tests avec des technologies d'assistance (lecteurs d'écran, loupes, commande vocale) restent à compléter.</li>
</ul>

<h2>Signaler un problème</h2>
<p>
    Vous rencontrez une difficulté pour accéder à un contenu ou à une fonctionnalité ?
    Utilisez le <a href="contact.php">formulaire de contact</a> en choisissant le sujet
    « Signaler un problème d'accessibilité ».
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
