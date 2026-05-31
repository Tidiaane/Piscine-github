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

    return $initiales !== "" ? $initiales : "AD";
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

function tableExiste($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function getColonnes($pdo, $table) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), "Field");
    } catch (PDOException $e) {
        return [];
    }
}

function colonneExiste($colonnes, $colonne) {
    return in_array($colonne, $colonnes, true);
}

function normaliserListeJson($valeur) {
    $valeur = trim($valeur ?? "");

    if ($valeur === "") {
        return null;
    }

    if (($valeur[0] ?? "") === "[" || ($valeur[0] ?? "") === "{") {
        return $valeur;
    }

    $items = array_values(array_filter(array_map("trim", explode(",", $valeur)), function ($item) {
        return $item !== "";
    }));

    return json_encode($items, JSON_UNESCAPED_UNICODE);
}

function valeurPost($nom, $defaut = "") {
    return trim($_POST[$nom] ?? $defaut);
}

function insererOffre($pdo, $table, $donnees) {
    $colonnesTable = getColonnes($pdo, $table);
    $colonnes = [];
    $valeurs = [];
    $params = [];

    foreach ($donnees as $colonne => $valeur) {
        if (colonneExiste($colonnesTable, $colonne)) {
            $colonnes[] = "`" . $colonne . "`";
            $valeurs[] = "?";
            $params[] = $valeur;
        }
    }

    if (count($colonnes) === 0) {
        throw new Exception("Aucune colonne compatible trouvée pour la table `" . $table . "`.");
    }

    $sql = "INSERT INTO `$table` (" . implode(", ", $colonnes) . ") VALUES (" . implode(", ", $valeurs) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function supprimerOffre($pdo, $table, $idColonne, $id) {
    $id = intval($id);

    if ($id <= 0) {
        throw new Exception("Identifiant invalide.");
    }

    $pdo->beginTransaction();

    try {
        if ($table === "destination") {
            $stmt = $pdo->prepare("DELETE FROM ligne_panier WHERE type_element = 'destination' AND id_element = ?");
            $stmt->execute([$id]);
        }

        if ($table === "transport") {
            if (tableExiste($pdo, "reservation_transport")) {
                $stmt = $pdo->prepare("UPDATE reservation_transport SET statut = 'annulee' WHERE id_transport = ? AND statut = 'confirmee'");
                $stmt->execute([$id]);
            }
            $stmt = $pdo->prepare("DELETE FROM ligne_panier WHERE type_element = 'transport' AND id_element = ?");
            $stmt->execute([$id]);
            if (tableExiste($pdo, "itineraire_element")) {
                $stmt = $pdo->prepare("DELETE FROM itineraire_element WHERE type_element = 'transport' AND id_element = ?");
                $stmt->execute([$id]);
            }
        }

        if ($table === "hebergement") {
            if (tableExiste($pdo, "reservation_hebergement")) {
                $stmt = $pdo->prepare("UPDATE reservation_hebergement SET statut = 'annulee' WHERE id_hebergement = ? AND statut = 'confirmee'");
                $stmt->execute([$id]);
            }
            $stmt = $pdo->prepare("DELETE FROM ligne_panier WHERE type_element = 'hebergement' AND id_element = ?");
            $stmt->execute([$id]);
            if (tableExiste($pdo, "itineraire_element")) {
                $stmt = $pdo->prepare("DELETE FROM itineraire_element WHERE type_element = 'hebergement' AND id_element = ?");
                $stmt->execute([$id]);
            }
        }

        if ($table === "activite") {
            $stmt = $pdo->prepare("DELETE FROM ligne_panier WHERE type_element = 'activite' AND id_element = ?");
            $stmt->execute([$id]);
            if (tableExiste($pdo, "itineraire_element")) {
                $stmt = $pdo->prepare("DELETE FROM itineraire_element WHERE type_element = 'activite' AND id_element = ?");
                $stmt->execute([$id]);
            }
        }

        $sql = "DELETE FROM `$table` WHERE `$idColonne` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function fetchAllSafe($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function fetchOneSafe($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function countTable($pdo, $table) {
    if (!tableExiste($pdo, $table)) {
        return 0;
    }

    $row = fetchOneSafe($pdo, "SELECT COUNT(*) AS total FROM `$table`");
    return intval($row["total"] ?? 0);
}

function extraireChampMessage($message, $labels) {
    foreach ((array) $labels as $label) {
        $pattern = '/^' . preg_quote($label, '/') . '\s*:\s*(.+)$/miu';

        if (preg_match($pattern, $message ?? "", $match)) {
            return trim($match[1]);
        }
    }

    return "";
}

function typeInterneReservation($typeLisible) {
    $type = strtolower(trim($typeLisible ?? ""));

    if ($type === "destination") return "destination";
    if ($type === "transport") return "transport";
    if ($type === "hébergement" || $type === "hebergement") return "hebergement";
    if ($type === "activité" || $type === "activite") return "activite";

    return "";
}

function nomClient($prenom, $nom, $email) {
    $nomComplet = trim(($prenom ?? "") . " " . ($nom ?? ""));

    if ($nomComplet !== "") {
        return $nomComplet;
    }

    return $email !== "" ? $email : "Utilisateur inconnu";
}

if (!isset($_SESSION["user_id"])) {
    header("Location: Connexion.php?erreur=connexion_requise");
    exit;
}

$idUtilisateur = intval($_SESSION["user_id"]);
$utilisateur = fetchOneSafe($pdo, "SELECT * FROM utilisateur WHERE id_utilisateur = ?", [$idUtilisateur]);

if (!$utilisateur) {
    session_destroy();
    header("Location: Connexion.php?erreur=connexion_requise");
    exit;
}

$role = $utilisateur["role"] ?? "client";
$estAdmin = $role === "admin";
$estGestionnaire = $role === "gestionnaire";

if (!$estAdmin && !$estGestionnaire) {
    header("Location: Acceuil.php");
    exit;
}

$initiales = getInitiales($utilisateur["prenom"] ?? "", $utilisateur["nom"] ?? "", $utilisateur["email"] ?? "");
$messageAction = "";
$typeMessageAction = "";

$catalogueConfig = [
    "destination" => [
        "table" => "destination",
        "id" => "id_destination",
        "label" => "Destination",
        "nom" => "nom_destination"
    ],
    "transport" => [
        "table" => "transport",
        "id" => "id_transport",
        "label" => "Transport",
        "nom" => "compagnie"
    ],
    "hebergement" => [
        "table" => "hebergement",
        "id" => "id_hebergement",
        "label" => "Hébergement",
        "nom" => "nom"
    ],
    "activite" => [
        "table" => "activite",
        "id" => "id_activite",
        "label" => "Activité",
        "nom" => "nom"
    ]
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    try {
        if ($action === "ajouter_destination") {
            insererOffre($pdo, "destination", [
                "nom_destination" => valeurPost("nom_destination"),
                "pays" => valeurPost("pays"),
                "image" => valeurPost("image"),
                "categorie" => valeurPost("categorie"),
                "note_moyenne" => valeurPost("note_moyenne", "4.5"),
                "prix" => valeurPost("prix", "0"),
                "description" => valeurPost("description"),
                "duree" => valeurPost("duree", "7"),
                "saison" => valeurPost("saison", "ete"),
                "styles" => normaliserListeJson(valeurPost("styles")),
                "tags" => normaliserListeJson(valeurPost("tags")),
                "recommande" => valeurPost("recommande", "5")
            ]);

            $messageAction = "Destination ajoutée au catalogue.";
            $typeMessageAction = "success";
        }

        if ($action === "ajouter_transport") {
            insererOffre($pdo, "transport", [
                "type" => valeurPost("type"),
                "icone" => valeurPost("icone"),
                "compagnie" => valeurPost("compagnie"),
                "ville_depart" => valeurPost("ville_depart"),
                "ville_arrivee" => valeurPost("ville_arrivee"),
                "date_depart" => valeurPost("date_depart"),
                "date_retour" => valeurPost("date_retour"),
                "heure_depart" => valeurPost("heure_depart"),
                "heure_arrivee" => valeurPost("heure_arrivee"),
                "duree" => valeurPost("duree", "0"),
                "prix" => valeurPost("prix", "0"),
                "places_disponibles" => valeurPost("places_disponibles", "0"),
                "description" => valeurPost("description"),
                "options" => normaliserListeJson(valeurPost("options")),
                "tags" => normaliserListeJson(valeurPost("tags")),
                "recommande" => valeurPost("recommande", "5")
            ]);

            $messageAction = "Transport ajouté au catalogue.";
            $typeMessageAction = "success";
        }

        if ($action === "ajouter_hebergement") {
            insererOffre($pdo, "hebergement", [
                "nom" => valeurPost("nom"),
                "destination" => valeurPost("destination"),
                "pays" => valeurPost("pays"),
                "type" => valeurPost("type"),
                "adresse" => valeurPost("adresse"),
                "capacite" => valeurPost("capacite", "1"),
                "prix" => valeurPost("prix", "0"),
                "prix_nuit" => valeurPost("prix", "0"),
                "note" => valeurPost("note", "4.5"),
                "etoiles" => valeurPost("etoiles", "★★★★☆"),
                "disponibilite" => valeurPost("disponibilite", "Disponible"),
                "description" => valeurPost("description"),
                "image" => valeurPost("image"),
                "equipements" => normaliserListeJson(valeurPost("equipements")),
                "tags" => normaliserListeJson(valeurPost("tags")),
                "recommande" => valeurPost("recommande", "5"),
                "statut" => "actif"
            ]);

            $messageAction = "Hébergement ajouté au catalogue.";
            $typeMessageAction = "success";
        }

        if ($action === "ajouter_activite") {
            insererOffre($pdo, "activite", [
                "nom" => valeurPost("nom"),
                "destination" => valeurPost("destination"),
                "categorie" => valeurPost("categorie"),
                "niveau" => valeurPost("niveau"),
                "moment" => valeurPost("moment"),
                "duree" => valeurPost("duree", "1"),
                "prix" => valeurPost("prix", "0"),
                "note" => valeurPost("note", "4.5"),
                "places_disponibles" => valeurPost("places_disponibles", "0"),
                "capacite_max" => valeurPost("places_disponibles", "0"),
                "description" => valeurPost("description"),
                "image" => valeurPost("image"),
                "statut" => "active"
            ]);

            $messageAction = "Activité ajoutée au catalogue.";
            $typeMessageAction = "success";
        }

        if ($action === "supprimer_offre") {
            $typeOffre = $_POST["type_offre"] ?? "";
            $idOffre = intval($_POST["id_offre"] ?? 0);

            if (!isset($catalogueConfig[$typeOffre])) {
                throw new Exception("Type d'offre invalide.");
            }

            $config = $catalogueConfig[$typeOffre];
            supprimerOffre($pdo, $config["table"], $config["id"], $idOffre);

            $messageAction = $config["label"] . " supprimé(e) du catalogue.";
            $typeMessageAction = "success";
        }

        if ($action === "supprimer_itineraire") {
            $idItineraire = intval($_POST["id_itineraire"] ?? 0);

            if ($idItineraire <= 0) {
                throw new Exception("Séjour introuvable.");
            }

            $stmt = $pdo->prepare("DELETE FROM itineraire WHERE id_itineraire = ?");
            $stmt->execute([$idItineraire]);

            $messageAction = "Itinéraire supprimé du système.";
            $typeMessageAction = "success";
        }

        if ($action === "changer_role" && $estAdmin) {
            $idCible = intval($_POST["id_utilisateur_cible"] ?? 0);
            $nouveauRole = $_POST["nouveau_role"] ?? "client";
            $rolesAutorises = ["client", "admin", "gestionnaire", "fournisseur"];

            if ($idCible <= 0 || !in_array($nouveauRole, $rolesAutorises, true)) {
                throw new Exception("Utilisateur ou rôle invalide.");
            }

            $stmt = $pdo->prepare("UPDATE utilisateur SET role = ? WHERE id_utilisateur = ?");
            $stmt->execute([$nouveauRole, $idCible]);

            $messageAction = "Rôle utilisateur mis à jour.";
            $typeMessageAction = "success";
        }

        if ($action === "ajouter_voyageur") {
            if (!tableExiste($pdo, "destination_voyageur")) {
                throw new Exception("La table destination_voyageur n'existe pas. Importez le patch SQL destination fourni.");
            }

            $idDestination = intval($_POST["id_destination"] ?? 0);
            $emailVoyageur = trim($_POST["email_voyageur"] ?? "");
            $voyageur = fetchOneSafe($pdo, "SELECT id_utilisateur FROM utilisateur WHERE email = ?", [$emailVoyageur]);

            if ($idDestination <= 0 || !$voyageur) {
                throw new Exception("Destination ou voyageur introuvable.");
            }

            $idVoyageur = intval($voyageur["id_utilisateur"]);

            $associationExistante = fetchOneSafe(
                $pdo,
                "SELECT id_destination_voyageur FROM destination_voyageur WHERE id_destination = ? AND id_utilisateur = ? LIMIT 1",
                [$idDestination, $idVoyageur]
            );

            if ($associationExistante) {
                $messageAction = "Ce voyageur est déjà associé à cette destination.";
                $typeMessageAction = "success";
            } else {
                $sql = "
                    INSERT INTO destination_voyageur (id_destination, id_utilisateur, role_voyageur, date_ajout)
                    VALUES (?, ?, 'voyageur', NOW())
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$idDestination, $idVoyageur]);

                $messageAction = "Voyageur ajouté à la destination.";
                $typeMessageAction = "success";
            }
        }

        if ($action === "retirer_voyageur") {
            if (!tableExiste($pdo, "destination_voyageur")) {
                throw new Exception("La table destination_voyageur n'existe pas.");
            }

            $idDestinationVoyageur = intval($_POST["id_destination_voyageur"] ?? 0);

            if ($idDestinationVoyageur <= 0) {
                throw new Exception("Association voyageur introuvable.");
            }

            $stmt = $pdo->prepare("DELETE FROM destination_voyageur WHERE id_destination_voyageur = ?");
            $stmt->execute([$idDestinationVoyageur]);

            $messageAction = "Voyageur retiré de la destination.";
            $typeMessageAction = "success";
        }
    } catch (Exception $e) {
        $messageAction = $e->getMessage();
        $typeMessageAction = "error";
    }
}

$stats = [
    "destinations" => countTable($pdo, "destination"),
    "transports" => countTable($pdo, "transport"),
    "hebergements" => countTable($pdo, "hebergement"),
    "activites" => countTable($pdo, "activite"),
    "itineraires" => countTable($pdo, "itineraire"),
    "utilisateurs" => countTable($pdo, "utilisateur")
];

$destinations = fetchAllSafe($pdo, "SELECT * FROM destination ORDER BY id_destination DESC LIMIT 20");
$transports = fetchAllSafe($pdo, "SELECT * FROM transport ORDER BY id_transport DESC LIMIT 20");
$hebergements = fetchAllSafe($pdo, "SELECT * FROM hebergement ORDER BY id_hebergement DESC LIMIT 20");
$activites = fetchAllSafe($pdo, "SELECT * FROM activite ORDER BY id_activite DESC LIMIT 20");
$itineraires = fetchAllSafe($pdo, "
    SELECT i.*, u.nom AS nom_utilisateur, u.prenom AS prenom_utilisateur, u.email AS email_utilisateur,
           COALESCE(SUM(ie.prix_unitaire * ie.quantite), 0) AS cout_total,
           COUNT(ie.id_itineraire_element) AS nb_elements
    FROM itineraire i
    LEFT JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
    LEFT JOIN itineraire_element ie ON ie.id_itineraire = i.id_itineraire
    GROUP BY i.id_itineraire
    ORDER BY i.date_creation DESC
    LIMIT 30
");
$utilisateurs = fetchAllSafe($pdo, "SELECT * FROM utilisateur ORDER BY role DESC, nom ASC, prenom ASC LIMIT 50");
$voyageursAssocies = [];

if (tableExiste($pdo, "destination_voyageur")) {
    $voyageursAssocies = fetchAllSafe($pdo, "
        SELECT
            MIN(dv.id_destination_voyageur) AS id_destination_voyageur,
            dv.id_destination,
            dv.id_utilisateur,
            MAX(dv.role_voyageur) AS role_voyageur,
            MAX(dv.date_ajout) AS date_ajout,
            d.nom_destination,
            d.pays,
            u.nom,
            u.prenom,
            u.email
        FROM destination_voyageur dv
        JOIN destination d ON d.id_destination = dv.id_destination
        JOIN utilisateur u ON u.id_utilisateur = dv.id_utilisateur
        GROUP BY dv.id_destination, dv.id_utilisateur, d.nom_destination, d.pays, u.nom, u.prenom, u.email
        ORDER BY MAX(dv.date_ajout) DESC
        LIMIT 50
    ");
}

$reservations = [];

if (tableExiste($pdo, "reservation_hebergement")) {
    $rows = fetchAllSafe($pdo, "
        SELECT
            rh.id_reservation_hebergement,
            rh.id_hebergement,
            rh.id_utilisateur,
            rh.date_arrivee,
            rh.date_depart,
            rh.quantite,
            rh.statut,
            rh.date_creation,
            h.nom AS nom_hebergement,
            h.destination AS destination_hebergement,
            h.pays AS pays_hebergement,
            u.nom AS nom_utilisateur,
            u.prenom AS prenom_utilisateur,
            u.email AS email_utilisateur
        FROM reservation_hebergement rh
        LEFT JOIN hebergement h ON h.id_hebergement = rh.id_hebergement
        LEFT JOIN utilisateur u ON u.id_utilisateur = rh.id_utilisateur
        ORDER BY rh.date_creation DESC
        LIMIT 200
    ");

    foreach ($rows as $row) {
        $reservations[] = [
            "type" => "Hébergement",
            "source" => "reservation_hebergement",
            "id_reservation" => intval($row["id_reservation_hebergement"] ?? 0),
            "id_element" => intval($row["id_hebergement"] ?? 0),
            "nom_element" => $row["nom_hebergement"] ?? "Hébergement",
            "details" => trim(($row["destination_hebergement"] ?? "") . " " . ($row["pays_hebergement"] ?? "")),
            "destination_label" => trim(($row["destination_hebergement"] ?? "Destination non précisée") . (($row["pays_hebergement"] ?? "") !== "" ? " · " . $row["pays_hebergement"] : "")),
            "periode" => "Du " . formatDateFr($row["date_arrivee"] ?? "") . " au " . formatDateFr($row["date_depart"] ?? ""),
            "quantite" => intval($row["quantite"] ?? 1),
            "statut" => $row["statut"] ?? "confirmee",
            "date_creation" => $row["date_creation"] ?? "",
            "client" => nomClient($row["prenom_utilisateur"] ?? "", $row["nom_utilisateur"] ?? "", $row["email_utilisateur"] ?? ""),
            "email" => $row["email_utilisateur"] ?? ""
        ];
    }
}

if (tableExiste($pdo, "reservation_transport")) {
    $rows = fetchAllSafe($pdo, "
        SELECT
            rt.id_reservation_transport,
            rt.id_transport,
            rt.id_utilisateur,
            rt.quantite,
            rt.statut,
            rt.date_creation,
            t.compagnie,
            t.type AS type_transport,
            t.ville_depart,
            t.ville_arrivee,
            t.date_depart,
            t.date_retour,
            t.heure_depart,
            t.heure_arrivee,
            u.nom AS nom_utilisateur,
            u.prenom AS prenom_utilisateur,
            u.email AS email_utilisateur
        FROM reservation_transport rt
        LEFT JOIN transport t ON t.id_transport = rt.id_transport
        LEFT JOIN utilisateur u ON u.id_utilisateur = rt.id_utilisateur
        ORDER BY rt.date_creation DESC
        LIMIT 200
    ");

    foreach ($rows as $row) {
        $reservations[] = [
            "type" => "Transport",
            "source" => "reservation_transport",
            "id_reservation" => intval($row["id_reservation_transport"] ?? 0),
            "id_element" => intval($row["id_transport"] ?? 0),
            "nom_element" => trim(($row["ville_depart"] ?? "") . " → " . ($row["ville_arrivee"] ?? "")),
            "details" => trim(($row["compagnie"] ?? "") . " · " . ($row["type_transport"] ?? "")),
            "destination_label" => $row["ville_arrivee"] ?? "Destination non précisée",
            "periode" => "Départ le " . formatDateFr($row["date_depart"] ?? "") . " à " . formatHeure($row["heure_depart"] ?? "") . " · arrivée " . formatHeure($row["heure_arrivee"] ?? ""),
            "quantite" => intval($row["quantite"] ?? 1),
            "statut" => $row["statut"] ?? "confirmee",
            "date_creation" => $row["date_creation"] ?? "",
            "client" => nomClient($row["prenom_utilisateur"] ?? "", $row["nom_utilisateur"] ?? "", $row["email_utilisateur"] ?? ""),
            "email" => $row["email_utilisateur"] ?? ""
        ];
    }
}

$activitesDepuisTable = false;
if (tableExiste($pdo, "reservation_activite")) {
    $activitesDepuisTable = true;
    $rows = fetchAllSafe($pdo, "
        SELECT
            ra.*,
            a.nom AS nom_activite,
            a.destination AS destination_activite,
            a.categorie AS categorie_activite,
            u.nom AS nom_utilisateur,
            u.prenom AS prenom_utilisateur,
            u.email AS email_utilisateur
        FROM reservation_activite ra
        LEFT JOIN activite a ON a.id_activite = ra.id_activite
        LEFT JOIN utilisateur u ON u.id_utilisateur = ra.id_utilisateur
        ORDER BY ra.date_creation DESC
        LIMIT 200
    ");

    foreach ($rows as $row) {
        $reservations[] = [
            "type" => "Activité",
            "source" => "reservation_activite",
            "id_reservation" => intval($row["id_reservation_activite"] ?? 0),
            "id_element" => intval($row["id_activite"] ?? 0),
            "nom_element" => $row["nom_activite"] ?? "Activité",
            "details" => trim(($row["destination_activite"] ?? "") . " · " . ($row["categorie_activite"] ?? "")),
            "destination_label" => $row["destination_activite"] ?? "Destination non précisée",
            "periode" => "Réservation créée le " . formatDateFr($row["date_creation"] ?? ""),
            "quantite" => intval($row["quantite"] ?? 1),
            "statut" => $row["statut"] ?? "confirmee",
            "date_creation" => $row["date_creation"] ?? "",
            "client" => nomClient($row["prenom_utilisateur"] ?? "", $row["nom_utilisateur"] ?? "", $row["email_utilisateur"] ?? ""),
            "email" => $row["email_utilisateur"] ?? ""
        ];
    }
}

$destinationsDepuisTable = false;
if (tableExiste($pdo, "reservation_destination")) {
    $destinationsDepuisTable = true;
    $rows = fetchAllSafe($pdo, "
        SELECT
            rd.*,
            d.nom_destination,
            d.pays,
            u.nom AS nom_utilisateur,
            u.prenom AS prenom_utilisateur,
            u.email AS email_utilisateur
        FROM reservation_destination rd
        LEFT JOIN destination d ON d.id_destination = rd.id_destination
        LEFT JOIN utilisateur u ON u.id_utilisateur = rd.id_utilisateur
        ORDER BY rd.date_creation DESC
        LIMIT 200
    ");

    foreach ($rows as $row) {
        $reservations[] = [
            "type" => "Destination",
            "source" => "reservation_destination",
            "id_reservation" => intval($row["id_reservation_destination"] ?? 0),
            "id_element" => intval($row["id_destination"] ?? 0),
            "nom_element" => $row["nom_destination"] ?? "Destination",
            "details" => $row["pays"] ?? "",
            "destination_label" => trim(($row["nom_destination"] ?? "Destination") . (($row["pays"] ?? "") !== "" ? " · " . $row["pays"] : "")),
            "periode" => "Réservation créée le " . formatDateFr($row["date_creation"] ?? ""),
            "quantite" => intval($row["quantite"] ?? 1),
            "statut" => $row["statut"] ?? "confirmee",
            "date_creation" => $row["date_creation"] ?? "",
            "client" => nomClient($row["prenom_utilisateur"] ?? "", $row["nom_utilisateur"] ?? "", $row["email_utilisateur"] ?? ""),
            "email" => $row["email_utilisateur"] ?? ""
        ];
    }
}

if (!$activitesDepuisTable || !$destinationsDepuisTable) {
    $notificationsReservations = fetchAllSafe($pdo, "
        SELECT n.*, u.nom AS nom_utilisateur, u.prenom AS prenom_utilisateur, u.email AS email_utilisateur
        FROM notification n
        LEFT JOIN utilisateur u ON u.id_utilisateur = n.id_utilisateur
        WHERE n.titre LIKE 'Réservation confirmée -%'
           OR n.titre LIKE 'Reservation confirmee -%'
        ORDER BY n.date_envoi DESC
        LIMIT 300
    ");

    foreach ($notificationsReservations as $notificationReservation) {
        $message = $notificationReservation["message"] ?? "";
        $typeLisible = extraireChampMessage($message, "Type");
        $typeInterne = typeInterneReservation($typeLisible);

        if ($typeInterne === "activite" && !$activitesDepuisTable) {
            $reservations[] = [
                "type" => "Activité",
                "source" => "notification",
                "id_reservation" => intval($notificationReservation["id_notification"] ?? $notificationReservation["id"] ?? 0),
                "id_element" => intval(extraireChampMessage($message, "ID élément")),
                "nom_element" => extraireChampMessage($message, "Nom") ?: ($notificationReservation["titre"] ?? "Activité"),
                "details" => "Réservation issue des notifications",
                "destination_label" => "Destination non précisée",
                "periode" => "Notification envoyée le " . formatDateFr($notificationReservation["date_envoi"] ?? ""),
                "quantite" => intval(extraireChampMessage($message, ["Quantité", "Quantité réservée"]) ?: 1),
                "statut" => "confirmee",
                "date_creation" => $notificationReservation["date_envoi"] ?? "",
                "client" => nomClient($notificationReservation["prenom_utilisateur"] ?? "", $notificationReservation["nom_utilisateur"] ?? "", $notificationReservation["email_utilisateur"] ?? ""),
                "email" => $notificationReservation["email_utilisateur"] ?? ""
            ];
        }

        if ($typeInterne === "destination" && !$destinationsDepuisTable) {
            $reservations[] = [
                "type" => "Destination",
                "source" => "notification",
                "id_reservation" => intval($notificationReservation["id_notification"] ?? $notificationReservation["id"] ?? 0),
                "id_element" => intval(extraireChampMessage($message, "ID élément")),
                "nom_element" => extraireChampMessage($message, "Nom") ?: ($notificationReservation["titre"] ?? "Destination"),
                "details" => "Réservation issue des notifications",
                "destination_label" => "Destination non précisée",
                "periode" => "Notification envoyée le " . formatDateFr($notificationReservation["date_envoi"] ?? ""),
                "quantite" => intval(extraireChampMessage($message, ["Quantité", "Quantité réservée"]) ?: 1),
                "statut" => "confirmee",
                "date_creation" => $notificationReservation["date_envoi"] ?? "",
                "client" => nomClient($notificationReservation["prenom_utilisateur"] ?? "", $notificationReservation["nom_utilisateur"] ?? "", $notificationReservation["email_utilisateur"] ?? ""),
                "email" => $notificationReservation["email_utilisateur"] ?? ""
            ];
        }
    }
}

usort($reservations, function ($a, $b) {
    return strcmp($b["date_creation"] ?? "", $a["date_creation"] ?? "");
});

$reservations = array_slice($reservations, 0, 250);

$reservationsParDestination = [];

foreach ($reservations as $reservation) {
    $destinationLabel = trim($reservation["destination_label"] ?? "");

    if ($destinationLabel === "") {
        $destinationLabel = "Destination non précisée";
    }

    if (!isset($reservationsParDestination[$destinationLabel])) {
        $reservationsParDestination[$destinationLabel] = [];
    }

    $reservationsParDestination[$destinationLabel][] = $reservation;
}

ksort($reservationsParDestination, SORT_NATURAL | SORT_FLAG_CASE);

$nombreElementsPanier = 0;
$nombreNotifications = 0;
$notificationsPopup = [];

try {
    $rowPanier = fetchOneSafe($pdo, "
        SELECT COALESCE(SUM(lp.quantite), 0) AS total
        FROM ligne_panier lp
        JOIN panier p ON lp.id_panier = p.id_panier
        WHERE p.id_utilisateur = ?
    ", [$idUtilisateur]);
    $nombreElementsPanier = intval($rowPanier["total"] ?? 0);

    $rowNotif = fetchOneSafe($pdo, "SELECT COUNT(*) AS total FROM notification WHERE id_utilisateur = ? AND statut_lecture = 0", [$idUtilisateur]);
    $nombreNotifications = intval($rowNotif["total"] ?? 0);

    $notificationsPopup = fetchAllSafe($pdo, "
        SELECT titre, message, date_envoi, statut_lecture
        FROM notification
        WHERE id_utilisateur = ?
        ORDER BY date_envoi DESC
        LIMIT 3
    ", [$idUtilisateur]);
} catch (PDOException $e) {
    $nombreElementsPanier = 0;
    $nombreNotifications = 0;
    $notificationsPopup = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>VoyageVista - Administration</title>

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
   
</html>
