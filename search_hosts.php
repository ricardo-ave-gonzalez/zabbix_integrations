<?php

$term = $_GET['term'] ?? '';

$conn = new PDO("mysql:host=127.0.0.1;port=3306;dbname=zabbix", "zabbix", "zabbix");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
SELECT DISTINCT host
FROM hosts
WHERE
    status = 0
    AND flags = 0
    AND LOWER(host) LIKE LOWER(:term)
ORDER BY host
LIMIT 20;
";

$stmt = $conn->prepare($sql);
$stmt->execute(['term' => "%$term%"]);

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
