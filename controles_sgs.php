<?php

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["download_csv"])) {

    $data = json_decode($_POST["csv_data"], true);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="zabbix_export.csv"');

    $output = fopen("php://output", "w");

    fputcsv($output, ['HOST', 'SERVICE', 'SG']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['host'],
            $row['service'],
            $row['sg_values']
        ]);
    }

    fclose($output);
    exit;
}

?>

<html lang="en">
<head>
<meta charset="UTF-8">
<title>Zagios Info</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
body {
    background-color: #292929;
    display: flex;
    flex-direction: column;
    width: 98vw;
    height: 95vh;
    font-family: 'Poppins', sans-serif;
}

header {
    border-radius: 1em;
    border: 2px solid #4e8cf9;
    color: #4e8cf9;
    padding: 0.5rem;
    margin: 1rem;
    text-align: center;
}

#contenido {
    color: #4e8cf9;
    margin: 1rem;
}

table {
    width: 100%;
    border-collapse: collapse;
    color: #d3d3d3;
}

th, td {
    border: 1px solid #ddd;
    padding: 8px;
}

th {
    background-color: #3a3a3a;
    color: #4e8cf9;
}

tr:nth-child(even) {
    background-color: #3a3a3a;
}

#suggestions {
    background: #fff;
    color: #000;
    position: absolute;
    top: 100%;       /* debajo del input */
    left: 0;
    width: 100%;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ccc;
    z-index: 999;
}

#suggestions div:hover {
    background: #4e8cf9;
    color: #fff;
}

.tag {
    display:inline-block;
    margin:5px;
    padding:5px;
    background:#4e8cf9;
    color:#fff;
    cursor:pointer;
}

.btn {
    background: #4e8cf9;
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    margin: 5px;
    transition: 0.2s;
}

.btn:hover {
    background: #3b6fd1;
}

.btn-secondary {
    background: #555;
}

.btn-secondary:hover {
    background: #777;
}
</style>
</head>

<body>

<header>
    <h2>Consultas de items y SGS</h2>

    <form method="post">
        <label>Hosts:</label><br>

        <div style="position: relative; display: inline-block;">

                <input type="text" id="HOST_INPUT" style="width:300px"
                        placeholder="Buscar Hosts..." autocomplete="off">

                <div id="suggestions"></div>
        </div>

        <input type="hidden" id="HOST" name="HOST">

        <div id="tags"></div>

        <div style="margin-top:10px;">
            <button type="submit" class="btn">🔍 Consultar</button>
            <button type="button" class="btn btn-secondary" onclick="resetAll();">🔄 Refrescar</button>
        </div>

    </form>
</header>

<?php

$servername = "127.0.0.1";
$dbport = "3306";
$dbname = "zabbix";
$username = "zabbix";
$password = "zabbix";

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["HOST"])) {

    $HOSTS_ARRAY = array_map('trim', explode(",", $_POST["HOST"]));

    try {
        $conn = new PDO("mysql:host=$servername;port=$dbport;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // validar hosts
        $placeholders = implode(',', array_fill(0, count($HOSTS_ARRAY), '?'));
        $stmt = $conn->prepare("SELECT host FROM hosts WHERE host IN ($placeholders)");
        $stmt->execute($HOSTS_ARRAY);
        $VALID_HOSTS = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($VALID_HOSTS)) {
            echo "<p>No se encontraron hosts válidos.</p>";
            exit;
        }

        // query principal
        $placeholders = implode(',', array_fill(0, count($VALID_HOSTS), '?'));

        $sql = "
        SELECT
            h.host,
            REPLACE(i.name, '.exit', '') AS service,
            GROUP_CONCAT(DISTINCT tt.value ORDER BY tt.value SEPARATOR ', ') AS sg_values
        FROM triggers t
        JOIN problem p ON t.triggerid = p.objectid
        JOIN functions f ON t.triggerid = f.triggerid
        JOIN items i ON f.itemid = i.itemid
        JOIN hosts h ON i.hostid = h.hostid
        LEFT JOIN trigger_tag tt ON t.triggerid = tt.triggerid AND tt.tag = 'sg'
        WHERE
            p.severity IN (0,1,2,3,4)
            AND t.value = 1
            AND t.status = 0
            AND h.status = 0
            AND i.status = 0
            AND i.name LIKE '%.exit'
            AND h.host IN ($placeholders)
        GROUP BY h.host, i.name
        LIMIT 1000
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute($VALID_HOSTS);
        $RESULTS = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<div id='contenido'>";
        echo "<h3>Hosts válidos: " . implode(", ", $VALID_HOSTS) . "</h3>";

        $csv_data = htmlspecialchars(json_encode($RESULTS));

        echo "
        <form method='post' action='' style='margin-bottom:10px;'>
                <input type='hidden' name='download_csv' value='1'>
                <input type='hidden' name='csv_data' value='$csv_data'>
                <button type='submit' style='
                        background:#4e8cf9;
                        color:white;
                        border:none;
                        padding:6px 12px;
                        border-radius:6px;
                        cursor:pointer;
                '> Descargar CSV</button>
        </form>
        ";

        if ($RESULTS) {
            echo "<table><tr><th>HOST</th><th>SERVICE</th><th>SG</th></tr>";
            foreach ($RESULTS as $row) {
                echo "<tr>
                        <td>{$row['host']}</td>
                        <td>{$row['service']}</td>
                        <td>{$row['sg_values']}</td>
                      </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No hay problemas activos.</p>";
        }

        echo "</div>";

    } catch(PDOException $e) {
        echo "Error DB: " . $e->getMessage();
    }
}
?>

<script>
let selectedHosts = [];

const input = document.getElementById("HOST_INPUT");
const hidden = document.getElementById("HOST");
const box = document.getElementById("suggestions");

input.addEventListener("input", function() {
    let val = this.value.trim();

    if (val.length < 2) {
        box.innerHTML = "";
        return;
    }

    fetch("search_hosts.php?term=" + val)
    .then(res => res.json())
    .then(data => {
        box.innerHTML = "";

        data.forEach(host => {

            if (selectedHosts.includes(host)) {
                return;
            }

            let div = document.createElement("div");
            div.innerText = host;
            div.style.cursor = "pointer";

            div.onclick = () => {
                if (!selectedHosts.includes(host)) {
                    selectedHosts.push(host);
                    renderTags();
                }
                input.value = "";
                box.innerHTML = "";
            };

            box.appendChild(div);
        });
    });
});

function renderTags() {
    let container = document.getElementById("tags");
    container.innerHTML = "";

    selectedHosts.forEach((host, index) => {
        let tag = document.createElement("span");
        tag.className = "tag";
        tag.innerText = host + " ✖";

        tag.onclick = () => {
            selectedHosts.splice(index, 1);
            renderTags();
        };

        container.appendChild(tag);
    });

    hidden.value = selectedHosts.join(",");
}

function resetAll() {
    selectedHosts = [];
    document.getElementById("HOST_INPUT").value = "";
    document.getElementById("HOST").value = "";
    document.getElementById("tags").innerHTML = "";
    document.getElementById("suggestions").innerHTML = "";

    window.location.href = window.location.pathname;
}
</script>

</body>
</html>
