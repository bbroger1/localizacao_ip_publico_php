<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname_map = "map";

$conn = new mysqli($servername, $username, $password, $dbname_map);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

header("Content-type: text/xml");

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<markers>';

$sql = "SELECT name, address, lat, lng, type FROM markers";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo '<marker ';
        echo 'name="' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '" ';
        echo 'address="' . htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8') . '" ';
        echo 'lat="' . $row['lat'] . '" ';
        echo 'lng="' . $row['lng'] . '" ';
        echo 'type="' . htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8') . '" ';
        echo '/>';
    }
}

echo '</markers>';

$conn->close();
