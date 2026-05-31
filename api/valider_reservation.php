<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Connexion.php");
    exit;
}

$idUtilisateur = $_SESSION["user_id"];

/* Récupérer le panier */
$sql = "
SELECT lp.*, p.id_panier
FROM ligne_panier lp
JOIN panier p ON lp.id_panier = p.id_panier
WHERE p.id_utilisateur = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idUtilisateur]);
$lignes = $stmt->fetchAll();

if (count($lignes) === 0) {
    header("Location: ../Panier.php?erreur=panier_vide");
    exit;
}

$total = 0;
foreach ($lignes as $ligne) {
    $total += $ligne["prix_unitaire"] * $ligne["quantite"];
}

/* Créer la réservation */
$sql = "INSERT INTO reservation (id_utilisateur, montant_total, statut) VALUES (?, ?, 'validee')";
$stmt = $pdo->prepare($sql);
$stmt->execute([$idUtilisateur, $total]);

/* Créer une notification */
$sql = "INSERT INTO notification (id_utilisateur, titre, message) VALUES (?, ?, ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    $idUtilisateur,
    "Réservation validée",
    "Votre réservation a été validée pour un montant total de " . $total . " €."
]);

/* Vider le panier */
$idPanier = $lignes[0]["id_panier"];

$sql = "DELETE FROM ligne_panier WHERE id_panier = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$idPanier]);

header("Location: ../Notifications.php");
exit;
?>