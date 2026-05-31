<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Connexion.php");
    exit;
}

$nom = trim($_POST["nom"] ?? "");
$prenom = trim($_POST["prenom"] ?? "");
$adresse = trim($_POST["adresse"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";

if ($nom === "" || $prenom === "" || $adresse === "" || $email === "" || $password === "") {
    header("Location: ../Connexion.php?erreur=champs");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../Connexion.php?erreur=email");
    exit;
}

if ($password !== $confirmPassword) {
    header("Location: ../Connexion.php?erreur=password_confirm");
    exit;
}

if (strlen($password) < 8) {
    header("Location: ../Connexion.php?erreur=password");
    exit;
}

if (!isset($_POST["acceptTerms"])) {
    header("Location: ../Connexion.php?erreur=conditions");
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $sql = "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, 'client')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nom, $prenom, $email, $hash]);

    header("Location: ../Connexion.php?success=compte_cree");
    exit;
} catch (PDOException $e) {
    header("Location: ../Connexion.php?erreur=email_existant");
    exit;
}
?>
