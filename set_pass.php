<?php
require_once 'config/db.php'; // Apni database connection file ka sahi naam likhein
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$email = 'admin@saashub.com';

$sql = "UPDATE users SET password = ? WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $hashed_password, $email);

if ($stmt->execute()) {
    echo "Password updated successfully with fresh hash!";
}
else {
    echo "Error: " . $conn->error;
}
?>