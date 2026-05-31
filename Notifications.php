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

function formatDateNotification($date) {
    if (empty($date)) {
        return "Date non précisée";
    }

    try {
        $dateObj = new DateTime($date);
        return $dateObj->format("d/m/Y à H:i");
    } catch (Exception $e) {
        return $date;
    }
}

function formatDateFr($date) {
    if (empty($date)) {
        return "Non précisée";
    }

    try {
        $dateObj = new DateTime($date);
        return $dateObj->format("d/m/Y");
    } catch (Exception $e) {
        return $date;
    }
}

function formatHeure($heure) {
    if (empty($heure)) {
        return "Non précisée";
    }

    return substr($heure, 0, 5);
}

function formatPrix($prix) {
    return number_format(floatval($prix), 2, ",", " ") . " €";
}

function normaliserDateSql($date) {
    $date = trim($date ?? "");

    if ($date === "") {
        return "";
    }

    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
        return $date;
    }

    if (preg_match('/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/', $date, $match)) {
        return $match[3] . "-" . $match[2] . "-" . $match[1];
    }

    return "";
}

function dateSqlValide($date) {
    if (empty($date)) {
        return false;
    }

    $dateObj = DateTime::createFromFormat("Y-m-d", $date);
    return $dateObj && $dateObj->format("Y-m-d") === $date;
}

function calculerNuits($dateArrivee, $dateDepart) {
    if (!dateSqlValide($dateArrivee) || !dateSqlValide($dateDepart)) {
        return 0;
    }

    $arrivee = new DateTime($dateArrivee);
    $depart = new DateTime($dateDepart);

    if ($depart <= $arrivee) {
        return 0;
    }

    return intval($arrivee->diff($depart)->days);
}

function typeInterneDepuisTypeLisible($typeLisible) {
    $typeLisible = trim($typeLisible);
    $typeMin = strtolower($typeLisible);

    if ($typeLisible === "Activité" || $typeMin === "activite") {
        return "activite";
    }

    if ($typeLisible === "Hébergement" || $typeMin === "hebergement") {
        return "hebergement";
    }

    if ($typeLisible === "Transport" || $typeMin === "transport") {
        return "transport";
    }

    if ($typeLisible === "Destination" || $typeMin === "destination") {
        return "destination";
    }

    return "";
}

function extraireIdNotificationOrigineAction($message) {
    if (preg_match('/Notification d[\'’]origine\s*:\s*#?([0-9]+)/iu', $message ?? "", $match)) {
        return intval($match[1]);
    }

    return null;
}

function verifierActionDejaFaite($pdo, $idUtilisateur, $idNotification) {
    $sql = "
        SELECT COUNT(*) AS total
        FROM notification
        WHERE id_utilisateur = ?
        AND message LIKE ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $idUtilisateur,
        "%Notification d'origine : #" . $idNotification . "%"
    ]);

    $resultat = $stmt->fetch();
    return intval($resultat["total"] ?? 0) > 0;
}

function extraireReservationDepuisNotification($notification) {
    $titre = $notification["titre"] ?? "";
    $message = $notification["message"] ?? "";

    $titreReservation =
        stripos($titre, "Réservation confirmée -") !== false ||
        stripos($titre, "Reservation confirmee -") !== false ||
        stripos($titre, "Réservation modifiée -") !== false ||
        stripos($titre, "Reservation modifiee -") !== false;

    if (!$titreReservation) {
        return null;
    }

    $typeLisible = "";
    $nomElement = "";
    $idElement = 0;
    $quantite = 0;

    $idReservationHebergement = 0;
    $idReservationTransport = 0;

    $dateArrivee = "";
    $dateDepart = "";
    $nbNuits = 0;

    $dateDepartTransport = "";
    $heureDepartTransport = "";
    $heureArriveeTransport = "";
    $compagnie = "";
    $trajet = "";

    if (preg_match('/^Type\s*:\s*(.+)$/miu', $message, $match)) {
        $typeLisible = trim($match[1]);
    }

    if (preg_match('/^Nom\s*:\s*(.+)$/miu', $message, $match)) {
        $nomElement = trim($match[1]);
    }

    if (preg_match('/^ID élément\s*:\s*([0-9]+)/miu', $message, $match)) {
        $idElement = intval($match[1]);
    }

    if (preg_match('/^ID réservation hébergement\s*:\s*([0-9]+)/miu', $message, $match)) {
        $idReservationHebergement = intval($match[1]);
    }

    if (preg_match('/^ID réservation transport\s*:\s*([0-9]+)/miu', $message, $match)) {
        $idReservationTransport = intval($match[1]);
    }

    if (preg_match('/^Date d[\'’]arriv[eé]e\s*:\s*(.+)$/miu', $message, $match)) {
        $dateArrivee = normaliserDateSql($match[1]);
    }

    if (preg_match('/^Date de d[eé]part\s*:\s*(.+)$/miu', $message, $match)) {
        $dateDepart = normaliserDateSql($match[1]);
        $dateDepartTransport = $dateDepart;
    }

    if (preg_match('/^Nombre de nuits\s*:\s*([0-9]+)/miu', $message, $match)) {
        $nbNuits = intval($match[1]);
    }

    if (preg_match('/^Quantit[ée]\s*:\s*([0-9]+)/miu', $message, $match)) {
        $quantite = intval($match[1]);
    }

    if (preg_match('/^Quantit[ée] réservée\s*:\s*([0-9]+)/miu', $message, $match)) {
        $quantite = intval($match[1]);
    }

    if (preg_match('/^Compagnie\s*:\s*(.+)$/miu', $message, $match)) {
        $compagnie = trim($match[1]);
    }

    if (preg_match('/^Trajet\s*:\s*(.+)$/miu', $message, $match)) {
        $trajet = trim($match[1]);
    }

    if (preg_match('/^Heure de d[eé]part\s*:\s*(.+)$/miu', $message, $match)) {
        $heureDepartTransport = trim($match[1]);
    }

    if (preg_match('/^Heure d[\'’]arriv[eé]e\s*:\s*(.+)$/miu', $message, $match)) {
        $heureArriveeTransport = trim($match[1]);
    }

    if ($nomElement === "" && strpos($titre, " - ") !== false) {
        $nomElement = trim(substr($titre, strpos($titre, " - ") + 3));
    }

    $typeInterne = typeInterneDepuisTypeLisible($typeLisible);

    if ($typeInterne === "hebergement") {
        if ($nbNuits <= 0) {
            $nbNuits = calculerNuits($dateArrivee, $dateDepart);
        }

        if ($nomElement === "" || $idElement <= 0 || !dateSqlValide($dateArrivee) || !dateSqlValide($dateDepart) || $nbNuits <= 0) {
            return null;
        }

        return [
            "type_lisible" => $typeLisible,
            "type_interne" => "hebergement",
            "nom_element" => $nomElement,
            "id_element" => $idElement,
            "quantite" => $nbNuits,
            "id_reservation_hebergement" => $idReservationHebergement,
            "id_reservation_transport" => 0,
            "date_arrivee" => $dateArrivee,
            "date_depart" => $dateDepart,
            "nb_nuits" => $nbNuits,
            "compagnie" => "",
            "trajet" => "",
            "heure_depart" => "",
            "heure_arrivee" => ""
        ];
    }

    if ($typeInterne === "transport") {
        if ($nomElement === "" || $idElement <= 0 || $quantite <= 0) {
            return null;
        }

        return [
            "type_lisible" => $typeLisible,
            "type_interne" => "transport",
            "nom_element" => $nomElement,
            "id_element" => $idElement,
            "quantite" => $quantite,
            "id_reservation_hebergement" => 0,
            "id_reservation_transport" => $idReservationTransport,
            "date_arrivee" => "",
            "date_depart" => $dateDepartTransport,
            "nb_nuits" => 0,
            "compagnie" => $compagnie,
            "trajet" => $trajet,
            "heure_depart" => $heureDepartTransport,
            "heure_arrivee" => $heureArriveeTransport
        ];
    }

    if ($typeInterne === "" || $nomElement === "" || $quantite <= 0) {
        return null;
    }

    return [
        "type_lisible" => $typeLisible,
        "type_interne" => $typeInterne,
        "nom_element" => $nomElement,
        "id_element" => $idElement,
        "quantite" => $quantite,
        "id_reservation_hebergement" => 0,
        "id_reservation_transport" => 0,
        "date_arrivee" => "",
        "date_depart" => "",
        "nb_nuits" => 0,
        "compagnie" => "",
        "trajet" => "",
        "heure_depart" => "",
        "heure_arrivee" => ""
    ];
}

function trouverReservationHebergement($pdo, $reservation, $idUtilisateur) {
    if (intval($reservation["id_reservation_hebergement"] ?? 0) > 0) {
        $sql = "
            SELECT *
            FROM reservation_hebergement
            WHERE id_reservation_hebergement = ?
            AND id_utilisateur = ?
            AND statut = 'confirmee'
            FOR UPDATE
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            intval($reservation["id_reservation_hebergement"]),
            $idUtilisateur
        ]);

        $ligne = $stmt->fetch();

        if ($ligne) {
            return $ligne;
        }
    }

    $sql = "
        SELECT *
        FROM reservation_hebergement
        WHERE id_hebergement = ?
        AND id_utilisateur = ?
        AND date_arrivee = ?
        AND date_depart = ?
        AND statut = 'confirmee'
        ORDER BY id_reservation_hebergement DESC
        LIMIT 1
        FOR UPDATE
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        intval($reservation["id_element"]),
        $idUtilisateur,
        $reservation["date_arrivee"],
        $reservation["date_depart"]
    ]);

    return $stmt->fetch();
}

function trouverReservationTransport($pdo, $reservation, $idUtilisateur) {
    if (intval($reservation["id_reservation_transport"] ?? 0) > 0) {
        $sql = "
            SELECT rt.*, t.compagnie, t.ville_depart, t.ville_arrivee, t.date_depart, t.heure_depart, t.heure_arrivee
            FROM reservation_transport rt
            JOIN transport t ON rt.id_transport = t.id_transport
            WHERE rt.id_reservation_transport = ?
            AND rt.id_utilisateur = ?
            AND rt.statut = 'confirmee'
            FOR UPDATE
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            intval($reservation["id_reservation_transport"]),
            $idUtilisateur
        ]);

        $ligne = $stmt->fetch();

        if ($ligne) {
            return $ligne;
        }
    }

    $sql = "
        SELECT rt.*, t.compagnie, t.ville_depart, t.ville_arrivee, t.date_depart, t.heure_depart, t.heure_arrivee
        FROM reservation_transport rt
        JOIN transport t ON rt.id_transport = t.id_transport
        WHERE rt.id_transport = ?
        AND rt.id_utilisateur = ?
        AND rt.statut = 'confirmee'
        ORDER BY rt.id_reservation_transport DESC
        LIMIT 1
        FOR UPDATE
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        intval($reservation["id_element"]),
        $idUtilisateur
    ]);

    return $stmt->fetch();
}

function verifierDatesHebergementDisponibles($pdo, $idHebergement, $dateArrivee, $dateDepart, $idReservationAExclure) {
    $sql = "
        SELECT COUNT(*) AS total
        FROM reservation_hebergement
        WHERE id_hebergement = ?
        AND statut = 'confirmee'
        AND id_reservation_hebergement <> ?
        AND date_arrivee < ?
        AND date_depart > ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $idHebergement,
        $idReservationAExclure,
        $dateDepart,
        $dateArrivee
    ]);

    $resultat = $stmt->fetch();

    return intval($resultat["total"] ?? 0) === 0;
}

if (!isset($_SESSION["user_id"])) {
    header("Location: Connexion.php?erreur=connexion_requise");
    exit;
}

$idUtilisateur = intval($_SESSION["user_id"]);
$messageAction = "";
$typeMessageAction = "";

$utilisateur = null;
$initiales = "";

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

$colonnesNotification = [];
$idNotificationCol = null;

try {
    $stmtColonnes = $pdo->query("SHOW COLUMNS FROM notification");
    $colonnesNotification = array_column($stmtColonnes->fetchAll(PDO::FETCH_ASSOC), "Field");

    if (in_array("id_notification", $colonnesNotification)) {
        $idNotificationCol = "id_notification";
    } elseif (in_array("id", $colonnesNotification)) {
        $idNotificationCol = "id";
    }
} catch (PDOException $e) {
    $colonnesNotification = [];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "marquer_toutes_lues") {
        $sql = "
            UPDATE notification
            SET statut_lecture = 1
            WHERE id_utilisateur = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idUtilisateur]);

        header("Location: Notifications.php");
        exit;
    }

    if ($action === "marquer_lue" && $idNotificationCol !== null) {
        $idNotification = intval($_POST["id_notification"] ?? 0);

        if ($idNotification > 0) {
            $sql = "
                UPDATE notification
                SET statut_lecture = 1
                WHERE $idNotificationCol = ?
                AND id_utilisateur = ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idNotification, $idUtilisateur]);
        }

        header("Location: Notifications.php");
        exit;
    }

    if ($action === "supprimer" && $idNotificationCol !== null) {
        $idNotification = intval($_POST["id_notification"] ?? 0);

        if ($idNotification > 0) {
            $sql = "
                DELETE FROM notification
                WHERE $idNotificationCol = ?
                AND id_utilisateur = ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idNotification, $idUtilisateur]);
        }

        header("Location: Notifications.php");
        exit;
    }

    if ($action === "annuler_reservation_transport" && $idNotificationCol !== null) {
        $idNotification = intval($_POST["id_notification"] ?? 0);

        try {
            $pdo->beginTransaction();

            if ($idNotification <= 0) {
                throw new Exception("Notification introuvable.");
            }

            if (verifierActionDejaFaite($pdo, $idUtilisateur, $idNotification)) {
                throw new Exception("Cette réservation transport a déjà été traitée.");
            }

            $sqlNotificationOrigine = "
                SELECT *
                FROM notification
                WHERE $idNotificationCol = ?
                AND id_utilisateur = ?
                FOR UPDATE
            ";

            $stmtNotificationOrigine = $pdo->prepare($sqlNotificationOrigine);
            $stmtNotificationOrigine->execute([$idNotification, $idUtilisateur]);
            $notificationOrigine = $stmtNotificationOrigine->fetch();

            if (!$notificationOrigine) {
                throw new Exception("Notification introuvable.");
            }

            $reservation = extraireReservationDepuisNotification($notificationOrigine);

            if (!$reservation || $reservation["type_interne"] !== "transport") {
                throw new Exception("Cette notification ne correspond pas à une réservation transport annulable.");
            }

            $reservationTransport = trouverReservationTransport($pdo, $reservation, $idUtilisateur);

            if (!$reservationTransport) {
                throw new Exception("Réservation transport introuvable ou déjà annulée.");
            }

            $quantiteAnnulee = intval($reservationTransport["quantite"] ?? $reservation["quantite"] ?? 1);
            $idTransport = intval($reservationTransport["id_transport"]);

            if ($quantiteAnnulee <= 0) {
                $quantiteAnnulee = 1;
            }

            $sqlAnnuler = "
                UPDATE reservation_transport
                SET statut = 'annulee'
                WHERE id_reservation_transport = ?
                AND id_utilisateur = ?
                AND statut = 'confirmee'
            ";

            $stmtAnnuler = $pdo->prepare($sqlAnnuler);
            $stmtAnnuler->execute([
                intval($reservationTransport["id_reservation_transport"]),
                $idUtilisateur
            ]);

            if ($stmtAnnuler->rowCount() === 0) {
                throw new Exception("Impossible d'annuler cette réservation transport.");
            }

            $sqlMajPlaces = "
                UPDATE transport
                SET places_disponibles = places_disponibles + ?
                WHERE id_transport = ?
            ";

            $stmtMajPlaces = $pdo->prepare($sqlMajPlaces);
            $stmtMajPlaces->execute([
                $quantiteAnnulee,
                $idTransport
            ]);

            $titreAnnulation = "Annulation confirmée - Transport";

            $messageAnnulation =
                "Votre réservation de transport a bien été annulée.\n\n" .
                "Type : Transport\n" .
                "ID élément : " . $idTransport . "\n" .
                "ID réservation transport : " . intval($reservationTransport["id_reservation_transport"]) . "\n" .
                "Nom : " . ($reservation["nom_element"] ?? "Transport") . "\n" .
                "Compagnie : " . ($reservationTransport["compagnie"] ?? "Non précisée") . "\n" .
                "Trajet : " . ($reservationTransport["ville_depart"] ?? "Non précisé") . " → " . ($reservationTransport["ville_arrivee"] ?? "Non précisé") . "\n" .
                "Date de départ : " . formatDateFr($reservationTransport["date_depart"] ?? "") . "\n" .
                "Heure de départ : " . formatHeure($reservationTransport["heure_depart"] ?? "") . "\n" .
                "Heure d'arrivée : " . formatHeure($reservationTransport["heure_arrivee"] ?? "") . "\n" .
                "Quantité annulée : " . $quantiteAnnulee . "\n" .
                "Notification d'origine : #" . $idNotification . "\n\n" .
                "La réservation transport est annulée dans la base et les places disponibles ont été mises à jour.";

            $sqlNouvelleNotification = "
                INSERT INTO notification (id_utilisateur, titre, message, date_envoi, statut_lecture)
                VALUES (?, ?, ?, NOW(), 0)
            ";

            $stmtNouvelleNotification = $pdo->prepare($sqlNouvelleNotification);
            $stmtNouvelleNotification->execute([
                $idUtilisateur,
                $titreAnnulation,
                $messageAnnulation
            ]);

            $sqlMarquerLue = "
                UPDATE notification
                SET statut_lecture = 1
                WHERE $idNotificationCol = ?
                AND id_utilisateur = ?
            ";

            $stmtMarquerLue = $pdo->prepare($sqlMarquerLue);
            $stmtMarquerLue->execute([$idNotification, $idUtilisateur]);

            $pdo->commit();

            header("Location: Notifications.php?action=transport_annulation_ok");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $messageAction = $e->getMessage();
            $typeMessageAction = "error";
        }
    }

    if ($action === "annuler_reservation_hebergement" && $idNotificationCol !== null) {
        $idNotification = intval($_POST["id_notification"] ?? 0);

        try {
            $pdo->beginTransaction();

            if ($idNotification <= 0) {
                throw new Exception("Notification introuvable.");
            }

            if (verifierActionDejaFaite($pdo, $idUtilisateur, $idNotification)) {
                throw new Exception("Cette réservation hébergement a déjà été traitée.");
            }

            $sqlNotificationOrigine = "
                SELECT *
                FROM notification
                WHERE $idNotificationCol = ?
                AND id_utilisateur = ?
                FOR UPDATE
            ";

            $stmtNotificationOrigine = $pdo->prepare($sqlNotificationOrigine);
            $stmtNotificationOrigine->execute([$idNotification, $idUtilisateur]);
            $notificationOrigine = $stmtNotificationOrigine->fetch();

            if (!$notificationOrigine) {
                throw new Exception("Notification introuvable.");
            }

            $reservation = extraireReservationDepuisNotification($notificationOrigine);

            if (!$reservation || $reservation["type_interne"] !== "hebergement") {
                throw new Exception("Cette notification ne correspond pas à une réservation d'hébergement annulable.");
            }

            $reservationHebergement = trouverReservationHebergement($pdo, $reservation, $idUtilisateur);

            if (!$reservationHebergement) {
                throw new Exception("Réservation d'hébergement introuvable ou déjà annulée.");
            }

            $sqlAnnuler = "
                UPDATE reservation_hebergement
                SET statut = 'annulee'
                WHERE id_reservation_hebergement = ?
                AND id_utilisateur = ?
                AND statut = 'confirmee'
            ";

            $stmtAnnuler = $pdo->prepare($sqlAnnuler);
            $stmtAnnuler->execute([
                intval($reservationHebergement["id_reservation_hebergement"]),
                $idUtilisateur
            ]);

            if ($stmtAnnuler->rowCount() === 0) {
                throw new Exception("Impossible d'annuler cette réservation.");
            }

            $titreAnnulation = "Annulation confirmée - " . $reservation["nom_element"];

            $messageAnnulation =
                "Votre réservation d'hébergement a bien été annulée.\n\n" .
                "Type : Hébergement\n" .
                "ID élément : " . intval($reservation["id_element"]) . "\n" .
                "ID réservation hébergement : " . intval($reservationHebergement["id_reservation_hebergement"]) . "\n" .
                "Nom : " . $reservation["nom_element"] . "\n" .
                "Date d'arrivée annulée : " . formatDateFr($reservation["date_arrivee"]) . "\n" .
                "Date de départ annulée : " . formatDateFr($reservation["date_depart"]) . "\n" .
                "Nombre de nuits annulées : " . intval($reservation["nb_nuits"]) . "\n" .
                "Notification d'origine : #" . $idNotification . "\n\n" .
                "Les dates sont de nouveau disponibles dans la base de données.";

            $sqlNouvelleNotification = "
                INSERT INTO notification (id_utilisateur, titre, message, date_envoi, statut_lecture)
                VALUES (?, ?, ?, NOW(), 0)
            ";

            $stmtNouvelleNotification = $pdo->prepare($sqlNouvelleNotification);
            $stmtNouvelleNotification->execute([
                $idUtilisateur,
                $titreAnnulation,
                $messageAnnulation
            ]);

            $sqlMarquerLue = "
                UPDATE notification
                SET statut_lecture = 1
                WHERE $idNotificationCol = ?
                AND id_utilisateur = ?
            ";

            $stmtMarquerLue = $pdo->prepare($sqlMarquerLue);
            $stmtMarquerLue->execute([$idNotification, $idUtilisateur]);

            $pdo->commit();

            header("Location: Notifications.php?action=annulation_ok");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $messageAction = $e->getMessage();
            $typeMessageAction = "error";
        }
    }

    if ($action === "modifier_reservation_hebergement" && $idNotificationCol !== null) {
        $idNotification = intval($_POST["id_notification"] ?? 0);
        $nouvelleDateArrivee = normaliserDateSql($_POST["nouvelle_date_arrivee"] ?? "");
        $nouvelleDateDepart = normaliserDateSql($_POST["nouvelle_date_depart"] ?? "");

        try {
            $pdo->beginTransaction();

            if ($idNotification <= 0) {
                throw new Exception("Notification introuvable.");
            }

            if (verifierActionDejaFaite($pdo, $idUtilisateur, $idNotification)) {
                throw new Exception("Cette réservation a déjà été traitée.");
            }

            if (!dateSqlValide($nouvelleDateArrivee) || !dateSqlValide($nouvelleDateDepart)) {
                throw new Exception("Les nouvelles dates sont invalides.");
            }

            $nouveauNbNuits = calculerNuits($nouvelleDateArrivee, $nouvelleDateDepart);

            if ($nouveauNbNuits <= 0) {
                throw new Exception("La date de départ doit être après la date d'arrivée.");
            }

            if ($nouvelleDateArrivee < date("Y-m-d")) {
                throw new Exception("La nouvelle date d'arrivée ne peut pas être dans le passé.");
            }

            $sqlNotificationOrigine = "
                SELECT *
                FROM notification
                WHERE $idNotificationCol = ?
                AND id_utilisateur = ?
                FOR UPDATE
            ";

            $stmtNotificationOrigine = $pdo->prepare($sqlNotificationOrigine);
            $stmtNotificationOrigine->execute([$idNotification, $idUtilisateur]);
            $notificationOrigine = $stmtNotificationOrigine->fetch();

            if (!$notificationOrigine) {
                throw new Exception("Notification introuvable.");
            }

            $reservation = extraireReservationDepuisNotification($notificationOrigine);

            if (!$reservation || $reservation["type_interne"] !== "hebergement") {
                throw new Exception("Cette notification ne correspond pas à une réservation d'hébergement modifiable.");
            }

            $reservationHebergement = trouverReservationHebergement($pdo, $reservation, $idUtilisateur);

            if (!$reservationHebergement) {
                throw new Exception("Réservation d'hébergement introuvable ou déjà annulée.");
            }

            $idReservationHebergement = intval($reservationHebergement["id_reservation_hebergement"]);
            $idHebergement = intval($reservationHebergement["id_hebergement"]);

            if (!verifierDatesHebergementDisponibles($pdo, $idHebergement, $nouvelleDateArrivee, $nouvelleDateDepart, $idReservationHebergement)) {
                throw new Exception("Ces nouvelles dates sont déjà réservées pour cet hébergement.");
            }

            $ancienneArrivee = $reservationHebergement["date_arrivee"];
            $ancienneDepart = $reservationHebergement["date_depart"];
            $ancienNbNuits = calculerNuits($ancienneArrivee, $ancienneDepart);

            $sqlUpdateReservation = "
                UPDATE reservation_hebergement
                SET date_arrivee = ?, date_depart = ?
                WHERE id_reservation_hebergement = ?
                AND id_utilisateur = ?
                AND statut = 'confirmee'
            ";

            $stmtUpdateReservation = $pdo->prepare($sqlUpdateReservation);
            $stmtUpdateReservation->execute([
                $nouvelleDateArrivee,
                $nouvelleDateDepart,
                $idReservationHebergement,
                $idUtilisateur
            ]);

            $titreModification = "Réservation modifiée - " . $reservation["nom_element"];

            $messageModification =
                "Votre réservation d'hébergement a bien été modifiée.\n\n" .
                "Type : Hébergement\n" .
                "ID élément : " . intval($reservation["id_element"]) . "\n" .
                "ID réservation hébergement : " . $idReservationHebergement . "\n" .
                "Nom : " . $reservation["nom_element"] . "\n\n" .
                "Anciennes dates :\n" .
                "Date d'arrivée : " . formatDateFr($ancienneArrivee) . "\n" .
                "Date de départ : " . formatDateFr($ancienneDepart) . "\n" .
                "Nombre de nuits : " . $ancienNbNuits . "\n\n" .
                "Nouvelles dates :\n" .
                "Date d'arrivée : " . formatDateFr($nouvelleDateArrivee) . "\n" .
                "Date de départ : " . formatDateFr($nouvelleDateDepart) . "\n" .
                "Nombre de nuits : " . $nouveauNbNuits . "\n" .
                "Notification d'origine : #" . $idNotification . "\n\n" .
                "Les nouvelles dates sont maintenant enregistrées dans la base de données.";

            $sqlNouvelleNotification = "
                INSERT INTO notification (id_utilisateur, titre, message, date_envoi, statut_lecture)
                VALUES (?, ?, ?, NOW(), 0)
            ";

            $stmtNouvelleNotification = $pdo->prepare($sqlNouvelleNotification);
            $stmtNouvelleNotification->execute([
                $idUtilisateur,
                $titreModification,
                $messageModification
            ]);

            $sqlMarquerLue = "
                UPDATE notification
                SET statut_lecture = 1
                WHERE $idNotificationCol = ?
                AND id_utilisateur = ?
            ";

            $stmtMarquerLue = $pdo->prepare($sqlMarquerLue);
            $stmtMarquerLue->execute([$idNotification, $idUtilisateur]);

            $pdo->commit();

            header("Location: Notifications.php?action=modification_ok");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $messageAction = $e->getMessage();
            $typeMessageAction = "error";
        }
    }
}

if (isset($_GET["action"]) && $_GET["action"] === "annulation_ok") {
    $messageAction = "Réservation d'hébergement annulée. Une notification de confirmation a été créée.";
    $typeMessageAction = "success";
}

if (isset($_GET["action"]) && $_GET["action"] === "transport_annulation_ok") {
    $messageAction = "Réservation transport annulée. Les places disponibles ont été mises à jour.";
    $typeMessageAction = "success";
}

if (isset($_GET["action"]) && $_GET["action"] === "modification_ok") {
    $messageAction = "Dates de réservation modifiées. Une nouvelle notification a été créée.";
    $typeMessageAction = "success";
}

$nombreElementsPanier = 0;

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

$notifications = [];

try {
    $sql = "
        SELECT *
        FROM notification
        WHERE id_utilisateur = ?
        ORDER BY date_envoi DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idUtilisateur]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}

$notificationsActionnees = [];

foreach ($notifications as $notification) {
    $idOrigine = extraireIdNotificationOrigineAction($notification["message"] ?? "");

    if ($idOrigine !== null) {
        $notificationsActionnees[$idOrigine] = true;
    }
}

$nombreNotifications = 0;
$nombreNotificationsLues = 0;
$derniereNotification = null;

foreach ($notifications as $notification) {
    if (intval($notification["statut_lecture"] ?? 0) === 0) {
        $nombreNotifications++;
    } else {
        $nombreNotificationsLues++;
    }

    if ($derniereNotification === null) {
        $derniereNotification = $notification["date_envoi"] ?? null;
    }
}

$totalNotifications = count($notifications);
$notificationsPopup = array_slice($notifications, 0, 3);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>VoyageVista - Notifications</title>

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
    .dark-btn,
    .small-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      font-weight: 800;
      transition: 0.2s;
      white-space: nowrap;
      text-decoration: none;
    }

    .primary-btn,
    .secondary-btn,
    .dark-btn {
      min-height: 42px;
      padding: 11px 18px;
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

    .small-btn {
      border: none;
      padding: 9px 13px;
      font-size: 13px;
    }

    .small-btn.info {
      background: #ecfeff;
      color: #0e7490;
    }

    .small-btn.remove {
      background: #fff7f7;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    .small-btn.cancel {
      background: #fff7ed;
      color: #ea580c;
      border: 1px solid #fed7aa;
    }

    .small-btn.modify {
      background: #eff6ff;
      color: #2563eb;
      border: 1px solid #bfdbfe;
    }

    .small-btn.disabled {
      background: #e5e7eb;
      color: #64748b;
      border: 1px solid #cbd5e1;
      cursor: not-allowed;
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

    .icon-btn:hover,
    .icon-btn.active {
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
        url("https://images.unsplash.com/photo-1492724441997-5dc865305da7?auto=format&fit=crop&w=1600&q=80");
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
      background-image: url("https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1100&q=80");
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

    .status-box {
      margin-bottom: 18px;
      border-radius: 18px;
      padding: 14px 16px;
      font-weight: 800;
      line-height: 1.5;
    }

    .status-box.success {
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0;
    }

    .status-box.error {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
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
      grid-template-columns: 1.4fr 1fr auto;
      gap: 14px;
      align-items: end;
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

    .notification-page-header {
      margin-top: 42px;
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 20px;
    }

    .notification-page-header p {
      color: #0e7490;
      font-weight: 800;
      margin-bottom: 8px;
    }

    .notification-page-header h2 {
      font-size: 32px;
      letter-spacing: -0.02em;
    }

    .notification-list {
      margin-top: 24px;
      display: grid;
      gap: 16px;
    }

    .notice-card {
      display: grid;
      grid-template-columns: 54px 1fr auto;
      gap: 16px;
      align-items: start;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 24px;
      padding: 18px;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
      transition: 0.2s;
    }

    .notice-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.10);
    }

    .notice-card.unread {
      border-color: #67e8f9;
      background: linear-gradient(90deg, #ecfeff, white 42%);
    }

    .notice-card.actioned {
      border-color: #cbd5e1;
      background: #f8fafc;
    }

    .notice-icon {
      width: 46px;
      height: 46px;
      border-radius: 18px;
      background: #ecfeff;
      color: #0e7490;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }

    .notice-content h3 {
      font-size: 20px;
      margin-bottom: 8px;
    }

    .notice-content p {
      color: #475569;
      line-height: 1.6;
      margin-bottom: 12px;
      white-space: normal;
    }

    .notice-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .notice-meta span {
      border-radius: 999px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #64748b;
      padding: 7px 10px;
      font-size: 13px;
      font-weight: 800;
    }

    .notice-meta .unread-label {
      background: #ecfeff;
      color: #0e7490;
      border-color: #bae6fd;
    }

    .notice-meta .actioned-label {
      background: #e5e7eb;
      color: #475569;
      border-color: #cbd5e1;
    }

    .notice-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
      min-width: 280px;
    }

    .date-edit-form {
      grid-column: 1 / -1;
      display: none;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 20px;
      padding: 16px;
      margin-top: 4px;
    }

    .date-edit-form.visible {
      display: block;
    }

    .date-edit-grid {
      display: grid;
      grid-template-columns: 1fr 1fr auto;
      gap: 12px;
      align-items: end;
    }

    .empty-result,
    .empty-notification {
      margin-top: 24px;
      background: white;
      border: 1px dashed #cbd5e1;
      border-radius: 24px;
      padding: 36px;
      text-align: center;
      color: #64748b;
    }

    .empty-result {
      display: none;
    }

    .empty-result strong,
    .empty-notification strong {
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

      .page-hero-container {
        grid-template-columns: 1fr;
      }

      .filters-grid,
      .date-edit-grid {
        grid-template-columns: 1fr 1fr;
      }

      .notice-card {
        grid-template-columns: 54px 1fr;
      }

      .notice-actions {
        grid-column: 1 / -1;
        justify-content: flex-start;
      }
    }

    @media (max-width: 640px) {
      .navbar,
      .notification-page-header,
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
      .hero-stats,
      .date-edit-grid {
        grid-template-columns: 1fr;
      }

      .notice-card {
        grid-template-columns: 1fr;
      }

      .notice-actions,
      .back-top-zone {
        justify-content: stretch;
      }

      .notice-actions button,
      .notice-actions form,
      .notice-actions form button,
      .filters-grid button,
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
        <button onclick="window.location.href='Hebergements.php'">Hébergements</button>
        <button onclick="window.location.href='Activites.php'">Activités</button>
        <button onclick="window.location.href='Itineraires.php'">Itinéraires</button>
      </div>

      <div class="nav-actions">
        <div class="notification-wrapper">
          <button class="icon-btn active" onclick="window.location.href='Notifications.php'" aria-label="Notifications">
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

        <?php if ($utilisateur): ?>
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
          <div class="breadcrumb">VoyageVista &gt; Notifications</div>

          <h1>Centre de notifications</h1>

          <p>
            Consultez vos notifications, annulez une réservation de transport ou gérez vos réservations d’hébergement.
          </p>

          <div class="hero-stats">
            <div class="hero-stat">
              <strong><?= h($totalNotifications) ?></strong>
              <span>notification(s) au total</span>
            </div>

            <div class="hero-stat">
              <strong><?= h($nombreNotifications) ?></strong>
              <span>notification(s) non lue(s)</span>
            </div>

            <div class="hero-stat">
              <strong><?= $derniereNotification ? h(formatDateNotification($derniereNotification)) : "—" ?></strong>
              <span>dernière notification</span>
            </div>
          </div>
        </div>

        <div class="hero-panel">
          <div class="hero-panel-inner">
            <div class="hero-panel-image"></div>

            <div class="hero-panel-body">
              <h2>Annulation transport</h2>
              <p>
                L’annulation passe la réservation transport en statut annulée et rend les places disponibles.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="main-container">
      <section class="search-card">
        <?php if ($messageAction !== ""): ?>
          <div class="status-box <?= h($typeMessageAction) ?>">
            <?= h($messageAction) ?>
          </div>
        <?php endif; ?>

        <div class="search-title-line">
          <h2>Rechercher une notification</h2>

          <?php if ($nombreNotifications > 0): ?>
            <form method="POST">
              <input type="hidden" name="action" value="marquer_toutes_lues">
              <button class="secondary-btn" type="submit">Tout marquer comme lu</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="filters-grid">
          <div class="field">
            <label for="notificationSearch">Mot-clé</label>
            <input
              id="notificationSearch"
              type="text"
              placeholder="Ex : réservation, transport, annulation..."
              oninput="filtrerNotifications()"
            >
          </div>

          <div class="field">
            <label for="notificationStatus">Statut</label>
            <select id="notificationStatus" onchange="filtrerNotifications()">
              <option value="">Toutes</option>
              <option value="non-lue">Non lues</option>
              <option value="lue">Lues</option>
              <option value="hebergement">Hébergements actifs</option>
              <option value="transport">Transports actifs</option>
              <option value="actionnee">Déjà traitées</option>
              <option value="annulation">Annulations</option>
            </select>
          </div>

          <button class="primary-btn" type="button" onclick="resetRecherche()">Réinitialiser</button>
        </div>
      </section>

      <div class="notification-page-header">
        <div>
          <p>Notifications du compte</p>
          <h2><span id="nombreResultats"><?= h($totalNotifications) ?></span> résultat(s)</h2>
        </div>

        <button class="secondary-btn" onclick="window.location.href='Acceuil.php'">
          Retour accueil
        </button>
      </div>

      <?php if (count($notifications) === 0): ?>
        <div class="empty-notification">
          <strong>Aucune notification</strong>
          <span>Vous n’avez pas encore reçu de notification.</span>
        </div>
      <?php else: ?>
        <section id="notificationList" class="notification-list">
          <?php foreach ($notifications as $notification): ?>
            <?php
              $idNotification = $idNotificationCol !== null ? intval($notification[$idNotificationCol] ?? 0) : 0;
              $estNonLue = intval($notification["statut_lecture"] ?? 0) === 0;
              $titre = $notification["titre"] ?? "Notification";
              $message = $notification["message"] ?? "";
              $date = $notification["date_envoi"] ?? "";

              $reservation = extraireReservationDepuisNotification($notification);
              $estReservationHebergement = $reservation !== null && $reservation["type_interne"] === "hebergement";
              $estReservationTransport = $reservation !== null && $reservation["type_interne"] === "transport";
              $estActionnee = $idNotification > 0 && isset($notificationsActionnees[$idNotification]);
              $estAnnulation = stripos($titre, "Annulation confirmée") !== false;

              if ($estActionnee) {
                  $statutTexte = "actionnee";
              } elseif ($estReservationTransport) {
                  $statutTexte = "transport";
              } elseif ($estReservationHebergement) {
                  $statutTexte = "hebergement";
              } elseif ($estAnnulation) {
                  $statutTexte = "annulation";
              } else {
                  $statutTexte = $estNonLue ? "non-lue" : "lue";
              }

              $texteRecherche = strtolower(str_replace(["\n", "\r"], " ", $titre . " " . $message . " " . $statutTexte));
            ?>

            <article
              class="notice-card <?= $estNonLue ? "unread" : "read" ?> <?= $estActionnee ? "actioned" : "" ?>"
              data-status="<?= h($statutTexte) ?>"
              data-search="<?= h($texteRecherche) ?>"
            >
              <div class="notice-icon">
                <?php if ($estActionnee): ?>
                  ✅
                <?php elseif ($estAnnulation): ?>
                  ↩️
                <?php elseif ($estReservationTransport): ?>
                  🚆
                <?php elseif ($estReservationHebergement): ?>
                  🏨
                <?php else: ?>
                  <?= $estNonLue ? "🔔" : "📩" ?>
                <?php endif; ?>
              </div>

              <div class="notice-content">
                <h3><?= h($titre) ?></h3>
                <p><?= nl2br(h($message)) ?></p>

                <div class="notice-meta">
                  <span><?= h(formatDateNotification($date)) ?></span>

                  <?php if ($estActionnee): ?>
                    <span class="actioned-label">Action déjà effectuée</span>
                  <?php elseif ($estNonLue): ?>
                    <span class="unread-label">Non lue</span>
                  <?php else: ?>
                    <span>Lue</span>
                  <?php endif; ?>

                  <?php if ($estReservationTransport && !$estActionnee): ?>
                    <span>Transport annulable</span>
                  <?php endif; ?>

                  <?php if ($estReservationHebergement && !$estActionnee): ?>
                    <span>Hébergement modifiable</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="notice-actions">
                <?php if ($estReservationTransport && $idNotification > 0): ?>
                  <?php if ($estActionnee): ?>
                    <button class="small-btn disabled" type="button" disabled>
                      Action effectuée
                    </button>
                  <?php else: ?>
                    <form method="POST">
                      <input type="hidden" name="action" value="annuler_reservation_transport">
                      <input type="hidden" name="id_notification" value="<?= h($idNotification) ?>">
                      <button class="small-btn cancel" type="submit">
                        Annuler le transport
                      </button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>

                <?php if ($estReservationHebergement && $idNotification > 0): ?>
                  <?php if ($estActionnee): ?>
                    <button class="small-btn disabled" type="button" disabled>
                      Action effectuée
                    </button>
                  <?php else: ?>
                    <form method="POST">
                      <input type="hidden" name="action" value="annuler_reservation_hebergement">
                      <input type="hidden" name="id_notification" value="<?= h($idNotification) ?>">
                      <button class="small-btn cancel" type="submit">
                        Annuler
                      </button>
                    </form>

                    <button
                      class="small-btn modify"
                      type="button"
                      onclick="toggleModifierDates('form-dates-<?= h($idNotification) ?>')"
                    >
                      Modifier les dates
                    </button>
                  <?php endif; ?>
                <?php endif; ?>

                <?php if ($estNonLue && $idNotificationCol !== null && $idNotification > 0): ?>
                  <form method="POST">
                    <input type="hidden" name="action" value="marquer_lue">
                    <input type="hidden" name="id_notification" value="<?= h($idNotification) ?>">
                    <button class="small-btn info" type="submit">Marquer lue</button>
                  </form>
                <?php endif; ?>

                <?php if ($idNotificationCol !== null && $idNotification > 0): ?>
                  <form method="POST">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id_notification" value="<?= h($idNotification) ?>">
                    <button class="small-btn remove" type="submit">Supprimer</button>
                  </form>
                <?php endif; ?>
              </div>

              <?php if ($estReservationHebergement && !$estActionnee && $idNotification > 0): ?>
                <form id="form-dates-<?= h($idNotification) ?>" class="date-edit-form" method="POST">
                  <input type="hidden" name="action" value="modifier_reservation_hebergement">
                  <input type="hidden" name="id_notification" value="<?= h($idNotification) ?>">

                  <div class="date-edit-grid">
                    <div class="field">
                      <label for="arrivee-<?= h($idNotification) ?>">Nouvelle arrivée</label>
                      <input
                        id="arrivee-<?= h($idNotification) ?>"
                        type="date"
                        name="nouvelle_date_arrivee"
                        value="<?= h($reservation["date_arrivee"] ?? "") ?>"
                        required
                      >
                    </div>

                    <div class="field">
                      <label for="depart-<?= h($idNotification) ?>">Nouveau départ</label>
                      <input
                        id="depart-<?= h($idNotification) ?>"
                        type="date"
                        name="nouvelle_date_depart"
                        value="<?= h($reservation["date_depart"] ?? "") ?>"
                        required
                      >
                    </div>

                    <button class="small-btn modify" type="submit">
                      Valider les nouvelles dates
                    </button>
                  </div>
                </form>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </section>

        <div id="emptyResult" class="empty-result">
          <strong>Aucune notification trouvée</strong>
          <span>Modifiez votre recherche ou le filtre de statut.</span>
        </div>
      <?php endif; ?>

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
    function normaliserTexte(texte) {
      return texte
        .toString()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
    }

    function filtrerNotifications() {
      const recherche = normaliserTexte(document.getElementById("notificationSearch").value.trim());
      const statut = document.getElementById("notificationStatus").value;
      const cartes = Array.from(document.querySelectorAll(".notice-card"));
      const emptyResult = document.getElementById("emptyResult");
      const nombreResultats = document.getElementById("nombreResultats");

      let totalVisible = 0;

      cartes.forEach((carte) => {
        const texte = normaliserTexte(carte.dataset.search || "");
        const statutCarte = carte.dataset.status || "";

        const correspondRecherche = recherche === "" || texte.includes(recherche);
        const correspondStatut = statut === "" || statutCarte === statut;

        if (correspondRecherche && correspondStatut) {
          carte.style.display = "grid";
          totalVisible++;
        } else {
          carte.style.display = "none";
        }
      });

      nombreResultats.textContent = totalVisible;

      if (emptyResult) {
        emptyResult.style.display = totalVisible === 0 ? "block" : "none";
      }
    }

    function resetRecherche() {
      document.getElementById("notificationSearch").value = "";
      document.getElementById("notificationStatus").value = "";
      filtrerNotifications();
    }

    function toggleModifierDates(idFormulaire) {
      const formulaire = document.getElementById(idFormulaire);

      if (!formulaire) {
        return;
      }

      formulaire.classList.toggle("visible");
    }

    function retourHautPage() {
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    }

    document.addEventListener("DOMContentLoaded", function () {
      const aujourdHui = new Date().toISOString().split("T")[0];
      const inputsDate = document.querySelectorAll('input[type="date"]');

      inputsDate.forEach((input) => {
        input.min = aujourdHui;
      });
    });
  </script>
</body>
</html>