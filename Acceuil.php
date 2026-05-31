<?php
session_start();
require_once "api/db.php";

function h($valeur) {
    return htmlspecialchars($valeur ?? "", ENT_QUOTES, "UTF-8");
}

function formatPrix($prix) {
    return number_format(floatval($prix), 2, ",", " ") . " €";
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

function texteCourt($texte, $taille = 105) {
    $texte = trim($texte ?? "");

    if (strlen($texte) <= $taille) {
        return $texte;
    }

    return substr($texte, 0, $taille) . "...";
}

function fetchAllSafe($pdo, $sql) {
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function fetchOneSafe($pdo, $sql) {
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
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

$destinations = fetchAllSafe($pdo, "
    SELECT *
    FROM destination
    ORDER BY recommande ASC, note_moyenne DESC, id_destination ASC
    LIMIT 3
");

$transports = fetchAllSafe($pdo, "
    SELECT *
    FROM transport
    ORDER BY recommande ASC, prix ASC, id_transport ASC
    LIMIT 2
");

$hebergements = fetchAllSafe($pdo, "
    SELECT *
    FROM hebergement
    ORDER BY recommande ASC, note DESC, id_hebergement ASC
    LIMIT 2
");

$activites = fetchAllSafe($pdo, "
    SELECT *
    FROM activite
    ORDER BY recommande ASC, prix ASC, id_activite ASC
    LIMIT 2
");

$totalDestinations = fetchOneSafe($pdo, "SELECT COUNT(*) AS total FROM destination");
$totalTransports = fetchOneSafe($pdo, "SELECT COUNT(*) AS total FROM transport");
$totalHebergements = fetchOneSafe($pdo, "SELECT COUNT(*) AS total FROM hebergement");
$totalActivites = fetchOneSafe($pdo, "SELECT COUNT(*) AS total FROM activite");

$nbDestinations = intval($totalDestinations["total"] ?? 0);
$nbTransports = intval($totalTransports["total"] ?? 0);
$nbHebergements = intval($totalHebergements["total"] ?? 0);
$nbActivites = intval($totalActivites["total"] ?? 0);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>VoyageVista - Accueil</title>

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
      background-color: #f8fafc;
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
      background-color: #0e7490;
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
    }

    .avatar-btn:hover {
      background: #155e75;
      transform: translateY(-1px);
    }

    .hero {
      background:
        linear-gradient(135deg, rgba(21, 94, 117, 0.96), rgba(2, 132, 199, 0.88), rgba(5, 150, 105, 0.9)),
        url("https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1600&q=80");
      background-size: cover;
      background-position: center;
      color: white;
    }

    .hero-container {
      max-width: 1240px;
      margin: auto;
      padding: 86px 24px;
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
      gap: 48px;
      align-items: center;
    }

    .hero-badge {
      display: inline-block;
      padding: 9px 15px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.18);
      font-weight: 700;
      margin-bottom: 18px;
    }

    .hero h1 {
      max-width: 760px;
      font-size: clamp(40px, 5vw, 70px);
      line-height: 1.02;
      letter-spacing: -0.04em;
    }

    .hero-description {
      max-width: 690px;
      margin-top: 22px;
      font-size: 18px;
      line-height: 1.7;
      color: #ecfeff;
    }

    .search-box {
      margin-top: 32px;
      display: flex;
      gap: 12px;
      background: white;
      padding: 12px;
      border-radius: 28px;
      box-shadow: 0 25px 50px rgba(15, 23, 42, 0.18);
    }

    .search-box input {
      flex: 1;
      min-width: 0;
      padding: 13px 16px;
      border-radius: 20px;
      border: 1px solid #cbd5e1;
      font-size: 16px;
      outline: none;
    }

    .tags {
      margin-top: 22px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .tags button {
      padding: 10px 15px;
      border-radius: 999px;
      color: white;
      background: rgba(255, 255, 255, 0.16);
      border: 1px solid rgba(255, 255, 255, 0.28);
      font-weight: 700;
      transition: 0.2s;
    }

    .tags button:hover {
      background: rgba(255, 255, 255, 0.28);
    }

    .hero-panel {
      display: grid;
      gap: 16px;
    }

    .hero-card {
      background: rgba(255, 255, 255, 0.16);
      border-radius: 34px;
      padding: 16px;
      box-shadow: 0 25px 55px rgba(15, 23, 42, 0.22);
    }

    .hero-card-inner {
      background: white;
      color: #0f172a;
      border-radius: 26px;
      padding: 24px;
      overflow: hidden;
    }

    .hero-suggestion-image {
      height: 190px;
      border-radius: 22px;
      background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.05), rgba(15, 23, 42, 0.34)),
        url("https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=1100&q=80");
      background-size: cover;
      background-position: center;
      margin-bottom: 22px;
      position: relative;
      overflow: hidden;
    }

    .hero-suggestion-label {
      position: absolute;
      left: 16px;
      bottom: 16px;
      display: inline-flex;
      align-items: center;
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.92);
      color: #0e7490;
      font-size: 13px;
      font-weight: 900;
    }

    .hero-card-inner h2 {
      font-size: 26px;
      margin-bottom: 10px;
    }

    .hero-card-inner p {
      color: #64748b;
      line-height: 1.6;
      margin-bottom: 18px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
    }

    .stat-card {
      background: rgba(255, 255, 255, 0.16);
      border: 1px solid rgba(255, 255, 255, 0.22);
      border-radius: 22px;
      padding: 16px;
    }

    .stat-card strong {
      display: block;
      font-size: 24px;
    }

    .stat-card span {
      display: block;
      margin-top: 4px;
      color: #cffafe;
      font-size: 12px;
      font-weight: 700;
    }

    .section {
      max-width: 1240px;
      margin: auto;
      padding: 64px 24px;
    }

    .section-white {
      max-width: none;
      background: white;
    }

    .section-white .section-title,
    .section-white .recommendation-grid {
      max-width: 1240px;
      margin-left: auto;
      margin-right: auto;
    }

    .section-title {
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 24px;
      margin-bottom: 32px;
    }

    .section-title p {
      margin-bottom: 8px;
      color: #0e7490;
      font-weight: 800;
    }

    .section-title h2 {
      font-size: 34px;
      letter-spacing: -0.02em;
    }

    .service-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 20px;
    }

    .service-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 28px;
      padding: 26px;
      text-align: left;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
      transition: 0.2s;
    }

    .service-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.10);
    }

    .service-icon {
      display: flex;
      width: 58px;
      height: 58px;
      align-items: center;
      justify-content: center;
      border-radius: 20px;
      background: #ecfeff;
      font-size: 26px;
      margin-bottom: 20px;
    }

    .service-card h3 {
      font-size: 20px;
    }

    .service-card p {
      margin-top: 10px;
      color: #64748b;
      line-height: 1.6;
    }

    .recommendation-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 22px;
    }

    .recommendation-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 28px;
      overflow: hidden;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
      transition: 0.2s;
    }

    .recommendation-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.10);
    }

    .recommendation-image {
      height: 170px;
      background-size: cover;
      background-position: center;
      background-color: #dbeafe;
    }

    .recommendation-body {
      padding: 20px;
    }

    .recommendation-type {
      display: inline-flex;
      margin-bottom: 10px;
      padding: 6px 10px;
      border-radius: 999px;
      background: #ecfeff;
      color: #0e7490;
      font-size: 12px;
      font-weight: 900;
    }

    .recommendation-body h3 {
      font-size: 19px;
      margin-bottom: 8px;
    }

    .recommendation-body p {
      color: #64748b;
      line-height: 1.5;
      font-size: 14px;
      min-height: 63px;
    }

    .recommendation-footer {
      margin-top: 18px;
      display: grid;
      gap: 10px;
    }

    .recommendation-footer strong {
      color: #155e75;
    }

    .recommendation-footer button {
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

    @media (max-width: 1050px) {
      .recommendation-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 950px) {
      .nav-links {
        display: none;
      }

      .hero-container {
        grid-template-columns: 1fr;
      }

      .service-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 620px) {
      .navbar {
        align-items: flex-start;
        flex-direction: column;
      }

      .nav-actions {
        width: 100%;
        justify-content: space-between;
        flex-wrap: wrap;
      }

      .hero-container {
        padding: 56px 18px;
      }

      .search-box,
      .section-title,
      .footer-content {
        flex-direction: column;
        align-items: stretch;
      }

      .service-grid,
      .recommendation-grid,
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .section {
        padding: 48px 18px;
      }

      .hero-suggestion-image {
        height: 160px;
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
        <button class="active" onclick="window.location.href='Acceuil.php'">Accueil</button>
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
                    <?= intval($notification["statut_lecture"]) === 0 ? "🔔" : "📩" ?>
                  </span>
                  <span>
                    <strong><?= h($notification["titre"]) ?></strong>
                    <small><?= h($notification["message"]) ?></small>
                  </span>
                </button>
              <?php endforeach; ?>

            <?php endif; ?>

            <button class="notification-all" onclick="window.location.href='<?= $estConnecte ? "Notifications.php" : "Connexion.php" ?>'">
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
    <section class="hero">
      <div class="hero-container">
        <div>
          <p class="hero-badge">Planification de voyages personnalisés</p>

          <h1>Construisez un séjour complet, cohérent et prêt à réserver</h1>

          <p class="hero-description">
            Sélectionnez une destination, comparez les trajets, choisissez votre hébergement
            et ajoutez des activités pour composer un voyage adapté à vos envies.
          </p>

          <form class="search-box" onsubmit="rechercherDestination(event)">
            <input id="searchInput" type="text" placeholder="Où voulez-vous partir ?" />
            <button class="primary-btn" type="submit">Rechercher</button>
          </form>

          <div class="tags">
            <button onclick="window.location.href='Destination.php'">Plage</button>
            <button onclick="window.location.href='Destination.php'">Montagne</button>
            <button onclick="window.location.href='Activites.php'">Aventure</button>
            <button onclick="window.location.href='Destination.php'">Culture</button>
          </div>
        </div>

        <div class="hero-panel">
          <div class="hero-card">
            <div class="hero-card-inner">
              <div class="hero-suggestion-image">
                
              </div>

              <h2>Suggestions personnalisées</h2>

              <p>
                Découvrez des idées de destinations, trajets, hébergements et activités
                directement issues du catalogue.
              </p>

              <button class="secondary-btn" onclick="window.location.href='#suggestions'">
                Voir les suggestions
              </button>
            </div>
          </div>

          <div class="stats-grid">
            <div class="stat-card">
              <strong><?= h($nbDestinations) ?></strong>
              <span>destinations</span>
            </div>

            <div class="stat-card">
              <strong><?= h($nbTransports) ?></strong>
              <span>trajets</span>
            </div>

            <div class="stat-card">
              <strong><?= h($nbHebergements) ?></strong>
              <span>hébergements</span>
            </div>

            <div class="stat-card">
              <strong><?= h($nbActivites) ?></strong>
              <span>activités</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="section-title">
        <div>
          <p>Services</p>
          <h2>Organisez chaque partie du voyage</h2>
        </div>

        <button class="secondary-btn" onclick="window.location.href='Destination.php'">
          Explorer
        </button>
      </div>

      <div class="service-grid">
        <button class="service-card" onclick="window.location.href='Destination.php'">
          <span class="service-icon">🌍</span>
          <h3>Destinations</h3>
          <p>Explorez les pays, villes et séjours disponibles dans le catalogue.</p>
        </button>

        <button class="service-card" onclick="window.location.href='Transport.php'">
          <span class="service-icon">✈️</span>
          <h3>Transports</h3>
          <p>Comparez les trajets selon le prix, la durée et les options disponibles.</p>
        </button>

        <button class="service-card" onclick="window.location.href='Hebergements.php'">
          <span class="service-icon">🏨</span>
          <h3>Hébergements</h3>
          <p>Sélectionnez un hôtel, une villa, un appartement ou un resort.</p>
        </button>

        <button class="service-card" onclick="window.location.href='Activites.php'">
          <span class="service-icon">🎒</span>
          <h3>Activités</h3>
          <p>Ajoutez des expériences pour enrichir votre séjour.</p>
        </button>

        <button class="service-card" onclick="window.location.href='Itineraires.php'">
          <span class="service-icon">🧭</span>
          <h3>Itinéraires</h3>
          <p>Composez un séjour complet avec vos destinations, transports, hébergements et activités.</p>
        </button>
      </div>
    </section>

    <section id="suggestions" class="section section-white">
      <div class="section-title">
        <div>
          <p>Suggestions</p>
          <h2>Idées de voyage recommandées</h2>
        </div>

        <button class="secondary-btn" onclick="window.location.href='Destination.php'">
          Voir le catalogue
        </button>
      </div>

      <div class="recommendation-grid">
        <?php foreach ($destinations as $destination): ?>
          <article class="recommendation-card">
            <div class="recommendation-image" style="background-image: url('<?= h($destination["image"]) ?>')"></div>

            <div class="recommendation-body">
              <span class="recommendation-type">Destination</span>
              <h3><?= h($destination["nom_destination"]) ?>, <?= h($destination["pays"]) ?></h3>
              <p><?= h(texteCourt($destination["description"])) ?></p>

              <div class="recommendation-footer">
                <strong><?= h(formatPrix($destination["prix"])) ?></strong>
                <button class="primary-btn" onclick="window.location.href='Voir.php?type=destination&id=<?= h($destination["id_destination"]) ?>'">
                  Voir
                </button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>

        <?php foreach ($transports as $transport): ?>
          <article class="recommendation-card">
            <div class="recommendation-image" style="background-image: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=900&q=80')"></div>

            <div class="recommendation-body">
              <span class="recommendation-type">Transport</span>
              <h3><?= h($transport["ville_depart"]) ?> → <?= h($transport["ville_arrivee"]) ?></h3>
              <p><?= h(texteCourt($transport["description"] ?? $transport["compagnie"])) ?></p>

              <div class="recommendation-footer">
                <strong><?= h(formatPrix($transport["prix"])) ?></strong>
                <button class="primary-btn" onclick="window.location.href='Voir.php?type=transport&id=<?= h($transport["id_transport"]) ?>'">
                  Voir
                </button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>

        <?php foreach ($hebergements as $hebergement): ?>
          <article class="recommendation-card">
            <div class="recommendation-image" style="background-image: url('<?= h($hebergement["image"]) ?>')"></div>

            <div class="recommendation-body">
              <span class="recommendation-type">Hébergement</span>
              <h3><?= h($hebergement["nom"]) ?></h3>
              <p><?= h(texteCourt($hebergement["description"])) ?></p>

              <div class="recommendation-footer">
                <strong><?= h(formatPrix($hebergement["prix"])) ?> / nuit</strong>
                <button class="primary-btn" onclick="window.location.href='Voir.php?type=hebergement&id=<?= h($hebergement["id_hebergement"]) ?>'">
                  Voir
                </button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>

        <?php foreach ($activites as $activite): ?>
          <article class="recommendation-card">
            <div class="recommendation-image" style="background-image: url('<?= h($activite["image"] ?? "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80") ?>')"></div>

            <div class="recommendation-body">
              <span class="recommendation-type">Activité</span>
              <h3><?= h($activite["nom"] ?? $activite["titre"] ?? "Activité") ?></h3>
              <p><?= h(texteCourt($activite["description"] ?? "Activité recommandée depuis la base de données.")) ?></p>

              <div class="recommendation-footer">
                <strong><?= h(formatPrix($activite["prix"] ?? 0)) ?></strong>
                <button class="primary-btn" onclick="window.location.href='Voir.php?type=activite&id=<?= h($activite["id_activite"]) ?>'">
                  Voir
                </button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
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
    function rechercherDestination(event) {
      event.preventDefault();

      const valeurRecherche = document.getElementById("searchInput").value.trim();

      if (valeurRecherche !== "") {
        localStorage.setItem("voyageVistaRecherche", valeurRecherche);
      }

      window.location.href = "Destination.php";
    }
  </script>
</body>
</html>