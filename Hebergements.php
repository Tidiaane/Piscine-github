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

function jsonToArraySafe($value) {
    if (empty($value)) {
        return [];
    }

    $decoded = json_decode($value, true);

    if (is_array($decoded)) {
        return $decoded;
    }

    return [];
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

$tableReservationExiste = false;

try {
    $stmtTable = $pdo->query("SHOW TABLES LIKE 'reservation_hebergement'");
    $tableReservationExiste = $stmtTable->fetch() !== false;
} catch (PDOException $e) {
    $tableReservationExiste = false;
}

$reservationsParHebergement = [];
$nombreReservationsActives = 0;

if ($tableReservationExiste) {
    try {
        $sqlReservations = "
            SELECT
                id_reservation_hebergement,
                id_hebergement,
                id_utilisateur,
                date_arrivee,
                date_depart,
                quantite,
                statut
            FROM reservation_hebergement
            WHERE statut = 'confirmee'
            AND date_depart >= CURDATE()
            ORDER BY date_arrivee ASC
        ";

        $stmtReservations = $pdo->query($sqlReservations);
        $reservationsDb = $stmtReservations->fetchAll();

        foreach ($reservationsDb as $reservation) {
            $idHebergement = intval($reservation["id_hebergement"]);

            if (!isset($reservationsParHebergement[$idHebergement])) {
                $reservationsParHebergement[$idHebergement] = [];
            }

            $reservationsParHebergement[$idHebergement][] = [
                "idReservation" => intval($reservation["id_reservation_hebergement"]),
                "idUtilisateur" => intval($reservation["id_utilisateur"] ?? 0),
                "dateArrivee" => $reservation["date_arrivee"],
                "dateDepart" => $reservation["date_depart"],
                "quantite" => intval($reservation["quantite"] ?? 1),
                "statut" => $reservation["statut"]
            ];

            $nombreReservationsActives++;
        }
    } catch (PDOException $e) {
        $reservationsParHebergement = [];
        $nombreReservationsActives = 0;
    }
}

try {
    $sql = "
        SELECT *
        FROM hebergement
        ORDER BY recommande ASC, note DESC, id_hebergement ASC
    ";

    $stmt = $pdo->query($sql);
    $hebergementsDb = $stmt->fetchAll();
} catch (PDOException $e) {
    $hebergementsDb = [];
}

$nombreHebergements = 0;
$prixMinHebergement = null;
$noteMoyenneHebergement = null;

try {
    $sqlStats = "
        SELECT
            COUNT(*) AS total,
            MIN(prix) AS prix_min,
            AVG(note) AS note_moyenne
        FROM hebergement
    ";

    $stmtStats = $pdo->query($sqlStats);
    $stats = $stmtStats->fetch();

    if ($stats) {
        $nombreHebergements = intval($stats["total"] ?? 0);
        $prixMinHebergement = $stats["prix_min"] !== null ? floatval($stats["prix_min"]) : null;
        $noteMoyenneHebergement = $stats["note_moyenne"] !== null ? floatval($stats["note_moyenne"]) : null;
    }
} catch (PDOException $e) {
    $nombreHebergements = count($hebergementsDb);
    $prixMinHebergement = null;
    $noteMoyenneHebergement = null;
}

$hebergementsJs = [];

foreach ($hebergementsDb as $hebergement) {
    $idHebergement = intval($hebergement["id_hebergement"]);
    $equipements = jsonToArraySafe($hebergement["equipements"] ?? "");
    $tags = jsonToArraySafe($hebergement["tags"] ?? "");

    if (empty($tags)) {
        $tags[] = ucfirst($hebergement["type"] ?? "Hébergement");
        $tags[] = $hebergement["destination"] ?? "";
        $tags[] = intval($hebergement["capacite"] ?? 0) . " pers.";
    }

    $hebergementsJs[] = [
        "id" => $idHebergement,
        "nom" => $hebergement["nom"] ?? "",
        "destination" => $hebergement["destination"] ?? "",
        "pays" => $hebergement["pays"] ?? "",
        "type" => $hebergement["type"] ?? "",
        "capacite" => intval($hebergement["capacite"] ?? 0),
        "prix" => floatval($hebergement["prix"] ?? 0),
        "note" => floatval($hebergement["note"] ?? 0),
        "etoiles" => $hebergement["etoiles"] ?? "",
        "disponibilite" => $hebergement["disponibilite"] ?? "",
        "description" => $hebergement["description"] ?? "",
        "image" => $hebergement["image"] ?? "",
        "equipements" => $equipements,
        "tags" => $tags,
        "reservations" => $reservationsParHebergement[$idHebergement] ?? [],
        "recommande" => intval($hebergement["recommande"] ?? 1)
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>VoyageVista - Hébergements</title>

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
        url("https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80");
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
      background-image: url("https://images.unsplash.com/photo-1564501049412-61c2a3083791?auto=format&fit=crop&w=1100&q=80");
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
      grid-template-columns: 1.2fr 1fr 1fr;
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
      grid-template-columns: 1.4fr 1fr;
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

    .check-list {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .check-list label {
      display: flex;
      align-items: center;
      gap: 9px;
      color: #475569;
      font-weight: 600;
      font-size: 14px;
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

    .date-error {
      display: none;
      margin-top: 16px;
      padding: 14px 16px;
      border-radius: 18px;
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
      font-weight: 800;
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

    .hotel-list {
      margin-top: 24px;
      display: grid;
      gap: 20px;
    }

    .hotel-card {
      display: grid;
      grid-template-columns: 260px 1fr;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 26px;
      overflow: hidden;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
      transition: 0.2s;
    }

    .hotel-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.10);
    }

    .hotel-card.reserved {
      opacity: 0.78;
      border-color: #fecaca;
    }

    .hotel-image {
      min-height: 230px;
      background-size: cover;
      background-position: center;
      position: relative;
    }

    .availability-badge {
      position: absolute;
      left: 14px;
      top: 14px;
      border-radius: 999px;
      padding: 8px 11px;
      background: rgba(255, 255, 255, 0.94);
      font-size: 13px;
      font-weight: 900;
    }

    .availability-badge.available {
      color: #047857;
    }

    .availability-badge.reserved {
      color: #dc2626;
    }

    .availability-badge.unknown {
      color: #0e7490;
    }

    .hotel-body {
      padding: 22px;
      display: grid;
      gap: 16px;
    }

    .hotel-top {
      display: flex;
      justify-content: space-between;
      gap: 20px;
    }

    .hotel-title h3 {
      font-size: 23px;
      margin-bottom: 7px;
    }

    .hotel-title p {
      color: #64748b;
      line-height: 1.5;
    }

    .rating {
      display: inline-flex;
      gap: 5px;
      align-items: center;
      margin-top: 10px;
      color: #f59e0b;
      font-weight: 800;
    }

    .hotel-price {
      text-align: right;
      min-width: 130px;
    }

    .hotel-price strong {
      display: block;
      color: #155e75;
      font-size: 26px;
    }

    .hotel-price span {
      color: #64748b;
      font-size: 13px;
      font-weight: 700;
    }

    .hotel-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .hotel-tags span {
      border-radius: 999px;
      background: #ecfeff;
      color: #0e7490;
      padding: 7px 11px;
      font-size: 13px;
      font-weight: 800;
    }

    .hotel-bottom {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      border-top: 1px solid #e2e8f0;
      padding-top: 16px;
    }

    .availability {
      color: #059669;
      font-weight: 800;
      font-size: 14px;
      line-height: 1.5;
    }

    .availability.reserved {
      color: #dc2626;
    }

    .availability.unknown {
      color: #0e7490;
    }

    .hotel-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .empty-result {
      display: none;
      background: white;
      border: 1px dashed #cbd5e1;
      border-radius: 24px;
      padding: 36px;
      text-align: center;
      color: #64748b;
      margin-top: 24px;
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
      .hotel-card,
      .filter-panel {
        grid-template-columns: 1fr;
      }

      .filters-grid,
      .advanced-filters {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 640px) {
      .navbar,
      .results-header,
      .footer-content,
      .hotel-top,
      .hotel-bottom,
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
      .check-list {
        grid-template-columns: 1fr;
      }

      .hotel-card {
        grid-template-columns: 1fr;
      }

      .hotel-price {
        text-align: left;
      }

      .hotel-actions,
      .filter-actions,
      .back-top-zone {
        justify-content: stretch;
      }

      .hotel-actions button,
      .filter-actions button,
      .back-top-zone button {
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
        <button class="active" onclick="window.location.href='Hebergements.php'">Hébergements</button>
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
    <section class="page-hero">
      <div class="page-hero-container">
        <div>
          <div class="breadcrumb">VoyageVista &gt; Hébergements</div>

          <h1>Trouvez un hébergement disponible à vos dates</h1>

          <p>
            Recherchez un hébergement par destination, dates, voyageurs, type, équipements et budget.
            Si les dates sont renseignées, les hébergements déjà réservés sur cette période sont exclus.
          </p>

          <div class="hero-stats">
            <div class="hero-stat">
              <strong><?= h($nombreHebergements) ?></strong>
              <span>hébergements</span>
            </div>

            <div class="hero-stat">
              <strong><?= $prixMinHebergement !== null ? h(formatPrixCourt($prixMinHebergement)) : "—" ?></strong>
              <span>prix d’entrée</span>
            </div>

            <div class="hero-stat">
              <strong><?= h($nombreReservationsActives) ?></strong>
              <span>réservation(s) active(s)</span>
            </div>
          </div>
        </div>

        <div class="hero-panel">
          <div class="hero-panel-inner">
            <div class="hero-panel-image"></div>

            <div class="hero-panel-body">
              <h2>Disponibilité par dates</h2>
              <p>
                Le contrôle utilise les réservations confirmées enregistrées en base.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="main-container">
      <form id="searchForm" class="search-card" onsubmit="rechercherHebergement(event)">
        <div class="search-title-line">
          <h2>Affiner votre recherche</h2>
          <button class="secondary-btn" type="button" onclick="resetFiltres()">Réinitialiser</button>
        </div>

        <div class="filters-grid">
          <div class="field">
            <label for="destination">Destination</label>
            <input id="destination" type="text" placeholder="Ex : Paris, Tokyo, Marrakech" />
          </div>

          <div class="field">
            <label for="dateArrivee">Date d'arrivée</label>
            <input id="dateArrivee" type="date" />
          </div>

          <div class="field">
            <label for="dateDepart">Date de départ</label>
            <input id="dateDepart" type="date" />
          </div>
        </div>

        <div class="advanced-filters">
          <div class="field">
            <label for="typeHebergement">Type d'hébergement</label>
            <select id="typeHebergement">
              <option value="">Tous les types</option>
              <option value="hotel">Hôtel</option>
              <option value="appartement">Appartement</option>
              <option value="villa">Villa</option>
              <option value="resort">Resort</option>
            </select>
          </div>

          <div class="field">
            <label for="voyageurs">Voyageurs</label>
            <select id="voyageurs">
              <option value="1">1 voyageur</option>
              <option value="2" selected>2 voyageurs</option>
              <option value="3">3 voyageurs</option>
              <option value="4">4 voyageurs</option>
              <option value="5">5 voyageurs ou plus</option>
            </select>
          </div>

          <div class="field">
            <label for="noteMin">Note minimum</label>
            <select id="noteMin">
              <option value="">Toutes les notes</option>
              <option value="4">4/5 minimum</option>
              <option value="4.5">4,5/5 minimum</option>
              <option value="4.8">4,8/5 minimum</option>
            </select>
          </div>
        </div>

        <div class="filter-panel">
          <div class="filter-block">
            <h3>Équipements souhaités</h3>

            <div class="check-list">
              <label>
                <input class="equipement-filter" type="checkbox" value="wifi" />
                Wi-Fi
              </label>

              <label>
                <input class="equipement-filter" type="checkbox" value="piscine" />
                Piscine
              </label>

              <label>
                <input class="equipement-filter" type="checkbox" value="petit-dejeuner" />
                Petit-déjeuner
              </label>

              <label>
                <input class="equipement-filter" type="checkbox" value="parking" />
                Parking
              </label>
            </div>
          </div>

          <div class="filter-block">
            <h3>Budget maximum par nuit</h3>

            <input
              id="prixRangeInput"
              type="range"
              min="50"
              max="500"
              value="500"
              oninput="changerPrix(this.value)"
            />

            <div class="range-value">
              <span>50 €</span>
              <span id="prixRange">500 €</span>
              <span>500 €</span>
            </div>
          </div>
        </div>

        <div id="dateError" class="date-error">
          La date de départ doit être après la date d'arrivée.
        </div>

        <div class="filter-actions">
          <button class="primary-btn" type="submit">Rechercher</button>
        </div>
      </form>

      <div class="results-header">
        <div>
          <p>Hébergements sélectionnés</p>
          <h2><span id="nombreResultats">0</span> proposition(s)</h2>
        </div>

        <div class="sort-box">
          <label for="tri">Trier par</label>

          <select id="tri">
            <option value="recommande">Recommandés</option>
            <option value="prix-croissant">Prix croissant</option>
            <option value="prix-decroissant">Prix décroissant</option>
            <option value="note">Meilleure note</option>
            <option value="capacite">Capacité</option>
          </select>
        </div>
      </div>

      <section>
        <div id="hotelList" class="hotel-list"></div>

        <div id="emptyResult" class="empty-result">
          <strong>Aucun hébergement trouvé</strong>
          <span>Modifiez les filtres puis cliquez sur Rechercher.</span>
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
    const hebergements = <?= json_encode($hebergementsJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function normaliserTexte(texte) {
      return texte
        .toString()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
    }

    function escapeHtml(texte) {
      return texte
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    function rechercherHebergement(event) {
      event.preventDefault();
      appliquerFiltres();
    }

    function getFiltresActifs() {
      const destination = normaliserTexte(document.getElementById("destination").value.trim());
      const dateArrivee = document.getElementById("dateArrivee").value;
      const dateDepart = document.getElementById("dateDepart").value;
      const typeHebergement = normaliserTexte(document.getElementById("typeHebergement").value);
      const voyageurs = parseInt(document.getElementById("voyageurs").value, 10);
      const noteMinSelect = document.getElementById("noteMin").value;
      const prixMax = parseInt(document.getElementById("prixRangeInput").value, 10);
      const tri = document.getElementById("tri").value;

      const noteMin = noteMinSelect === "" ? 0 : parseFloat(noteMinSelect);

      const equipements = Array.from(document.querySelectorAll(".equipement-filter:checked"))
        .map((checkbox) => normaliserTexte(checkbox.value));

      return {
        destination,
        dateArrivee,
        dateDepart,
        typeHebergement,
        voyageurs,
        noteMin,
        prixMax,
        equipements,
        tri
      };
    }

    function datesRechercheValides(dateArrivee, dateDepart) {
      if (dateArrivee === "" && dateDepart === "") {
        return true;
      }

      if (dateArrivee === "" || dateDepart === "") {
        return false;
      }

      return dateDepart > dateArrivee;
    }

    function datesSeChevauchent(arriveeDemandee, departDemandee, arriveeReservee, departReservee) {
      return arriveeDemandee < departReservee && departDemandee > arriveeReservee;
    }

    function getReservationChevauchante(hotel, dateArrivee, dateDepart) {
      if (dateArrivee === "" || dateDepart === "") {
        return null;
      }

      return hotel.reservations.find((reservation) => {
        if (reservation.statut !== "confirmee") {
          return false;
        }

        return datesSeChevauchent(
          dateArrivee,
          dateDepart,
          reservation.dateArrivee,
          reservation.dateDepart
        );
      }) || null;
    }

    function estReserveSurDates(hotel, dateArrivee, dateDepart) {
      return getReservationChevauchante(hotel, dateArrivee, dateDepart) !== null;
    }

    function formatDateFr(dateIso) {
      if (!dateIso) {
        return "";
      }

      const morceaux = dateIso.split("-");

      if (morceaux.length !== 3) {
        return dateIso;
      }

      return morceaux[2] + "/" + morceaux[1] + "/" + morceaux[0];
    }

    function getStatutDisponibilite(hotel, filtres) {
      if (filtres.dateArrivee === "" || filtres.dateDepart === "") {
        return {
          classe: "unknown",
          badge: "Dates à préciser",
          texte: hotel.disponibilite || "Renseignez vos dates pour vérifier la disponibilité."
        };
      }

      const reservation = getReservationChevauchante(hotel, filtres.dateArrivee, filtres.dateDepart);

      if (reservation) {
        return {
          classe: "reserved",
          badge: "Déjà réservé",
          texte: "Indisponible du " + formatDateFr(reservation.dateArrivee) + " au " + formatDateFr(reservation.dateDepart) + "."
        };
      }

      return {
        classe: "available",
        badge: "Disponible",
        texte: "Disponible du " + formatDateFr(filtres.dateArrivee) + " au " + formatDateFr(filtres.dateDepart) + "."
      };
    }

    function appliquerFiltres() {
      const filtres = getFiltresActifs();
      const dateError = document.getElementById("dateError");

      if (!datesRechercheValides(filtres.dateArrivee, filtres.dateDepart)) {
        dateError.style.display = "block";
        afficherHebergements([], filtres);
        return;
      }

      dateError.style.display = "none";

      let resultats = hebergements.filter((hotel) => {
        const texteHotel = normaliserTexte(
          hotel.nom + " " +
          hotel.destination + " " +
          hotel.pays + " " +
          hotel.type + " " +
          hotel.description + " " +
          hotel.tags.join(" ")
        );

        const typeHotel = normaliserTexte(hotel.type);
        const equipementsHotel = hotel.equipements.map((equipement) => normaliserTexte(equipement));

        const correspondDestination =
          filtres.destination === "" || texteHotel.includes(filtres.destination);

        const correspondType =
          filtres.typeHebergement === "" || typeHotel === filtres.typeHebergement;

        const correspondVoyageurs =
          hotel.capacite >= filtres.voyageurs;

        const correspondPrix =
          hotel.prix <= filtres.prixMax;

        const correspondNote =
          hotel.note >= filtres.noteMin;

        const correspondEquipements =
          filtres.equipements.every((equipement) => equipementsHotel.includes(equipement));

        const reserveSurDates =
          estReserveSurDates(hotel, filtres.dateArrivee, filtres.dateDepart);

        const correspondDisponibilite =
          !(filtres.dateArrivee !== "" && filtres.dateDepart !== "" && reserveSurDates);

        return (
          correspondDestination &&
          correspondType &&
          correspondVoyageurs &&
          correspondPrix &&
          correspondNote &&
          correspondEquipements &&
          correspondDisponibilite
        );
      });

      resultats = trierHebergements(resultats, filtres.tri);
      afficherHebergements(resultats, filtres);
    }

    function trierHebergements(liste, tri) {
      const resultats = [...liste];

      if (tri === "prix-croissant") {
        resultats.sort((a, b) => a.prix - b.prix);
      } else if (tri === "prix-decroissant") {
        resultats.sort((a, b) => b.prix - a.prix);
      } else if (tri === "note") {
        resultats.sort((a, b) => b.note - a.note);
      } else if (tri === "capacite") {
        resultats.sort((a, b) => b.capacite - a.capacite);
      } else {
        resultats.sort((a, b) => a.recommande - b.recommande);
      }

      return resultats;
    }

    function afficherHebergements(liste, filtresParam = null) {
      const filtres = filtresParam || getFiltresActifs();
      const hotelList = document.getElementById("hotelList");
      const emptyResult = document.getElementById("emptyResult");
      const nombreResultats = document.getElementById("nombreResultats");

      nombreResultats.textContent = liste.length;
      hotelList.innerHTML = "";

      if (liste.length === 0) {
        emptyResult.style.display = "block";
        return;
      }

      emptyResult.style.display = "none";

      liste.forEach((hotel) => {
        const statut = getStatutDisponibilite(hotel, filtres);
        const article = document.createElement("article");

        article.className = "hotel-card" + (statut.classe === "reserved" ? " reserved" : "");

        let lienVoir = "Voir.php?type=hebergement&id=" + encodeURIComponent(hotel.id);

        if (filtres.dateArrivee !== "" && filtres.dateDepart !== "") {
          lienVoir +=
            "&arrivee=" + encodeURIComponent(filtres.dateArrivee) +
            "&depart=" + encodeURIComponent(filtres.dateDepart) +
            "&voyageurs=" + encodeURIComponent(filtres.voyageurs);
        }

        const tagsHtml = hotel.tags
          .filter((tag) => tag !== "")
          .map((tag) => `<span>${escapeHtml(tag)}</span>`)
          .join("");

        article.innerHTML = `
          <div class="hotel-image" style="background-image: url('${escapeHtml(hotel.image)}')">
            <span class="availability-badge ${statut.classe}">
              ${escapeHtml(statut.badge)}
            </span>
          </div>

          <div class="hotel-body">
            <div class="hotel-top">
              <div class="hotel-title">
                <h3>${escapeHtml(hotel.nom)}</h3>
                <p>${escapeHtml(hotel.description)}</p>

                <div class="rating">
                  ${escapeHtml(hotel.etoiles)}
                  <span>${hotel.note.toFixed(1).replace(".", ",")} / 5</span>
                </div>
              </div>

              <div class="hotel-price">
                <strong>${hotel.prix.toLocaleString("fr-FR")} €</strong>
                <span>par nuit</span>
              </div>
            </div>

            <div class="hotel-tags">
              ${tagsHtml}
              <span>${escapeHtml(hotel.destination)}</span>
              <span>${escapeHtml(hotel.pays)}</span>
              <span>${hotel.capacite} pers.</span>
            </div>

            <div class="hotel-bottom">
              <p class="availability ${statut.classe}">
                ${escapeHtml(statut.texte)}
              </p>

              <div class="hotel-actions">
                <button
                  class="primary-btn"
                  type="button"
                  onclick="window.location.href='${lienVoir}'"
                >
                  Voir
                </button>
              </div>
            </div>
          </div>
        `;

        hotelList.appendChild(article);
      });
    }

    function changerPrix(valeur) {
      document.getElementById("prixRange").textContent = valeur + " €";
    }

    function resetFiltres() {
      document.getElementById("searchForm").reset();
      document.getElementById("voyageurs").value = "2";
      document.getElementById("prixRangeInput").value = "500";
      document.getElementById("prixRange").textContent = "500 €";
      document.getElementById("tri").value = "recommande";
      document.getElementById("dateError").style.display = "none";
      afficherHebergements(trierHebergements(hebergements, "recommande"), getFiltresActifs());
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

    document.getElementById("dateArrivee").addEventListener("change", function () {
      const dateArrivee = document.getElementById("dateArrivee").value;
      const dateDepart = document.getElementById("dateDepart");

      if (dateArrivee !== "") {
        dateDepart.min = dateArrivee;
      }

      appliquerFiltres();
    });

    document.getElementById("dateDepart").addEventListener("change", function () {
      appliquerFiltres();
    });

    document.addEventListener("DOMContentLoaded", function () {
      const aujourdHui = new Date().toISOString().split("T")[0];

      document.getElementById("dateArrivee").min = aujourdHui;
      document.getElementById("dateDepart").min = aujourdHui;

      afficherHebergements(trierHebergements(hebergements, "recommande"), getFiltresActifs());
    });
  </script>
</body>

</html>
