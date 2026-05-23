<?php
// generate-hash.php - Run this file in your browser, then copy the hash
$password = 'Eladniperc007';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br><br>";
echo "Copy this hash into your SQL query:<br>";
echo "<strong style='background:#f0f0f0; padding:10px; display:block;'>" . $hash . "</strong>";
?>