<?php
session_start();
require_once "api/db.php";

function h($valeur) {
    return htmlspecialchars($valeur ?? "", ENT_QUOTES, "UTF-8");
}

function formatPrixCourt($prix) {
    return number_format(floatval($prix), 0, ",", " ") . " €";
}

function formatDateFr($date) {
    if (empty($date)) {
        return "Non précisée";
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return "Non précisée";
    }

    return date("d/m/Y", $timestamp);
}

function formatHeure($heure) {
    if (empty($heure)) {
        return "";
    }

    return substr($heure, 0, 5);
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

function iconeTransport($type) {
    if ($type === "avion") return "✈️";
    if ($type === "train") return "🚆";
    if ($type === "bus") return "🚌";
    if ($type === "voiture") return "🚗";
    return "🧭";
}

function jsonToArraySafe($value) {
    if (empty($value)) {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

$estConnecte = isset($_SESSION["user_id"]);
$idUtilisateur = $estConnecte ? intval($_SESSION["user_id"]) : null;

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

try {
    $sql = "
        SELECT *
        FROM transport
        ORDER BY recommande ASC, prix ASC, date_depart ASC, heure_depart ASC, id_transport ASC
    ";

    $stmt = $pdo->query($sql);
    $transportsDb = $stmt->fetchAll();
} catch (PDOException $e) {
    $transportsDb = [];
}

$transportsJs = [];
$nombreTransports = 0;
$prixMinTransport = null;
$placesRestantesTotal = 0;

foreach ($transportsDb as $transport) {
    $idTransport = intval($transport["id_transport"]);
    $placesRestantes = max(intval($transport["places_disponibles"] ?? 0), 0);

    $options = jsonToArraySafe($transport["options"] ?? "");
    $tags = jsonToArraySafe($transport["tags"] ?? "");

    $typeTransport = $transport["type"] ?? "";
    $icone = $transport["icone"] ?? "";

    if ($icone === "") {
        $icone = iconeTransport($typeTransport);
    }

    $prix = floatval($transport["prix"] ?? 0);

    if ($prixMinTransport === null || $prix < $prixMinTransport) {
        $prixMinTransport = $prix;
    }

    $nombreTransports++;
    $placesRestantesTotal += $placesRestantes;

    $transportsJs[] = [
        "id" => $idTransport,
        "type" => $typeTransport,
        "icone" => $icone,
        "compagnie" => $transport["compagnie"] ?? "",
        "departVille" => $transport["ville_depart"] ?? "",
        "arriveeVille" => $transport["ville_arrivee"] ?? "",
        "dateDepart" => $transport["date_depart"] ?? "",
        "dateRetour" => $transport["date_retour"] ?? "",
        "departHeure" => formatHeure($transport["heure_depart"] ?? ""),
        "arriveeHeure" => formatHeure($transport["heure_arrivee"] ?? ""),
        "duree" => floatval($transport["duree"] ?? 0),
        "prix" => $prix,
        "placesRestantes" => $placesRestantes,
        "options" => $options,
        "tags" => $tags,
        "description" => $transport["description"] ?? "",
        "recommande" => intval($transport["recommande"] ?? 1)
    ];
}
?>

