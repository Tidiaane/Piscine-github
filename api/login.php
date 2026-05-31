<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Connexion.php");
    exit;
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    header("Location: ../Connexion.php?erreur=champs");
    exit;
}

$sql = "SELECT * FROM utilisateur WHERE email = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user["mot_de_passe"])) {
    header("Location: ../Connexion.php?erreur=identifiants");
    exit;
}

$role = $user["role"] ?? "client";

$_SESSION["user_id"] = $user["id_utilisateur"];
$_SESSION["user_nom"] = $user["nom"];
$_SESSION["user_prenom"] = $user["prenom"] ?? "";
$_SESSION["user_email"] = $user["email"] ?? "";
$_SESSION["user_role"] = $role;

if ($role === "admin" || $role === "gestionnaire") {
    header("Location: ../Admin.php");
    exit;
}

header("Location: ../Acceuil.php");
exit;
?>
