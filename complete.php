<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';
if (isset($_POST['chore_id'])) {
    $stmt = $pdo->prepare("UPDATE chores SET is_completed = TRUE, updated_at=NOW() WHERE id = :id");
    $stmt->execute(['id' => $_POST['chore_id']]);
}
header('Location: home.php');
exit;
?>