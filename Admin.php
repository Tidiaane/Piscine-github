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
    body { font-family: Arial, Helvetica, sans-serif; background: #f8fafc; color: #0f172a; }
    button, input, select, textarea { font-family: inherit; }
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

    .logo { display: flex; align-items: center; gap: 10px; border: none; background: transparent; text-align: left; }
    .logo-icon { width: 42px; height: 42px; border-radius: 16px; background: #0e7490; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; }
    .logo-title { display: block; color: #155e75; font-size: 20px; font-weight: 800; line-height: 1; }
    .logo-subtitle { display: block; margin-top: 3px; font-size: 12px; color: #64748b; }

    .nav-links, .nav-actions { display: flex; align-items: center; gap: 8px; }
    .nav-links button { border: none; background: transparent; color: #475569; font-weight: 700; padding: 10px 14px; border-radius: 999px; transition: 0.2s; }
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
    .primary-btn { border: none; background: #0e7490; color: white; box-shadow: 0 10px 18px rgba(14, 116, 144, 0.18); }
    .primary-btn:hover { background: #155e75; transform: translateY(-1px); }
    .secondary-btn { background: white; color: #0e7490; border: 1px solid #bae6fd; }
    .secondary-btn:hover { background: #ecfeff; transform: translateY(-1px); }
    .danger-btn { border: 1px solid #fecaca; background: #fff7f7; color: #dc2626; }
    .danger-btn:hover { background: #fee2e2; transform: translateY(-1px); }
    .dark-btn { border: none; background: #0f172a; color: white; }
    .small-btn { border: none; padding: 8px 12px; font-size: 13px; }
    .small-btn.info { background: #ecfeff; color: #0e7490; }
    .small-btn.remove { background: #fff7f7; color: #dc2626; border: 1px solid #fecaca; }

    .icon-btn { position: relative; width: 42px; height: 42px; border-radius: 50%; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 18px; transition: 0.2s; }
    .icon-btn:hover { background: #ecfeff; border-color: #67e8f9; }
    .badge-count { position: absolute; top: -5px; right: -5px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: #ef4444; color: white; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
    .notification-wrapper { position: relative; }
    .notification-dropdown { position: absolute; top: 52px; right: 0; width: 330px; background: white; border: 1px solid #e2e8f0; border-radius: 22px; box-shadow: 0 22px 45px rgba(15, 23, 42, 0.18); padding: 14px; opacity: 0; visibility: hidden; transform: translateY(8px); transition: 0.2s ease; }
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
    .avatar-btn { width: 44px; height: 44px; border: none; border-radius: 50%; background: #0e7490; color: white; font-weight: 900; font-size: 15px; box-shadow: 0 10px 18px rgba(14, 116, 144, 0.18); transition: 0.2s; }
    .avatar-btn:hover { background: #155e75; transform: translateY(-1px); }

    .hero {
      background: linear-gradient(135deg, rgba(15,95,117,0.94), rgba(8,145,178,0.78), rgba(5,150,105,0.78)), url("https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1600&q=80");
      background-size: cover;
      background-position: center;
      color: white;
    }

    .hero-inner { max-width: 1240px; margin: auto; padding: 64px 24px 88px; display: grid; grid-template-columns: 1fr 0.7fr; gap: 42px; align-items: center; }
    .breadcrumb { display: inline-flex; gap: 8px; align-items: center; padding: 8px 14px; border-radius: 999px; background: rgba(255,255,255,0.16); font-size: 14px; font-weight: 700; margin-bottom: 18px; }
    .hero h1 { max-width: 760px; font-size: clamp(38px, 5vw, 62px); line-height: 1.05; letter-spacing: -0.04em; margin-bottom: 18px; }
    .hero p { max-width: 720px; color: #ecfeff; line-height: 1.7; font-size: 18px; }
    .hero-card { background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.22); border-radius: 30px; padding: 18px; box-shadow: 0 25px 55px rgba(15,23,42,0.22); }
    .hero-card-inner { background: white; color: #0f172a; border-radius: 24px; padding: 24px; }
    .hero-card-inner h2 { font-size: 24px; margin-bottom: 8px; }
    .hero-card-inner p { color: #64748b; font-size: 15px; line-height: 1.6; }

    .main-container { max-width: 1240px; margin: auto; padding: 0 24px 64px; }
    .stats-grid { margin-top: -40px; position: relative; z-index: 4; display: grid; grid-template-columns: repeat(6, 1fr); gap: 14px; }
    .stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 22px; padding: 18px; box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08); }
    .stat-card strong { display: block; color: #155e75; font-size: 28px; }
    .stat-card span { display: block; color: #64748b; margin-top: 5px; font-weight: 800; font-size: 13px; }

    .status-box { margin-top: 22px; border-radius: 18px; padding: 14px 16px; font-weight: 800; line-height: 1.5; }
    .status-box.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .status-box.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    .section-grid { margin-top: 28px; display: grid; grid-template-columns: 1fr 1fr; gap: 22px; align-items: start; }
    .panel { background: white; border: 1px solid #e2e8f0; border-radius: 28px; overflow: hidden; box-shadow: 0 12px 28px rgba(15,23,42,0.06); }
    .panel-header { padding: 22px 24px; border-bottom: 1px solid #e2e8f0; }
    .panel-header p { color: #0e7490; font-weight: 900; margin-bottom: 8px; }
    .panel-header h2 { font-size: 25px; letter-spacing: -0.02em; }
    .panel-body { padding: 24px; }

    .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
    .tab-btn { border: 1px solid #bae6fd; background: white; color: #0e7490; border-radius: 999px; padding: 10px 14px; font-weight: 900; }
    .tab-btn.active { background: #0e7490; color: white; }
    .admin-form { display: none; gap: 14px; }
    .admin-form.active { display: grid; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .field label { display: block; color: #475569; font-size: 13px; font-weight: 900; margin-bottom: 7px; }
    .field input, .field select, .field textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 16px; padding: 12px 14px; outline: none; font-size: 15px; background: white; }
    .field textarea { min-height: 92px; resize: vertical; }
    .field input:focus, .field select:focus, .field textarea:focus { border-color: #0891b2; box-shadow: 0 0 0 3px rgba(8,145,178,0.12); }

    .list { display: grid; gap: 12px; max-height: 520px; overflow: auto; padding-right: 4px; }
    .list-item { border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 18px; padding: 14px; display: grid; grid-template-columns: 1fr auto; gap: 14px; align-items: center; }
    .list-item strong { display: block; color: #0f172a; margin-bottom: 4px; }
    .list-item span { display: block; color: #64748b; font-size: 13px; font-weight: 700; line-height: 1.4; }

    .wide-section { margin-top: 28px; }
    .reservations-panel .panel-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
    .reservations-panel .panel-header > div { min-width: 0; }
    .mini-page-badge { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; background: #ecfeff; color: #0e7490; border: 1px solid #bae6fd; padding: 8px 12px; font-size: 12px; font-weight: 900; white-space: nowrap; }
    .reservations-mini-page { max-height: 460px; overflow-y: auto; padding-right: 8px; scroll-behavior: smooth; }
    .reservations-mini-page::-webkit-scrollbar { width: 8px; }
    .reservations-mini-page::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    .reservations-mini-page::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 999px; }
    .reservations-mini-page .list { max-height: none; overflow: visible; }
    .reservations-destination-block { margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px solid #e2e8f0; }
    .reservations-destination-block:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
    .role-badge { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; background: #ecfeff; color: #0e7490; font-size: 12px; font-weight: 900; }
    .muted { color: #64748b; font-size: 13px; font-weight: 700; line-height: 1.5; }

    footer { border-top: 1px solid #e2e8f0; background: white; padding: 28px 24px; }
    .footer-content { max-width: 1240px; margin: auto; display: flex; justify-content: space-between; gap: 20px; color: #64748b; }
    .footer-links { display: flex; gap: 18px; }
    .footer-links button { border: none; background: transparent; color: #64748b; font-weight: 700; }
    .footer-links button:hover { color: #0e7490; }

    @media (max-width: 1100px) {
      .nav-links { display: none; }
      .hero-inner, .section-grid { grid-template-columns: 1fr; }
      .stats-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 640px) {
      .navbar, .footer-content, .form-row { flex-direction: column; align-items: stretch; grid-template-columns: 1fr; }
      .navbar { align-items: flex-start; }
      .nav-actions { width: 100%; justify-content: space-between; flex-wrap: wrap; }
      .hero-inner { padding: 44px 18px 64px; }
      .main-container { padding: 0 18px 48px; }
      .stats-grid { grid-template-columns: 1fr 1fr; }
      .list-item { grid-template-columns: 1fr; }
      .list-item form, .list-item button { width: 100%; }
      .reservations-panel .panel-header { flex-direction: column; align-items: flex-start; }
      .reservations-mini-page { max-height: 390px; }
      .notification-dropdown { right: -80px; width: 300px; }
    }
  </style>
</head>
<body>
  <header>
    <nav class="navbar">
      <button class="logo" onclick="window.location.href='Admin.php'">
        <span class="logo-icon">VV</span>
        <span>
          <span class="logo-title">VoyageVista</span>
          <span class="logo-subtitle">Administration du projet</span>
        </span>
      </button>

      <div class="nav-links">
        <button class="active" onclick="window.location.href='Admin.php'">Admin</button>
        <button onclick="window.location.href='Acceuil.php'">Accueil public</button>
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
              <span><?= $nombreNotifications > 0 ? h($nombreNotifications) . " nouvelle(s)" : "Aucune nouvelle" ?></span>
            </div>

            <?php if (count($notificationsPopup) === 0): ?>
              <button class="notification-item" onclick="window.location.href='Notifications.php'">
                <span class="notification-icon">🔔</span>
                <span><strong>Aucune notification</strong><small>Vous n’avez pas encore de notification.</small></span>
              </button>
            <?php else: ?>
              <?php foreach ($notificationsPopup as $notification): ?>
                <button class="notification-item" onclick="window.location.href='Notifications.php'">
                  <span class="notification-icon"><?= intval($notification["statut_lecture"] ?? 0) === 0 ? "🔔" : "📩" ?></span>
                  <span><strong><?= h($notification["titre"] ?? "Notification") ?></strong><small><?= h($notification["message"] ?? "") ?></small></span>
                </button>
              <?php endforeach; ?>
            <?php endif; ?>

            <button class="notification-all" onclick="window.location.href='Notifications.php'">Voir toutes les notifications</button>
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
    <section class="hero">
      <div class="hero-inner">
        <div>
          <div class="breadcrumb">VoyageVista &gt; Administration</div>
          <h1>Gestion avancée du catalogue et des destinations</h1>
          <p>
            Cette page est réservée aux profils admin et gestionnaire. Elle permet de gérer les offres touristiques,
            les réservations regroupées par destination, les voyageurs associés aux destinations et les rôles utilisateur.
          </p>
        </div>

        <div class="hero-card">
          <div class="hero-card-inner">
            <h2>Profil actif : <?= h($role) ?></h2>
            <p>
              Un utilisateur classique conserve l’accès normal au site. Un admin ou gestionnaire est redirigé ici après connexion.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="main-container">
      <div class="stats-grid">
        <div class="stat-card"><strong><?= h($stats["destinations"]) ?></strong><span>Destinations</span></div>
        <div class="stat-card"><strong><?= h($stats["transports"]) ?></strong><span>Transports</span></div>
        <div class="stat-card"><strong><?= h($stats["hebergements"]) ?></strong><span>Hébergements</span></div>
        <div class="stat-card"><strong><?= h($stats["activites"]) ?></strong><span>Activités</span></div>
        <div class="stat-card"><strong><?= h(count($reservations)) ?></strong><span>Réservations</span></div>
        <div class="stat-card"><strong><?= h($stats["utilisateurs"]) ?></strong><span>Utilisateurs</span></div>
      </div>

      <?php if ($messageAction !== ""): ?>
        <div class="status-box <?= h($typeMessageAction) ?>"><?= h($messageAction) ?></div>
      <?php endif; ?>

      <div class="section-grid">
        <section class="panel">
          <div class="panel-header">
            <p>Catalogue</p>
            <h2>Ajouter une offre touristique</h2>
          </div>
          <div class="panel-body">
            <div class="tabs">
              <button class="tab-btn active" type="button" onclick="showForm('destination', this)">Destination</button>
              <button class="tab-btn" type="button" onclick="showForm('transport', this)">Transport</button>
              <button class="tab-btn" type="button" onclick="showForm('hebergement', this)">Hébergement</button>
              <button class="tab-btn" type="button" onclick="showForm('activite', this)">Activité</button>
            </div>

            <form id="form-destination" class="admin-form active" method="POST">
              <input type="hidden" name="action" value="ajouter_destination">
              <div class="form-row">
                <div class="field"><label>Nom</label><input name="nom_destination" required></div>
                <div class="field"><label>Pays</label><input name="pays" required></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Catégorie</label><input name="categorie" placeholder="plage, culture..."></div>
                <div class="field"><label>Prix</label><input name="prix" type="number" step="0.01" min="0" required></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Note</label><input name="note_moyenne" type="number" step="0.1" min="0" max="5" value="4.5"></div>
                <div class="field"><label>Durée</label><input name="duree" type="number" min="1" value="7"></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Saison</label><input name="saison" placeholder="ete, hiver..."></div>
                <div class="field"><label>Recommandation</label><input name="recommande" type="number" min="1" value="5"></div>
              </div>
              <div class="field"><label>Image URL</label><input name="image"></div>
              <div class="field"><label>Tags</label><input name="tags" placeholder="famille, soleil, budget"></div>
              <div class="field"><label>Styles</label><input name="styles" placeholder="detente, aventure"></div>
              <div class="field"><label>Description</label><textarea name="description"></textarea></div>
              <button class="primary-btn" type="submit">Ajouter la destination</button>
            </form>

            <form id="form-transport" class="admin-form" method="POST">
              <input type="hidden" name="action" value="ajouter_transport">
              <div class="form-row">
                <div class="field"><label>Type</label><select name="type"><option value="avion">Avion</option><option value="train">Train</option><option value="bus">Bus</option><option value="voiture">Voiture</option></select></div>
                <div class="field"><label>Icône</label><input name="icone" placeholder="✈️"></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Compagnie</label><input name="compagnie" required></div>
                <div class="field"><label>Prix</label><input name="prix" type="number" step="0.01" min="0" required></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Ville départ</label><input name="ville_depart" required></div>
                <div class="field"><label>Ville arrivée</label><input name="ville_arrivee" required></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Date départ</label><input name="date_depart" type="date"></div>
                <div class="field"><label>Date retour</label><input name="date_retour" type="date"></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Heure départ</label><input name="heure_depart" type="time"></div>
                <div class="field"><label>Heure arrivée</label><input name="heure_arrivee" type="time"></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Durée en heures</label><input name="duree" type="number" step="0.1" min="0"></div>
                <div class="field"><label>Places disponibles</label><input name="places_disponibles" type="number" min="0" required></div>
              </div>
              <div class="field"><label>Options</label><input name="options" placeholder="bagage, wifi"></div>
              <div class="field"><label>Tags</label><input name="tags" placeholder="rapide, économique"></div>
              <div class="field"><label>Description</label><textarea name="description"></textarea></div>
              <button class="primary-btn" type="submit">Ajouter le transport</button>
            </form>

            <form id="form-hebergement" class="admin-form" method="POST">
              <input type="hidden" name="action" value="ajouter_hebergement">
              <div class="form-row">
                <div class="field"><label>Nom</label><input name="nom" required></div>
                <div class="field"><label>Type</label><input name="type" placeholder="hotel, appartement..." required></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Destination</label><input name="destination" required></div>
                <div class="field"><label>Pays</label><input name="pays"></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Capacité</label><input name="capacite" type="number" min="1" value="2"></div>
                <div class="field"><label>Prix / nuit</label><input name="prix" type="number" step="0.01" min="0" required></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Note</label><input name="note" type="number" step="0.1" min="0" max="5" value="4.5"></div>
                <div class="field"><label>Étoiles</label><input name="etoiles" value="★★★★☆"></div>
              </div>
              <div class="field"><label>Adresse</label><input name="adresse"></div>
              <div class="field"><label>Disponibilité</label><input name="disponibilite" value="Disponible"></div>
              <div class="field"><label>Image URL</label><input name="image"></div>
              <div class="field"><label>Équipements</label><input name="equipements" placeholder="Wi-Fi, piscine, parking"></div>
              <div class="field"><label>Tags</label><input name="tags" placeholder="centre-ville, petit-déjeuner"></div>
              <div class="field"><label>Description</label><textarea name="description"></textarea></div>
              <button class="primary-btn" type="submit">Ajouter l’hébergement</button>
            </form>

            <form id="form-activite" class="admin-form" method="POST">
              <input type="hidden" name="action" value="ajouter_activite">
              <div class="form-row">
                <div class="field"><label>Nom</label><input name="nom" required></div>
                <div class="field"><label>Destination</label><input name="destination" required></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Catégorie</label><input name="categorie" placeholder="sport, culture..." required></div>
                <div class="field"><label>Niveau</label><input name="niveau" placeholder="facile, moyen..."></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Moment</label><input name="moment" placeholder="matin, soir..."></div>
                <div class="field"><label>Durée</label><input name="duree" type="number" step="0.5" min="0"></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Prix</label><input name="prix" type="number" step="0.01" min="0" required></div>
                <div class="field"><label>Places disponibles</label><input name="places_disponibles" type="number" min="0" required></div>
              </div>
              <div class="field"><label>Note</label><input name="note" type="number" step="0.1" min="0" max="5" value="4.5"></div>
              <div class="field"><label>Image URL</label><input name="image"></div>
              <div class="field"><label>Description</label><textarea name="description"></textarea></div>
              <button class="primary-btn" type="submit">Ajouter l’activité</button>
            </form>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <p>Catalogue</p>
            <h2>Supprimer une offre</h2>
          </div>
          <div class="panel-body">
            <div class="list">
              <?php foreach (["destination" => $destinations, "transport" => $transports, "hebergement" => $hebergements, "activite" => $activites] as $type => $items): ?>
                <?php $config = $catalogueConfig[$type]; ?>
                <?php foreach ($items as $item): ?>
                  <?php
                    $id = intval($item[$config["id"]] ?? 0);
                    $nom = $item[$config["nom"]] ?? ($config["label"] . " #" . $id);
                    if ($type === "transport") {
                        $nom = ($item["ville_depart"] ?? "") . " → " . ($item["ville_arrivee"] ?? "") . " · " . ($item["compagnie"] ?? "");
                    }
                  ?>
                  <div class="list-item">
                    <div>
                      <strong><?= h($config["label"]) ?> — <?= h($nom) ?></strong>
                      <span>ID <?= h($id) ?><?php if (isset($item["prix"])): ?> · <?= h(formatPrix($item["prix"])) ?><?php endif; ?></span>
                    </div>
                    <form method="POST" onsubmit="return confirm('Supprimer cette offre ?');">
                      <input type="hidden" name="action" value="supprimer_offre">
                      <input type="hidden" name="type_offre" value="<?= h($type) ?>">
                      <input type="hidden" name="id_offre" value="<?= h($id) ?>">
                      <button class="small-btn remove" type="submit">Supprimer</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      </div>

      <section class="panel wide-section reservations-panel">
        <div class="panel-header">
          <div>
            <p>Réservations par destination</p>
            <h2>Suivi des réservations clients</h2>
          </div>
          <span class="mini-page-badge">Zone défilante</span>
        </div>
        <div class="panel-body">
          <div class="reservations-mini-page" aria-label="Liste des réservations clients par destination">
            <?php if (count($reservationsParDestination) === 0): ?>
              <p class="muted">Aucune réservation n’a encore été trouvée.</p>
            <?php endif; ?>

            <?php foreach ($reservationsParDestination as $destinationNom => $reservationsDestination): ?>
              <div class="list reservations-destination-block">
              <div class="list-item" style="background:#ecfeff;border-color:#bae6fd;">
                <div>
                  <strong>Destination — <?= h($destinationNom) ?></strong>
                  <span><?= h(count($reservationsDestination)) ?> réservation(s) associée(s)</span>
                </div>
                <span class="role-badge">Destination</span>
              </div>

              <?php foreach ($reservationsDestination as $reservation): ?>
                <div class="list-item">
                  <div>
                    <strong>
                      <?= h($reservation["type"] ?? "Réservation") ?> — <?= h($reservation["nom_element"] ?? "Élément") ?>
                    </strong>
                    <span>
                      Réservé par <?= h($reservation["client"] ?? "Utilisateur inconnu") ?>
                      <?php if (!empty($reservation["email"])): ?> · <?= h($reservation["email"]) ?><?php endif; ?>
                    </span>
                    <span>
                      ID réservation <?= h($reservation["id_reservation"] ?? 0) ?>
                      · ID élément <?= h($reservation["id_element"] ?? 0) ?>
                      · quantité <?= h($reservation["quantite"] ?? 1) ?>
                      · statut <?= h($reservation["statut"] ?? "confirmee") ?>
                    </span>
                    <span>
                      <?= h($reservation["details"] ?? "") ?>
                      <?php if (!empty($reservation["periode"])): ?> · <?= h($reservation["periode"]) ?><?php endif; ?>
                    </span>
                    <span>
                      Source : <?= h($reservation["source"] ?? "") ?>
                      <?php if (!empty($reservation["date_creation"])): ?> · créée le <?= h(formatDateFr($reservation["date_creation"])) ?><?php endif; ?>
                    </span>
                  </div>

                  <span class="role-badge"><?= h($reservation["type"] ?? "Réservation") ?></span>
                </div>
              <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <div class="section-grid">
        <section class="panel">
          <div class="panel-header">
            <p>Voyageurs</p>
            <h2>Associer un voyageur à une destination</h2>
          </div>
          <div class="panel-body">
            <?php if (!tableExiste($pdo, "destination_voyageur")): ?>
              <p class="muted">Importez le patch SQL destination_voyageur pour activer cette partie.</p>
            <?php else: ?>
              <form class="admin-form active" method="POST">
                <input type="hidden" name="action" value="ajouter_voyageur">
                <div class="field">
                  <label>Destination</label>
                  <select name="id_destination" required>
                    <?php foreach ($destinations as $destination): ?>
                      <option value="<?= h($destination["id_destination"] ?? 0) ?>">
                        <?= h($destination["nom_destination"] ?? "Destination") ?><?php if (!empty($destination["pays"])): ?> · <?= h($destination["pays"]) ?><?php endif; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field"><label>Email du voyageur</label><input name="email_voyageur" type="email" required></div>
                <button class="primary-btn" type="submit">Ajouter le voyageur</button>
              </form>

              <br>

              <div class="list">
                <?php foreach ($voyageursAssocies as $assoc): ?>
                  <div class="list-item">
                    <div>
                      <strong>
                        <?= h($assoc["nom_destination"] ?? "Destination") ?><?php if (!empty($assoc["pays"])): ?> · <?= h($assoc["pays"]) ?><?php endif; ?>
                      </strong>
                      <span><?= h(trim(($assoc["prenom"] ?? "") . " " . ($assoc["nom"] ?? ""))) ?> · <?= h($assoc["email"] ?? "") ?></span>
                    </div>
                    <form method="POST">
                      <input type="hidden" name="action" value="retirer_voyageur">
                      <input type="hidden" name="id_destination_voyageur" value="<?= h($assoc["id_destination_voyageur"] ?? 0) ?>">
                      <button class="small-btn remove" type="submit">Retirer</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <p>Profils</p>
            <h2>Administrateurs et gestionnaires</h2>
          </div>
          <div class="panel-body">
            <?php if (!$estAdmin): ?>
              <p class="muted">Seul un administrateur peut modifier les rôles. Le gestionnaire peut gérer le catalogue, les destinations et les réservations.</p>
            <?php endif; ?>

            <div class="list">
              <?php foreach ($utilisateurs as $userRow): ?>
                <div class="list-item">
                  <div>
                    <strong><?= h(trim(($userRow["prenom"] ?? "") . " " . ($userRow["nom"] ?? ""))) ?></strong>
                    <span><?= h($userRow["email"] ?? "") ?> · <span class="role-badge"><?= h($userRow["role"] ?? "client") ?></span></span>
                  </div>

                  <?php if ($estAdmin): ?>
                    <form method="POST">
                      <input type="hidden" name="action" value="changer_role">
                      <input type="hidden" name="id_utilisateur_cible" value="<?= h($userRow["id_utilisateur"] ?? 0) ?>">
                      <select name="nouveau_role" style="border:1px solid #cbd5e1;border-radius:999px;padding:8px 10px;font-weight:800;">
                        <option value="client" <?= ($userRow["role"] ?? "") === "client" ? "selected" : "" ?>>client</option>
                        <option value="gestionnaire" <?= ($userRow["role"] ?? "") === "gestionnaire" ? "selected" : "" ?>>gestionnaire</option>
                        <option value="admin" <?= ($userRow["role"] ?? "") === "admin" ? "selected" : "" ?>>admin</option>
                      </select>
                      <button class="small-btn info" type="submit">Appliquer</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      </div>
    </section>
  </main>

  <footer>
    <div class="footer-content">
      <p>© 2026 VoyageVista — Administration</p>
      <div class="footer-links">
        <button onclick="window.location.href='Contact.php'">Contact</button>
        <button onclick="window.location.href='api/logout.php'">Déconnexion</button>
      </div>
    </div>
  </footer>

  <script>
    function showForm(type, button) {
      document.querySelectorAll('.admin-form').forEach((form) => form.classList.remove('active'));
      document.querySelectorAll('.tab-btn').forEach((btn) => btn.classList.remove('active'));

      const form = document.getElementById('form-' + type);

      if (form) {
        form.classList.add('active');
      }

      if (button) {
        button.classList.add('active');
      }
    }
  </script>
</body>
</html>
