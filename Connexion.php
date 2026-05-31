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

$message = "";
$typeMessage = "";

if (isset($_GET["erreur"])) {
    $typeMessage = "error";

    if ($_GET["erreur"] === "connexion_requise") {
        $message = "Vous devez être connecté pour accéder à cette page.";
    } elseif ($_GET["erreur"] === "champs") {
        $message = "Veuillez remplir tous les champs.";
    } elseif ($_GET["erreur"] === "identifiants") {
        $message = "Adresse e-mail ou mot de passe incorrect.";
    } elseif ($_GET["erreur"] === "email") {
        $message = "Adresse e-mail invalide.";
    } elseif ($_GET["erreur"] === "password") {
        $message = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif ($_GET["erreur"] === "password_confirm") {
        $message = "Les deux mots de passe ne correspondent pas.";
    } elseif ($_GET["erreur"] === "conditions") {
        $message = "Vous devez accepter les conditions d'utilisation.";
    } elseif ($_GET["erreur"] === "email_existant") {
        $message = "Un compte existe déjà avec cette adresse e-mail.";
    } else {
        $message = "Une erreur est survenue.";
    }
}

if (isset($_GET["success"])) {
    $typeMessage = "success";

    if ($_GET["success"] === "compte_cree") {
        $message = "Compte créé avec succès. Vous pouvez maintenant vous connecter.";
    }
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

  <title>VoyageVista - Connexion</title>

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
      min-height: 100vh;
      font-family: Arial, Helvetica, sans-serif;
      background: #f8fafc;
      color: #0f172a;
    }

    button,
    input {
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
    .secondary-btn,
    .dark-btn {
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

    .dark-btn {
      border: none;
      background: #0f172a;
      color: white;
    }

    .dark-btn:hover {
      background: #1e293b;
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

    .auth-page {
      min-height: calc(100vh - 75px);
      background:
        linear-gradient(135deg, rgba(15, 95, 117, 0.90), rgba(8, 145, 178, 0.78), rgba(5, 150, 105, 0.72)),
        url("https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1600&q=80");
      background-size: cover;
      background-position: center;
      padding: 64px 24px;
    }

    .auth-container {
      max-width: 1120px;
      margin: auto;
      display: grid;
      grid-template-columns: 0.9fr 1.1fr;
      gap: 36px;
      align-items: flex-start;
    }

    .auth-intro {
      color: white;
    }

    .auth-badge {
      display: inline-block;
      padding: 9px 15px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.18);
      font-weight: 800;
      margin-bottom: 18px;
    }

    .auth-intro h1 {
      font-size: clamp(38px, 5vw, 64px);
      line-height: 1.04;
      letter-spacing: -0.04em;
      margin-bottom: 18px;
    }

    .auth-intro p {
      max-width: 560px;
      color: #ecfeff;
      font-size: 18px;
      line-height: 1.7;
    }

    .auth-points {
      margin-top: 28px;
      display: grid;
      gap: 12px;
    }

    .auth-point {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 18px;
      background: rgba(255, 255, 255, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.2);
      font-weight: 800;
    }

    .auth-card {
      background: white;
      border: 1px solid rgba(226, 232, 240, 0.9);
      border-radius: 34px;
      padding: 28px;
      box-shadow: 0 28px 60px rgba(15, 23, 42, 0.22);
      align-self: start;
    }

    .connected-card {
      display: grid;
      gap: 16px;
      text-align: left;
    }

    .connected-card h2 {
      font-size: 30px;
      letter-spacing: -0.02em;
    }

    .connected-card p {
      color: #64748b;
      line-height: 1.6;
    }

    .connected-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 8px;
    }

    .tabs {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      background: #f1f5f9;
      padding: 6px;
      border-radius: 999px;
      margin-bottom: 26px;
    }

    .tab-btn {
      border: none;
      border-radius: 999px;
      padding: 12px 14px;
      background: transparent;
      color: #475569;
      font-weight: 900;
      transition: 0.2s;
    }

    .tab-btn.active {
      background: white;
      color: #0e7490;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    }

    .form-title {
      margin-bottom: 22px;
    }

    .form-title h2 {
      font-size: 30px;
      letter-spacing: -0.02em;
      margin-bottom: 8px;
    }

    .form-title p {
      color: #64748b;
      line-height: 1.5;
    }

    .form-grid {
      display: grid;
      gap: 16px;
    }

    .field label {
      display: block;
      color: #475569;
      font-size: 13px;
      font-weight: 800;
      margin-bottom: 7px;
    }

    .field input {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 16px;
      padding: 13px 14px;
      outline: none;
      font-size: 15px;
      background: white;
      transition: 0.2s;
    }

    .field input:focus {
      border-color: #0891b2;
      box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.12);
    }

    .field input.valid {
      border-color: #10b981;
    }

    .field input.invalid {
      border-color: #ef4444;
      background: #fff7f7;
    }

    .password-row {
      position: relative;
    }

    .password-row input {
      padding-right: 92px;
    }

    .show-password {
      position: absolute;
      top: 50%;
      right: 10px;
      transform: translateY(-50%);
      border: none;
      background: #ecfeff;
      color: #0e7490;
      border-radius: 999px;
      padding: 8px 10px;
      font-size: 12px;
      font-weight: 900;
    }

    .error-message {
      display: none;
      color: #dc2626;
      font-size: 13px;
      font-weight: 700;
      margin-top: 6px;
    }

    .error-message.visible {
      display: block;
    }

    .form-options {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 4px;
      color: #475569;
      font-size: 14px;
      font-weight: 700;
    }

    .remember {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .link-btn {
      border: none;
      background: transparent;
      color: #0e7490;
      font-weight: 900;
      text-align: left;
    }

    .link-btn:hover {
      color: #155e75;
      text-decoration: underline;
    }

    .submit-zone {
      margin-top: 20px;
      display: grid;
      gap: 12px;
    }

    .submit-zone button {
      width: 100%;
    }

    .status-box {
      display: none;
      margin-top: 18px;
      border-radius: 18px;
      padding: 14px 16px;
      font-weight: 800;
      line-height: 1.5;
    }

    .status-box.success {
      display: block;
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0;
    }

    .status-box.error {
      display: block;
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }

    .password-rules {
      margin-top: 8px;
      display: grid;
      gap: 6px;
      color: #64748b;
      font-size: 13px;
      font-weight: 700;
    }

    .password-rules span.ok {
      color: #059669;
    }

    .hidden {
      display: none;
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

      .auth-container {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .navbar,
      .footer-content,
      .form-options {
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

      .auth-page {
        padding: 42px 18px;
      }

      .auth-card {
        padding: 22px;
        border-radius: 28px;
      }

      .connected-actions {
        flex-direction: column;
      }

      .connected-actions button {
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

  <main class="auth-page">
    <section class="auth-container">
      <div class="auth-intro">
        <span class="auth-badge">Espace personnel VoyageVista</span>
        <h1>Connectez-vous à votre espace voyage</h1>
        <p>
          Retrouvez vos destinations, vos transports, vos hébergements, vos activités
          et votre panier depuis un espace sécurisé.
        </p>

        <div class="auth-points">
          <div class="auth-point">🔐 Connexion avec vérification des champs</div>
          <div class="auth-point">🧳 Sauvegarde du panier de voyage</div>
          <div class="auth-point">✨ Préparation des recommandations personnalisées</div>
        </div>
      </div>

      <div class="auth-card">
        <?php if ($estConnecte && $utilisateur): ?>
          <div class="connected-card">
            <h2>Vous êtes déjà connecté</h2>
            <p>
              Votre session est active. Vous pouvez accéder à votre profil,
              consulter votre panier ou continuer vos recherches.
            </p>

            <div class="connected-actions">
              <button class="primary-btn" onclick="window.location.href='Profil.php'">
                Voir mon profil
              </button>

              <button class="secondary-btn" onclick="window.location.href='Panier.php'">
                Voir mon panier
              </button>

              <button class="secondary-btn" onclick="window.location.href='Destination.php'">
                Continuer mes recherches
              </button>
            </div>
          </div>
        <?php else: ?>
          <div class="tabs">
            <button id="loginTab" class="tab-btn active" onclick="afficherFormulaire('login')" type="button">
              Se connecter
            </button>
            <button id="registerTab" class="tab-btn" onclick="afficherFormulaire('register')" type="button">
              Créer un compte
            </button>
          </div>

          <form id="loginForm" action="api/login.php" method="POST" onsubmit="return validerConnexionAvantEnvoi()">
            <div class="form-title">
              <h2>Connexion</h2>
              <p>Utilisez votre adresse e-mail et votre mot de passe.</p>
            </div>

            <div class="form-grid">
              <div class="field">
                <label for="loginEmail">Adresse e-mail</label>
                <input
                  id="loginEmail"
                  name="email"
                  type="email"
                  placeholder="exemple@email.com"
                  oninput="validerChampEmail('loginEmail', 'loginEmailError')"
                />
                <div id="loginEmailError" class="error-message">
                  Veuillez saisir une adresse e-mail valide.
                </div>
              </div>

              <div class="field">
                <label for="loginPassword">Mot de passe</label>
                <div class="password-row">
                  <input
                    id="loginPassword"
                    name="password"
                    type="password"
                    placeholder="Votre mot de passe"
                    oninput="validerChampObligatoire('loginPassword', 'loginPasswordError')"
                  />
                  <button class="show-password" type="button" onclick="togglePassword('loginPassword', this)">
                    Afficher
                  </button>
                </div>
                <div id="loginPasswordError" class="error-message">
                  Le mot de passe est obligatoire.
                </div>
              </div>

              <div class="form-options">
                <label class="remember">
                  <input id="rememberMe" type="checkbox" />
                  Se souvenir de moi
                </label>

                <button class="link-btn" type="button" onclick="motDePasseOublie()">
                  Mot de passe oublié ?
                </button>
              </div>
            </div>

            <div class="submit-zone">
              <button class="primary-btn" type="submit">Se connecter</button>
            </div>
          </form>

          <form id="registerForm" class="hidden" action="api/register.php" method="POST" onsubmit="return validerInscriptionAvantEnvoi()">
            <div class="form-title">
              <h2>Créer un compte</h2>
              <p>Créez un compte pour préparer votre voyage et conserver votre panier.</p>
            </div>

            <div class="form-grid">
              <div class="field">
                <label for="registerName">Nom</label>
                <input
                  id="registerName"
                  name="nom"
                  type="text"
                  placeholder="Votre nom"
                  oninput="validerNom()"
                />
                <div id="registerNameError" class="error-message">
                  Le nom doit contenir au moins 2 caractères.
                </div>
              </div>

              <div class="field">
                <label for="registerFirstName">Prénom</label>
                <input
                  id="registerFirstName"
                  name="prenom"
                  type="text"
                  placeholder="Votre prénom"
                  oninput="validerPrenom()"
                />
                <div id="registerFirstNameError" class="error-message">
                  Le prénom doit contenir au moins 2 caractères.
                </div>
              </div>

              <div class="field">
                <label for="registerAddress">Adresse</label>
                <input
                  id="registerAddress"
                  name="adresse"
                  type="text"
                  placeholder="Votre adresse"
                  oninput="validerAdresse()"
                />
                <div id="registerAddressError" class="error-message">
                  L'adresse doit contenir au moins 5 caractères.
                </div>
              </div>

              <div class="field">
                <label for="registerEmail">Adresse e-mail</label>
                <input
                  id="registerEmail"
                  name="email"
                  type="email"
                  placeholder="exemple@email.com"
                  oninput="validerChampEmail('registerEmail', 'registerEmailError')"
                />
                <div id="registerEmailError" class="error-message">
                  Veuillez saisir une adresse e-mail valide.
                </div>
              </div>

              <div class="field">
                <label for="registerPassword">Mot de passe</label>
                <div class="password-row">
                  <input
                    id="registerPassword"
                    name="password"
                    type="password"
                    placeholder="Minimum 8 caractères"
                    oninput="validerMotDePasseCreation()"
                  />
                  <button class="show-password" type="button" onclick="togglePassword('registerPassword', this)">
                    Afficher
                  </button>
                </div>

                <div id="registerPasswordError" class="error-message">
                  Le mot de passe ne respecte pas les règles.
                </div>

                <div class="password-rules">
                  <span id="ruleLength">• Au moins 8 caractères</span>
                  <span id="ruleUpper">• Au moins une majuscule</span>
                  <span id="ruleNumber">• Au moins un chiffre</span>
                </div>
              </div>

              <div class="field">
                <label for="confirmPassword">Confirmer le mot de passe</label>
                <div class="password-row">
                  <input
                    id="confirmPassword"
                    name="confirmPassword"
                    type="password"
                    placeholder="Confirmez le mot de passe"
                    oninput="validerConfirmationMotDePasse()"
                  />
                  <button class="show-password" type="button" onclick="togglePassword('confirmPassword', this)">
                    Afficher
                  </button>
                </div>

                <div id="confirmPasswordError" class="error-message">
                  Les deux mots de passe doivent être identiques.
                </div>
              </div>

              <label class="remember">
                <input id="acceptTerms" name="acceptTerms" type="checkbox" value="1" />
                J'accepte les conditions d'utilisation
              </label>
            </div>

            <div class="submit-zone">
              <button class="primary-btn" type="submit">Créer le compte</button>
              <button class="secondary-btn" type="button" onclick="afficherFormulaire('login')">
                J'ai déjà un compte
              </button>
            </div>
          </form>

          <?php if ($message !== ""): ?>
            <div class="status-box <?= h($typeMessage) ?>" style="display:block;">
              <?= h($message) ?>
            </div>
          <?php endif; ?>

          <div id="statusBox" class="status-box"></div>
        <?php endif; ?>
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

  <script>
    function afficherFormulaire(type) {
      const loginForm = document.getElementById("loginForm");
      const registerForm = document.getElementById("registerForm");
      const loginTab = document.getElementById("loginTab");
      const registerTab = document.getElementById("registerTab");

      masquerStatus();

      if (type === "login") {
        loginForm.classList.remove("hidden");
        registerForm.classList.add("hidden");
        loginTab.classList.add("active");
        registerTab.classList.remove("active");
      } else {
        registerForm.classList.remove("hidden");
        loginForm.classList.add("hidden");
        registerTab.classList.add("active");
        loginTab.classList.remove("active");
      }
    }

    function emailValide(email) {
      const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return regex.test(email);
    }

    function motDePasseSolide(password) {
      return {
        longueur: password.length >= 8,
        majuscule: /[A-Z]/.test(password),
        chiffre: /[0-9]/.test(password)
      };
    }

    function afficherErreur(inputId, errorId, actif) {
      const input = document.getElementById(inputId);
      const error = document.getElementById(errorId);

      if (actif) {
        input.classList.add("invalid");
        input.classList.remove("valid");
        error.classList.add("visible");
      } else {
        input.classList.remove("invalid");
        error.classList.remove("visible");

        if (input.value.trim() !== "") {
          input.classList.add("valid");
        }
      }
    }

    function validerChampEmail(inputId, errorId) {
      const value = document.getElementById(inputId).value.trim();
      const invalide = value === "" || !emailValide(value);
      afficherErreur(inputId, errorId, invalide);
      return !invalide;
    }

    function validerChampObligatoire(inputId, errorId) {
      const value = document.getElementById(inputId).value.trim();
      const invalide = value === "";
      afficherErreur(inputId, errorId, invalide);
      return !invalide;
    }

    function validerNom() {
      const value = document.getElementById("registerName").value.trim();
      const invalide = value.length < 2;
      afficherErreur("registerName", "registerNameError", invalide);
      return !invalide;
    }

    function validerPrenom() {
      const value = document.getElementById("registerFirstName").value.trim();
      const invalide = value.length < 2;
      afficherErreur("registerFirstName", "registerFirstNameError", invalide);
      return !invalide;
    }

    function validerAdresse() {
      const value = document.getElementById("registerAddress").value.trim();
      const invalide = value.length < 5;
      afficherErreur("registerAddress", "registerAddressError", invalide);
      return !invalide;
    }

    function validerMotDePasseCreation() {
      const password = document.getElementById("registerPassword").value;
      const rules = motDePasseSolide(password);
      const valide = rules.longueur && rules.majuscule && rules.chiffre;

      document.getElementById("ruleLength").classList.toggle("ok", rules.longueur);
      document.getElementById("ruleUpper").classList.toggle("ok", rules.majuscule);
      document.getElementById("ruleNumber").classList.toggle("ok", rules.chiffre);

      afficherErreur("registerPassword", "registerPasswordError", !valide);

      if (document.getElementById("confirmPassword").value !== "") {
        validerConfirmationMotDePasse();
      }

      return valide;
    }

    function validerConfirmationMotDePasse() {
      const password = document.getElementById("registerPassword").value;
      const confirmPassword = document.getElementById("confirmPassword").value;
      const invalide = confirmPassword === "" || password !== confirmPassword;

      afficherErreur("confirmPassword", "confirmPasswordError", invalide);
      return !invalide;
    }

    function togglePassword(inputId, button) {
      const input = document.getElementById(inputId);

      if (input.type === "password") {
        input.type = "text";
        button.textContent = "Masquer";
      } else {
        input.type = "password";
        button.textContent = "Afficher";
      }
    }

    function validerConnexionAvantEnvoi() {
      const emailOk = validerChampEmail("loginEmail", "loginEmailError");
      const passwordOk = validerChampObligatoire("loginPassword", "loginPasswordError");

      if (!emailOk || !passwordOk) {
        afficherStatus("Connexion impossible : veuillez corriger les champs en rouge.", "error");
        return false;
      }

      return true;
    }

    function validerInscriptionAvantEnvoi() {
      const nameOk = validerNom();
      const firstNameOk = validerPrenom();
      const addressOk = validerAdresse();
      const emailOk = validerChampEmail("registerEmail", "registerEmailError");
      const passwordOk = validerMotDePasseCreation();
      const confirmOk = validerConfirmationMotDePasse();
      const termsOk = document.getElementById("acceptTerms").checked;

      if (!nameOk || !firstNameOk || !addressOk || !emailOk || !passwordOk || !confirmOk) {
        afficherStatus("Création impossible : veuillez corriger les champs en rouge.", "error");
        return false;
      }

      if (!termsOk) {
        afficherStatus("Vous devez accepter les conditions d'utilisation.", "error");
        return false;
      }

      return true;
    }

    function motDePasseOublie() {
      const email = document.getElementById("loginEmail").value.trim();

      if (email === "" || !emailValide(email)) {
        afficherStatus("Saisissez d'abord une adresse e-mail valide pour réinitialiser le mot de passe.", "error");
        return;
      }

      afficherStatus("Un lien de réinitialisation serait envoyé à : " + email, "success");
    }

    function afficherStatus(message, type) {
      const box = document.getElementById("statusBox");
      box.textContent = message;
      box.className = "status-box " + type;
      box.style.display = "block";
    }

    function masquerStatus() {
      const box = document.getElementById("statusBox");

      if (!box) {
        return;
      }

      box.textContent = "";
      box.className = "status-box";
      box.style.display = "none";
    }
  </script>
</body>
</html>