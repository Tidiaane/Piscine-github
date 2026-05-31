<?php
session_start();
require_once "api/db.php";

function h($valeur) {
    return htmlspecialchars($valeur ?? "", ENT_QUOTES, "UTF-8");
}

function formatPrixCourt($prix) {
    return number_format(floatval($prix), 0, ",", " ") . " €";
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

$estConnecte = isset($_SESSION["user_id"]);
$idUtilisateur = $estConnecte ? $_SESSION["user_id"] : null;

$utilisateur = null;
$initiales = "";

if ($estConnecte) {
    try {
        $sqlUser = "SELECT * FROM utilisateur WHERE id_utilisateur = ?";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$idUtilisateur]);
        $utilisateur = $stmtUser->fetch();

        if ($utilisateur) {
            $initiales = getInitiales(
                $utilisateur["prenom"] ?? "",
                $utilisateur["nom"] ?? "",
                $utilisateur["email"] ?? ""
            );
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

$activitesDb = [];

try {
    $sqlActivites = "
        SELECT
            a.*,
            COALESCE(res.qte_reservee, 0) AS places_reservees,
            GREATEST(a.places_disponibles - COALESCE(res.qte_reservee, 0), 0) AS places_restantes
        FROM activite a
        LEFT JOIN (
            SELECT
                id_element,
                SUM(quantite) AS qte_reservee
            FROM ligne_panier
            WHERE type_element = 'activite'
            GROUP BY id_element
        ) res ON res.id_element = a.id_activite
        ORDER BY a.note DESC, a.prix ASC, a.id_activite ASC
    ";

    $stmtActivites = $pdo->query($sqlActivites);
    $activitesDb = $stmtActivites->fetchAll();
} catch (PDOException $e) {
    $activitesDb = [];
}

$nombreActivites = 0;
$prixMinActivite = null;
$placesRestantesTotal = 0;
$noteMoyenneActivite = null;

try {
    $sqlStats = "
        SELECT
            COUNT(*) AS total,
            MIN(a.prix) AS prix_min,
            COALESCE(SUM(GREATEST(a.places_disponibles - COALESCE(res.qte_reservee, 0), 0)), 0) AS places_restantes_total,
            AVG(a.note) AS note_moyenne
        FROM activite a
        LEFT JOIN (
            SELECT
                id_element,
                SUM(quantite) AS qte_reservee
            FROM ligne_panier
            WHERE type_element = 'activite'
            GROUP BY id_element
        ) res ON res.id_element = a.id_activite
    ";

    $stmtStats = $pdo->query($sqlStats);
    $stats = $stmtStats->fetch();

    if ($stats) {
        $nombreActivites = intval($stats["total"] ?? 0);
        $prixMinActivite = $stats["prix_min"] !== null ? floatval($stats["prix_min"]) : null;
        $placesRestantesTotal = intval($stats["places_restantes_total"] ?? 0);
        $noteMoyenneActivite = $stats["note_moyenne"] !== null ? floatval($stats["note_moyenne"]) : null;
    }
} catch (PDOException $e) {
    $nombreActivites = count($activitesDb);
    $prixMinActivite = null;
    $placesRestantesTotal = 0;
    $noteMoyenneActivite = null;
}

$activitesJs = [];

foreach ($activitesDb as $activite) {
    $placesTotal = intval($activite["places_disponibles"] ?? 0);
    $placesReservees = intval($activite["places_reservees"] ?? 0);
    $placesRestantes = intval($activite["places_restantes"] ?? $placesTotal);

    $activitesJs[] = [
        "id" => intval($activite["id_activite"]),
        "nom" => $activite["nom"] ?? "",
        "destination" => $activite["destination"] ?? "",
        "categorie" => $activite["categorie"] ?? "",
        "niveau" => $activite["niveau"] ?? "",
        "moment" => $activite["moment"] ?? "",
        "duree" => floatval($activite["duree"] ?? 0),
        "prix" => floatval($activite["prix"] ?? 0),
        "note" => floatval($activite["note"] ?? 0),
        "placesTotal" => $placesTotal,
        "placesReservees" => $placesReservees,
        "placesRestantes" => $placesRestantes,
        "description" => $activite["description"] ?? "",
        "image" => $activite["image"] ?? ""
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>VoyageVista - Activités</title>
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

    .page-hero {
      background:
        linear-gradient(135deg, rgba(15, 95, 117, 0.94), rgba(8, 145, 178, 0.78), rgba(5, 150, 105, 0.78)),
        url("https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1600&q=80");
      background-size: cover;
      background-position: center;
      color: white;
    }

    .page-hero-container {
      max-width: 1240px;
      margin: auto;
      padding: 64px 24px 82px;
      display: grid;
      grid-template-columns: 1fr 0.75fr;
      gap: 42px;
      align-items: center;
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

    .page-hero h1 {
      max-width: 780px;
      font-size: clamp(38px, 5vw, 62px);
      line-height: 1.05;
      letter-spacing: -0.04em;
      margin-bottom: 18px;
    }

    .page-hero p {
      max-width: 700px;
      color: #ecfeff;
      line-height: 1.7;
      font-size: 18px;
    }

    .hero-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-top: 28px;
    }

    .hero-stat {
      background: rgba(255, 255, 255, 0.14);
      border: 1px solid rgba(255, 255, 255, 0.24);
      padding: 16px;
      border-radius: 20px;
    }

    .hero-stat strong {
      display: block;
      font-size: 22px;
    }

    .hero-stat span {
      display: block;
      margin-top: 4px;
      color: #cffafe;
      font-size: 13px;
      font-weight: 700;
    }

    .hero-panel {
      background: rgba(255, 255, 255, 0.16);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 30px;
      padding: 18px;
      box-shadow: 0 25px 55px rgba(15, 23, 42, 0.22);
    }

    .hero-panel-inner {
      background: white;
      border-radius: 24px;
      overflow: hidden;
      color: #0f172a;
    }

    .hero-panel-image {
      height: 220px;
      background-image: url("https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=1100&q=80");
      background-size: cover;
      background-position: center;
    }

    .hero-panel-body {
      padding: 22px;
    }

    .hero-panel-body h2 {
      font-size: 24px;
      margin-bottom: 8px;
    }

    .hero-panel-body p {
      color: #64748b;
      font-size: 15px;
      line-height: 1.6;
    }

    .main-container {
      max-width: 1240px;
      margin: auto;
      padding: 0 24px 64px;
    }

    .search-card {
      margin-top: -38px;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 30px;
      padding: 24px;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.10);
      position: relative;
      z-index: 5;
    }

    .search-title-line {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 18px;
    }

    .search-card h2 {
      font-size: 24px;
    }

    .filters-grid {
      display: grid;
      grid-template-columns: 1.2fr 1fr 1fr 1fr;
      gap: 14px;
      align-items: end;
    }

    .advanced-filters {
      margin-top: 16px;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
    }

    .field label {
      display: block;
      color: #475569;
      font-size: 13px;
      font-weight: 800;
      margin-bottom: 7px;
    }

    .field input,
    .field select {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 16px;
      padding: 13px 14px;
      outline: none;
      font-size: 15px;
      background: white;
    }

    .field input:focus,
    .field select:focus {
      border-color: #0891b2;
      box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.12);
    }

    .filter-panel {
      margin-top: 18px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
      padding: 18px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 24px;
    }

    .filter-block h3 {
      color: #0f172a;
      font-size: 16px;
      margin-bottom: 12px;
    }

    .filter-block input[type="range"] {
      display: block;
      width: 100%;
      margin: 0;
    }

    .range-value {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      width: 100%;
      color: #64748b;
      font-size: 13px;
      font-weight: 700;
      margin-top: 8px;
      padding: 0 2px;
    }

    .range-value span:nth-child(1) {
      text-align: left;
    }

    .range-value span:nth-child(2) {
      text-align: center;
    }

    .range-value span:nth-child(3) {
      text-align: right;
    }

    .availability-info {
      color: #64748b;
      font-size: 14px;
      line-height: 1.6;
      font-weight: 700;
    }

    .filter-actions {
      margin-top: 20px;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      flex-wrap: wrap;
    }

    .filter-actions button {
      min-width: 170px;
    }

    .results-header {
      margin-top: 42px;
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 20px;
    }

    .results-header p {
      color: #0e7490;
      font-weight: 800;
      margin-bottom: 8px;
    }

    .results-header h2 {
      font-size: 32px;
      letter-spacing: -0.02em;
    }

    .sort-box {
      min-width: 210px;
    }

    .sort-box label {
      display: block;
      color: #475569;
      font-size: 13px;
      font-weight: 800;
      margin-bottom: 7px;
    }

    .sort-box select {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 999px;
      padding: 12px 16px;
      outline: none;
      font-size: 15px;
      background: white;
    }

    .activity-list {
      margin-top: 24px;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
    }

    .activity-card {
      overflow: hidden;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 28px;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
      transition: 0.2s;
    }

    .activity-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.10);
    }

    .activity-image {
      height: 210px;
      background-size: cover;
      background-position: center;
      position: relative;
      background-color: #e2e8f0;
    }

    .activity-badge {
      position: absolute;
      left: 16px;
      top: 16px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.92);
      color: #0e7490;
      padding: 7px 11px;
      font-size: 13px;
      font-weight: 800;
    }

    .activity-body {
      padding: 22px;
    }

    .activity-body h3 {
      font-size: 22px;
      margin-bottom: 8px;
    }

    .activity-body p {
      color: #64748b;
      line-height: 1.6;
    }

    .activity-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 15px;
    }

    .activity-meta span {
      border-radius: 999px;
      background: #ecfeff;
      color: #0e7490;
      padding: 7px 10px;
      font-size: 13px;
      font-weight: 800;
    }

    .activity-meta span.warning {
      background: #fff7ed;
      color: #ea580c;
    }

    .activity-meta span.full {
      background: #fef2f2;
      color: #dc2626;
    }

    .activity-footer {
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .activity-footer strong {
      display: block;
      color: #155e75;
      font-size: 22px;
    }

    .activity-footer small {
      display: block;
      color: #64748b;
      font-weight: 700;
      margin-top: 2px;
    }

    .empty-result {
      display: none;
      margin-top: 24px;
      background: white;
      border: 1px dashed #cbd5e1;
      border-radius: 24px;
      padding: 36px;
      text-align: center;
      color: #64748b;
    }

    .empty-result strong {
      display: block;
      color: #0f172a;
      font-size: 20px;
      margin-bottom: 8px;
    }

    .back-top-zone {
      margin-top: 36px;
      display: flex;
      justify-content: flex-end;
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

      .page-hero-container,
      .filter-panel {
        grid-template-columns: 1fr;
      }

      .filters-grid,
      .advanced-filters {
        grid-template-columns: repeat(2, 1fr);
      }

      .activity-list {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 640px) {
      .navbar,
      .results-header,
      .footer-content,
      .search-title-line {
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

      .page-hero-container {
        padding: 44px 18px 64px;
      }

      .main-container {
        padding: 0 18px 48px;
      }

      .filters-grid,
      .advanced-filters,
      .hero-stats,
      .activity-list {
        grid-template-columns: 1fr;
      }

      .filter-actions,
      .back-top-zone {
        justify-content: stretch;
      }

      .filter-actions button,
      .back-top-zone button {
        width: 100%;
      }

      .activity-footer {
        flex-direction: column;
        align-items: stretch;
      }

      .activity-footer button {
        width: 100%;
      }

      .notification-dropdown {
        right: -80px;
        width: 300px;
      }
    }
  </style>

</head>

<body id="haut-page">
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
        <button class="active" onclick="window.location.href='Activites.php'">Activités</button>
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
    <section class="page-hero">
      <div class="page-hero-container">
        <div>
          <div class="breadcrumb">VoyageVista &gt; Activités</div>

          <h1>Ajoutez des expériences à votre voyage</h1>

          <p>
            Comparez les activités disponibles selon la destination, le niveau,
            le moment de la journée, la durée, le budget et surtout les places restantes.
          </p>

          <div class="hero-stats">
            <div class="hero-stat">
              <strong><?= h($nombreActivites) ?></strong>
              <span>activités disponibles</span>
            </div>

            <div class="hero-stat">
              <strong><?= $prixMinActivite !== null ? h(formatPrixCourt($prixMinActivite)) : "—" ?></strong>
              <span>prix d’entrée le plus bas</span>
            </div>

            <div class="hero-stat">
              <strong><?= h($placesRestantesTotal) ?></strong>
              <span>places restantes au total</span>
            </div>
          </div>
        </div>

        <div class="hero-panel">
          <div class="hero-panel-inner">
            <div class="hero-panel-image"></div>

            <div class="hero-panel-body">
              <h2>Réservez selon les places restantes</h2>
              <p>
                Les résultats prennent en compte les places déjà présentes dans les paniers.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="main-container">
      <form id="searchForm" class="search-card" onsubmit="rechercherActivite(event)">
        <div class="search-title-line">
          <h2>Affiner votre recherche</h2>
          <button class="secondary-btn" type="button" onclick="resetFiltres()">Réinitialiser</button>
        </div>

        <div class="filters-grid">
          <div class="field">
            <label for="destination">Destination</label>
            <input id="destination" type="text" placeholder="Ex : Bali, Athènes, Tokyo" />
          </div>

          <div class="field">
            <label for="categorie">Catégorie</label>
            <select id="categorie">
              <option value="">Toutes</option>
              <option value="nature">Nature</option>
              <option value="culture">Culture</option>
              <option value="sport">Sport</option>
              <option value="gastronomie">Gastronomie</option>
              <option value="detente">Détente</option>
            </select>
          </div>

          <div class="field">
            <label for="participants">Participants</label>
            <select id="participants">
              <option value="1">1 personne</option>
              <option value="2" selected>2 personnes</option>
              <option value="3">3 personnes</option>
              <option value="4">4 personnes</option>
              <option value="5">5 personnes ou plus</option>
            </select>
          </div>

          <div class="field">
            <label for="moment">Moment</label>
            <select id="moment">
              <option value="">Tous</option>
              <option value="matin">Matin</option>
              <option value="apres-midi">Après-midi</option>
              <option value="soir">Soir</option>
              <option value="journee">Journée</option>
            </select>
          </div>
        </div>

        <div class="advanced-filters">
          <div class="field">
            <label for="niveau">Niveau</label>
            <select id="niveau">
              <option value="">Tous</option>
              <option value="facile">Facile</option>
              <option value="moyen">Moyen</option>
              <option value="sportif">Sportif</option>
              <option value="difficile">Difficile</option>
            </select>
          </div>

          <div class="field">
            <label for="dureeMax">Durée maximum</label>
            <select id="dureeMax">
              <option value="">Toutes</option>
              <option value="1">1 h max</option>
              <option value="2">2 h max</option>
              <option value="4">4 h max</option>
              <option value="8">Journée max</option>
            </select>
          </div>

          <div class="field">
            <label for="placeMin">Disponibilité minimum</label>
            <select id="placeMin">
              <option value="1">Au moins 1 place</option>
              <option value="2" selected>Au moins 2 places</option>
              <option value="4">Au moins 4 places</option>
              <option value="6">Au moins 6 places</option>
              <option value="10">Au moins 10 places</option>
            </select>
          </div>
        </div>

        <div class="filter-panel">
          <div class="filter-block">
            <h3>Prix maximum</h3>

            <input
              id="prixRangeInput"
              type="range"
              min="0"
              max="500"
              value="500"
              oninput="changerPrix(this.value)"
            />

            <div class="range-value">
              <span>0 €</span>
              <span id="prixRange">500 €</span>
              <span>500 €</span>
            </div>
          </div>

          <div class="filter-block">
            <h3>Gestion des places</h3>
            <p class="availability-info">
              Le filtre Participants et le filtre Disponibilité minimum utilisent les places restantes.
              Une activité est masquée si elle n’a plus assez de places disponibles.
            </p>
          </div>
        </div>

        <div class="filter-actions">
          <button class="primary-btn" type="submit">Afficher les résultats</button>
        </div>
      </form>

      <div class="results-header">
        <div>
          <p>Activités sélectionnées</p>
          <h2><span id="nombreResultats">0</span> proposition(s)</h2>
        </div>

        <div class="sort-box">
          <label for="tri">Trier par</label>

          <select id="tri">
            <option value="note">Meilleure note</option>
            <option value="prix-croissant">Prix croissant</option>
            <option value="prix-decroissant">Prix décroissant</option>
            <option value="duree">Durée la plus courte</option>
            <option value="places">Places restantes</option>
          </select>
        </div>
      </div>

      <section>
        <div id="activityList" class="activity-list"></div>

        <div id="emptyResult" class="empty-result">
          <strong>Aucune activité trouvée</strong>
          <span>Modifiez vos critères ou réduisez le nombre de participants.</span>
        </div>
      </section>

      <div class="back-top-zone">
        <button class="dark-btn" onclick="retourHautPage()">Revenir en haut de page</button>
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
    const activites = <?= json_encode($activitesJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function normaliserTexte(texte) {
      return texte
        .toString()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
    }

    function formatPrixJs(montant) {
      return montant.toLocaleString("fr-FR", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }) + " €";
    }

    function rechercherActivite(event) {
      event.preventDefault();
      appliquerFiltres();
    }

    function getFiltresActifs() {
      const destination = normaliserTexte(document.getElementById("destination").value.trim());
      const categorie = normaliserTexte(document.getElementById("categorie").value);
      const participants = parseInt(document.getElementById("participants").value, 10);
      const placeMin = parseInt(document.getElementById("placeMin").value, 10);
      const niveau = normaliserTexte(document.getElementById("niveau").value);
      const dureeMaxSelect = document.getElementById("dureeMax").value;
      const moment = normaliserTexte(document.getElementById("moment").value);
      const prixMax = parseInt(document.getElementById("prixRangeInput").value, 10);
      const tri = document.getElementById("tri").value;

      return {
        destination,
        categorie,
        participants,
        placeMin,
        niveau,
        dureeMax: dureeMaxSelect === "" ? Infinity : parseFloat(dureeMaxSelect),
        moment,
        prixMax,
        tri
      };
    }

    function appliquerFiltres() {
      const filtres = getFiltresActifs();
      const minimumPlaces = Math.max(filtres.participants, filtres.placeMin);

      let resultats = activites.filter((activite) => {
        const texteActivite = normaliserTexte(
          activite.nom + " " +
          activite.destination + " " +
          activite.categorie + " " +
          activite.niveau + " " +
          activite.moment + " " +
          activite.description
        );

        const correspondDestination =
          filtres.destination === "" || texteActivite.includes(filtres.destination);

        const correspondCategorie =
          filtres.categorie === "" || normaliserTexte(activite.categorie) === filtres.categorie;

        const correspondNiveau =
          filtres.niveau === "" || normaliserTexte(activite.niveau) === filtres.niveau;

        const correspondMoment =
          filtres.moment === "" || normaliserTexte(activite.moment) === filtres.moment;

        const correspondDuree =
          activite.duree <= filtres.dureeMax;

        const correspondPrix =
          activite.prix <= filtres.prixMax;

        const correspondPlacesRestantes =
          activite.placesRestantes >= minimumPlaces;

        return (
          correspondDestination &&
          correspondCategorie &&
          correspondNiveau &&
          correspondMoment &&
          correspondDuree &&
          correspondPrix &&
          correspondPlacesRestantes
        );
      });

      resultats = trierActivites(resultats, filtres.tri);
      afficherActivites(resultats);
    }

    function trierActivites(liste, tri) {
      const resultats = [...liste];

      if (tri === "prix-croissant") {
        resultats.sort((a, b) => a.prix - b.prix);
      } else if (tri === "prix-decroissant") {
        resultats.sort((a, b) => b.prix - a.prix);
      } else if (tri === "duree") {
        resultats.sort((a, b) => a.duree - b.duree);
      } else if (tri === "places") {
        resultats.sort((a, b) => b.placesRestantes - a.placesRestantes);
      } else {
        resultats.sort((a, b) => b.note - a.note);
      }

      return resultats;
    }

    function getClassePlace(placesRestantes) {
      if (placesRestantes <= 0) {
        return "full";
      }

      if (placesRestantes <= 3) {
        return "warning";
      }

      return "";
    }

    function getTextePlace(placesRestantes) {
      if (placesRestantes <= 0) {
        return "Complet";
      }

      if (placesRestantes === 1) {
        return "1 place restante";
      }

      return placesRestantes + " places restantes";
    }

    function afficherActivites(liste) {
      const activityList = document.getElementById("activityList");
      const emptyResult = document.getElementById("emptyResult");
      const nombreResultats = document.getElementById("nombreResultats");

      nombreResultats.textContent = liste.length;
      activityList.innerHTML = "";

      if (liste.length === 0) {
        emptyResult.style.display = "block";
        return;
      }

      emptyResult.style.display = "none";

      liste.forEach((activite) => {
        const article = document.createElement("article");
        article.className = "activity-card";

        const classePlace = getClassePlace(activite.placesRestantes);
        const textePlace = getTextePlace(activite.placesRestantes);

        article.innerHTML = `
          <div class="activity-image" style="background-image: url('${activite.image}')">
            <span class="activity-badge">${activite.categorie}</span>
          </div>

          <div class="activity-body">
            <h3>${activite.nom}</h3>

            <p>${activite.description}</p>

            <div class="activity-meta">
              <span>${activite.destination}</span>
              <span>${activite.duree} h</span>
              <span>Note ${activite.note.toFixed(1).replace(".", ",")}/5</span>
              <span>${activite.niveau}</span>
              <span>${activite.moment}</span>
              <span class="${classePlace}">${textePlace}</span>
            </div>

            <div class="activity-footer">
              <div>
                <strong>${formatPrixJs(activite.prix)}</strong>
                <small>par personne</small>
              </div>

              <button
                class="primary-btn"
                type="button"
                onclick="window.location.href='Voir.php?type=activite&id=${activite.id}'"
              >
                Voir
              </button>
            </div>
          </div>
        `;

        activityList.appendChild(article);
      });
    }

    function changerPrix(valeur) {
      document.getElementById("prixRange").textContent = valeur + " €";
    }

    function resetFiltres() {
      document.getElementById("searchForm").reset();
      document.getElementById("participants").value = "2";
      document.getElementById("placeMin").value = "2";
      document.getElementById("prixRangeInput").value = "500";
      document.getElementById("prixRange").textContent = "500 €";
      document.getElementById("tri").value = "note";
      afficherActivites(trierActivites(activites, "note"));
    }

    function retourHautPage() {
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    }

    document.getElementById("tri").addEventListener("change", function () {
      appliquerFiltres();
    });

    document.addEventListener("DOMContentLoaded", function () {
      const rechercheAccueil = localStorage.getItem("voyageVistaRecherche");

      if (rechercheAccueil) {
        document.getElementById("destination").value = rechercheAccueil;
        localStorage.removeItem("voyageVistaRecherche");
        appliquerFiltres();
      } else {
        afficherActivites(trierActivites(activites, "note"));
      }
    });
  </script>
</body>
</html>
