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

function calculerNuits($dateArrivee, $dateDepart) {
    if (empty($dateArrivee) || empty($dateDepart)) {
        return 0;
    }

    try {
        $arrivee = new DateTime($dateArrivee);
        $depart = new DateTime($dateDepart);

        if ($depart <= $arrivee) {
            return 0;
        }

        return intval($arrivee->diff($depart)->days);
    } catch (Exception $e) {
        return 0;
    }
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

function lienVoirElement($ligne) {
    $type = $ligne["type_element"] ?? "";
    $idElement = intval($ligne["id_element"] ?? 0);

    $typesAutorises = ["destination", "transport", "hebergement", "activite"];

    if (!in_array($type, $typesAutorises, true)) {
        return "Destination.php";
    }

    $lien = "Voir.php?type=" . urlencode($type) . "&id=" . $idElement;

    if ($type === "hebergement") {
        $dateArrivee = $ligne["date_arrivee"] ?? "";
        $dateDepart = $ligne["date_depart"] ?? "";

        if (!empty($dateArrivee) && !empty($dateDepart)) {
            $lien .= "&arrivee=" . urlencode($dateArrivee);
            $lien .= "&depart=" . urlencode($dateDepart);
        }
    }

    return $lien;
}

function imageParDefaut($type) {
    if ($type === "transport") {
        return "https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=900&q=80";
    }

    if ($type === "hebergement") {
        return "https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80";
    }

    if ($type === "activite") {
        return "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80";
    }

    return "https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=900&q=80";
}

function jsonToArraySafe($value) {
    if (empty($value)) {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function enrichirLignePanier($pdo, $ligne) {
    $type = $ligne["type_element"] ?? "";
    $idElement = intval($ligne["id_element"] ?? 0);

    $details = [
        "image" => imageParDefaut($type),
        "description" => "Élément ajouté à votre panier de voyage.",
        "tags" => [],
        "transport" => []
    ];

    try {
        if ($type === "destination") {
            $sql = "SELECT * FROM destination WHERE id_destination = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idElement]);
            $element = $stmt->fetch();

            if ($element) {
                $details["image"] = !empty($element["image"]) ? $element["image"] : $details["image"];
                $details["description"] = $element["description"] ?? $details["description"];
                $details["tags"] = jsonToArraySafe($element["tags"] ?? "");
                $details["tags"][] = $element["pays"] ?? "";
                $details["tags"][] = ($element["duree"] ?? "7") . " jours";
                $details["tags"][] = "Note " . number_format(floatval($element["note_moyenne"] ?? 0), 1, ",", " ") . "/5";
            }
        }

        if ($type === "transport") {
            $sql = "SELECT * FROM transport WHERE id_transport = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idElement]);
            $element = $stmt->fetch();

            if ($element) {
                $details["image"] = !empty($element["image"]) ? $element["image"] : $details["image"];
                $details["description"] = $element["description"] ?? ($element["compagnie"] ?? $details["description"]);

                $details["tags"] = jsonToArraySafe($element["tags"] ?? "");
                $details["tags"][] = ucfirst($element["type"] ?? "Transport");
                $details["tags"][] = ($element["ville_depart"] ?? "") . " → " . ($element["ville_arrivee"] ?? "");

                if (!empty($element["date_depart"])) {
                    $details["tags"][] = "Départ " . formatDateFr($element["date_depart"]);
                }

                if (!empty($element["heure_depart"])) {
                    $details["tags"][] = "À " . formatHeure($element["heure_depart"]);
                }

                $details["transport"] = [
                    "type" => $element["type"] ?? "",
                    "compagnie" => $element["compagnie"] ?? "",
                    "ville_depart" => $element["ville_depart"] ?? "",
                    "ville_arrivee" => $element["ville_arrivee"] ?? "",
                    "date_depart" => $element["date_depart"] ?? "",
                    "date_retour" => $element["date_retour"] ?? "",
                    "heure_depart" => formatHeure($element["heure_depart"] ?? ""),
                    "heure_arrivee" => formatHeure($element["heure_arrivee"] ?? ""),
                    "duree" => $element["duree"] ?? ""
                ];
            }
        }

        if ($type === "hebergement") {
            $sql = "SELECT * FROM hebergement WHERE id_hebergement = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idElement]);
            $element = $stmt->fetch();

            if ($element) {
                $details["image"] = !empty($element["image"]) ? $element["image"] : $details["image"];
                $details["description"] = $element["description"] ?? $details["description"];
                $details["tags"] = jsonToArraySafe($element["tags"] ?? "");

                if (empty($details["tags"])) {
                    $details["tags"][] = ucfirst($element["type"] ?? "Hébergement");
                    $details["tags"][] = $element["destination"] ?? "";
                    $details["tags"][] = intval($element["capacite"] ?? 0) . " pers.";
                    $details["tags"][] = "Note " . number_format(floatval($element["note"] ?? 0), 1, ",", " ") . "/5";
                }
            }
        }

        if ($type === "activite") {
            $sql = "SELECT * FROM activite WHERE id_activite = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idElement]);
            $element = $stmt->fetch();

            if ($element) {
                $details["image"] = !empty($element["image"]) ? $element["image"] : $details["image"];
                $details["description"] = $element["description"] ?? $details["description"];
                $details["tags"] = jsonToArraySafe($element["tags"] ?? "");
                $details["tags"][] = ucfirst($element["categorie"] ?? "Activité");
                $details["tags"][] = $element["destination"] ?? "";
            }
        }
    } catch (PDOException $e) {
        return $details;
    }

    $details["tags"] = array_values(array_filter($details["tags"], function ($tag) {
        return trim($tag ?? "") !== "";
    }));

    return $details;
}

if (!isset($_SESSION["user_id"])) {
    header("Location: Connexion.php?erreur=connexion_requise");
    exit;
}

$estConnecte = true;
$idUtilisateur = intval($_SESSION["user_id"]);

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
    session_destroy();
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
    $sqlCreatePanier = "INSERT INTO panier (id_utilisateur, date_creation) VALUES (?, NOW())";
    $stmtCreatePanier = $pdo->prepare($sqlCreatePanier);
    $stmtCreatePanier->execute([$idUtilisateur]);
    $idPanier = intval($pdo->lastInsertId());
} else {
    $idPanier = intval($panier["id_panier"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "supprimer") {
        $idLigne = intval($_POST["id_ligne"] ?? 0);

        if ($idLigne > 0) {
            $sql = "
                DELETE lp
                FROM ligne_panier lp
                JOIN panier p ON lp.id_panier = p.id_panier
                WHERE lp.id_ligne = ?
                AND p.id_utilisateur = ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idLigne, $idUtilisateur]);
        }

        header("Location: Panier.php");
        exit;
    }

    if ($action === "modifier_quantite") {
        $idLigne = intval($_POST["id_ligne"] ?? 0);
        $quantite = intval($_POST["quantite"] ?? 1);

        if (isset($_POST["nouvelle_quantite"])) {
            $quantite = intval($_POST["nouvelle_quantite"]);
        }

        if ($quantite < 1) {
            $quantite = 1;
        }

        if ($idLigne > 0) {
            $sqlType = "
                SELECT lp.type_element
                FROM ligne_panier lp
                JOIN panier p ON lp.id_panier = p.id_panier
                WHERE lp.id_ligne = ?
                AND p.id_utilisateur = ?
            ";

            $stmtType = $pdo->prepare($sqlType);
            $stmtType->execute([$idLigne, $idUtilisateur]);
            $ligneType = $stmtType->fetch();

            if ($ligneType && ($ligneType["type_element"] ?? "") !== "hebergement") {
                $sql = "
                    UPDATE ligne_panier lp
                    JOIN panier p ON lp.id_panier = p.id_panier
                    SET lp.quantite = ?
                    WHERE lp.id_ligne = ?
                    AND p.id_utilisateur = ?
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$quantite, $idLigne, $idUtilisateur]);
            }
        }

        header("Location: Panier.php");
        exit;
    }

    if ($action === "vider") {
        $sql = "DELETE FROM ligne_panier WHERE id_panier = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idPanier]);

        header("Location: Panier.php");
        exit;
    }
}

$sqlLignes = "
    SELECT lp.*
    FROM ligne_panier lp
    JOIN panier p ON lp.id_panier = p.id_panier
    WHERE p.id_utilisateur = ?
    ORDER BY lp.id_ligne DESC
";

$stmtLignes = $pdo->prepare($sqlLignes);
$stmtLignes->execute([$idUtilisateur]);
$lignes = $stmtLignes->fetchAll();

$sousTotal = 0;
$nombreSelections = count($lignes);
$nombreQuantites = 0;
$nombreNuitsHebergement = 0;
$nombreHebergementsSansDates = 0;
$lignesEnrichies = [];

foreach ($lignes as $ligne) {
    $typeElement = $ligne["type_element"] ?? "";
    $quantite = intval($ligne["quantite"] ?? 1);
    $prixUnitaire = floatval($ligne["prix_unitaire"] ?? 0);
    $dateArrivee = $ligne["date_arrivee"] ?? null;
    $dateDepart = $ligne["date_depart"] ?? null;
    $nbNuits = intval($ligne["nb_nuits"] ?? 0);

    if ($typeElement === "hebergement") {
        if ($nbNuits <= 0) {
            $nbNuits = calculerNuits($dateArrivee, $dateDepart);
        }

        if ($nbNuits <= 0) {
            $nbNuits = $quantite > 0 ? $quantite : 1;
        }

        $quantiteCalculee = $nbNuits;
        $ligne["quantite_calculee"] = $quantiteCalculee;
        $ligne["nb_nuits_calcule"] = $nbNuits;

        if (empty($dateArrivee) || empty($dateDepart) || $nbNuits <= 0) {
            $nombreHebergementsSansDates++;
        }

        $nombreNuitsHebergement += max($nbNuits, 0);
    } else {
        $quantiteCalculee = max($quantite, 1);
        $ligne["quantite_calculee"] = $quantiteCalculee;
        $ligne["nb_nuits_calcule"] = 0;
    }

    $totalLigne = $prixUnitaire * $quantiteCalculee;

    $ligne["total_ligne"] = $totalLigne;
    $ligne["details"] = enrichirLignePanier($pdo, $ligne);

    $sousTotal += $totalLigne;
    $nombreQuantites += $quantiteCalculee;

    $lignesEnrichies[] = $ligne;
}

$frais = $sousTotal > 0 ? 19 : 0;
$totalFinal = $sousTotal + $frais;
$panierPretPaiement = count($lignesEnrichies) > 0 && $nombreHebergementsSansDates === 0;
$nombreElementsPanier = $nombreQuantites;

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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>VoyageVista - Panier</title>

  

    .item-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .summary-body {
      padding: 24px;
    }

    .summary-line {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      color: #475569;
      font-weight: 800;
      padding: 13px 0;
      border-bottom: 1px solid #e2e8f0;
    }

    .summary-line strong {
      color: #0f172a;
    }

    .summary-total {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      padding-top: 18px;
      font-size: 24px;
      font-weight: 900;
    }

    .summary-total strong {
      color: #155e75;
    }

    .promo-box {
      margin-top: 22px;
      padding: 18px;
      border-radius: 22px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
    }

    .promo-box h3 {
      font-size: 18px;
      margin-bottom: 12px;
    }

    .promo-row {
      display: flex;
      gap: 10px;
    }

    .promo-row input {
      flex: 1;
      min-width: 0;
      border: 1px solid #cbd5e1;
      border-radius: 999px;
      padding: 11px 14px;
      outline: none;
    }

    .promo-message {
      margin-top: 10px;
      font-weight: 800;
      font-size: 13px;
      line-height: 1.4;
    }

    .promo-message.success {
      color: #047857;
    }

    .promo-message.error {
      color: #dc2626;
    }

    .checkout-actions {
      margin-top: 22px;
      display: grid;
      gap: 12px;
    }

    .checkout-actions button,
    .checkout-actions form,
    .checkout-actions form button {
      width: 100%;
    }

    .secure-box {
      margin-top: 18px;
      display: grid;
      gap: 10px;
      color: #64748b;
      font-size: 13px;
      font-weight: 700;
      line-height: 1.5;
    }

    .warning-box {
      margin-top: 16px;
      padding: 14px 16px;
      border-radius: 18px;
      background: #fff7ed;
      border: 1px solid #fed7aa;
      color: #c2410c;
      font-weight: 800;
      line-height: 1.5;
      font-size: 14px;
    }

    .empty-cart {
      padding: 42px 24px;
      text-align: center;
      color: #64748b;
    }

    .empty-cart strong {
      display: block;
      color: #0f172a;
      font-size: 24px;
      margin-bottom: 10px;
    }

    .empty-cart p {
      line-height: 1.6;
      margin-bottom: 20px;
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
      .cart-layout {
        grid-template-columns: 1fr;
      }

      .summary-panel {
        position: static;
      }
    }

    @media (max-width: 640px) {
      .navbar,
      .footer-content,
      .panel-header,
      .cart-top,
      .cart-bottom {
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

      .cart-item {
        grid-template-columns: 1fr;
      }

      .cart-price {
        text-align: left;
      }

      .item-actions {
        justify-content: stretch;
      }

      .item-actions button,
      .item-actions form,
      .item-actions form button,
      .promo-row button {
        width: 100%;
      }

      .promo-row {
        flex-direction: column;
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

        <button class="icon-btn active" onclick="window.location.href='Panier.php'" aria-label="Panier">
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
          <div class="breadcrumb">VoyageVista &gt; Panier</div>

          <h1>Votre panier de voyage</h1>

          <p>
            Vérifiez vos sélections avant le paiement. Les transports affichent maintenant
            le trajet, les dates, les horaires et la durée.
          </p>
        </div>

        <div class="hero-summary-card">
          <div class="hero-summary-inner">
            <h2>Résumé du panier</h2>

            <p>Le détail sera repris lors de la validation de votre réservation.</p>

            <div class="mini-summary-line">
              <span>Sélections différentes</span>
              <strong><?= h($nombreSelections) ?></strong>
            </div>

            <div class="mini-summary-line">
              <span>Nuits d’hébergement</span>
              <strong><?= h($nombreNuitsHebergement) ?></strong>
            </div>

            <div class="mini-summary-line">
              <span>Total actuel</span>
              <strong><?= h(formatPrix($totalFinal)) ?></strong>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="main-container">
      <div class="cart-layout">
        <section class="cart-panel">
          <div class="panel-header">
            <div>
              <h2>Votre sélection</h2>
              <p><?= h($nombreSelections) ?> sélection(s) dans le panier</p>
            </div>

            <?php if (count($lignesEnrichies) > 0): ?>
              <form method="POST">
                <input type="hidden" name="action" value="vider">
                <button class="danger-btn" type="submit">Vider le panier</button>
              </form>
            <?php endif; ?>
          </div>

          <?php if (count($lignesEnrichies) === 0): ?>
            <div class="empty-cart">
              <strong>Votre panier est vide</strong>
              <p>Ajoutez une destination, un transport, un hébergement ou une activité pour construire votre voyage.</p>
              <button class="primary-btn" onclick="window.location.href='Destination.php'">Explorer les destinations</button>
            </div>
          <?php else: ?>
            <div class="cart-list">
              <?php foreach ($lignesEnrichies as $ligne): ?>
                <?php
                  $typeElement = $ligne["type_element"] ?? "";
                  $typeLisible = afficherTypeElement($typeElement);
                  $quantiteActuelle = intval($ligne["quantite_calculee"] ?? 1);
                  $prixUnitaire = floatval($ligne["prix_unitaire"] ?? 0);
                  $totalLigne = floatval($ligne["total_ligne"] ?? ($prixUnitaire * $quantiteActuelle));
                  $details = $ligne["details"] ?? [];
                  $dateArrivee = $ligne["date_arrivee"] ?? "";
                  $dateDepart = $ligne["date_depart"] ?? "";
                  $nbNuits = intval($ligne["nb_nuits_calcule"] ?? 0);
                  $lienVoir = lienVoirElement($ligne);
                  $estHebergement = $typeElement === "hebergement";
                  $estTransport = $typeElement === "transport";
                  $transportInfo = $details["transport"] ?? [];
                  $hebergementAvecDates = !$estHebergement || (!empty($dateArrivee) && !empty($dateDepart) && $nbNuits > 0);
                ?>

                <article class="cart-item">
                  <div class="cart-image" style="background-image: url('<?= h($details["image"] ?? imageParDefaut($typeElement)) ?>')">
                    <span class="cart-type"><?= h($typeLisible) ?></span>
                  </div>

                  <div class="cart-content">
                    <div class="cart-top">
                      <div class="cart-title">
                        <h3><?= h($ligne["nom_element"] ?? "Élément") ?></h3>
                        <p><?= h($details["description"] ?? "") ?></p>
                      </div>

                      <div class="cart-price">
                        <strong><?= h(formatPrix($totalLigne)) ?></strong>

                        <?php if ($estHebergement): ?>
                          <span><?= h(formatPrix($prixUnitaire)) ?> / nuit</span>
                        <?php else: ?>
                          <span><?= h(formatPrix($prixUnitaire)) ?> / unité</span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <?php if ($estHebergement): ?>
                      <?php if ($hebergementAvecDates): ?>
                        <div class="stay-box">
                          <span>Arrivée : <?= h(formatDateFr($dateArrivee)) ?></span>
                          <span>Départ : <?= h(formatDateFr($dateDepart)) ?></span>
                          <span>Durée : <?= h($nbNuits) ?> nuit(s)</span>
                        </div>
                      <?php else: ?>
                        <div class="stay-box warning">
                          <span>Dates manquantes pour cet hébergement.</span>
                          <span>Retournez sur la page détail pour choisir une arrivée et un départ.</span>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($estTransport): ?>
                      <?php if (!empty($transportInfo["date_depart"])): ?>
                        <div class="transport-box">
                          <span>
                            Trajet :
                            <?= h($transportInfo["ville_depart"] ?? "") ?>
                            →
                            <?= h($transportInfo["ville_arrivee"] ?? "") ?>
                          </span>

                          <span>
                            Départ :
                            <?= h(formatDateFr($transportInfo["date_depart"] ?? "")) ?>
                            à
                            <?= h($transportInfo["heure_depart"] ?? "Non précisée") ?>
                          </span>

                          <?php if (!empty($transportInfo["heure_arrivee"])): ?>
                            <span>
                              Arrivée :
                              <?= h($transportInfo["heure_arrivee"]) ?>
                            </span>
                          <?php endif; ?>

                          <?php if (!empty($transportInfo["date_retour"])): ?>
                            <span>
                              Retour :
                              <?= h(formatDateFr($transportInfo["date_retour"])) ?>
                            </span>
                          <?php endif; ?>

                          <?php if (!empty($transportInfo["duree"])): ?>
                            <span>
                              Durée :
                              <?= h($transportInfo["duree"]) ?> h
                            </span>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <div class="transport-box warning">
                          <span>Les informations de date ou d’horaire ne sont pas renseignées pour ce transport.</span>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>

                    <div class="cart-tags">
                      <?php foreach (array_slice($details["tags"] ?? [], 0, 5) as $tag): ?>
                        <span><?= h($tag) ?></span>
                      <?php endforeach; ?>

                      <?php if ($estHebergement && $hebergementAvecDates): ?>
                        <span><?= h($nbNuits) ?> nuit(s)</span>
                      <?php endif; ?>
                    </div>

                    <div class="cart-bottom">
                      <?php if ($estHebergement): ?>
                        <div class="quantity-box">
                          <span class="fixed-quantity">
                            Quantité fixée par les dates : <?= h($nbNuits > 0 ? $nbNuits : 0) ?> nuit(s)
                          </span>
                        </div>
                      <?php else: ?>
                        <div class="quantity-box">
                          <?php if ($quantiteActuelle > 1): ?>
                            <form method="POST">
                              <input type="hidden" name="action" value="modifier_quantite">
                              <input type="hidden" name="id_ligne" value="<?= h($ligne["id_ligne"]) ?>">
                              <input type="hidden" name="nouvelle_quantite" value="<?= h($quantiteActuelle - 1) ?>">
                              <button class="small-btn info" type="submit">−</button>
                            </form>
                          <?php else: ?>
                            <button class="small-btn" type="button" disabled>−</button>
                          <?php endif; ?>

                          <form method="POST">
                            <input type="hidden" name="action" value="modifier_quantite">
                            <input type="hidden" name="id_ligne" value="<?= h($ligne["id_ligne"]) ?>">
                            <input type="number" name="quantite" min="1" value="<?= h($quantiteActuelle) ?>">
                            <button class="small-btn info" type="submit">OK</button>
                          </form>

                          <form method="POST">
                            <input type="hidden" name="action" value="modifier_quantite">
                            <input type="hidden" name="id_ligne" value="<?= h($ligne["id_ligne"]) ?>">
                            <input type="hidden" name="nouvelle_quantite" value="<?= h($quantiteActuelle + 1) ?>">
                            <button class="small-btn info" type="submit">+</button>
                          </form>
                        </div>
                      <?php endif; ?>

                      <div class="item-actions">
                        <button class="small-btn info" type="button" onclick="window.location.href='<?= h($lienVoir) ?>'">
                          <?= $estHebergement ? "Modifier / Voir" : "Voir" ?>
                        </button>

                        <form method="POST">
                          <input type="hidden" name="action" value="supprimer">
                          <input type="hidden" name="id_ligne" value="<?= h($ligne["id_ligne"]) ?>">
                          <button class="small-btn remove" type="submit">Retirer</button>
                        </form>
                      </div>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <aside class="summary-panel">
          <div class="panel-header">
            <div>
              <h2>Récapitulatif</h2>
              <p>Total avant validation</p>
            </div>
          </div>

          <div class="summary-body">
            <div class="summary-line">
              <span>Sélections</span>
              <strong><?= h($nombreSelections) ?></strong>
            </div>

            <div class="summary-line">
              <span>Quantités / nuits</span>
              <strong><?= h($nombreQuantites) ?></strong>
            </div>

            <div class="summary-line">
              <span>Sous-total</span>
              <strong id="subtotalAmount"><?= h(formatPrix($sousTotal)) ?></strong>
            </div>

            <div class="summary-line">
              <span>Réduction</span>
              <strong id="discountAmount"><?= h(formatPrix(0)) ?></strong>
            </div>

            <div class="summary-line">
              <span>Frais de dossier</span>
              <strong id="feesAmount"><?= h(formatPrix($frais)) ?></strong>
            </div>

            <div class="promo-box">
              <h3>Code promotionnel</h3>

              <div class="promo-row">
                <input id="promoCode" type="text" placeholder="VOYAGE10 ou ETE15">
                <button class="secondary-btn" onclick="appliquerCodePromo()" type="button">Appliquer</button>
              </div>

              <div id="promoMessage" class="promo-message"></div>
            </div>

            <div class="summary-total">
              <span>Total</span>
              <strong id="totalAmount"><?= h(formatPrix($totalFinal)) ?></strong>
            </div>

            <?php if ($nombreHebergementsSansDates > 0): ?>
              <div class="warning-box">
                <?= h($nombreHebergementsSansDates) ?> hébergement(s) n’ont pas de dates valides.
                Choisissez les dates avant de valider le paiement.
              </div>
            <?php endif; ?>

            <div class="checkout-actions">
              <?php if (count($lignesEnrichies) > 0): ?>
                <?php if ($panierPretPaiement): ?>
                  <form action="Paiement.php" method="GET">
                    <button class="primary-btn" type="submit">Valider la réservation</button>
                  </form>
                <?php else: ?>
                  <button class="primary-btn" type="button" disabled>
                    Dates d’hébergement à compléter
                  </button>
                <?php endif; ?>
              <?php endif; ?>

              <button class="secondary-btn" onclick="window.location.href='Destination.php'" type="button">
                Continuer mes achats
              </button>
            </div>

            <div class="secure-box">
              <span>🔐 Paiement simulé sécurisé</span>
              <span>🧾 Les dates d’hébergement et les horaires de transport seront repris dans la validation.</span>
              <span>💡 Codes test : <strong>VOYAGE10</strong> ou <strong>ETE15</strong></span>
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

  
</body>
</html>
