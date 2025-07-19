<?php
$host = 'db';
$db = 'rivera';
$user = 'riverauser';
$pass = 'riverapass';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
echo "✅ Conexión a MySQL exitosa desde PHP";
