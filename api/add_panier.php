<?php
session_start();
require_once "db.php";

function nettoyer($valeur) {
    return trim($valeur ?? "");
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

function hebergementDisponible($pdo, $idHebergement, $dateArrivee, $dateDepart) {
    $sql = "
        SELECT COUNT(*) AS total
        FROM reservation_hebergement
        WHERE id_hebergement = ?
        AND statut = 'confirmee'
        AND date_arrivee < ?
        AND date_depart > ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $idHebergement,
        $dateDepart,
        $dateArrivee
    ]);

    $resultat = $stmt->fetch();

    return intval($resultat["total"] ?? 0) === 0;
}

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Connexion.php?erreur=connexion_requise");
    exit;
}

$idUtilisateur = intval($_SESSION["user_id"]);

$typeElement = nettoyer($_POST["type_element"] ?? "");
$idElement = intval($_POST["id_element"] ?? 0);
$nomElement = nettoyer($_POST["nom_element"] ?? "");
$prixUnitaire = floatval($_POST["prix_unitaire"] ?? 0);
$quantite = intval($_POST["quantite"] ?? 1);

$dateArrivee = nettoyer($_POST["date_arrivee"] ?? "");
$dateDepart = nettoyer($_POST["date_depart"] ?? "");
$nbNuits = intval($_POST["nb_nuits"] ?? 0);

$typesAutorises = ["destination", "transport", "hebergement", "activite"];

if (!in_array($typeElement, $typesAutorises, true)) {
    header("Location: ../Acceuil.php");
    exit;
}

if ($idElement <= 0 || $nomElement === "" || $prixUnitaire <= 0) {
    header("Location: ../Voir.php?type=" . urlencode($typeElement) . "&id=" . $idElement . "&erreur=donnees_invalides");
    exit;
}

if ($quantite < 1) {
    $quantite = 1;
}

if ($typeElement === "hebergement") {
    if (!dateValide($dateArrivee) || !dateValide($dateDepart)) {
        header("Location: ../Voir.php?type=hebergement&id=" . $idElement . "&erreur=dates_manquantes");
        exit;
    }

    $nbNuitsCalcule = calculerNuits($dateArrivee, $dateDepart);

    if ($nbNuitsCalcule <= 0) {
        header("Location: ../Voir.php?type=hebergement&id=" . $idElement . "&erreur=dates_invalides");
        exit;
    }

    $nbNuits = $nbNuitsCalcule;
    $quantite = $nbNuits;

    if (!hebergementDisponible($pdo, $idElement, $dateArrivee, $dateDepart)) {
        header("Location: ../Voir.php?type=hebergement&id=" . $idElement . "&erreur=deja_reserve");
        exit;
    }
} else {
    $dateArrivee = null;
    $dateDepart = null;
    $nbNuits = null;
}

try {
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
        $idPanier = intval($panier["id_panier"]);
    } else {
        $sqlCreatePanier = "
            INSERT INTO panier (id_utilisateur, date_creation)
            VALUES (?, NOW())
        ";

        $stmtCreatePanier = $pdo->prepare($sqlCreatePanier);
        $stmtCreatePanier->execute([$idUtilisateur]);

        $idPanier = intval($pdo->lastInsertId());
    }

    if ($typeElement === "hebergement") {
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
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            $idPanier,
            $typeElement,
            $idElement,
            $nomElement,
            $prixUnitaire,
            $quantite,
            $dateArrivee,
            $dateDepart,
            $nbNuits
        ]);
    } else {
        $sqlLigneExistante = "
            SELECT id_ligne, quantite
            FROM ligne_panier
            WHERE id_panier = ?
            AND type_element = ?
            AND id_element = ?
            LIMIT 1
        ";

        $stmtLigneExistante = $pdo->prepare($sqlLigneExistante);
        $stmtLigneExistante->execute([
            $idPanier,
            $typeElement,
            $idElement
        ]);

        $ligneExistante = $stmtLigneExistante->fetch();

        if ($ligneExistante) {
            $nouvelleQuantite = intval($ligneExistante["quantite"]) + $quantite;

            $sqlUpdate = "
                UPDATE ligne_panier
                SET 
                    nom_element = ?,
                    prix_unitaire = ?,
                    quantite = ?
                WHERE id_ligne = ?
            ";

            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                $nomElement,
                $prixUnitaire,
                $nouvelleQuantite,
                $ligneExistante["id_ligne"]
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

    header("Location: ../Panier.php");
    exit;
} catch (PDOException $e) {
    header("Location: ../Voir.php?type=" . urlencode($typeElement) . "&id=" . $idElement . "&erreur=panier");
    exit;
}