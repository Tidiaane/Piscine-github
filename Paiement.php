<?php
session_start();
require_once "api/db.php";

function h($valeur) {
    return htmlspecialchars($valeur ?? "", ENT_QUOTES, "UTF-8");
}

function formatPrix($prix) {
    return number_format(floatval($prix), 2, ",", " ") . " €";
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
        return "Non précisée";
    }

    return substr($heure, 0, 5);
}

function dateValide($date) {
    if (empty($date)) {
        return false;
    }

    $d = DateTime::createFromFormat("Y-m-d", $date);
    return $d && $d->format("Y-m-d") === $date;
}

function calculerNuits($dateArrivee, $dateDepart) {
    if (!dateValide($dateArrivee) || !dateValide($dateDepart)) {
        return 0;
    }

    $arrivee = new DateTime($dateArrivee);
    $depart = new DateTime($dateDepart);

    if ($depart <= $arrivee) {
        return 0;
    }

    return intval($arrivee->diff($depart)->days);
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

function afficherTypeElement($type) {
    if ($type === "destination") return "Destination";
    if ($type === "transport") return "Transport";
    if ($type === "hebergement") return "Hébergement";
    if ($type === "activite") return "Activité";

    return ucfirst($type);
}

function numeroCarteValide($numero) {
    $numero = preg_replace('/\D/', '', $numero);
    return preg_match('/^[0-9]{16}$/', $numero);
}

function expirationValide($expiration) {
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiration, $matches)) {
        return false;
    }

    $mois = intval($matches[1]);
    $annee = intval("20" . $matches[2]);

    $expirationDate = DateTime::createFromFormat("Y-m-d", $annee . "-" . $mois . "-01");
    $expirationDate->modify("last day of this month");

    $aujourdhui = new DateTime();

    return $expirationDate >= $aujourdhui;
}

function getTransportInfo($pdo, $idTransport) {
    try {
        $sql = "
            SELECT *
            FROM transport
            WHERE id_transport = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idTransport]);
        $transport = $stmt->fetch();

        return $transport ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function getQuantiteFacturee($ligne) {
    $type = $ligne["type_element"] ?? "";
    $quantite = intval($ligne["quantite"] ?? 1);

    if ($type === "hebergement") {
        $dateArrivee = $ligne["date_arrivee"] ?? "";
        $dateDepart = $ligne["date_depart"] ?? "";
        $nbNuits = intval($ligne["nb_nuits"] ?? 0);

        if ($nbNuits <= 0) {
            $nbNuits = calculerNuits($dateArrivee, $dateDepart);
        }

        return max($nbNuits, 0);
    }

    return max($quantite, 1);
}

function ligneRecapTexte($pdo, $ligne) {
    $type = $ligne["type_element"] ?? "";
    $typeLisible = afficherTypeElement($type);
    $nom = $ligne["nom_element"] ?? "Élément";
    $prixUnitaire = floatval($ligne["prix_unitaire"] ?? 0);
    $quantiteFacturee = getQuantiteFacturee($ligne);
    $totalLigne = $prixUnitaire * $quantiteFacturee;

    if ($type === "hebergement") {
        $dateArrivee = $ligne["date_arrivee"] ?? "";
        $dateDepart = $ligne["date_depart"] ?? "";
        $nbNuits = $quantiteFacturee;

        return
            "- " . $typeLisible . " : " . $nom .
            " du " . formatDateFr($dateArrivee) .
            " au " . formatDateFr($dateDepart) .
            " (" . $nbNuits . " nuit(s), " . formatPrix($totalLigne) . ")\n";
    }

    if ($type === "transport") {
        $transport = getTransportInfo($pdo, intval($ligne["id_element"] ?? 0));

        if ($transport) {
            return
                "- " . $typeLisible . " : " . $nom .
                " | " . ($transport["ville_depart"] ?? "") . " → " . ($transport["ville_arrivee"] ?? "") .
                " | Départ : " . formatDateFr($transport["date_depart"] ?? "") .
                " à " . formatHeure($transport["heure_depart"] ?? "") .
                " | Arrivée : " . formatHeure($transport["heure_arrivee"] ?? "") .
                " | Quantité : " . $quantiteFacturee .
                " (" . formatPrix($totalLigne) . ")\n";
        }
    }

    return
        "- " . $typeLisible . " : " . $nom .
        " x" . $quantiteFacturee .
        " (" . formatPrix($totalLigne) . ")\n";
}

function getTotauxPanier($pdo, $lignes) {
    $sousTotal = 0;
    $nombreElements = 0;
    $recapitulatif = "";

    foreach ($lignes as $ligne) {
        $quantiteFacturee = getQuantiteFacturee($ligne);
        $prixUnitaire = floatval($ligne["prix_unitaire"] ?? 0);

        $sousTotal += $prixUnitaire * $quantiteFacturee;
        $nombreElements += $quantiteFacturee;
        $recapitulatif .= ligneRecapTexte($pdo, $ligne);
    }

    $frais = $sousTotal > 0 ? 19 : 0;
    $totalFinal = $sousTotal + $frais;

    return [
        "sous_total" => $sousTotal,
        "frais" => $frais,
        "total_final" => $totalFinal,
        "nombre_elements" => $nombreElements,
        "recapitulatif" => $recapitulatif
    ];
}

if (!isset($_SESSION["user_id"])) {
    header("Location: Connexion.php?erreur=connexion_requise");
    exit;
}

$idUtilisateur = intval($_SESSION["user_id"]);
$estConnecte = true;

$utilisateur = null;
$initiales = "";

try {
    $sqlUser = "SELECT * FROM utilisateur WHERE id_utilisateur = ?";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([$idUtilisateur]);
    $utilisateur = $stmtUser->fetch();

    if (!$utilisateur) {
        session_destroy();
        header("Location: Connexion.php?erreur=compte_introuvable");
        exit;
    }

    $initiales = getInitiales(
        $utilisateur["prenom"] ?? "",
        $utilisateur["nom"] ?? "",
        $utilisateur["email"] ?? ""
    );
} catch (PDOException $e) {
    header("Location: Connexion.php?erreur=connexion_requise");
    exit;
}

$sqlPanier = "
    SELECT id_panier
    FROM panier
    WHERE id_utilisateur = ?
    ORDER BY id_panier DESC
    LIMIT 1
";

$stmtPanier = $pdo->prepare($sqlPanier);
$stmtPanier->execute([$idUtilisateur]);
$panier = $stmtPanier->fetch();

if (!$panier) {
    header("Location: Panier.php");
    exit;
}

$idPanier = intval($panier["id_panier"]);

$sqlLignes = "
    SELECT *
    FROM ligne_panier
    WHERE id_panier = ?
    ORDER BY id_ligne DESC
";

$stmtLignes = $pdo->prepare($sqlLignes);
$stmtLignes->execute([$idPanier]);
$lignes = $stmtLignes->fetchAll();

if (count($lignes) === 0) {
    header("Location: Panier.php");
    exit;
}

$totaux = getTotauxPanier($pdo, $lignes);
$sousTotal = $totaux["sous_total"];
$frais = $totaux["frais"];
$totalFinal = $totaux["total_final"];
$nombreElementsPanier = $totaux["nombre_elements"];

$nombreNotifications = 0;
$notificationsPopup = [];

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

$erreurs = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titulaire = trim($_POST["titulaire"] ?? "");
    $numeroCarte = preg_replace('/\D/', '', $_POST["numero_carte"] ?? "");
    $expiration = trim($_POST["expiration"] ?? "");
    $cvv = trim($_POST["cvv"] ?? "");
    $adresseFacturation = trim($_POST["adresse_facturation"] ?? "");
    $conditions = isset($_POST["conditions"]);

    if ($titulaire === "" || strlen($titulaire) < 3) {
        $erreurs[] = "Le nom du titulaire est obligatoire.";
    }

    if (!numeroCarteValide($numeroCarte)) {
        $erreurs[] = "Le numéro de carte doit contenir 16 chiffres.";
    }

    if (!expirationValide($expiration)) {
        $erreurs[] = "La date d'expiration est invalide ou dépassée.";
    }

    if (!preg_match('/^[0-9]{3}$/', $cvv)) {
        $erreurs[] = "Le code de sécurité doit contenir 3 chiffres.";
    }

    if ($adresseFacturation === "" || strlen($adresseFacturation) < 5) {
        $erreurs[] = "L'adresse de facturation est obligatoire.";
    }

    if (!$conditions) {
        $erreurs[] = "Vous devez confirmer les conditions de paiement.";
    }

    if (count($erreurs) === 0) {
        try {
            $pdo->beginTransaction();

            $sqlLignesLock = "
                SELECT lp.*
                FROM ligne_panier lp
                JOIN panier p ON lp.id_panier = p.id_panier
                WHERE lp.id_panier = ?
                AND p.id_utilisateur = ?
                ORDER BY lp.id_ligne DESC
                FOR UPDATE
            ";

            $stmtLignesLock = $pdo->prepare($sqlLignesLock);
            $stmtLignesLock->execute([$idPanier, $idUtilisateur]);
            $lignesPaiement = $stmtLignesLock->fetchAll();

            if (count($lignesPaiement) === 0) {
                throw new Exception("Votre panier est vide.");
            }

            $idsReservationHebergement = [];
            $idsReservationTransport = [];
            $infosTransport = [];

            foreach ($lignesPaiement as $ligne) {
                $typeElement = $ligne["type_element"] ?? "";
                $idElement = intval($ligne["id_element"] ?? 0);
                $quantiteFacturee = getQuantiteFacturee($ligne);

                if ($quantiteFacturee <= 0) {
                    throw new Exception("Quantité invalide pour `" . ($ligne["nom_element"] ?? "élément") . "`.");
                }

                if ($typeElement === "activite") {
                    $sqlCheckActivite = "
                        SELECT nom, places_disponibles
                        FROM activite
                        WHERE id_activite = ?
                        FOR UPDATE
                    ";

                    $stmtCheckActivite = $pdo->prepare($sqlCheckActivite);
                    $stmtCheckActivite->execute([$idElement]);
                    $activite = $stmtCheckActivite->fetch();

                    if (!$activite) {
                        throw new Exception("L'activité `" . ($ligne["nom_element"] ?? "") . "` n'existe plus.");
                    }

                    $placesDisponibles = intval($activite["places_disponibles"]);

                    if ($placesDisponibles < $quantiteFacturee) {
                        throw new Exception(
                            "Il ne reste que " .
                            $placesDisponibles .
                            " place(s) pour l'activité `" .
                            ($ligne["nom_element"] ?? "") .
                            "`."
                        );
                    }

                    $sqlUpdateActivite = "
                        UPDATE activite
                        SET places_disponibles = places_disponibles - ?
                        WHERE id_activite = ?
                        AND places_disponibles >= ?
                    ";

                    $stmtUpdateActivite = $pdo->prepare($sqlUpdateActivite);
                    $stmtUpdateActivite->execute([
                        $quantiteFacturee,
                        $idElement,
                        $quantiteFacturee
                    ]);

                    if ($stmtUpdateActivite->rowCount() === 0) {
                        throw new Exception("Impossible de réserver les places pour l'activité `" . ($ligne["nom_element"] ?? "") . "`.");
                    }
                }

                if ($typeElement === "hebergement") {
                    $dateArrivee = $ligne["date_arrivee"] ?? "";
                    $dateDepart = $ligne["date_depart"] ?? "";
                    $nbNuits = calculerNuits($dateArrivee, $dateDepart);

                    if (!dateValide($dateArrivee) || !dateValide($dateDepart) || $nbNuits <= 0) {
                        throw new Exception(
                            "Les dates de l'hébergement `" .
                            ($ligne["nom_element"] ?? "") .
                            "` sont manquantes ou invalides."
                        );
                    }

                    $sqlCheckHebergement = "
                        SELECT id_reservation_hebergement
                        FROM reservation_hebergement
                        WHERE id_hebergement = ?
                        AND statut = 'confirmee'
                        AND date_arrivee < ?
                        AND date_depart > ?
                        LIMIT 1
                        FOR UPDATE
                    ";

                    $stmtCheckHebergement = $pdo->prepare($sqlCheckHebergement);
                    $stmtCheckHebergement->execute([
                        $idElement,
                        $dateDepart,
                        $dateArrivee
                    ]);

                    $reservationExistante = $stmtCheckHebergement->fetch();

                    if ($reservationExistante) {
                        throw new Exception(
                            "L'hébergement `" .
                            ($ligne["nom_element"] ?? "") .
                            "` est déjà réservé sur ces dates."
                        );
                    }

                    $sqlInsertReservationHebergement = "
                        INSERT INTO reservation_hebergement
                        (
                            id_hebergement,
                            id_utilisateur,
                            date_arrivee,
                            date_depart,
                            quantite,
                            statut,
                            date_creation
                        )
                        VALUES (?, ?, ?, ?, 1, 'confirmee', NOW())
                    ";

                    $stmtInsertReservationHebergement = $pdo->prepare($sqlInsertReservationHebergement);
                    $stmtInsertReservationHebergement->execute([
                        $idElement,
                        $idUtilisateur,
                        $dateArrivee,
                        $dateDepart
                    ]);

                    $idsReservationHebergement[intval($ligne["id_ligne"])] = intval($pdo->lastInsertId());
                }

                if ($typeElement === "transport") {
                    $sqlCheckTransport = "
                        SELECT *
                        FROM transport
                        WHERE id_transport = ?
                        FOR UPDATE
                    ";

                    $stmtCheckTransport = $pdo->prepare($sqlCheckTransport);
                    $stmtCheckTransport->execute([$idElement]);
                    $transport = $stmtCheckTransport->fetch();

                    if (!$transport) {
                        throw new Exception("Le transport `" . ($ligne["nom_element"] ?? "") . "` n'existe plus.");
                    }

                    $placesDisponibles = intval($transport["places_disponibles"] ?? 0);

                    if ($placesDisponibles < $quantiteFacturee) {
                        throw new Exception(
                            "Il ne reste que " .
                            $placesDisponibles .
                            " place(s) pour le transport `" .
                            ($ligne["nom_element"] ?? "") .
                            "`."
                        );
                    }

                    if (!empty($transport["date_depart"]) && dateValide($transport["date_depart"])) {
                        if ($transport["date_depart"] < date("Y-m-d")) {
                            throw new Exception("Le transport `" . ($ligne["nom_element"] ?? "") . "` est déjà passé.");
                        }
                    }

                    if (!empty($transport["date_retour"]) && !empty($transport["date_depart"])) {
                        if (dateValide($transport["date_retour"]) && dateValide($transport["date_depart"])) {
                            if ($transport["date_retour"] < $transport["date_depart"]) {
                                throw new Exception("Les dates du transport `" . ($ligne["nom_element"] ?? "") . "` sont incohérentes.");
                            }
                        }
                    }

                    $sqlUpdateTransport = "
                        UPDATE transport
                        SET places_disponibles = places_disponibles - ?
                        WHERE id_transport = ?
                        AND places_disponibles >= ?
                    ";

                    $stmtUpdateTransport = $pdo->prepare($sqlUpdateTransport);
                    $stmtUpdateTransport->execute([
                        $quantiteFacturee,
                        $idElement,
                        $quantiteFacturee
                    ]);

                    if ($stmtUpdateTransport->rowCount() === 0) {
                        throw new Exception("Impossible de réserver les places pour le transport `" . ($ligne["nom_element"] ?? "") . "`.");
                    }

                    $sqlInsertReservationTransport = "
                        INSERT INTO reservation_transport
                        (
                            id_transport,
                            id_utilisateur,
                            quantite,
                            statut,
                            date_creation
                        )
                        VALUES (?, ?, ?, 'confirmee', NOW())
                    ";

                    $stmtInsertReservationTransport = $pdo->prepare($sqlInsertReservationTransport);
                    $stmtInsertReservationTransport->execute([
                        $idElement,
                        $idUtilisateur,
                        $quantiteFacturee
                    ]);

                    $idsReservationTransport[intval($ligne["id_ligne"])] = intval($pdo->lastInsertId());
                    $infosTransport[intval($ligne["id_ligne"])] = $transport;
                }
            }

            $totauxPaiement = getTotauxPanier($pdo, $lignesPaiement);

            $sqlNotification = "
                INSERT INTO notification (id_utilisateur, titre, message, date_envoi, statut_lecture)
                VALUES (?, ?, ?, NOW(), 0)
            ";

            $stmtNotification = $pdo->prepare($sqlNotification);

            $titreNotification = "Paiement validé - Réservation confirmée";

            $messageNotification =
                "Votre paiement a été validé avec succès.\n\n" .
                "Montant total : " . formatPrix($totauxPaiement["total_final"]) . "\n" .
                "Nombre d'éléments / nuits : " . $totauxPaiement["nombre_elements"] . "\n" .
                "Frais de dossier : " . formatPrix($totauxPaiement["frais"]) . "\n\n" .
                "Récapitulatif :\n" .
                $totauxPaiement["recapitulatif"];

            $stmtNotification->execute([
                $idUtilisateur,
                $titreNotification,
                $messageNotification
            ]);

            foreach ($lignesPaiement as $ligne) {
                $typeElement = $ligne["type_element"] ?? "";
                $idElement = intval($ligne["id_element"] ?? 0);
                $idLigne = intval($ligne["id_ligne"] ?? 0);
                $quantiteFacturee = getQuantiteFacturee($ligne);
                $prixUnitaire = floatval($ligne["prix_unitaire"] ?? 0);
                $totalLigne = $prixUnitaire * $quantiteFacturee;
                $typeLisible = afficherTypeElement($typeElement);
                $nomElement = $ligne["nom_element"] ?? "Élément";

                $titreReservation = "Réservation confirmée - " . $nomElement;

                $messageReservation =
                    "Votre réservation a été confirmée.\n\n" .
                    "Type : " . $typeLisible . "\n" .
                    "ID élément : " . $idElement . "\n" .
                    "Nom : " . $nomElement . "\n";

                if ($typeElement === "hebergement") {
                    $dateArrivee = $ligne["date_arrivee"] ?? "";
                    $dateDepart = $ligne["date_depart"] ?? "";
                    $nbNuits = calculerNuits($dateArrivee, $dateDepart);
                    $idReservationHebergement = intval($idsReservationHebergement[$idLigne] ?? 0);

                    $messageReservation .=
                        "ID réservation hébergement : " . $idReservationHebergement . "\n" .
                        "Date d'arrivée : " . formatDateFr($dateArrivee) . "\n" .
                        "Date de départ : " . formatDateFr($dateDepart) . "\n" .
                        "Nombre de nuits : " . $nbNuits . "\n" .
                        "Prix par nuit : " . formatPrix($prixUnitaire) . "\n" .
                        "Total hébergement : " . formatPrix($totalLigne) . "\n\n" .
                        "Les dates sont maintenant bloquées dans la base de données.";
                } elseif ($typeElement === "transport") {
                    $transport = $infosTransport[$idLigne] ?? getTransportInfo($pdo, $idElement);
                    $idReservationTransport = intval($idsReservationTransport[$idLigne] ?? 0);

                    $messageReservation .=
                        "ID réservation transport : " . $idReservationTransport . "\n" .
                        "Compagnie : " . ($transport["compagnie"] ?? "Non précisée") . "\n" .
                        "Trajet : " . ($transport["ville_depart"] ?? "Non précisé") . " → " . ($transport["ville_arrivee"] ?? "Non précisé") . "\n" .
                        "Date de départ : " . formatDateFr($transport["date_depart"] ?? "") . "\n" .
                        "Heure de départ : " . formatHeure($transport["heure_depart"] ?? "") . "\n" .
                        "Heure d'arrivée : " . formatHeure($transport["heure_arrivee"] ?? "") . "\n";

                    if (!empty($transport["date_retour"])) {
                        $messageReservation .= "Date de retour : " . formatDateFr($transport["date_retour"]) . "\n";
                    }

                    $messageReservation .=
                        "Quantité réservée : " . $quantiteFacturee . "\n" .
                        "Prix par personne : " . formatPrix($prixUnitaire) . "\n" .
                        "Total transport : " . formatPrix($totalLigne) . "\n\n" .
                        "Les places disponibles ont été diminuées dans la base de données.";
                } else {
                    $messageReservation .=
                        "Quantité : " . $quantiteFacturee . "\n" .
                        "Prix unitaire : " . formatPrix($prixUnitaire) . "\n" .
                        "Total : " . formatPrix($totalLigne);

                    if ($typeElement === "activite") {
                        $messageReservation .= "\n\nLes places disponibles de cette activité ont été mises à jour.";
                    }
                }

                $stmtNotification->execute([
                    $idUtilisateur,
                    $titreReservation,
                    $messageReservation
                ]);
            }

            $sqlViderPanier = "DELETE FROM ligne_panier WHERE id_panier = ?";
            $stmtViderPanier = $pdo->prepare($sqlViderPanier);
            $stmtViderPanier->execute([$idPanier]);

            $pdo->commit();

            header("Location: Notifications.php");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erreurs[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>VoyageVista - Paiement</title>

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

    .nav-links button:hover {
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

    .payment-hero {
      background:
        linear-gradient(135deg, rgba(15, 95, 117, 0.94), rgba(8, 145, 178, 0.78), rgba(5, 150, 105, 0.78)),
        url("https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1600&q=80");
      background-size: cover;
      background-position: center;
      color: white;
    }

    .payment-hero-container {
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

    .payment-hero h1 {
      max-width: 760px;
      font-size: clamp(38px, 5vw, 62px);
      line-height: 1.05;
      letter-spacing: -0.04em;
      margin-bottom: 18px;
    }

    .payment-hero p {
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

    .payment-layout {
      margin-top: -38px;
      display: grid;
      grid-template-columns: 1fr 420px;
      gap: 24px;
      position: relative;
      z-index: 5;
      align-items: start;
    }

    .payment-panel,
    .summary-panel {
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

    .security-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
      margin-bottom: 24px;
    }

    .security-card {
      border-radius: 22px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      padding: 16px;
      color: #475569;
      font-weight: 800;
      line-height: 1.4;
    }

    .security-card strong {
      display: block;
      color: #155e75;
      margin-bottom: 4px;
    }

    .form-grid {
      display: grid;
      gap: 16px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    .field label {
      display: block;
      color: #475569;
      font-size: 13px;
      font-weight: 900;
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
    }

    .field input:focus {
      border-color: #0891b2;
      box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.12);
    }

    .checkbox-line {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      color: #475569;
      font-weight: 700;
      line-height: 1.5;
      margin-top: 4px;
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

    .summary-panel {
      position: sticky;
      top: 96px;
    }

    .summary-list {
      display: grid;
      gap: 14px;
      max-height: 340px;
      overflow-y: auto;
      padding-right: 4px;
    }

    .summary-item {
      border-bottom: 1px solid #e2e8f0;
      padding-bottom: 12px;
    }

    .summary-item strong {
      display: block;
      color: #0f172a;
      margin-bottom: 5px;
    }

    .summary-item span {
      color: #64748b;
      font-weight: 700;
      font-size: 14px;
      line-height: 1.5;
      display: block;
    }

    .summary-line {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      color: #475569;
      font-weight: 800;
      padding: 12px 0;
      border-bottom: 1px solid #e2e8f0;
    }

    .summary-total {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      font-size: 23px;
      font-weight: 900;
      color: #0f172a;
      padding-top: 18px;
    }

    .summary-total strong {
      color: #155e75;
    }

    .submit-zone {
      margin-top: 22px;
      display: grid;
      gap: 12px;
    }

    .submit-zone button {
      width: 100%;
    }

    .note-box {
      margin-top: 18px;
      padding: 16px;
      border-radius: 22px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #64748b;
      font-size: 13px;
      font-weight: 700;
      line-height: 1.5;
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

      .payment-layout {
        grid-template-columns: 1fr;
      }

      .summary-panel {
        position: static;
      }

      .security-row {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .navbar,
      .footer-content {
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

      .payment-hero-container {
        padding: 44px 18px 64px;
      }

      .main-container {
        padding: 0 18px 48px;
      }

      .form-row {
        grid-template-columns: 1fr;
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
          <button class="icon-btn" onclick="window.location.href='Notifications.php'" aria-label="Notifications">
            🔔

            <?php if ($nombreNotifications > 0): ?>
              <span class="badge-count"><?= h($nombreNotifications) ?></span>
            <?php endif; ?>
          </button>

          <div class="notification-dropdown">
            <div class="notification-header">
              <strong>Notifications</strong>

              <?php if ($nombreNotifications > 0): ?>
                <span><?= h($nombreNotifications) ?> nouvelle(s)</span>
              <?php else: ?>
                <span>Aucune nouvelle</span>
              <?php endif; ?>
            </div>

            <?php if (count($notificationsPopup) === 0): ?>
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

            <button class="notification-all" onclick="window.location.href='Notifications.php'">
              Voir toutes les notifications
            </button>
          </div>
        </div>

        <button class="icon-btn" onclick="window.location.href='Panier.php'" aria-label="Panier">
          🛒

          <?php if ($nombreElementsPanier > 0): ?>
            <span class="badge-count"><?= h($nombreElementsPanier) ?></span>
          <?php endif; ?>
        </button>

        <button class="avatar-btn" onclick="window.location.href='Profil.php'" title="Mon profil">
          <?= h($initiales) ?>
        </button>
      </div>
    </nav>
  </header>

  <main>
    <section class="payment-hero">
      <div class="payment-hero-container">
        <div class="breadcrumb">VoyageVista &gt; Panier &gt; Paiement</div>

        <h1>Paiement sécurisé</h1>

        <p>
          Après validation, les hébergements seront bloqués sur leurs dates,
          les transports diminueront les places disponibles en base et une notification sera créée pour chaque réservation.
        </p>
      </div>
    </section>

    <section class="main-container">
      <div class="payment-layout">
        <section class="payment-panel">
          <div class="panel-header">
            <p>Étape finale</p>
            <h2>Informations de paiement</h2>
          </div>

          <div class="panel-body">
            <div class="security-row">
            
            </div>

            <?php if (count($erreurs) > 0): ?>
              <div class="error-box">
                <ul>
                  <?php foreach ($erreurs as $erreur): ?>
                    <li><?= h($erreur) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form method="POST" class="form-grid">
              <div class="field">
                <label for="titulaire">Nom du titulaire</label>
                <input
                  id="titulaire"
                  name="titulaire"
                  type="text"
                  placeholder="Ex : Jean Dupont"
                  value="<?= h($_POST["titulaire"] ?? "") ?>"
                  required
                >
              </div>

              <div class="field">
                <label for="numero_carte">Numéro de carte</label>
                <input
                  id="numero_carte"
                  name="numero_carte"
                  type="text"
                  placeholder="1234 5678 9012 3456"
                  maxlength="19"
                  value="<?= h($_POST["numero_carte"] ?? "") ?>"
                  required
                >
              </div>

              <div class="form-row">
                <div class="field">
                  <label for="expiration">Expiration</label>
                  <input
                    id="expiration"
                    name="expiration"
                    type="text"
                    placeholder="MM/AA"
                    maxlength="5"
                    value="<?= h($_POST["expiration"] ?? "") ?>"
                    required
                  >
                </div>

                <div class="field">
                  <label for="cvv">CVV</label>
                  <input
                    id="cvv"
                    name="cvv"
                    type="password"
                    placeholder="123"
                    maxlength="3"
                    value="<?= h($_POST["cvv"] ?? "") ?>"
                    required
                  >
                </div>
              </div>

              <div class="field">
                <label for="adresse_facturation">Adresse de facturation</label>
                <input
                  id="adresse_facturation"
                  name="adresse_facturation"
                  type="text"
                  placeholder="Adresse complète"
                  value="<?= h($_POST["adresse_facturation"] ?? ($utilisateur["adresse"] ?? "")) ?>"
                  required
                >
              </div>

              <label class="checkbox-line">
                <input type="checkbox" name="conditions" value="1" <?= isset($_POST["conditions"]) ? "checked" : "" ?>>
                Je confirme que les informations sont correctes et que je souhaite valider cette réservation.
              </label>

              <div class="submit-zone">
                <button class="primary-btn" type="submit">Valider le paiement</button>
                <button class="secondary-btn" type="button" onclick="window.location.href='Panier.php'">
                  Retour au panier
                </button>
              </div>
            </form>

            <div class="note-box">
              Pour tester rapidement : utilisez un numéro de carte de 16 chiffres,
              une expiration future au format MM/AA et un CVV de 3 chiffres.
            </div>
          </div>
        </section>

        <aside class="summary-panel">
          <div class="panel-header">
            <p>Récapitulatif</p>
            <h2>Votre réservation</h2>
          </div>

          <div class="panel-body">
            <div class="summary-list">
              <?php foreach ($lignes as $ligne): ?>
                <?php
                  $typeElement = $ligne["type_element"] ?? "";
                  $quantiteFacturee = getQuantiteFacturee($ligne);
                  $prixUnitaire = floatval($ligne["prix_unitaire"] ?? 0);
                  $totalLigne = $quantiteFacturee * $prixUnitaire;
                  $transport = $typeElement === "transport" ? getTransportInfo($pdo, intval($ligne["id_element"] ?? 0)) : null;
                ?>

                <div class="summary-item">
                  <strong><?= h($ligne["nom_element"] ?? "Élément") ?></strong>

                  <?php if ($typeElement === "hebergement"): ?>
                    <span>
                      Hébergement ·
                      du <?= h(formatDateFr($ligne["date_arrivee"] ?? "")) ?>
                      au <?= h(formatDateFr($ligne["date_depart"] ?? "")) ?>
                      · <?= h($quantiteFacturee) ?> nuit(s)
                      · <?= h(formatPrix($totalLigne)) ?>
                    </span>
                  <?php elseif ($typeElement === "transport" && $transport): ?>
                    <span>
                      Transport ·
                      <?= h($transport["ville_depart"] ?? "") ?>
                      →
                      <?= h($transport["ville_arrivee"] ?? "") ?>
                      · <?= h(formatDateFr($transport["date_depart"] ?? "")) ?>
                      à <?= h(formatHeure($transport["heure_depart"] ?? "")) ?>
                      · <?= h($quantiteFacturee) ?> place(s)
                      · <?= h(formatPrix($totalLigne)) ?>
                    </span>
                  <?php else: ?>
                    <span>
                      <?= h(afficherTypeElement($typeElement)) ?>
                      · Quantité <?= h($quantiteFacturee) ?>
                      · <?= h(formatPrix($totalLigne)) ?>
                    </span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>

            <br>

            <div class="summary-line">
              <span>Sous-total</span>
              <strong><?= h(formatPrix($sousTotal)) ?></strong>
            </div>

            <div class="summary-line">
              <span>Frais de dossier</span>
              <strong><?= h(formatPrix($frais)) ?></strong>
            </div>

            <div class="summary-total">
              <span>Total</span>
              <strong><?= h(formatPrix($totalFinal)) ?></strong>
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

  <script>
    const numeroCarte = document.getElementById("numero_carte");
    const expiration = document.getElementById("expiration");

    numeroCarte.addEventListener("input", function () {
      let value = this.value.replace(/\D/g, "").substring(0, 16);
      value = value.replace(/(.{4})/g, "$1 ").trim();
      this.value = value;
    });

    expiration.addEventListener("input", function () {
      let value = this.value.replace(/\D/g, "").substring(0, 4);

      if (value.length >= 3) {
        value = value.substring(0, 2) + "/" + value.substring(2);
      }

      this.value = value;
    });
  </script>
</body>
</html>