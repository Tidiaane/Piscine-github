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

function typeLisible($type) {
    if ($type === "transport") return "Transport";
    if ($type === "hebergement") return "Hébergement";
    if ($type === "activite") return "Activité";
    return "Élément";
}

function iconeType($type) {
    if ($type === "transport") return "🚆";
    if ($type === "hebergement") return "🏨";
    if ($type === "activite") return "🎯";
    return "🧭";
}

function getPrixHebergement($hebergement) {
    if (isset($hebergement["prix"])) {
        return floatval($hebergement["prix"]);
    }

    if (isset($hebergement["prix_nuit"])) {
        return floatval($hebergement["prix_nuit"]);
    }

    return 0;
}

function getElementCatalogue($pdo, $type, $idElement) {
    if ($type === "transport") {
        $sql = "SELECT * FROM transport WHERE id_transport = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idElement]);
        $transport = $stmt->fetch();

        if (!$transport) {
            return null;
        }

        $nom = trim(($transport["compagnie"] ?? "Transport") . " - " . ($transport["ville_depart"] ?? "") . " vers " . ($transport["ville_arrivee"] ?? ""));
        $details = trim(($transport["ville_depart"] ?? "") . " → " . ($transport["ville_arrivee"] ?? "") . " · " . formatDateFr($transport["date_depart"] ?? "") . " à " . formatHeure($transport["heure_depart"] ?? ""));

        return [
            "id" => intval($transport["id_transport"]),
            "nom" => $nom,
            "prix" => floatval($transport["prix"] ?? 0),
            "details" => $details
        ];
    }

    if ($type === "hebergement") {
        $sql = "SELECT * FROM hebergement WHERE id_hebergement = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idElement]);
        $hebergement = $stmt->fetch();

        if (!$hebergement) {
            return null;
        }

        $nom = $hebergement["nom"] ?? "Hébergement";
        $destination = $hebergement["destination"] ?? ($hebergement["adresse"] ?? "Destination non précisée");
        $details = trim($destination . " · " . ucfirst($hebergement["type"] ?? "Hébergement") . " · " . intval($hebergement["capacite"] ?? 0) . " pers.");

        return [
            "id" => intval($hebergement["id_hebergement"]),
            "nom" => $nom,
            "prix" => getPrixHebergement($hebergement),
            "details" => $details
        ];
    }

    if ($type === "activite") {
        $sql = "SELECT * FROM activite WHERE id_activite = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idElement]);
        $activite = $stmt->fetch();

        if (!$activite) {
            return null;
        }

        $nom = $activite["nom"] ?? substr($activite["description"] ?? "Activité", 0, 80);
        $details = trim(($activite["destination"] ?? "Destination non précisée") . " · " . ucfirst($activite["categorie"] ?? "Activité") . " · " . floatval($activite["duree"] ?? 0) . " h");

        return [
            "id" => intval($activite["id_activite"]),
            "nom" => $nom,
            "prix" => floatval($activite["prix"] ?? 0),
            "details" => $details
        ];
    }

    return null;
}

function chargerCatalogue($pdo) {
    $catalogue = [
        "transport" => [],
        "hebergement" => [],
        "activite" => []
    ];

    try {
        $stmt = $pdo->query("SELECT * FROM transport ORDER BY date_depart ASC, prix ASC");
        foreach ($stmt->fetchAll() as $transport) {
            $catalogue["transport"][] = [
                "id" => intval($transport["id_transport"]),
                "nom" => trim(($transport["compagnie"] ?? "Transport") . " - " . ($transport["ville_depart"] ?? "") . " vers " . ($transport["ville_arrivee"] ?? "")),
                "prix" => floatval($transport["prix"] ?? 0),
                "details" => trim(($transport["ville_depart"] ?? "") . " → " . ($transport["ville_arrivee"] ?? "") . " · " . formatDateFr($transport["date_depart"] ?? "") . " à " . formatHeure($transport["heure_depart"] ?? ""))
            ];
        }
    } catch (PDOException $e) {
        $catalogue["transport"] = [];
    }

    try {
        $stmt = $pdo->query("SELECT * FROM hebergement ORDER BY nom ASC");
        foreach ($stmt->fetchAll() as $hebergement) {
            $destination = $hebergement["destination"] ?? ($hebergement["adresse"] ?? "Destination non précisée");

            $catalogue["hebergement"][] = [
                "id" => intval($hebergement["id_hebergement"]),
                "nom" => $hebergement["nom"] ?? "Hébergement",
                "prix" => getPrixHebergement($hebergement),
                "details" => trim($destination . " · " . ucfirst($hebergement["type"] ?? "Hébergement") . " · " . intval($hebergement["capacite"] ?? 0) . " pers.")
            ];
        }
    } catch (PDOException $e) {
        $catalogue["hebergement"] = [];
    }

    try {
        $stmt = $pdo->query("SELECT * FROM activite ORDER BY prix ASC");
        foreach ($stmt->fetchAll() as $activite) {
            $catalogue["activite"][] = [
                "id" => intval($activite["id_activite"]),
                "nom" => $activite["nom"] ?? substr($activite["description"] ?? "Activité", 0, 80),
                "prix" => floatval($activite["prix"] ?? 0),
                "details" => trim(($activite["destination"] ?? "Destination non précisée") . " · " . ucfirst($activite["categorie"] ?? "Activité") . " · " . floatval($activite["duree"] ?? 0) . " h")
            ];
        }
    } catch (PDOException $e) {
        $catalogue["activite"] = [];
    }

    return $catalogue;
}

function chargerItineraires($pdo, $idUtilisateur) {
    $sql = "
        SELECT *
        FROM itineraire
        WHERE id_utilisateur = ?
        ORDER BY date_creation DESC, id_itineraire DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idUtilisateur]);
    $itineraires = $stmt->fetchAll();

    $ids = array_map(function ($itineraire) {
        return intval($itineraire["id_itineraire"]);
    }, $itineraires);

    $elementsParItineraire = [];

    foreach ($ids as $id) {
        $elementsParItineraire[$id] = [];
    }

    if (count($ids) > 0) {
        $placeholders = implode(",", array_fill(0, count($ids), "?"));

        $sqlElements = "
            SELECT *
            FROM itineraire_element
            WHERE id_itineraire IN ($placeholders)
            ORDER BY ordre ASC, id_itineraire_element ASC
        ";

        $stmtElements = $pdo->prepare($sqlElements);
        $stmtElements->execute($ids);

        foreach ($stmtElements->fetchAll() as $element) {
            $idItineraire = intval($element["id_itineraire"]);
            $elementsParItineraire[$idItineraire][] = $element;
        }
    }

    return [$itineraires, $elementsParItineraire];
}

function getOrCreatePanier($pdo, $idUtilisateur) {
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

    if ($panier) {
        return intval($panier["id_panier"]);
    }

    $sqlCreatePanier = "
        INSERT INTO panier (id_utilisateur, date_creation)
        VALUES (?, NOW())
    ";

    $stmtCreatePanier = $pdo->prepare($sqlCreatePanier);
    $stmtCreatePanier->execute([$idUtilisateur]);

    return intval($pdo->lastInsertId());
}

function ajouterLignePanierDepuisItineraire($pdo, $idPanier, $element) {
    $typeElement = $element["type_element"] ?? "";
    $idElement = intval($element["id_element"] ?? 0);
    $nomElement = trim($element["nom_element"] ?? "");
    $prixUnitaire = floatval($element["prix_unitaire"] ?? 0);
    $quantite = intval($element["quantite"] ?? 1);

    if ($quantite < 1) {
        $quantite = 1;
    }

    if (!in_array($typeElement, ["transport", "hebergement", "activite"], true)) {
        throw new Exception("Un élément du séjour possède un type invalide.");
    }

    if ($idElement <= 0 || $nomElement === "") {
        throw new Exception("Un élément du séjour est incomplet.");
    }

    if ($typeElement === "hebergement") {
        $nbNuits = $quantite;

        $sqlExistant = "
            SELECT id_ligne, quantite, nb_nuits
            FROM ligne_panier
            WHERE id_panier = ?
            AND type_element = 'hebergement'
            AND id_element = ?
            AND date_arrivee IS NULL
            AND date_depart IS NULL
            LIMIT 1
        ";

        $stmtExistant = $pdo->prepare($sqlExistant);
        $stmtExistant->execute([$idPanier, $idElement]);
        $ligneExistante = $stmtExistant->fetch();

        if ($ligneExistante) {
            $nouvelleQuantite = intval($ligneExistante["quantite"] ?? 0) + $quantite;
            $nouveauNbNuits = intval($ligneExistante["nb_nuits"] ?? 0) + $nbNuits;

            if ($nouveauNbNuits < 1) {
                $nouveauNbNuits = $nouvelleQuantite;
            }

            $sqlUpdate = "
                UPDATE ligne_panier
                SET nom_element = ?, prix_unitaire = ?, quantite = ?, nb_nuits = ?
                WHERE id_ligne = ?
            ";

            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                $nomElement,
                $prixUnitaire,
                $nouvelleQuantite,
                $nouveauNbNuits,
                intval($ligneExistante["id_ligne"])
            ]);
        } else {
            $sqlInsert = "
                INSERT INTO ligne_panier
                (
                    id_panier,
                    type_element,
                    id_element,
                    nom_element,
                    prix_unitaire,
                    quantite,
                    date_arrivee,
                    date_depart,
                    nb_nuits
                )
                VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, ?)
            ";

            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                $idPanier,
                $typeElement,
                $idElement,
                $nomElement,
                $prixUnitaire,
                $quantite,
                $nbNuits
            ]);
        }

        return;
    }

    $sqlExistant = "
        SELECT id_ligne, quantite
        FROM ligne_panier
        WHERE id_panier = ?
        AND type_element = ?
        AND id_element = ?
        LIMIT 1
    ";

    $stmtExistant = $pdo->prepare($sqlExistant);
    $stmtExistant->execute([$idPanier, $typeElement, $idElement]);
    $ligneExistante = $stmtExistant->fetch();

    if ($ligneExistante) {
        $nouvelleQuantite = intval($ligneExistante["quantite"] ?? 0) + $quantite;

        $sqlUpdate = "
            UPDATE ligne_panier
            SET nom_element = ?, prix_unitaire = ?, quantite = ?
            WHERE id_ligne = ?
        ";

        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([
            $nomElement,
            $prixUnitaire,
            $nouvelleQuantite,
            intval($ligneExistante["id_ligne"])
        ]);
    } else {
        $sqlInsert = "
            INSERT INTO ligne_panier
            (
                id_panier,
                type_element,
                id_element,
                nom_element,
                prix_unitaire,
                quantite,
                date_arrivee,
                date_depart,
                nb_nuits
            )
            VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL)
        ";

        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            $idPanier,
            $typeElement,
            $idElement,
            $nomElement,
            $prixUnitaire,
            $quantite
        ]);
    }
}

if (!isset($_SESSION["user_id"])) {
    header("Location: Connexion.php?erreur=connexion_requise");
    exit;
}

$idUtilisateur = intval($_SESSION["user_id"]);
$utilisateur = null;
$initiales = "";
$messageAction = "";
$typeMessageAction = "";

try {
    $sqlUser = "SELECT * FROM utilisateur WHERE id_utilisateur = ?";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([$idUtilisateur]);
    $utilisateur = $stmtUser->fetch();

    if (!$utilisateur) {
        session_destroy();
        header("Location: Connexion.php?erreur=connexion_requise");
        exit;
    }

    $initiales = getInitiales(
        $utilisateur["prenom"] ?? "",
        $utilisateur["nom"] ?? "",
        $utilisateur["email"] ?? ""
    );
} catch (PDOException $e) {
    session_destroy();
    header("Location: Connexion.php?erreur=connexion_requise");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    try {
        if ($action === "creer_itineraire") {
            $nom = trim($_POST["nom"] ?? "");
            $description = trim($_POST["description"] ?? "");

            if ($nom === "" || strlen($nom) < 3) {
                throw new Exception("Le nom du séjour doit contenir au moins 3 caractères.");
            }

            $sql = "
                INSERT INTO itineraire (id_utilisateur, nom, description, date_creation, statut)
                VALUES (?, ?, ?, NOW(), 'actif')
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idUtilisateur, $nom, $description]);

            $messageAction = "Séjour créé correctement.";
            $typeMessageAction = "success";
        }

        if ($action === "ajouter_element") {
            $idItineraire = intval($_POST["id_itineraire"] ?? 0);
            $typeElement = trim($_POST["type_element"] ?? "");
            $idElement = intval($_POST["id_element"] ?? 0);
            $quantite = intval($_POST["quantite"] ?? 1);

            if ($quantite < 1) {
                $quantite = 1;
            }

            if (!in_array($typeElement, ["transport", "hebergement", "activite"], true)) {
                throw new Exception("Type d’élément invalide.");
            }

            $sqlCheck = "
                SELECT id_itineraire
                FROM itineraire
                WHERE id_itineraire = ?
                AND id_utilisateur = ?
                AND statut = 'actif'
            ";

            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$idItineraire, $idUtilisateur]);

            if (!$stmtCheck->fetch()) {
                throw new Exception("Séjour introuvable.");
            }

            $element = getElementCatalogue($pdo, $typeElement, $idElement);

            if (!$element) {
                throw new Exception("Élément introuvable dans la base de données.");
            }

            $sqlExistant = "
                SELECT id_itineraire_element, quantite
                FROM itineraire_element
                WHERE id_itineraire = ?
                AND type_element = ?
                AND id_element = ?
                LIMIT 1
            ";

            $stmtExistant = $pdo->prepare($sqlExistant);
            $stmtExistant->execute([$idItineraire, $typeElement, $idElement]);
            $existant = $stmtExistant->fetch();

            if ($existant) {
                $nouvelleQuantite = intval($existant["quantite"] ?? 1) + $quantite;

                $sqlUpdate = "
                    UPDATE itineraire_element
                    SET quantite = ?, nom_element = ?, prix_unitaire = ?, details = ?
                    WHERE id_itineraire_element = ?
                ";

                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    $nouvelleQuantite,
                    $element["nom"],
                    $element["prix"],
                    $element["details"],
                    intval($existant["id_itineraire_element"])
                ]);
            } else {
                $sqlOrdre = "
                    SELECT COALESCE(MAX(ordre), 0) + 1 AS prochain_ordre
                    FROM itineraire_element
                    WHERE id_itineraire = ?
                ";

                $stmtOrdre = $pdo->prepare($sqlOrdre);
                $stmtOrdre->execute([$idItineraire]);
                $ordre = intval(($stmtOrdre->fetch()["prochain_ordre"] ?? 1));

                $sqlInsert = "
                    INSERT INTO itineraire_element
                    (
                        id_itineraire,
                        type_element,
                        id_element,
                        nom_element,
                        details,
                        prix_unitaire,
                        quantite,
                        ordre,
                        date_ajout
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ";

                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    $idItineraire,
                    $typeElement,
                    $idElement,
                    $element["nom"],
                    $element["details"],
                    $element["prix"],
                    $quantite,
                    $ordre
                ]);
            }

            $messageAction = typeLisible($typeElement) . " ajouté correctement au séjour.";
            $typeMessageAction = "success";
        }

        if ($action === "retirer_element") {
            $idElementItineraire = intval($_POST["id_itineraire_element"] ?? 0);

            $sqlDelete = "
                DELETE ie
                FROM itineraire_element ie
                JOIN itineraire i ON ie.id_itineraire = i.id_itineraire
                WHERE ie.id_itineraire_element = ?
                AND i.id_utilisateur = ?
            ";

            $stmtDelete = $pdo->prepare($sqlDelete);
            $stmtDelete->execute([$idElementItineraire, $idUtilisateur]);

            $messageAction = "Élément retiré du séjour.";
            $typeMessageAction = "success";
        }

        if ($action === "supprimer_itineraire") {
            $idItineraire = intval($_POST["id_itineraire"] ?? 0);

            $pdo->beginTransaction();

            $sqlDeleteElements = "
                DELETE ie
                FROM itineraire_element ie
                JOIN itineraire i ON ie.id_itineraire = i.id_itineraire
                WHERE ie.id_itineraire = ?
                AND i.id_utilisateur = ?
            ";

            $stmtDeleteElements = $pdo->prepare($sqlDeleteElements);
            $stmtDeleteElements->execute([$idItineraire, $idUtilisateur]);

            $sqlDeleteItineraire = "
                DELETE FROM itineraire
                WHERE id_itineraire = ?
                AND id_utilisateur = ?
            ";

            $stmtDeleteItineraire = $pdo->prepare($sqlDeleteItineraire);
            $stmtDeleteItineraire->execute([$idItineraire, $idUtilisateur]);

            $pdo->commit();

            $messageAction = "Séjour supprimé.";
            $typeMessageAction = "success";
        }

        if ($action === "ajouter_itineraire_panier") {
            $idItineraire = intval($_POST["id_itineraire"] ?? 0);

            $pdo->beginTransaction();

            $sqlCheckItineraire = "
                SELECT id_itineraire, nom
                FROM itineraire
                WHERE id_itineraire = ?
                AND id_utilisateur = ?
                AND statut = 'actif'
                FOR UPDATE
            ";

            $stmtCheckItineraire = $pdo->prepare($sqlCheckItineraire);
            $stmtCheckItineraire->execute([$idItineraire, $idUtilisateur]);
            $itineraire = $stmtCheckItineraire->fetch();

            if (!$itineraire) {
                throw new Exception("Séjour introuvable.");
            }

            $sqlElements = "
                SELECT *
                FROM itineraire_element
                WHERE id_itineraire = ?
                ORDER BY ordre ASC, id_itineraire_element ASC
                FOR UPDATE
            ";

            $stmtElements = $pdo->prepare($sqlElements);
            $stmtElements->execute([$idItineraire]);
            $elementsAEnvoyer = $stmtElements->fetchAll();

            if (count($elementsAEnvoyer) === 0) {
                throw new Exception("Ce séjour ne contient aucun élément à ajouter au panier.");
            }

            $idPanier = getOrCreatePanier($pdo, $idUtilisateur);

            foreach ($elementsAEnvoyer as $elementAEnvoyer) {
                ajouterLignePanierDepuisItineraire($pdo, $idPanier, $elementAEnvoyer);
            }

            $sqlViderElementsItineraire = "
                DELETE FROM itineraire_element
                WHERE id_itineraire = ?
            ";

            $stmtViderElementsItineraire = $pdo->prepare($sqlViderElementsItineraire);
            $stmtViderElementsItineraire->execute([$idItineraire]);

            $sqlSupprimerItineraire = "
                DELETE FROM itineraire
                WHERE id_itineraire = ?
                AND id_utilisateur = ?
            ";

            $stmtSupprimerItineraire = $pdo->prepare($sqlSupprimerItineraire);
            $stmtSupprimerItineraire->execute([$idItineraire, $idUtilisateur]);

            $pdo->commit();

            $messageAction = "Tous les éléments du séjour `" . ($itineraire["nom"] ?? "Séjour") . "` ont été ajoutés au panier. Le séjour a été vidé de la page itinéraire.";
            $typeMessageAction = "success";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $messageAction = $e->getMessage();
        $typeMessageAction = "error";
    }
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
    $nombreElementsPanier = intval(($stmtPanier->fetch()["total"] ?? 0));
} catch (PDOException $e) {
    $nombreElementsPanier = 0;
}

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
    $nombreNotifications = intval(($stmtNotifCount->fetch()["total"] ?? 0));

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

$catalogue = chargerCatalogue($pdo);

try {
    [$itineraires, $elementsParItineraire] = chargerItineraires($pdo, $idUtilisateur);
} catch (PDOException $e) {
    $itineraires = [];
    $elementsParItineraire = [];
    $messageAction = "Les tables d’itinéraire ne sont pas encore installées. Importez sql/itineraires_patch.sql dans phpMyAdmin.";
    $typeMessageAction = "error";
}

$totalGlobal = 0;
$nombreItineraires = count($itineraires);
$nombreElementsAssocies = 0;

foreach ($itineraires as $itineraire) {
    $idItineraire = intval($itineraire["id_itineraire"]);

    foreach ($elementsParItineraire[$idItineraire] ?? [] as $element) {
        $totalGlobal += floatval($element["prix_unitaire"] ?? 0) * intval($element["quantite"] ?? 1);
        $nombreElementsAssocies++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>VoyageVista - Itinéraires</title>

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: Arial, Helvetica, sans-serif; background: #f8fafc; color: #0f172a; }
    button, input, textarea, select { font-family: inherit; }
    button { cursor: pointer; }

    header {
      position: sticky;
      top: 0;
      z-index: 20;
      background: rgba(255,255,255,0.96);
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

    .primary-btn, .secondary-btn, .danger-btn, .dark-btn, .small-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      font-weight: 800;
      transition: 0.2s;
      white-space: nowrap;
      text-decoration: none;
    }

    .primary-btn, .secondary-btn, .danger-btn, .dark-btn { min-height: 42px; padding: 11px 18px; }
    .primary-btn { border: none; background: #0e7490; color: white; box-shadow: 0 10px 18px rgba(14,116,144,0.18); }
    .primary-btn:hover { background: #155e75; transform: translateY(-1px); }
    .secondary-btn { background: white; color: #0e7490; border: 1px solid #bae6fd; }
    .secondary-btn:hover { background: #ecfeff; transform: translateY(-1px); }
    .danger-btn { border: 1px solid #fecaca; background: #fff7f7; color: #dc2626; }
    .danger-btn:hover { background: #fee2e2; transform: translateY(-1px); }
    .dark-btn { border: none; background: #0f172a; color: white; }
    .small-btn { border: none; padding: 9px 13px; font-size: 13px; }
    .small-btn.info { background: #ecfeff; color: #0e7490; }
    .small-btn.remove { background: #fff7f7; color: #dc2626; border: 1px solid #fecaca; }

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
      box-shadow: 0 22px 45px rgba(15,23,42,0.18);
      padding: 14px;
      opacity: 0;
      visibility: hidden;
      transform: translateY(8px);
      transition: 0.2s ease;
    }

    .notification-wrapper:hover .notification-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }

    .notification-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 8px 12px;
      border-bottom: 1px solid #e2e8f0;
      margin-bottom: 8px;
    }

    .notification-header strong { color: #0f172a; font-size: 16px; }
    .notification-header span { color: #0e7490; font-size: 12px; font-weight: 800; }

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

    .notification-item:hover { background: #f0fdfa; }

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

    .notification-item strong { display: block; color: #0f172a; font-size: 14px; }
    .notification-item small { display: block; color: #64748b; margin-top: 3px; line-height: 1.4; }

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
      box-shadow: 0 10px 18px rgba(14,116,144,0.18);
      transition: 0.2s;
    }

    .avatar-btn:hover { background: #155e75; transform: translateY(-1px); }

    .page-hero {
      background:
        linear-gradient(135deg, rgba(15,95,117,0.94), rgba(8,145,178,0.78), rgba(5,150,105,0.78)),
        url("https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1600&q=80");
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
      background: rgba(255,255,255,0.16);
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

    .page-hero p { max-width: 700px; color: #ecfeff; line-height: 1.7; font-size: 18px; }

    .hero-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 28px; }

    .hero-stat {
      background: rgba(255,255,255,0.14);
      border: 1px solid rgba(255,255,255,0.24);
      padding: 16px;
      border-radius: 20px;
    }

    .hero-stat strong { display: block; font-size: 22px; }
    .hero-stat span { display: block; margin-top: 4px; color: #cffafe; font-size: 13px; font-weight: 700; }

    .hero-panel {
      background: rgba(255,255,255,0.16);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 30px;
      padding: 18px;
      box-shadow: 0 25px 55px rgba(15,23,42,0.22);
    }

    .hero-panel-inner { background: white; border-radius: 24px; padding: 24px; color: #0f172a; }
    .hero-panel-inner h2 { font-size: 25px; margin-bottom: 8px; }
    .hero-panel-inner p { color: #64748b; line-height: 1.6; }

    .main-container { max-width: 1240px; margin: auto; padding: 0 24px 64px; }

    .workspace {
      margin-top: -38px;
      position: relative;
      z-index: 5;
      display: grid;
      grid-template-columns: 400px 1fr;
      gap: 24px;
      align-items: start;
    }

    .panel {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 30px;
      box-shadow: 0 18px 40px rgba(15,23,42,0.10);
      overflow: hidden;
    }

    .panel-header { padding: 24px; border-bottom: 1px solid #e2e8f0; }
    .panel-header p { color: #0e7490; font-weight: 900; margin-bottom: 8px; }
    .panel-header h2 { font-size: 26px; letter-spacing: -0.02em; }
    .panel-body { padding: 24px; display: grid; gap: 18px; }

    .field label { display: block; color: #475569; font-size: 13px; font-weight: 900; margin-bottom: 7px; }
    .field input, .field textarea, .field select {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 16px;
      padding: 13px 14px;
      outline: none;
      font-size: 15px;
      background: white;
    }

    .field textarea { min-height: 90px; resize: vertical; }
    .field input:focus, .field textarea:focus, .field select:focus { border-color: #0891b2; box-shadow: 0 0 0 3px rgba(8,145,178,0.12); }

    .status-box { border-radius: 18px; padding: 14px 16px; font-weight: 800; line-height: 1.5; }
    .status-box.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .status-box.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    .add-block { padding: 18px; border-radius: 22px; background: #f8fafc; border: 1px solid #e2e8f0; display: grid; gap: 14px; }
    .add-block h3 { font-size: 18px; }
    .add-grid { display: grid; grid-template-columns: 1.4fr 90px; gap: 12px; align-items: end; }

    .itinerary-list { display: grid; gap: 22px; }

    .itinerary-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 30px;
      box-shadow: 0 18px 40px rgba(15,23,42,0.08);
      overflow: hidden;
    }

    .itinerary-top {
      padding: 24px;
      display: flex;
      justify-content: space-between;
      gap: 18px;
      border-bottom: 1px solid #e2e8f0;
      background: linear-gradient(90deg, #ecfeff, white 58%);
    }

    .itinerary-top h3 { font-size: 26px; margin-bottom: 6px; }
    .itinerary-top p { color: #64748b; line-height: 1.5; }
    .total-box { text-align: right; min-width: 160px; }
    .total-box strong { display: block; color: #155e75; font-size: 28px; }
    .total-box span { color: #64748b; font-weight: 800; font-size: 13px; }

    .element-list { padding: 20px 24px 24px; display: grid; gap: 12px; }

    .element-row {
      display: grid;
      grid-template-columns: 46px 1fr auto;
      gap: 14px;
      align-items: center;
      padding: 14px;
      border: 1px solid #e2e8f0;
      border-radius: 20px;
      background: #f8fafc;
    }

    .element-icon {
      width: 42px;
      height: 42px;
      border-radius: 16px;
      background: #ecfeff;
      color: #0e7490;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
    }

    .element-row h4 { font-size: 17px; margin-bottom: 4px; }
    .element-row p { color: #64748b; font-size: 14px; line-height: 1.4; }
    .element-price { text-align: right; display: grid; gap: 8px; justify-items: end; }
    .element-price strong { color: #155e75; }

    .empty-box {
      padding: 36px;
      border: 1px dashed #cbd5e1;
      border-radius: 24px;
      text-align: center;
      color: #64748b;
      background: white;
    }

    .empty-box strong { display: block; color: #0f172a; font-size: 22px; margin-bottom: 8px; }

    footer { border-top: 1px solid #e2e8f0; background: white; padding: 28px 24px; }
    .footer-content { max-width: 1240px; margin: auto; display: flex; justify-content: space-between; gap: 20px; color: #64748b; }
    .footer-links { display: flex; gap: 18px; }
    .footer-links button { border: none; background: transparent; color: #64748b; font-weight: 700; }
    .footer-links button:hover { color: #0e7490; }

    @media (max-width: 1100px) {
      .nav-links { display: none; }
      .page-hero-container, .workspace { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
      .navbar, .footer-content, .itinerary-top { flex-direction: column; align-items: stretch; }
      .navbar { align-items: flex-start; }
      .nav-actions { width: 100%; justify-content: space-between; flex-wrap: wrap; }
      .page-hero-container { padding: 44px 18px 64px; }
      .main-container { padding: 0 18px 48px; }
      .hero-stats, .add-grid { grid-template-columns: 1fr; }
      .element-row { grid-template-columns: 1fr; }
      .element-price, .total-box { text-align: left; justify-items: start; }
      .element-price form, .element-price button { width: 100%; }
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
        <button onclick="window.location.href='Destination.php'">Destinations</button>
        <button onclick="window.location.href='Transport.php'">Transports</button>
        <button onclick="window.location.href='Hebergements.php'">Hébergements</button>
        <button onclick="window.location.href='Activites.php'">Activités</button>
        <button class="active" onclick="window.location.href='Itineraires.php'">Itinéraires</button>
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
    <section class="page-hero">
      <div class="page-hero-container">
        <div>
          <div class="breadcrumb">VoyageVista &gt; Itinéraires</div>
          <h1>Composez un séjour complet</h1>
          <p>
            Créez un itinéraire puis associez des transports, des hébergements et des activités.
            Le coût total est recalculé automatiquement à partir des éléments ajoutés.
          </p>

          <div class="hero-stats">
            <div class="hero-stat">
              <strong><?= h($nombreItineraires) ?></strong>
              <span>séjour(s) créé(s)</span>
            </div>

            <div class="hero-stat">
              <strong><?= h($nombreElementsAssocies) ?></strong>
              <span>élément(s) associé(s)</span>
            </div>

            <div class="hero-stat">
              <strong><?= h(formatPrixCourt($totalGlobal)) ?></strong>
              <span>budget total estimé</span>
            </div>
          </div>
        </div>

        <div class="hero-panel">
          <div class="hero-panel-inner">
            <h2>Composition du séjour</h2>
            <p>
              Les trajets, hébergements et activités ajoutés apparaissent dans le même séjour avec quantité,
              prix unitaire et total calculé.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="main-container">
      <div class="workspace">
        <aside class="panel">
          <div class="panel-header">
            <p>Création</p>
            <h2>Nouveau séjour</h2>
          </div>

          <div class="panel-body">
            <?php if ($messageAction !== ""): ?>
              <div class="status-box <?= h($typeMessageAction) ?>">
                <?= h($messageAction) ?>
              </div>
            <?php endif; ?>

            <form method="POST" class="add-block">
              <input type="hidden" name="action" value="creer_itineraire">

              <div class="field">
                <label for="nom">Nom du séjour</label>
                <input id="nom" name="nom" type="text" placeholder="Ex : Séjour Paris culturel" required>
              </div>

              <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Objectif du voyage, période, contraintes..."></textarea>
              </div>

              <button class="primary-btn" type="submit">Créer le séjour</button>
            </form>

            <?php if (count($itineraires) > 0): ?>
              <form method="POST" class="add-block">
                <input type="hidden" name="action" value="ajouter_element">

                <h3>Ajouter un transport</h3>

                <div class="field">
                  <label for="itineraire_transport">Séjour</label>
                  <select id="itineraire_transport" name="id_itineraire" required>
                    <?php foreach ($itineraires as $itineraire): ?>
                      <option value="<?= h($itineraire["id_itineraire"]) ?>"><?= h($itineraire["nom"]) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <input type="hidden" name="type_element" value="transport">

                <div class="field">
                  <label for="transport">Transport</label>
                  <select id="transport" name="id_element" required>
                    <?php foreach ($catalogue["transport"] as $transport): ?>
                      <option value="<?= h($transport["id"]) ?>">
                        <?= h($transport["nom"] . " · " . formatPrix($transport["prix"])) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="add-grid">
                  <div class="field">
                    <label for="q_transport">Place(s)</label>
                    <input id="q_transport" name="quantite" type="number" min="1" value="1" required>
                  </div>

                  <button class="secondary-btn" type="submit">Ajouter</button>
                </div>
              </form>

              <form method="POST" class="add-block">
                <input type="hidden" name="action" value="ajouter_element">

                <h3>Ajouter un hébergement</h3>

                <div class="field">
                  <label for="itineraire_hebergement">Séjour</label>
                  <select id="itineraire_hebergement" name="id_itineraire" required>
                    <?php foreach ($itineraires as $itineraire): ?>
                      <option value="<?= h($itineraire["id_itineraire"]) ?>"><?= h($itineraire["nom"]) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <input type="hidden" name="type_element" value="hebergement">

                <div class="field">
                  <label for="hebergement">Hébergement</label>
                  <select id="hebergement" name="id_element" required>
                    <?php foreach ($catalogue["hebergement"] as $hebergement): ?>
                      <option value="<?= h($hebergement["id"]) ?>">
                        <?= h($hebergement["nom"] . " · " . formatPrix($hebergement["prix"]) . " / nuit") ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="add-grid">
                  <div class="field">
                    <label for="q_hebergement">Nuit(s)</label>
                    <input id="q_hebergement" name="quantite" type="number" min="1" value="1" required>
                  </div>

                  <button class="secondary-btn" type="submit">Ajouter</button>
                </div>
              </form>

              <form method="POST" class="add-block">
                <input type="hidden" name="action" value="ajouter_element">

                <h3>Ajouter une activité</h3>

                <div class="field">
                  <label for="itineraire_activite">Séjour</label>
                  <select id="itineraire_activite" name="id_itineraire" required>
                    <?php foreach ($itineraires as $itineraire): ?>
                      <option value="<?= h($itineraire["id_itineraire"]) ?>"><?= h($itineraire["nom"]) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <input type="hidden" name="type_element" value="activite">

                <div class="field">
                  <label for="activite">Activité</label>
                  <select id="activite" name="id_element" required>
                    <?php foreach ($catalogue["activite"] as $activite): ?>
                      <option value="<?= h($activite["id"]) ?>">
                        <?= h($activite["nom"] . " · " . formatPrix($activite["prix"])) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="add-grid">
                  <div class="field">
                    <label for="q_activite">Place(s)</label>
                    <input id="q_activite" name="quantite" type="number" min="1" value="1" required>
                  </div>

                  <button class="secondary-btn" type="submit">Ajouter</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </aside>

        <section class="itinerary-list">
          <?php if (count($itineraires) === 0): ?>
            <div class="empty-box">
              <strong>Aucun séjour créé</strong>
              <span>Créez un premier itinéraire pour y associer des trajets, hébergements et activités.</span>
            </div>
          <?php else: ?>
            <?php foreach ($itineraires as $itineraire): ?>
              <?php
                $idItineraire = intval($itineraire["id_itineraire"]);
                $elements = $elementsParItineraire[$idItineraire] ?? [];
                $totalItineraire = 0;

                foreach ($elements as $element) {
                    $totalItineraire += floatval($element["prix_unitaire"] ?? 0) * intval($element["quantite"] ?? 1);
                }
              ?>

              <article class="itinerary-card">
                <div class="itinerary-top">
                  <div>
                    <h3><?= h($itineraire["nom"] ?? "Séjour") ?></h3>
                    <p><?= h($itineraire["description"] ?? "") ?></p>
                    <p>Créé le <?= h(formatDateFr($itineraire["date_creation"] ?? "")) ?> · <?= h(count($elements)) ?> élément(s)</p>
                  </div>

                  <div class="total-box">
                    <strong><?= h(formatPrix($totalItineraire)) ?></strong>
                    <span>coût total</span>

                    <?php if (count($elements) > 0): ?>
                      <form method="POST" style="margin-top:12px;">
                        <input type="hidden" name="action" value="ajouter_itineraire_panier">
                        <input type="hidden" name="id_itineraire" value="<?= h($idItineraire) ?>">
                        <button class="small-btn info" type="submit">Ajouter au panier</button>
                      </form>
                    <?php endif; ?>

                    <form method="POST" style="margin-top:12px;">
                      <input type="hidden" name="action" value="supprimer_itineraire">
                      <input type="hidden" name="id_itineraire" value="<?= h($idItineraire) ?>">
                      <button class="small-btn remove" type="submit">Supprimer</button>
                    </form>
                  </div>
                </div>

                <div class="element-list">
                  <?php if (count($elements) === 0): ?>
                    <div class="empty-box">
                      <strong>Séjour vide</strong>
                      <span>Ajoutez au moins un transport, un hébergement ou une activité.</span>
                    </div>
                  <?php else: ?>
                    <?php foreach ($elements as $element): ?>
                      <?php
                        $quantite = intval($element["quantite"] ?? 1);
                        $prixUnitaire = floatval($element["prix_unitaire"] ?? 0);
                        $totalElement = $prixUnitaire * $quantite;
                        $typeElement = $element["type_element"] ?? "";
                      ?>

                      <div class="element-row">
                        <div class="element-icon"><?= h(iconeType($typeElement)) ?></div>

                        <div>
                          <h4><?= h($element["nom_element"] ?? "Élément") ?></h4>
                          <p><?= h(typeLisible($typeElement)) ?> · <?= h($element["details"] ?? "") ?></p>
                          <p>Quantité : <?= h($quantite) ?> · Prix unitaire : <?= h(formatPrix($prixUnitaire)) ?></p>
                        </div>

                        <div class="element-price">
                          <strong><?= h(formatPrix($totalElement)) ?></strong>

                          <form method="POST">
                            <input type="hidden" name="action" value="retirer_element">
                            <input type="hidden" name="id_itineraire_element" value="<?= h($element["id_itineraire_element"]) ?>">
                            <button class="small-btn remove" type="submit">Retirer</button>
                          </form>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
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
