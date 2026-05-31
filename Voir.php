<?php
session_start();
require_once "api/db.php";

function h($valeur) {
    return htmlspecialchars($valeur ?? "", ENT_QUOTES, "UTF-8");
}

function formatPrix($prix) {
    return number_format(floatval($prix), 2, ",", " ") . " €";
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

    return is_array($decoded) ? $decoded : [];
}

function formatDateFr($date) {
    if (empty($date)) {
        return "Non précisée";
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return $date;
    }

    return date("d/m/Y", $timestamp);
}

function formatHeure($heure) {
    if (empty($heure)) {
        return "Non précisée";
    }

    return substr($heure, 0, 5);
}

function nomType($type) {
    if ($type === "transport") return "Transport";
    if ($type === "hebergement") return "Hébergement";
    if ($type === "activite") return "Activité";
    return "Destination";
}

function pageRetour($type) {
    if ($type === "transport") return "Transport.php";
    if ($type === "hebergement") return "Hebergements.php";
    if ($type === "activite") return "Activites.php";
    return "Destination.php";
}

function imageTransport($typeTransport) {
    if ($typeTransport === "avion") {
        return "https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1600&q=80";
    }

    if ($typeTransport === "train") {
        return "https://images.unsplash.com/photo-1474487548417-781cb71495f3?auto=format&fit=crop&w=1600&q=80";
    }

    if ($typeTransport === "bus") {
        return "https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=1600&q=80";
    }

    if ($typeTransport === "voiture") {
        return "https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=1600&q=80";
    }

    return "https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=1600&q=80";
}

function imageParDefaut($type) {
    if ($type === "transport") {
        return "https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1600&q=80";
    }

    if ($type === "hebergement") {
        return "https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80";
    }

    if ($type === "activite") {
        return "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1600&q=80";
    }

    return "https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=1600&q=80";
}

$type = $_GET["type"] ?? "destination";
$id = intval($_GET["id"] ?? 0);

$typesAutorises = ["destination", "transport", "hebergement", "activite"];

if (!in_array($type, $typesAutorises, true)) {
    $type = "destination";
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

$element = null;
$tags = [];
$options = [];

try {
    if ($id > 0 && $type === "destination") {
        $stmt = $pdo->prepare("SELECT * FROM destination WHERE id_destination = ?");
        $stmt->execute([$id]);
        $element = $stmt->fetch();

        if ($element) {
            $tags = jsonToArraySafe($element["tags"] ?? "");
            $options = jsonToArraySafe($element["styles"] ?? "");
        }
    }

    if ($id > 0 && $type === "transport") {
        $stmt = $pdo->prepare("SELECT * FROM transport WHERE id_transport = ?");
        $stmt->execute([$id]);
        $element = $stmt->fetch();

        if ($element) {
            $tags = jsonToArraySafe($element["tags"] ?? "");
            $options = jsonToArraySafe($element["options"] ?? "");
        }
    }

    if ($id > 0 && $type === "hebergement") {
        $stmt = $pdo->prepare("SELECT * FROM hebergement WHERE id_hebergement = ?");
        $stmt->execute([$id]);
        $element = $stmt->fetch();

        if ($element) {
            $tags = jsonToArraySafe($element["tags"] ?? "");
            $options = jsonToArraySafe($element["equipements"] ?? "");
        }
    }

    if ($id > 0 && $type === "activite") {
        $stmt = $pdo->prepare("SELECT * FROM activite WHERE id_activite = ?");
        $stmt->execute([$id]);
        $element = $stmt->fetch();

        if ($element) {
            $tags = jsonToArraySafe($element["tags"] ?? "");
            $options = jsonToArraySafe($element["options"] ?? "");
        }
    }
} catch (PDOException $e) {
    $element = null;
}

$reservationsHebergement = [];

if ($element && $type === "hebergement") {
    try {
        $sqlReservations = "
            SELECT date_arrivee, date_depart
            FROM reservation_hebergement
            WHERE id_hebergement = ?
            AND statut = 'confirmee'
            AND date_depart >= CURDATE()
            ORDER BY date_arrivee ASC
        ";

        $stmtReservations = $pdo->prepare($sqlReservations);
        $stmtReservations->execute([$id]);
        $reservationsHebergement = $stmtReservations->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $reservationsHebergement = [];
    }
}

$pageTitre = "Élément introuvable";
$pageSousTitre = "L’élément demandé n’existe pas ou n’est plus disponible.";
$imageHero = imageParDefaut($type);
$nomPanier = "";
$prixPanier = 0;
$idElementPanier = 0;
$infoPrincipales = [];
$details = [];
$prixLibelle = "Prix";
$optionsTitre = "Informations incluses";

if ($element && $type === "destination") {
    $pageTitre = ($element["nom_destination"] ?? "Destination") . ", " . ($element["pays"] ?? "");
    $pageSousTitre = $element["description"] ?? "";
    $imageHero = !empty($element["image"]) ? $element["image"] : imageParDefaut($type);
    $nomPanier = $element["nom_destination"] ?? "Destination";
    $prixPanier = floatval($element["prix"] ?? 0);
    $idElementPanier = intval($element["id_destination"]);
    $prixLibelle = "À partir de";

    $infoPrincipales = [
        ["label" => "Prix", "valeur" => formatPrixCourt($prixPanier)],
        ["label" => "Durée", "valeur" => intval($element["duree"] ?? 0) . " jours"],
        ["label" => "Note", "valeur" => number_format(floatval($element["note_moyenne"] ?? 0), 1, ",", " ") . "/5"]
    ];

    $details = [
        ["label" => "Pays", "valeur" => $element["pays"] ?? "Non précisé"],
        ["label" => "Catégorie", "valeur" => ucfirst($element["categorie"] ?? "Non précisée")],
        ["label" => "Saison conseillée", "valeur" => ucfirst($element["saison"] ?? "Non précisée")],
        ["label" => "Durée conseillée", "valeur" => intval($element["duree"] ?? 0) . " jours"]
    ];

    $optionsTitre = "Styles de voyage";
}

if ($element && $type === "transport") {
    $pageTitre = ($element["ville_depart"] ?? "Départ") . " → " . ($element["ville_arrivee"] ?? "Arrivée");
    $pageSousTitre = $element["description"] ?? "Détail du trajet sélectionné.";
    $imageHero = !empty($element["image"]) ? $element["image"] : imageTransport($element["type"] ?? "");
    $nomPanier = ($element["compagnie"] ?? "Transport") . " - " . ($element["ville_depart"] ?? "") . " vers " . ($element["ville_arrivee"] ?? "");
    $prixPanier = floatval($element["prix"] ?? 0);
    $idElementPanier = intval($element["id_transport"]);
    $prixLibelle = "Par personne";

    $infoPrincipales = [
        ["label" => "Prix", "valeur" => formatPrixCourt($prixPanier)],
        ["label" => "Durée", "valeur" => floatval($element["duree"] ?? 0) . " h"],
        ["label" => "Places", "valeur" => intval($element["places_disponibles"] ?? 0)]
    ];

    $details = [
        ["label" => "Compagnie", "valeur" => $element["compagnie"] ?? "Non précisée"],
        ["label" => "Mode", "valeur" => ucfirst($element["type"] ?? "Non précisé")],
        ["label" => "Départ", "valeur" => ($element["ville_depart"] ?? "Non précisé") . " — " . formatDateFr($element["date_depart"] ?? "") . " à " . formatHeure($element["heure_depart"] ?? "")],
        ["label" => "Arrivée", "valeur" => ($element["ville_arrivee"] ?? "Non précisé") . " — " . formatHeure($element["heure_arrivee"] ?? "")],
        ["label" => "Retour", "valeur" => formatDateFr($element["date_retour"] ?? "")]
    ];

    $optionsTitre = "Options du trajet";
}

if ($element && $type === "hebergement") {
    $pageTitre = ($element["nom"] ?? "Hébergement") . " - " . ($element["destination"] ?? "");
    $pageSousTitre = $element["description"] ?? "";
    $imageHero = !empty($element["image"]) ? $element["image"] : imageParDefaut($type);
    $nomPanier = $element["nom"] ?? "Hébergement";
    $prixPanier = floatval($element["prix"] ?? 0);
    $idElementPanier = intval($element["id_hebergement"]);
    $prixLibelle = "Par nuit";

    $infoPrincipales = [
        ["label" => "Prix", "valeur" => formatPrixCourt($prixPanier)],
        ["label" => "Capacité", "valeur" => intval($element["capacite"] ?? 0) . " pers."],
        ["label" => "Note", "valeur" => number_format(floatval($element["note"] ?? 0), 1, ",", " ") . "/5"]
    ];

    $details = [
        ["label" => "Destination", "valeur" => $element["destination"] ?? "Non précisée"],
        ["label" => "Pays", "valeur" => $element["pays"] ?? "Non précisé"],
        ["label" => "Type", "valeur" => ucfirst($element["type"] ?? "Non précisé")],
        ["label" => "Classement", "valeur" => $element["etoiles"] ?? "Non précisé"],
        ["label" => "Disponibilité", "valeur" => $element["disponibilite"] ?? "Non précisée"]
    ];

    $optionsTitre = "Équipements";
}

if ($element && $type === "activite") {
    $pageTitre = ($element["nom"] ?? "Activité") . " - " . ($element["destination"] ?? "");
    $pageSousTitre = $element["description"] ?? "";
    $imageHero = !empty($element["image"]) ? $element["image"] : imageParDefaut($type);
    $nomPanier = $element["nom"] ?? "Activité";
    $prixPanier = floatval($element["prix"] ?? 0);
    $idElementPanier = intval($element["id_activite"]);
    $prixLibelle = "Par personne";

    $infoPrincipales = [
        ["label" => "Prix", "valeur" => formatPrixCourt($prixPanier)],
        ["label" => "Durée", "valeur" => floatval($element["duree"] ?? 0) . " h"],
        ["label" => "Places", "valeur" => intval($element["places_disponibles"] ?? 0)]
    ];

    $details = [
        ["label" => "Destination", "valeur" => $element["destination"] ?? "Non précisée"],
        ["label" => "Catégorie", "valeur" => ucfirst($element["categorie"] ?? "Non précisée")],
        ["label" => "Niveau", "valeur" => ucfirst($element["niveau"] ?? "Non précisé")],
        ["label" => "Moment", "valeur" => ucfirst($element["moment"] ?? "Non précisé")],
        ["label" => "Date", "valeur" => formatDateFr($element["date_activite"] ?? "")],
        ["label" => "Note", "valeur" => number_format(floatval($element["note"] ?? 0), 1, ",", " ") . "/5"]
    ];

    $optionsTitre = "Options de l’activité";
}

if (empty($tags)) {
    $tags[] = nomType($type);
}

$retourPage = pageRetour($type);
$typeLisible = nomType($type);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>VoyageVista - <?= h($pageTitre) ?></title>

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: Arial, Helvetica, sans-serif; background: #f8fafc; color: #0f172a; }
    button, input { font-family: inherit; }
    button { cursor: pointer; }

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

    .logo-title { display: block; color: #155e75; font-size: 20px; font-weight: 800; line-height: 1; }
    .logo-subtitle { display: block; margin-top: 3px; font-size: 12px; color: #64748b; }
    .nav-links, .nav-actions { display: flex; align-items: center; gap: 8px; }

    .nav-links button {
      border: none;
      background: transparent;
      color: #475569;
      font-weight: 700;
      padding: 10px 14px;
      border-radius: 999px;
      transition: 0.2s;
    }

    .nav-links button:hover, .nav-links button.active { background: #ecfeff; color: #0e7490; }

    .primary-btn, .secondary-btn {
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

    .primary-btn { border: none; background: #0e7490; color: white; box-shadow: 0 10px 18px rgba(14, 116, 144, 0.18); }
    .primary-btn:hover { background: #155e75; transform: translateY(-1px); }
    .primary-btn:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }
    .secondary-btn { background: white; color: #0e7490; border: 1px solid #bae6fd; }
    .secondary-btn:hover { background: #ecfeff; transform: translateY(-1px); }

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

    .icon-btn:hover { background: #ecfeff; border-color: #67e8f9; }

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

    .notification-wrapper { position: relative; }

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

    .notification-wrapper:hover .notification-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
    .notification-header { display: flex; align-items: center; justify-content: space-between; padding: 8px 8px 12px; border-bottom: 1px solid #e2e8f0; margin-bottom: 8px; }
    .notification-header strong { color: #0f172a; font-size: 16px; }
    .notification-header span { color: #0e7490; font-size: 12px; font-weight: 800; }
    .notification-item { width: 100%; display: flex; gap: 12px; align-items: flex-start; border: none; background: transparent; text-align: left; padding: 12px 8px; border-radius: 16px; transition: 0.2s; }
    .notification-item:hover { background: #f0fdfa; }
    .notification-icon { width: 34px; height: 34px; border-radius: 50%; background: #ecfeff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .notification-item strong { display: block; color: #0f172a; font-size: 14px; }
    .notification-item small { display: block; color: #64748b; margin-top: 3px; line-height: 1.4; }
    .notification-all { width: 100%; margin-top: 8px; border: none; border-radius: 999px; background: #0e7490; color: white; padding: 11px 14px; font-weight: 800; }

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

    .avatar-btn:hover { background: #155e75; transform: translateY(-1px); }

    .page-hero {
      background:
        linear-gradient(135deg, rgba(15, 95, 117, 0.93), rgba(8, 145, 178, 0.78), rgba(15, 23, 42, 0.55)),
        url("<?= h($imageHero) ?>");
      background-size: cover;
      background-position: center;
      color: white;
    }

    .page-hero-container {
      max-width: 1240px;
      margin: auto;
      padding: 64px 24px 82px;
      display: grid;
      grid-template-columns: 1fr 0.85fr;
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
      max-width: 760px;
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

    .hero-tags { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
    .hero-tags span { border-radius: 999px; background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.28); color: white; padding: 9px 13px; font-size: 13px; font-weight: 800; }

    .hero-photo-card {
      background: rgba(255, 255, 255, 0.16);
      border: 1px solid rgba(255, 255, 255, 0.22);
      border-radius: 32px;
      padding: 16px;
      box-shadow: 0 25px 55px rgba(15, 23, 42, 0.24);
    }

    .hero-photo {
      height: 360px;
      border-radius: 24px;
      background-image: url("<?= h($imageHero) ?>");
      background-size: cover;
      background-position: center;
    }

    .hero-photo-body { background: white; color: #0f172a; border-radius: 22px; padding: 20px; margin-top: 14px; }
    .hero-photo-body h2 { font-size: 23px; margin-bottom: 8px; }
    .hero-photo-body p { color: #64748b; font-size: 14px; line-height: 1.5; }

    .main-container { max-width: 1240px; margin: auto; padding: 0 24px 64px; }
    .detail-layout { margin-top: -38px; display: grid; grid-template-columns: 1fr 390px; gap: 24px; align-items: start; position: relative; z-index: 5; }
    .detail-panel, .booking-panel { background: white; border: 1px solid #e2e8f0; border-radius: 30px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.10); overflow: hidden; }
    .panel-header { padding: 24px; border-bottom: 1px solid #e2e8f0; }
    .panel-header p { color: #0e7490; font-weight: 900; margin-bottom: 8px; }
    .panel-header h2 { font-size: 28px; letter-spacing: -0.02em; }
    .panel-body { padding: 24px; }

    .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
    .info-card { border-radius: 22px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 18px; }
    .info-card strong { display: block; color: #155e75; font-size: 22px; margin-bottom: 4px; }
    .info-card span { color: #64748b; font-size: 13px; font-weight: 800; }

    .description-box { color: #475569; line-height: 1.8; font-size: 16px; padding: 20px; border-radius: 24px; background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 24px; }
    .detail-list { display: grid; gap: 12px; }
    .detail-row { display: flex; justify-content: space-between; gap: 18px; padding: 14px 0; border-bottom: 1px solid #e2e8f0; }
    .detail-row span { color: #64748b; font-weight: 800; }
    .detail-row strong { color: #0f172a; text-align: right; }

    .chips { display: flex; flex-wrap: wrap; gap: 10px; }
    .chips span { border-radius: 999px; background: #ecfeff; color: #0e7490; padding: 8px 12px; font-size: 13px; font-weight: 900; }

    .booking-panel { position: sticky; top: 96px; }
    .booking-price { padding: 24px; border-bottom: 1px solid #e2e8f0; }
    .booking-price span { display: block; color: #64748b; font-weight: 800; margin-bottom: 6px; }
    .booking-price strong { color: #155e75; font-size: 36px; letter-spacing: -0.03em; }

    .booking-actions { display: grid; gap: 12px; padding: 24px; }
    .booking-actions button, .booking-actions form, .booking-actions form button { width: 100%; }

    .calendar-box {
      margin: 0 24px 24px;
      padding: 18px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 24px;
    }

    .calendar-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
    }

    .calendar-title strong {
      font-size: 16px;
      color: #0f172a;
    }

    .calendar-nav {
      display: flex;
      gap: 8px;
    }

    .calendar-nav button {
      width: 34px;
      height: 34px;
      border: none;
      border-radius: 50%;
      background: #ecfeff;
      color: #0e7490;
      font-weight: 900;
    }

    .calendar-month {
      color: #155e75;
      font-weight: 900;
      margin-bottom: 12px;
      text-align: center;
    }

    .calendar-week,
    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 6px;
    }

    .calendar-week span {
      text-align: center;
      color: #64748b;
      font-size: 12px;
      font-weight: 900;
      padding: 5px 0;
    }

    .calendar-day {
      height: 36px;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      background: white;
      color: #0f172a;
      font-weight: 800;
      transition: 0.15s;
    }

    .calendar-day:hover {
      background: #ecfeff;
      border-color: #67e8f9;
    }

    .calendar-day.empty {
      background: transparent;
      border-color: transparent;
      cursor: default;
    }

    .calendar-day.disabled {
      background: #e5e7eb;
      color: #94a3b8;
      border-color: #cbd5e1;
      cursor: not-allowed;
      text-decoration: line-through;
    }

    .calendar-day.start,
    .calendar-day.end {
      background: #0e7490;
      border-color: #0e7490;
      color: white;
    }

    .calendar-day.in-range {
      background: #ccfbf1;
      border-color: #99f6e4;
      color: #115e59;
    }

    .date-summary {
      display: grid;
      gap: 8px;
      margin-top: 14px;
      color: #475569;
      font-size: 14px;
      font-weight: 700;
      line-height: 1.5;
    }

    .date-summary strong {
      color: #0f172a;
    }

    .date-error {
      display: none;
      margin-top: 12px;
      color: #b91c1c;
      background: #fef2f2;
      border: 1px solid #fecaca;
      padding: 10px 12px;
      border-radius: 14px;
      font-size: 13px;
      font-weight: 800;
      line-height: 1.4;
    }

    .calendar-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 12px;
      color: #64748b;
      font-size: 12px;
      font-weight: 800;
    }

    .calendar-legend span {
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .legend-dot {
      width: 12px;
      height: 12px;
      border-radius: 4px;
      display: inline-block;
      background: #e5e7eb;
      border: 1px solid #cbd5e1;
    }

    .legend-dot.selected { background: #0e7490; border-color: #0e7490; }
    .legend-dot.range { background: #ccfbf1; border-color: #99f6e4; }

    .trust-box { display: grid; gap: 10px; margin: 0 24px 24px; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 22px; color: #64748b; font-size: 13px; font-weight: 700; line-height: 1.5; }

    .empty-box { margin-top: -38px; position: relative; z-index: 5; background: white; border: 1px dashed #cbd5e1; border-radius: 30px; padding: 42px 24px; text-align: center; color: #64748b; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.10); }
    .empty-box strong { display: block; color: #0f172a; font-size: 26px; margin-bottom: 10px; }

    footer { border-top: 1px solid #e2e8f0; background: white; padding: 28px 24px; }
    .footer-content { max-width: 1240px; margin: auto; display: flex; justify-content: space-between; gap: 20px; color: #64748b; }
    .footer-links { display: flex; gap: 18px; }
    .footer-links button { border: none; background: transparent; color: #64748b; font-weight: 700; }
    .footer-links button:hover { color: #0e7490; }

    @media (max-width: 980px) {
      .nav-links { display: none; }
      .page-hero-container, .detail-layout { grid-template-columns: 1fr; }
      .booking-panel { position: static; }
      .info-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
      .navbar, .footer-content, .detail-row { flex-direction: column; align-items: stretch; }
      .navbar { align-items: flex-start; }
      .nav-actions { width: 100%; justify-content: space-between; flex-wrap: wrap; }
      .page-hero-container { padding: 44px 18px 64px; }
      .main-container { padding: 0 18px 48px; }
      .hero-photo { height: 230px; }
      .detail-row strong { text-align: left; }
      .notification-dropdown { right: -80px; width: 300px; }
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
        <button class="<?= $type === "destination" ? "active" : "" ?>" onclick="window.location.href='Destination.php'">Destinations</button>
        <button class="<?= $type === "transport" ? "active" : "" ?>" onclick="window.location.href='Transport.php'">Transports</button>
        <button class="<?= $type === "hebergement" ? "active" : "" ?>" onclick="window.location.href='Hebergements.php'">Hébergements</button>
        <button class="<?= $type === "activite" ? "active" : "" ?>" onclick="window.location.href='Activites.php'">Activités</button>
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
          <div class="breadcrumb">
            VoyageVista &gt; <?= h($typeLisible) ?> &gt; Détail
          </div>

          <h1><?= h($pageTitre) ?></h1>

          <p><?= h($pageSousTitre) ?></p>

          <div class="hero-tags">
            <?php foreach (array_slice($tags, 0, 6) as $tag): ?>
              <span><?= h($tag) ?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="hero-photo-card">
          <div class="hero-photo"></div>

          <div class="hero-photo-body">
            <h2><?= h($typeLisible) ?> sélectionné</h2>
            <p>
              Consultez les détails de cette offre avant de l’ajouter à votre panier.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="main-container">
      <?php if (!$element): ?>
        <div class="empty-box">
          <strong>Élément introuvable</strong>
          <p>L’élément demandé n’existe pas ou n’est plus disponible.</p>
          <br>
          <button class="primary-btn" onclick="window.location.href='<?= h($retourPage) ?>'">
            Retour au catalogue
          </button>
        </div>
      <?php else: ?>
        <div class="detail-layout">
          <section class="detail-panel">
            <div class="panel-header">
              <p><?= h($typeLisible) ?></p>
              <h2>Détails de l’offre</h2>
            </div>

            <div class="panel-body">
              <div class="info-grid">
                <?php foreach ($infoPrincipales as $info): ?>
                  <div class="info-card">
                    <strong><?= h($info["valeur"]) ?></strong>
                    <span><?= h($info["label"]) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="description-box">
                <?= h($pageSousTitre) ?>
              </div>

              <div class="detail-list">
                <?php foreach ($details as $detail): ?>
                  <div class="detail-row">
                    <span><?= h($detail["label"]) ?></span>
                    <strong><?= h($detail["valeur"]) ?></strong>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <aside class="booking-panel">
            <div class="panel-header">
              <p>Réservation</p>
              <h2>Ajouter au panier</h2>
            </div>

            <div class="booking-price">
              <span><?= h($prixLibelle) ?></span>
              <strong id="prixAffiche"><?= h(formatPrix($prixPanier)) ?></strong>
            </div>

            <?php if ($type === "hebergement"): ?>
              <div class="calendar-box">
                <div class="calendar-title">
                  <strong>Sélectionnez vos dates</strong>

                  <div class="calendar-nav">
                    <button type="button" onclick="changerMois(-1)">‹</button>
                    <button type="button" onclick="changerMois(1)">›</button>
                  </div>
                </div>

                <div id="calendarMonth" class="calendar-month"></div>

                <div class="calendar-week">
                  <span>Lun</span>
                  <span>Mar</span>
                  <span>Mer</span>
                  <span>Jeu</span>
                  <span>Ven</span>
                  <span>Sam</span>
                  <span>Dim</span>
                </div>

                <div id="calendarGrid" class="calendar-grid"></div>

                <div class="calendar-legend">
                  <span><i class="legend-dot"></i> Réservé</span>
                  <span><i class="legend-dot selected"></i> Arrivée / départ</span>
                  <span><i class="legend-dot range"></i> Séjour</span>
                </div>

                <div class="date-summary">
                  <span>Arrivée : <strong id="resumeArrivee">Non sélectionnée</strong></span>
                  <span>Départ : <strong id="resumeDepart">Non sélectionné</strong></span>
                  <span>Nuits : <strong id="resumeNuits">0</strong></span>
                  <span>Total hébergement : <strong id="resumeTotal"><?= h(formatPrix(0)) ?></strong></span>
                </div>

                <div id="dateError" class="date-error"></div>
              </div>
            <?php endif; ?>

            <div class="booking-actions">
              <?php if ($estConnecte): ?>
                <form id="formAjoutPanier" action="api/add_panier.php" method="POST">
                  <input type="hidden" name="type_element" value="<?= h($type) ?>">
                  <input type="hidden" name="id_element" value="<?= h($idElementPanier) ?>">
                  <input type="hidden" name="nom_element" value="<?= h($nomPanier) ?>">
                  <input type="hidden" id="prix_unitaire" name="prix_unitaire" value="<?= h($prixPanier) ?>">
                  <input type="hidden" id="quantite" name="quantite" value="1">

                  <?php if ($type === "hebergement"): ?>
                    <input type="hidden" id="date_arrivee" name="date_arrivee" value="">
                    <input type="hidden" id="date_depart" name="date_depart" value="">
                    <input type="hidden" id="nb_nuits" name="nb_nuits" value="">
                    <button id="btnAjouterPanier" class="primary-btn" type="submit" disabled>
                      Sélectionnez vos dates
                    </button>
                  <?php else: ?>
                    <button class="primary-btn" type="submit">Ajouter au panier</button>
                  <?php endif; ?>
                </form>
              <?php else: ?>
                <button class="primary-btn" onclick="window.location.href='Connexion.php?erreur=connexion_requise'">
                  Se connecter pour ajouter
                </button>
              <?php endif; ?>

              <button class="secondary-btn" onclick="window.location.href='<?= h($retourPage) ?>'">
                Retour aux résultats
              </button>
            </div>

            <div class="trust-box">
              <?php if ($type === "hebergement"): ?>
                <span>Les dates déjà réservées sont grisées.</span>
                <span>Le prix est calculé automatiquement selon le nombre de nuits.</span>
                <span>Le panier reçoit une ligne avec le prix par nuit et la quantité correspondant aux nuits.</span>
              <?php else: ?>
                <span>Consultation détaillée avant validation.</span>
                <span>Ajout au panier lié à votre compte utilisateur.</span>
                <span>Modification possible depuis la page panier.</span>
              <?php endif; ?>
            </div>

            <div class="panel-header">
              <p>Informations</p>
              <h2><?= h($optionsTitre) ?></h2>
            </div>

            <div class="panel-body">
              <div class="chips">
                <?php if (count($options) > 0): ?>
                  <?php foreach ($options as $option): ?>
                    <span><?= h($option) ?></span>
                  <?php endforeach; ?>
                <?php else: ?>
                  <span>Aucune option précisée</span>
                <?php endif; ?>
              </div>
            </div>
          </aside>
        </div>
      <?php endif; ?>
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

  <?php if ($type === "hebergement" && $element): ?>
    <script>
      const prixParNuit = <?= json_encode($prixPanier) ?>;
      const reservations = <?= json_encode($reservationsHebergement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

      let moisAffiche = new Date();
      moisAffiche.setDate(1);

      let dateArriveeSelectionnee = "";
      let dateDepartSelectionnee = "";

      function formatDateIso(date) {
        const annee = date.getFullYear();
        const mois = String(date.getMonth() + 1).padStart(2, "0");
        const jour = String(date.getDate()).padStart(2, "0");

        return annee + "-" + mois + "-" + jour;
      }

      function formatDateFrJs(dateIso) {
        if (!dateIso) {
          return "Non sélectionnée";
        }

        const morceaux = dateIso.split("-");

        if (morceaux.length !== 3) {
          return dateIso;
        }

        return morceaux[2] + "/" + morceaux[1] + "/" + morceaux[0];
      }

      function formatPrixJs(montant) {
        return montant.toLocaleString("fr-FR", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }) + " €";
      }

      function estDateReservee(dateIso) {
        return reservations.some((reservation) => {
          return dateIso >= reservation.date_arrivee && dateIso < reservation.date_depart;
        });
      }

      function plageContientReservation(arrivee, depart) {
        let dateTest = new Date(arrivee + "T00:00:00");
        const dateFin = new Date(depart + "T00:00:00");

        while (dateTest < dateFin) {
          const dateIso = formatDateIso(dateTest);

          if (estDateReservee(dateIso)) {
            return true;
          }

          dateTest.setDate(dateTest.getDate() + 1);
        }

        return false;
      }

      function calculerNombreNuits(arrivee, depart) {
        if (!arrivee || !depart) {
          return 0;
        }

        const dateA = new Date(arrivee + "T00:00:00");
        const dateD = new Date(depart + "T00:00:00");
        const difference = dateD - dateA;

        if (difference <= 0) {
          return 0;
        }

        return Math.round(difference / (1000 * 60 * 60 * 24));
      }

      function afficherErreurDate(message) {
        const box = document.getElementById("dateError");

        if (!message) {
          box.style.display = "none";
          box.textContent = "";
          return;
        }

        box.textContent = message;
        box.style.display = "block";
      }

      function mettreAJourRecapitulatif() {
        const resumeArrivee = document.getElementById("resumeArrivee");
        const resumeDepart = document.getElementById("resumeDepart");
        const resumeNuits = document.getElementById("resumeNuits");
        const resumeTotal = document.getElementById("resumeTotal");
        const prixAffiche = document.getElementById("prixAffiche");

        const inputArrivee = document.getElementById("date_arrivee");
        const inputDepart = document.getElementById("date_depart");
        const inputNbNuits = document.getElementById("nb_nuits");
        const inputQuantite = document.getElementById("quantite");
        const bouton = document.getElementById("btnAjouterPanier");

        const nbNuits = calculerNombreNuits(dateArriveeSelectionnee, dateDepartSelectionnee);
        const total = nbNuits * prixParNuit;

        resumeArrivee.textContent = dateArriveeSelectionnee ? formatDateFrJs(dateArriveeSelectionnee) : "Non sélectionnée";
        resumeDepart.textContent = dateDepartSelectionnee ? formatDateFrJs(dateDepartSelectionnee) : "Non sélectionné";
        resumeNuits.textContent = nbNuits;
        resumeTotal.textContent = formatPrixJs(total);

        if (nbNuits > 0) {
          prixAffiche.textContent = formatPrixJs(total);
        } else {
          prixAffiche.textContent = formatPrixJs(prixParNuit);
        }

        inputArrivee.value = dateArriveeSelectionnee;
        inputDepart.value = dateDepartSelectionnee;
        inputNbNuits.value = nbNuits > 0 ? nbNuits : "";
        inputQuantite.value = nbNuits > 0 ? nbNuits : 1;

        if (nbNuits > 0) {
          bouton.disabled = false;
          bouton.textContent = "Ajouter au panier - " + formatPrixJs(total);
        } else {
          bouton.disabled = true;
          bouton.textContent = "Sélectionnez vos dates";
        }
      }

      function selectionnerDate(dateIso) {
        afficherErreurDate("");

        if (!dateArriveeSelectionnee || dateDepartSelectionnee) {
          dateArriveeSelectionnee = dateIso;
          dateDepartSelectionnee = "";
          mettreAJourRecapitulatif();
          afficherCalendrier();
          return;
        }

        if (dateIso <= dateArriveeSelectionnee) {
          dateArriveeSelectionnee = dateIso;
          dateDepartSelectionnee = "";
          mettreAJourRecapitulatif();
          afficherCalendrier();
          return;
        }

        if (plageContientReservation(dateArriveeSelectionnee, dateIso)) {
          afficherErreurDate("La période sélectionnée contient une date déjà réservée. Choisissez une autre période.");
          dateDepartSelectionnee = "";
          mettreAJourRecapitulatif();
          afficherCalendrier();
          return;
        }

        dateDepartSelectionnee = dateIso;
        mettreAJourRecapitulatif();
        afficherCalendrier();
      }

      function dateDansPlage(dateIso) {
        if (!dateArriveeSelectionnee || !dateDepartSelectionnee) {
          return false;
        }

        return dateIso > dateArriveeSelectionnee && dateIso < dateDepartSelectionnee;
      }

      function changerMois(delta) {
        moisAffiche.setMonth(moisAffiche.getMonth() + delta);
        afficherCalendrier();
      }

      function afficherCalendrier() {
        const calendarGrid = document.getElementById("calendarGrid");
        const calendarMonth = document.getElementById("calendarMonth");

        calendarGrid.innerHTML = "";

        const nomsMois = [
          "Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
          "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"
        ];

        const annee = moisAffiche.getFullYear();
        const mois = moisAffiche.getMonth();

        calendarMonth.textContent = nomsMois[mois] + " " + annee;

        const premierJour = new Date(annee, mois, 1);
        const dernierJour = new Date(annee, mois + 1, 0);
        const aujourdHuiIso = formatDateIso(new Date());

        let decalage = premierJour.getDay() - 1;

        if (decalage < 0) {
          decalage = 6;
        }

        for (let i = 0; i < decalage; i++) {
          const empty = document.createElement("button");
          empty.type = "button";
          empty.className = "calendar-day empty";
          empty.disabled = true;
          calendarGrid.appendChild(empty);
        }

        for (let jour = 1; jour <= dernierJour.getDate(); jour++) {
          const date = new Date(annee, mois, jour);
          const dateIso = formatDateIso(date);
          const bouton = document.createElement("button");

          bouton.type = "button";
          bouton.className = "calendar-day";
          bouton.textContent = jour;

          const datePasse = dateIso < aujourdHuiIso;
          const reservee = estDateReservee(dateIso);

          if (datePasse || reservee) {
            bouton.classList.add("disabled");
            bouton.disabled = true;
          }

          if (dateIso === dateArriveeSelectionnee) {
            bouton.classList.add("start");
          }

          if (dateIso === dateDepartSelectionnee) {
            bouton.classList.add("end");
          }

          if (dateDansPlage(dateIso)) {
            bouton.classList.add("in-range");
          }

          bouton.addEventListener("click", function () {
            selectionnerDate(dateIso);
          });

          calendarGrid.appendChild(bouton);
        }
      }

      document.getElementById("formAjoutPanier").addEventListener("submit", function (event) {
        const nbNuits = calculerNombreNuits(dateArriveeSelectionnee, dateDepartSelectionnee);

        if (nbNuits <= 0) {
          event.preventDefault();
          afficherErreurDate("Sélectionnez une date d’arrivée et une date de départ.");
          return;
        }

        if (plageContientReservation(dateArriveeSelectionnee, dateDepartSelectionnee)) {
          event.preventDefault();
          afficherErreurDate("La période sélectionnée contient une date déjà réservée.");
        }
      });

      afficherCalendrier();
      mettreAJourRecapitulatif();
    </script>
  <?php endif; ?>
</body>
</html>