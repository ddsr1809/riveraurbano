<?php
$host = 'db';
$db = 'rivera';
$user = 'riverauser';
$pass = 'riverapass';

function returnDB()
{
    $host = 'db';
    $db = 'rivera';
    $user = 'riverauser';
    $pass = 'riverapass';
    $conn = new mysqli($host, $user, $pass, $db);
    $result = $conn->query("SHOW TABLES");
    $allResults = '';
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_array()) {
            global $allResults;
            $allResults = $allResults . "<li>" . $row[0] . "</li>";
        }
        global $allResults;
         $allResults = $allResults . "</ul>";
    }
    return $allResults;
}
