<?php
session_start();
require_once "api/db.php";

function h($valeur) {
    return htmlspecialchars($valeur ?? "", ENT_QUOTES, "UTF-8");
}

function getInitiales($prenom, $nom, $email) {
    $prenom = trim($prenom ?? "");
    $nom = trim($nom ?? "");
    $email = trim($email ?? "");

    $initiales = "";

    if ($prenom !== "") {
        $initiales .= strtoupper(substr($prenom, 0, 1));
    }

    if ($nom !== "") {
        $initiales .= strtoupper(substr($nom, 0, 1));
    }

    if ($initiales === "" && $email !== "") {
        $initiales = strtoupper(substr($email, 0, 2));
    }

    return $initiales !== "" ? $initiales : "U";
}

function sujetLisible($sujet) {
    if ($sujet === "reservation") return "Réservation";
    if ($sujet === "panier") return "Panier";
    if ($sujet === "paiement") return "Paiement";
    if ($sujet === "compte") return "Compte";
    if ($sujet === "autre") return "Autre demande";

    return "Demande générale";
}

$estConnecte = isset($_SESSION["user_id"]);
$idUtilisateur = $estConnecte ? $_SESSION["user_id"] : null;

$utilisateur = null;
$prenomUtilisateur = "";
$nomUtilisateur = "";
$emailUtilisateur = "";
$initiales = "";

if ($estConnecte) {
    try {
        $sqlUser = "SELECT * FROM utilisateur WHERE id_utilisateur = ?";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$idUtilisateur]);
        $utilisateur = $stmtUser->fetch();

        if ($utilisateur) {
            $prenomUtilisateur = $utilisateur["prenom"] ?? "";
            $nomUtilisateur = $utilisateur["nom"] ?? "";
            $emailUtilisateur = $utilisateur["email"] ?? "";
            $initiales = getInitiales($prenomUtilisateur, $nomUtilisateur, $emailUtilisateur);
        }
    } catch (PDOException $e) {
        $utilisateur = null;
    }
}

$erreurs = [];

$valeurs = [
    "nom" => $nomUtilisateur,
    "prenom" => $prenomUtilisateur,
    "email" => $emailUtilisateur,
    "sujet" => "",
    "message" => ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$estConnecte || !$utilisateur) {
        header("Location: Connexion.php?erreur=connexion_requise");
        exit;
    }

    $valeurs["nom"] = trim($_POST["nom"] ?? "");
    $valeurs["prenom"] = trim($_POST["prenom"] ?? "");
    $valeurs["email"] = trim($_POST["email"] ?? "");
    $valeurs["sujet"] = trim($_POST["sujet"] ?? "");
    $valeurs["message"] = trim($_POST["message"] ?? "");

    if (strlen($valeurs["nom"]) < 2) {
        $erreurs[] = "Le nom doit contenir au moins 2 caractères.";
    }

    if (strlen($valeurs["prenom"]) < 2) {
        $erreurs[] = "Le prénom doit contenir au moins 2 caractères.";
    }

    if (!filter_var($valeurs["email"], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse e-mail est invalide.";
    }

    if ($valeurs["sujet"] === "") {
        $erreurs[] = "Veuillez sélectionner un sujet.";
    }

    if (strlen($valeurs["message"]) < 10) {
        $erreurs[] = "Le message doit contenir au moins 10 caractères.";
    }

    if (count($erreurs) === 0) {
        try {
            $titreNotification = "Message envoyé au support";

            $messageNotification =
                "Votre message a bien été envoyé à VoyageVista. " .
                "Sujet : " . sujetLisible($valeurs["sujet"]) . ". " .
                "Nom : " . $valeurs["prenom"] . " " . $valeurs["nom"] . ". " .
                "E-mail : " . $valeurs["email"] . ". " .
                "Message : " . $valeurs["message"];

            $sqlNotification = "
                INSERT INTO notification (id_utilisateur, titre, message, date_envoi, statut_lecture)
                VALUES (?, ?, ?, NOW(), 0)
            ";

            $stmtNotification = $pdo->prepare($sqlNotification);
            $stmtNotification->execute([
                $idUtilisateur,
                $titreNotification,
                $messageNotification
            ]);

            header("Location: Acceuil.php?success=contact_envoye");
            exit;
        } catch (PDOException $e) {
            $erreurs[] = "Impossible d'envoyer le message. Veuillez réessayer.";
        }
    }
}

$nombreElementsPanier = 0;

if ($estConnecte) {
    try {
        $sqlPanier = "
            SELECT COALESCE(SUM(lp.quantite), 0) AS total
            FROM ligne_panier lp
            JOIN panier p ON lp.id_panier = p.id_panier
            WHERE p.id_utilisateur = ?
        ";

        $stmtPanier = $pdo->prepare($sqlPanier);
        $stmtPanier->execute([$idUtilisateur]);
        $resultPanier = $stmtPanier->fetch();

        $nombreElementsPanier = intval($resultPanier["total"] ?? 0);
    } catch (PDOException $e) {
        $nombreElementsPanier = 0;
    }
}

$nombreNotifications = 0;
$notificationsPopup = [];

if ($estConnecte) {
    try {
        $sqlNotifCount = "
            SELECT COUNT(*) AS total
            FROM notification
            WHERE id_utilisateur = ?
            AND statut_lecture = 0
        ";

        $stmtNotifCount = $pdo->prepare($sqlNotifCount);
        $stmtNotifCount->execute([$idUtilisateur]);
        $resultNotifCount = $stmtNotifCount->fetch();

        $nombreNotifications = intval($resultNotifCount["total"] ?? 0);

        $sqlNotifPopup = "
            SELECT titre, message, date_envoi, statut_lecture
            FROM notification
            WHERE id_utilisateur = ?
            ORDER BY date_envoi DESC
            LIMIT 3
        ";

        $stmtNotifPopup = $pdo->prepare($sqlNotifPopup);
        $stmtNotifPopup->execute([$idUtilisateur]);
        $notificationsPopup = $stmtNotifPopup->fetchAll();
    } catch (PDOException $e) {
        $nombreNotifications = 0;
        $notificationsPopup = [];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>VoyageVista - Contact</title>

  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
      background: #f8fafc;
      color: #0f172a;
    }

    button,
    input,
    textarea,
    select {
      font-family: inherit;
    }

    button {
      cursor: pointer;
    }

    header {
      position: sticky;
      top: 0;
      z-index: 20;
      background: rgba(255, 255, 255, 0.96);
      border-bottom: 1px solid #e2e8f0;
      backdrop-filter: blur(8px);
    }

    .navbar {
      max-width: 1240px;
      margin: auto;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 24px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      border: none;
      background: transparent;
      text-align: left;
    }

    .logo-icon {
      width: 42px;
      height: 42px;
      border-radius: 16px;
      background: #0e7490;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
    }

    .logo-title {
      display: block;
      color: #155e75;
      font-size: 20px;
      font-weight: 800;
      line-height: 1;
    }

    .logo-subtitle {
      display: block;
      margin-top: 3px;
      font-size: 12px;
      color: #64748b;
    }

    .nav-links,
    .nav-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .nav-links button {
      border: none;
      background: transparent;
      color: #475569;
      font-weight: 700;
      padding: 10px 14px;
      border-radius: 999px;
      transition: 0.2s;
    }

    .nav-links button:hover,
    .nav-links button.active {
      background: #ecfeff;
      color: #0e7490;
    }

    .primary-btn,
    .secondary-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 42px;
      padding: 11px 18px;
      border-radius: 999px;
      font-weight: 800;
      transition: 0.2s;
      white-space: nowrap;
      text-decoration: none;
    }

    .primary-btn {
      border: none;
      background: #0e7490;
      color: white;
      box-shadow: 0 10px 18px rgba(14, 116, 144, 0.18);
    }

    .primary-btn:hover {
      background: #155e75;
      transform: translateY(-1px);
    }

    .secondary-btn {
      background: white;
      color: #0e7490;
      border: 1px solid #bae6fd;
    }

    .secondary-btn:hover {
      background: #ecfeff;
      transform: translateY(-1px);
    }

    .icon-btn {
      position: relative;
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      font-size: 18px;
      transition: 0.2s;
    }

    .icon-btn:hover {
      background: #ecfeff;
      border-color: #67e8f9;
    }

    .badge-count {
      position: absolute;
      top: -5px;
      right: -5px;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 999px;
      background: #ef4444;
      color: white;
      font-size: 11px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid white;
    }

    .notification-wrapper {
      position: relative;
    }

    .notification-dropdown {
      position: absolute;
      top: 52px;
      right: 0;
      width: 330px;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 22px;
      box-shadow: 0 22px 45px rgba(15, 23, 42, 0.18);
      padding: 14px;
      opacity: 0;
      visibility: hidden;
      transform: translateY(8px);
      transition: 0.2s ease;
    }

    .notification-wrapper:hover .notification-dropdown {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    .notification-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 8px 12px;
      border-bottom: 1px solid #e2e8f0;
      margin-bottom: 8px;
    }

    .notification-header strong {
      color: #0f172a;
      font-size: 16px;
    }

    .notification-header span {
      color: #0e7490;
      font-size: 12px;
      font-weight: 800;
    }

    .notification-item {
      width: 100%;
      display: flex;
      gap: 12px;
      align-items: flex-start;
      border: none;
      background: transparent;
      text-align: left;
      padding: 12px 8px;
      border-radius: 16px;
      transition: 0.2s;
    }

    .notification-item:hover {
      background: #f0fdfa;
    }

    .notification-icon {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: #ecfeff;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .notification-item strong {
      display: block;
      color: #0f172a;
      font-size: 14px;
    }

    .notification-item small {
      display: block;
      color: #64748b;
      margin-top: 3px;
      line-height: 1.4;
    }

    .notification-all {
      width: 100%;
      margin-top: 8px;
      border: none;
      border-radius: 999px;
      background: #0e7490;
      color: white;
      padding: 11px 14px;
      font-weight: 800;
    }

    .avatar-btn {
      width: 44px;
      height: 44px;
      border: none;
      border-radius: 50%;
      background: #0e7490;
      color: white;
      font-weight: 900;
      font-size: 15px;
      box-shadow: 0 10px 18px rgba(14, 116, 144, 0.18);
      transition: 0.2s;
    }

    .avatar-btn:hover {
      background: #155e75;
      transform: translateY(-1px);
    }

    .contact-hero {
      background:
        linear-gradient(135deg, rgba(15, 95, 117, 0.94), rgba(8, 145, 178, 0.78), rgba(5, 150, 105, 0.76)),
        url("https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1600&q=80");
      background-size: cover;
      background-position: center;
      color: white;
    }

    .contact-hero-container {
      max-width: 1240px;
      margin: auto;
      padding: 64px 24px 82px;
    }

    .breadcrumb {
      display: inline-flex;
      gap: 8px;
      align-items: center;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.16);
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 18px;
    }

    .contact-hero h1 {
      max-width: 760px;
      font-size: clamp(38px, 5vw, 62px);
      line-height: 1.05;
      letter-spacing: -0.04em;
      margin-bottom: 18px;
    }

    .contact-hero p {
      max-width: 720px;
      color: #ecfeff;
      line-height: 1.7;
      font-size: 18px;
    }

    .main-container {
      max-width: 1240px;
      margin: auto;
      padding: 0 24px 64px;
    }

    .contact-layout {
      margin-top: -38px;
      display: grid;
      grid-template-columns: 1fr 360px;
      gap: 24px;
      align-items: start;
      position: relative;
      z-index: 5;
    }

    .contact-card,
    .info-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 30px;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.10);
      overflow: hidden;
    }

    .panel-header {
      padding: 24px;
      border-bottom: 1px solid #e2e8f0;
    }

    .panel-header p {
      color: #0e7490;
      font-weight: 900;
      margin-bottom: 8px;
    }

    .panel-header h2 {
      font-size: 28px;
      letter-spacing: -0.02em;
    }

    .panel-body {
      padding: 24px;
    }

    .form-grid {
      display: grid;
      gap: 16px;
    }

    .two-cols {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .field label {
      display: block;
      color: #475569;
      font-size: 13px;
      font-weight: 800;
      margin-bottom: 7px;
    }

    .field input,
    .field select,
    .field textarea {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 16px;
      padding: 13px 14px;
      outline: none;
      font-size: 15px;
      background: white;
      transition: 0.2s;
    }

    .field textarea {
      min-height: 150px;
      resize: vertical;
    }

    .field input:focus,
    .field select:focus,
    .field textarea:focus {
      border-color: #0891b2;
      box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.12);
    }

    .error-box {
      margin-bottom: 18px;
      padding: 16px;
      border-radius: 20px;
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      font-weight: 800;
      line-height: 1.6;
    }

    .error-box ul {
      padding-left: 20px;
    }

    .form-actions {
      margin-top: 22px;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      flex-wrap: wrap;
    }

    .login-notice {
      margin-bottom: 18px;
      padding: 16px;
      border-radius: 20px;
      background: #ecfeff;
      border: 1px solid #bae6fd;
      color: #155e75;
      font-weight: 800;
      line-height: 1.5;
    }

    .info-list {
      display: grid;
      gap: 14px;
    }

    .info-item {
      display: flex;
      gap: 12px;
      padding: 14px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 18px;
    }

    .info-icon {
      width: 38px;
      height: 38px;
      border-radius: 14px;
      background: #ecfeff;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .info-item strong {
      display: block;
      margin-bottom: 4px;
    }

    .info-item span {
      color: #64748b;
      line-height: 1.5;
      font-size: 14px;
    }

    .quick-actions {
      margin-top: 20px;
      display: grid;
      gap: 10px;
    }

    .quick-actions button {
      width: 100%;
    }

    footer {
      border-top: 1px solid #e2e8f0;
      background: white;
      padding: 28px 24px;
    }

    .footer-content {
      max-width: 1240px;
      margin: auto;
      display: flex;
      justify-content: space-between;
      gap: 20px;
      color: #64748b;
    }

    .footer-links {
      display: flex;
      gap: 18px;
    }

    .footer-links button {
      border: none;
      background: transparent;
      color: #64748b;
      font-weight: 700;
    }

    .footer-links button:hover {
      color: #0e7490;
    }

    @media (max-width: 980px) {
      .nav-links {
        display: none;
      }

      .contact-layout {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .navbar,
      .footer-content,
      .two-cols,
      .form-actions {
        flex-direction: column;
        align-items: stretch;
      }

      .navbar {
        align-items: flex-start;
      }

      .nav-actions {
        width: 100%;
        justify-content: space-between;
        flex-wrap: wrap;
      }

      .contact-hero-container {
        padding: 44px 18px 64px;
      }

      .main-container {
        padding: 0 18px 48px;
      }

      .two-cols {
        display: grid;
        grid-template-columns: 1fr;
      }

      .form-actions button {
        width: 100%;
      }

      .notification-dropdown {
        right: -80px;
        width: 300px;
      }
    }
  </style>
</head>

<body>
  <header>
    <nav class="navbar">
      <button class="logo" onclick="window.location.href='Acceuil.php'">
        <span class="logo-icon">VV</span>
        <span>
          <span class="logo-title">VoyageVista</span>
          <span class="logo-subtitle">Planifiez. Explorez. Vivez.</span>
        </span>
      </button>

      <div class="nav-links">
        <?php if ((($_SESSION["user_role"] ?? "") === "admin") || (($_SESSION["user_role"] ?? "") === "gestionnaire")): ?>
          <button onclick="window.location.href='Admin.php'">Admin</button>
        <?php endif; ?>
        <button onclick="window.location.href='Acceuil.php'">Accueil</button>
        <button onclick="window.location.href='Destination.php'">Destinations</button>
        <button onclick="window.location.href='Transport.php'">Transports</button>
        <button onclick="window.location.href='Hebergements.php'">Hébergements</button>
        <button onclick="window.location.href='Activites.php'">Activités</button>
        <button onclick="window.location.href='Itineraires.php'">Itinéraires</button>
      </div>

      <div class="nav-actions">
        <div class="notification-wrapper">
          <button
            class="icon-btn"
            onclick="window.location.href='<?= $estConnecte ? "Notifications.php" : "Connexion.php?erreur=connexion_requise" ?>'"
            aria-label="Notifications"
          >
            🔔

            <?php if ($estConnecte && $nombreNotifications > 0): ?>
              <span class="badge-count"><?= h($nombreNotifications) ?></span>
            <?php endif; ?>
          </button>

          <div class="notification-dropdown">
            <div class="notification-header">
              <strong>Notifications</strong>

              <?php if (!$estConnecte): ?>
                <span>Connexion requise</span>
              <?php elseif ($nombreNotifications > 0): ?>
                <span><?= h($nombreNotifications) ?> nouvelle(s)</span>
              <?php else: ?>
                <span>Aucune nouvelle</span>
              <?php endif; ?>
            </div>

            <?php if (!$estConnecte): ?>
              <button class="notification-item" onclick="window.location.href='Connexion.php'">
                <span class="notification-icon">🔐</span>
                <span>
                  <strong>Connexion requise</strong>
                  <small>Connectez-vous pour consulter vos notifications.</small>
                </span>
              </button>
            <?php elseif (count($notificationsPopup) === 0): ?>
              <button class="notification-item" onclick="window.location.href='Notifications.php'">
                <span class="notification-icon">🔔</span>
                <span>
                  <strong>Aucune notification</strong>
                  <small>Vous n’avez pas encore de notification.</small>
                </span>
              </button>
            <?php else: ?>
              <?php foreach ($notificationsPopup as $notification): ?>
                <button class="notification-item" onclick="window.location.href='Notifications.php'">
                  <span class="notification-icon">
                    <?= intval($notification["statut_lecture"] ?? 0) === 0 ? "🔔" : "📩" ?>
                  </span>
                  <span>
                    <strong><?= h($notification["titre"] ?? "Notification") ?></strong>
                    <small><?= h($notification["message"] ?? "") ?></small>
                  </span>
                </button>
              <?php endforeach; ?>
            <?php endif; ?>

            <button
              class="notification-all"
              onclick="window.location.href='<?= $estConnecte ? "Notifications.php" : "Connexion.php" ?>'"
            >
              Voir toutes les notifications
            </button>
          </div>
        </div>

        <button
          class="icon-btn"
          onclick="window.location.href='<?= $estConnecte ? "Panier.php" : "Connexion.php?erreur=connexion_requise" ?>'"
          aria-label="Panier"
        >
          🛒

          <?php if ($estConnecte && $nombreElementsPanier > 0): ?>
            <span class="badge-count"><?= h($nombreElementsPanier) ?></span>
          <?php endif; ?>
        </button>

        <?php if ($estConnecte && $utilisateur): ?>
          <button class="avatar-btn" onclick="window.location.href='Profil.php'" title="Mon profil">
            <?= h($initiales) ?>
          </button>
        <?php else: ?>
          <button class="primary-btn" onclick="window.location.href='Connexion.php'">
            Connexion
          </button>
        <?php endif; ?>
      </div>
    </nav>
  </header>

  <main>
    <section class="contact-hero">
      <div class="contact-hero-container">
        <div class="breadcrumb">VoyageVista &gt; Contact</div>

        <h1>Contactez notre équipe</h1>

        <p>
          Une question sur votre voyage, votre panier, votre compte ou votre paiement ?
          Envoyez votre demande : un récapitulatif sera créé dans vos notifications.
        </p>
      </div>
    </section>

    <section class="main-container">
      <div class="contact-layout">
        <form class="contact-card" method="POST">
          <div class="panel-header">
            <p>Formulaire</p>
            <h2>Envoyer un message</h2>
          </div>

          <div class="panel-body">
            <?php if (!$estConnecte): ?>
              <div class="login-notice">
                Vous devez être connecté pour envoyer un message et recevoir le récapitulatif dans vos notifications.
              </div>
            <?php endif; ?>

            <?php if (count($erreurs) > 0): ?>
              <div class="error-box">
                <ul>
                  <?php foreach ($erreurs as $erreur): ?>
                    <li><?= h($erreur) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <div class="form-grid">
              <div class="two-cols">
                <div class="field">
                  <label for="nom">Nom</label>
                  <input id="nom" name="nom" type="text" placeholder="Votre nom" value="<?= h($valeurs["nom"]) ?>">
                </div>

                <div class="field">
                  <label for="prenom">Prénom</label>
                  <input id="prenom" name="prenom" type="text" placeholder="Votre prénom" value="<?= h($valeurs["prenom"]) ?>">
                </div>
              </div>

              <div class="field">
                <label for="email">Adresse e-mail</label>
                <input id="email" name="email" type="email" placeholder="exemple@email.com" value="<?= h($valeurs["email"]) ?>">
              </div>

              <div class="field">
                <label for="sujet">Sujet</label>
                <select id="sujet" name="sujet">
                  <option value="">Choisir un sujet</option>
                  <option value="reservation" <?= $valeurs["sujet"] === "reservation" ? "selected" : "" ?>>Réservation</option>
                  <option value="panier" <?= $valeurs["sujet"] === "panier" ? "selected" : "" ?>>Panier</option>
                  <option value="paiement" <?= $valeurs["sujet"] === "paiement" ? "selected" : "" ?>>Paiement</option>
                  <option value="compte" <?= $valeurs["sujet"] === "compte" ? "selected" : "" ?>>Compte</option>
                  <option value="autre" <?= $valeurs["sujet"] === "autre" ? "selected" : "" ?>>Autre demande</option>
                </select>
              </div>

              <div class="field">
                <label for="message">Message</label>
                <textarea id="message" name="message" placeholder="Expliquez votre demande"><?= h($valeurs["message"]) ?></textarea>
              </div>
            </div>

            <div class="form-actions">
              <button class="secondary-btn" type="button" onclick="window.location.href='Contact.php'">
                Effacer
              </button>

              <?php if ($estConnecte): ?>
                <button class="primary-btn" type="submit">
                  Envoyer le message
                </button>
              <?php else: ?>
                <button class="primary-btn" type="button" onclick="window.location.href='Connexion.php?erreur=connexion_requise'">
                  Se connecter pour envoyer
                </button>
              <?php endif; ?>
            </div>
          </div>
        </form>

        <aside class="info-card">
          <div class="panel-header">
            <p>Aide</p>
            <h2>Informations utiles</h2>
          </div>

          <div class="panel-body">
            <div class="info-list">
              <div class="info-item">
                <span class="info-icon">📧</span>
                <div>
                  <strong>E-mail</strong>
                  <span>support@voyagevista.fr</span>
                </div>
              </div>

              <div class="info-item">
                <span class="info-icon">📞</span>
                <div>
                  <strong>Téléphone</strong>
                  <span>01 23 45 67 89</span>
                </div>
              </div>

              <div class="info-item">
                <span class="info-icon">📍</span>
                <div>
                  <strong>Adresse</strong>
                  <span>VoyageVista France<br>12 avenue des Voyages<br>75008 Paris</span>
                </div>
              </div>

              <div class="info-item">
                <span class="info-icon">🔔</span>
                <div>
                  <strong>Suivi</strong>
                  <span>Après l’envoi, un récapitulatif sera disponible dans vos notifications.</span>
                </div>
              </div>
            </div>

            <div class="quick-actions">
              <button class="primary-btn" onclick="window.location.href='Panier.php'">
                Voir mon panier
              </button>

              <button class="secondary-btn" onclick="window.location.href='Notifications.php'">
                Voir mes notifications
              </button>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </main>

  <footer>
    <div class="footer-content">
      <p>© 2026 VoyageVista — Projet Web dynamique</p>

      <div class="footer-links">
        <button onclick="window.location.href='Contact.php'">Contact</button>
      </div>
    </div>
  </footer>
</body>
</html>