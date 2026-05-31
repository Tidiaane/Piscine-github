<?php
session_start();
require_once "api/db.php";

/* =========================
   PROTECTION DE LA PAGE
========================= */
if (!isset($_SESSION["user_id"])) {
    header("Location: Connexion.php?erreur=connexion_requise");
    exit;
}

$idUtilisateur = $_SESSION["user_id"];

/* =========================
   OUTILS
========================= */
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

    if ($initiales === "") {
        $initiales = "U";
    }

    return $initiales;
}

function formatPrix($prix) {
    return number_format(floatval($prix), 2, ",", " ") . " €";
}

/* =========================
   COLONNES TABLE UTILISATEUR
========================= */
$colonnesUtilisateur = [];

try {
    $stmtColonnes = $pdo->query("SHOW COLUMNS FROM utilisateur");
    $colonnesUtilisateur = array_column($stmtColonnes->fetchAll(PDO::FETCH_ASSOC), "Field");
} catch (PDOException $e) {
    $colonnesUtilisateur = [];
}

/* =========================
   RÉCUPÉRATION UTILISATEUR
========================= */
$sqlUser = "SELECT * FROM utilisateur WHERE id_utilisateur = ?";
$stmtUser = $pdo->prepare($sqlUser);
$stmtUser->execute([$idUtilisateur]);
$utilisateur = $stmtUser->fetch();

if (!$utilisateur) {
    session_destroy();
    header("Location: Connexion.php?erreur=compte_introuvable");
    exit;
}

$messageSucces = "";
$messageErreur = "";

/* =========================
   MODIFICATION PROFIL
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "modifier_profil") {
    $prenom = trim($_POST["prenom"] ?? "");
    $nom = trim($_POST["nom"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $adresse = trim($_POST["adresse"] ?? "");

    if ($prenom === "" || $nom === "" || $email === "") {
        $messageErreur = "Le prénom, le nom et l’adresse e-mail sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messageErreur = "L’adresse e-mail n’est pas valide.";
    } else {
        try {
            if (in_array("email", $colonnesUtilisateur)) {
                $sqlCheckEmail = "
                    SELECT id_utilisateur
                    FROM utilisateur
                    WHERE email = ?
                    AND id_utilisateur <> ?
                    LIMIT 1
                ";

                $stmtCheckEmail = $pdo->prepare($sqlCheckEmail);
                $stmtCheckEmail->execute([$email, $idUtilisateur]);
                $emailExiste = $stmtCheckEmail->fetch();

                if ($emailExiste) {
                    $messageErreur = "Cette adresse e-mail est déjà utilisée par un autre compte.";
                }
            }

            if ($messageErreur === "") {
                $champs = [];
                $valeurs = [];

                if (in_array("prenom", $colonnesUtilisateur)) {
                    $champs[] = "prenom = ?";
                    $valeurs[] = $prenom;
                }

                if (in_array("nom", $colonnesUtilisateur)) {
                    $champs[] = "nom = ?";
                    $valeurs[] = $nom;
                }

                if (in_array("email", $colonnesUtilisateur)) {
                    $champs[] = "email = ?";
                    $valeurs[] = $email;
                }

                if (in_array("adresse", $colonnesUtilisateur)) {
                    $champs[] = "adresse = ?";
                    $valeurs[] = $adresse;
                }

                if (count($champs) > 0) {
                    $valeurs[] = $idUtilisateur;

                    $sqlUpdate = "
                        UPDATE utilisateur
                        SET " . implode(", ", $champs) . "
                        WHERE id_utilisateur = ?
                    ";

                    $stmtUpdate = $pdo->prepare($sqlUpdate);
                    $stmtUpdate->execute($valeurs);

                    $messageSucces = "Vos informations ont bien été mises à jour.";

                    $stmtUser = $pdo->prepare($sqlUser);
                    $stmtUser->execute([$idUtilisateur]);
                    $utilisateur = $stmtUser->fetch();
                } else {
                    $messageErreur = "Aucune colonne modifiable n’a été trouvée dans la table utilisateur.";
                }
            }
        } catch (PDOException $e) {
            $messageErreur = "Erreur lors de la mise à jour du profil.";
        }
    }
}

/* =========================
   VALEURS PROFIL
========================= */
$prenomUtilisateur = $utilisateur["prenom"] ?? "";
$nomUtilisateur = $utilisateur["nom"] ?? "";
$emailUtilisateur = $utilisateur["email"] ?? "";
$adresseUtilisateur = $utilisateur["adresse"] ?? "";

$initiales = getInitiales($prenomUtilisateur, $nomUtilisateur, $emailUtilisateur);

/* =========================
   COMPTEUR PANIER
========================= */
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

/* =========================
   NOTIFICATIONS
========================= */
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

  <title>VoyageVista - Profil</title>

  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
      background: #f8fafc;
      color: #0f172a;
    }

    button,
    input,
    textarea {
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
      max-width: 1200px;
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
    .danger-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 42px;
      padding: 11px 18px;
      border-radius: 999px;
      font-weight: 800;
      transition: 0.2s;
      white-space: nowrap;
      border: none;
      text-decoration: none;
    }

    .primary-btn {
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

    .danger-btn {
      background: #fff7f7;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    .danger-btn:hover {
      background: #fee2e2;
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

    .badge {
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

    .notification-wrapper,
    .profile-wrapper {
      position: relative;
    }

    .notification-dropdown,
    .profile-dropdown {
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

    .notification-wrapper:hover .notification-dropdown,
    .profile-wrapper:hover .profile-dropdown {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    .notification-header,
    .profile-header {
      padding: 8px 8px 12px;
      border-bottom: 1px solid #e2e8f0;
      margin-bottom: 8px;
    }

    .notification-header strong,
    .profile-header strong {
      display: block;
      color: #0f172a;
      font-size: 16px;
    }

    .notification-header span,
    .profile-header span {
      display: block;
      color: #64748b;
      font-size: 13px;
      margin-top: 4px;
    }

    .notification-item,
    .profile-menu-btn {
      width: 100%;
      display: flex;
      gap: 12px;
      align-items: center;
      border: none;
      background: transparent;
      text-align: left;
      padding: 12px 8px;
      border-radius: 16px;
      transition: 0.2s;
      color: #0f172a;
      font-weight: 700;
    }

    .notification-item:hover,
    .profile-menu-btn:hover {
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

    .notification-item small {
      display: block;
      color: #64748b;
      margin-top: 3px;
      line-height: 1.4;
      font-weight: 500;
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
    }

    .avatar-btn:hover {
      background: #155e75;
      transform: translateY(-1px);
    }

    .page-hero {
      background:
        linear-gradient(135deg, rgba(15, 95, 117, 0.92), rgba(8, 145, 178, 0.72), rgba(5, 150, 105, 0.75)),
        url("https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1600&q=80");
      background-size: cover;
      background-position: center;
      color: white;
    }

    .page-hero-container {
      max-width: 1200px;
      margin: auto;
      padding: 58px 24px 78px;
    }

    .breadcrumb {
      display: inline-flex;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.16);
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 18px;
    }

    .page-hero h1 {
      max-width: 760px;
      font-size: clamp(36px, 5vw, 58px);
      line-height: 1.05;
      letter-spacing: -0.04em;
      margin-bottom: 18px;
    }

    .page-hero p {
      max-width: 680px;
      color: #ecfeff;
      line-height: 1.7;
      font-size: 18px;
    }

    .main-container {
      max-width: 1200px;
      margin: auto;
      padding: 0 24px 64px;
    }

    .profile-layout {
      margin-top: -42px;
      position: relative;
      z-index: 5;
      display: grid;
      grid-template-columns: 360px 1fr;
      gap: 24px;
      align-items: start;
    }

    .profile-card,
    .form-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 30px;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.10);
    }

    .profile-card {
      padding: 28px;
      text-align: center;
    }

    .big-avatar {
      width: 96px;
      height: 96px;
      margin: 0 auto 18px;
      border-radius: 50%;
      background: #0e7490;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 34px;
      font-weight: 900;
      box-shadow: 0 18px 35px rgba(14, 116, 144, 0.25);
    }

    .profile-card h2 {
      font-size: 25px;
      margin-bottom: 8px;
    }

    .profile-card p {
      color: #64748b;
      line-height: 1.5;
      margin-bottom: 18px;
    }

    .profile-stats {
      display: grid;
      gap: 10px;
      margin-top: 20px;
      text-align: left;
    }

    .profile-stat {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      padding: 14px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 18px;
      color: #475569;
      font-weight: 700;
    }

    .profile-stat strong {
      color: #0e7490;
    }

    .form-card {
      padding: 28px;
    }

    .form-card h2 {
      font-size: 28px;
      margin-bottom: 8px;
    }

    .form-card > p {
      color: #64748b;
      line-height: 1.6;
      margin-bottom: 24px;
    }

    .message {
      padding: 14px 16px;
      border-radius: 18px;
      margin-bottom: 18px;
      font-weight: 800;
      line-height: 1.4;
    }

    .message.success {
      background: #ecfdf5;
      color: #059669;
      border: 1px solid #bbf7d0;
    }

    .message.error {
      background: #fef2f2;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 16px;
    }

    .field {
      display: grid;
      gap: 7px;
    }

    .field.full {
      grid-column: 1 / -1;
    }

    .field label {
      color: #475569;
      font-size: 13px;
      font-weight: 900;
    }

    .field input,
    .field textarea {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 16px;
      padding: 13px 14px;
      outline: none;
      font-size: 15px;
      background: white;
    }

    .field textarea {
      min-height: 110px;
      resize: vertical;
    }

    .field input:focus,
    .field textarea:focus {
      border-color: #0891b2;
      box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.12);
    }

    .form-actions {
      margin-top: 22px;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      flex-wrap: wrap;
    }

    footer {
      border-top: 1px solid #e2e8f0;
      background: white;
      padding: 28px 24px;
    }

    .footer-content {
      max-width: 1200px;
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

      .profile-layout {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .navbar,
      .footer-content,
      .form-actions {
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

      .main-container {
        padding: 0 18px 48px;
      }

      .page-hero-container {
        padding: 44px 18px 66px;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }

      .notification-dropdown,
      .profile-dropdown {
        right: -70px;
        width: 300px;
      }
    }
  </style>
</head>

<body>
  <header>
    <nav class="navbar">
      <button class="logo" onclick="window.location.href='Acceuil.html'">
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
        <button onclick="window.location.href='Destination.php'">Destinations</button>
        <button onclick="window.location.href='Transport.php'">Transports</button>
        <button onclick="window.location.href='Hebergements.php'">Hébergements</button>
        <button onclick="window.location.href='Activites.php'">Activités</button>
        <button onclick="window.location.href='Itineraires.php'">Itinéraires</button>
      </div>

      <div class="nav-actions">
        <button class="secondary-btn" onclick="window.location.href='Destination.php'">Recherche</button>

        <div class="notification-wrapper">
          <button class="icon-btn" onclick="window.location.href='Notifications.php'" aria-label="Notifications">
            🔔

            <?php if ($nombreNotifications > 0): ?>
              <span class="badge"><?= h($nombreNotifications) ?></span>
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
                <span>
                  <strong>Aucune notification</strong>
                  <small>Vous n’avez pas encore de notification.</small>
                </span>
              </button>
            <?php else: ?>
              <?php foreach ($notificationsPopup as $notification): ?>
                <button class="notification-item" onclick="window.location.href='Notifications.php'">
                  <span class="notification-icon">
                    <?= intval($notification["statut_lecture"]) === 0 ? "🔔" : "📩" ?>
                  </span>
                  <span>
                    <strong><?= h($notification["titre"]) ?></strong>
                    <small><?= h($notification["message"]) ?></small>
                  </span>
                </button>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <button class="icon-btn" onclick="window.location.href='Panier.php'" aria-label="Panier">
          🛒

          <?php if ($nombreElementsPanier > 0): ?>
            <span class="badge"><?= h($nombreElementsPanier) ?></span>
          <?php endif; ?>
        </button>

        <div class="profile-wrapper">
          <button class="avatar-btn" type="button">
            <?= h($initiales) ?>
          </button>

          <div class="profile-dropdown">
            <div class="profile-header">
              <strong><?= h(trim($prenomUtilisateur . " " . $nomUtilisateur)) ?></strong>
              <span><?= h($emailUtilisateur) ?></span>
            </div>

            <button class="profile-menu-btn" onclick="window.location.href='Profil.php'">
              👤 Mon profil
            </button>

            <form action="api/logout.php" method="POST">
              <button class="profile-menu-btn" type="submit">
                🚪 Se déconnecter
              </button>
            </form>
          </div>
        </div>
      </div>
    </nav>
  </header>

  <main>
    <section class="page-hero">
      <div class="page-hero-container">
        <div class="breadcrumb">VoyageVista &gt; Profil</div>
        <h1>Mon profil</h1>
        <p>
          Consultez et modifiez les informations de votre compte VoyageVista.
        </p>
      </div>
    </section>

    <section class="main-container">
      <div class="profile-layout">
        <aside class="profile-card">
          <div class="big-avatar">
            <?= h($initiales) ?>
          </div>

          <h2><?= h(trim($prenomUtilisateur . " " . $nomUtilisateur)) ?></h2>
          <p><?= h($emailUtilisateur) ?></p>

          <form action="api/logout.php" method="POST">
            <button class="danger-btn" type="submit">
              Se déconnecter
            </button>
          </form>

          <div class="profile-stats">
            <div class="profile-stat">
              <span>Panier</span>
              <strong><?= h($nombreElementsPanier) ?></strong>
            </div>

            <div class="profile-stat">
              <span>Notifications</span>
              <strong><?= h($nombreNotifications) ?></strong>
            </div>
          </div>
        </aside>

        <section class="form-card">
          <h2>Modifier mes informations</h2>
          <p>
            Les informations modifiées seront enregistrées dans la table utilisateur.
          </p>

          <?php if ($messageSucces !== ""): ?>
            <div class="message success"><?= h($messageSucces) ?></div>
          <?php endif; ?>

          <?php if ($messageErreur !== ""): ?>
            <div class="message error"><?= h($messageErreur) ?></div>
          <?php endif; ?>

          <form method="POST">
            <input type="hidden" name="action" value="modifier_profil">

            <div class="form-grid">
              <div class="field">
                <label for="prenom">Prénom</label>
                <input
                  id="prenom"
                  name="prenom"
                  type="text"
                  value="<?= h($prenomUtilisateur) ?>"
                  required
                >
              </div>

              <div class="field">
                <label for="nom">Nom</label>
                <input
                  id="nom"
                  name="nom"
                  type="text"
                  value="<?= h($nomUtilisateur) ?>"
                  required
                >
              </div>

              <div class="field full">
                <label for="email">Adresse e-mail</label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  value="<?= h($emailUtilisateur) ?>"
                  required
                >
              </div>

              <div class="field full">
                <label for="adresse">Adresse</label>
                <textarea
                  id="adresse"
                  name="adresse"
                  placeholder="Votre adresse"
                ><?= h($adresseUtilisateur) ?></textarea>
              </div>
            </div>

            <div class="form-actions">
              <button class="secondary-btn" type="button" onclick="window.location.href='Acceuil.html'">
                Retour accueil
              </button>

              <button class="primary-btn" type="submit">
                Enregistrer les modifications
              </button>
            </div>
          </form>
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