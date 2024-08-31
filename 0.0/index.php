<?php
// Função para conectar ao banco de dados
function connectToDatabase($servername, $username, $password, $dbname)
{
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Erro na conexão com o banco de dados: " . $conn->connect_error);
    }

    return $conn;
}

// Função para inserir dados no banco de dados
function insertData($conn, $query, $params, $types)
{
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar a consulta: " . $stmt->error);
    }

    $stmt->close();
}

// Configurações de banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname_map = "map";
$dbname_iplogger = "iplogger";

try {
    // Conexão com os bancos de dados
    $conn_map = connectToDatabase($servername, $username, $password, $dbname_map);
    $conn_iplogger = connectToDatabase($servername, $username, $password, $dbname_iplogger);
} catch (Exception $e) {
    // Log de erro e mensagem de erro genérica para o usuário
    error_log($e->getMessage());
    die("Erro ao conectar-se aos bancos de dados. Tente novamente mais tarde.");
}

$client = null;


//classe cliente
class Client
{

    private $ip, $country, $state, $city, $cep, $latitude, $longitude, $org;



    public function __construct($ip, $country, $state, $city, $cep, $latitude, $longitude, $org)
    {
        $this->ip = $ip;
        $this->country = $country;
        $this->state = $state;
        $this->city = $city;
        $this->cep = $cep;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->org = $org;
    }

    public function getDetails() //define o retorno dos dados do banco
    {
        return [
            'ip' => $this->ip,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'cep' => $this->cep,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'org' => $this->org,
        ];
    }
}


//submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub1'])) {
    $input_ip = filter_input(INPUT_POST, 'ip', FILTER_VALIDATE_IP);

    if ($input_ip === false) {
        $erro = "IP inválido, tente outro";
        header("Location: index.php?erro=" . urlencode($erro));
        die();
    }

    // Obtenção dos detalhes do IP via API
    $response = file_get_contents('http://ip-api.com/php/' . urlencode($input_ip));
    if ($response === false) {

        $erro = "Erro ao conectar-se à API de IP.";
        header("Location: index.php?erro=" . urlencode($erro));
        die();
    }

    $user = @unserialize($response);

    if ($user && $user['status'] === 'success') {
        $client = new Client(
            $user['query'],
            $user['country'],
            $user['region'],
            $user['city'],
            $user['zip'],
            $user['lat'],
            $user['lon'],
            $user['org']
        );

        $details = $client->getDetails();

        try {
            // Inserção no banco de dados iplogger
            $query_iplogger = "INSERT INTO clients (ip, country, region, city, zip, latitude, longitude, org) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $params_iplogger = [
                $details['ip'],
                $details['country'],
                $details['state'],
                $details['city'],
                $details['cep'],
                $details['latitude'],
                $details['longitude'],
                $details['org']
            ];
            insertData($conn_iplogger, $query_iplogger, $params_iplogger, "ssssssss");

            // Inserção no banco de dados map
            $query_map = "INSERT INTO markers (name, address, lat, lng, type) 
                          VALUES (?, ?, ?, ?, ?)";
            $params_map = [
                $details['org'],
                $details['city'],
                $details['latitude'],
                $details['longitude'],
                $details['org']
            ];
            insertData($conn_map, $query_map, $params_map, "sssss");

            $ok = "Localização salva e colocada no mapa.";

            $latitude = $details['latitude'];
            $longitude = $details['longitude'];

            echo
            "<script>

                 var lat = $latitude;
            
                 var lng = $longitude;

            initMap();

            </script>";
        } catch (Exception $e) {
            error_log("Erro ao inserir dados: " . $e->getMessage());
            $erro = "Erro ao salvar as informações. Tente novamente.";
            header("Location: index.php?erro=" . urlencode($erro));
            die();
        }
    } else {
        $erro = "Erro ao obter informações do IP.";
    }
}

// Fechar conexões
$conn_map->close();
$conn_iplogger->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="icones/icone.ico" type="image/x-icon">    
    <title>Da onde</title>
    <style>
        /* Resetting some default styles */
        * {
            
            box-sizing: border-box;
            max-width: 1350px;
            margin: 0 auto;
            padding: 0;
            
        }

        html{
            background-color: black;
        }

        body {
            background-color: #f0f0f0;
            color: #000;
            font-family: Arial, sans-serif;
            text-align: center;
        }

        header {
            background-color: black;
            color: #fff;
            padding: 20px;
            border-bottom: 1px solid #ccc;
        }

        .container {
            width: 100%;          
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
            background-color: #fff;
        }

        h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .host-details pre {
            
            text-align: left;
            font-size: 28px;
            border: 1px solid #ccc;
            padding: 10px;
            background-color: #f9f9f9;
        }

        form {
            margin-top: 20px;
        }

        input[type="text"] {
            width: 80%;
            padding: 10px;
            border: 1px solid #ccc;
            margin-bottom: 10px;
        }

        button {
            padding: 10px 20px;
            background-color: black;
            color: #fff;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background-color: #1e1e1e;
        }




        #border{
            margin-top: 20px;
            border: 6px solid black;
        }
        #map {
            width: 100%;
            min-height: 600px;
            border: 1px solid #ccc;
        }

        footer {
            background-color: black;
            color: #fff;
            padding: 10px;
            margin-top: 20px;
            border-top: 1px solid #ccc;
        }

    </style>
</head>

<body>
    <div style="border: 2px solid black; background-color: black;">
<header style="text-align: center; padding: 10px;">
    <!-- <h1 style="font-size: 24px;">Da onde</h1> -->
    <img src="icones/icone.png" style="max-width: 150px; height: auto;">
    <p style="font-size: 14px;">em desenvolvimento...</p>
</header>

    <?php
    // Verifica se a mensagem de sucesso foi configurada
    $okk = isset($ok) ? $ok : '';
    // Verifica se há uma mensagem de erro no parâmetro GET
    $erro = isset($_GET['erro']) ? urldecode($_GET['erro']) : '';

    // Se a mensagem de sucesso foi definida, não exiba a mensagem de erro
    if (!empty($okk)) {
        $erro = '';
    }
    ?>

    <!-- Exibir mensagem de sucesso -->
    <?php if (!empty($okk)): ?>
        <div style="border: 1px solid #4F8A10;
        margin: 10px auto;
        padding: 10px;
        background-color: #DFF2BF;
        max-width: 460px;">
            <?php echo htmlspecialchars($okk, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- Exibir mensagem de erro -->
    <?php if (!empty($erro)): ?>
        <div style="border: 1px solid #D8000C;
        margin: 10px auto;
        padding: 10px;
        background-color: #FFBABA;
        max-width: 460px;">
            <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>


    <div class="container">
        <h2>Detalhes da Localização do Host</h2>
        <div class="host-details">
            <?php if ($client) : ?>
                <?php
                $details = $client->getDetails();
                echo "<pre>";
                echo "IP: " . $details['ip'] . "\n";
                echo "País: " . $details['country'] . "\n";
                echo "Estado/Região: " . $details['state'] . "\n";
                echo "Cidade: " . $details['city'] . "\n";
                echo "CEP: " . $details['cep'] . "\n";
                echo "Latitude: " . $details['latitude'] . "\n";
                echo "Longitude: " . $details['longitude'] . "\n";
                echo "Organização: " . $details['org'] . "\n";
                echo "</pre>";

                ?>
            <?php else : ?>
                <p>Insira um IP para visualizar os detalhes.</p>
            <?php endif; ?>
            <form method="POST" action="">
                <label for="ip">IP:</label><br>
                <input type="text" id="ip" name="ip" required placeholder="Digite o IP"><br>
                <button type="submit" name="sub1">Localizar</button>

            </form>

        </div>
        <div class="host-click-details">

        </div>
        <div id="border">
        <div id="map"></div>
        </div>
    </div>
    <footer>
        &copy; 2024 Da-onde
    </footer>
            </div>
    <script>
        var customLabel = {
            restaurant: {
                label: 'R'
            },
            bar: {
                label: 'B'
            }
        };

        function initMap() {
            var map = new google.maps.Map(document.getElementById('map'), {
                center: new google.maps.LatLng(-29.6914, -53.8008),
                zoom: 12
            });

            var initialLatLng = {
                lat: -29.6914,
                lng: -53.8008
            };

            if (typeof lat !== 'undefined' && typeof lng !== 'undefined') {
                initialLatLng = {
                    lat: parseFloat(lat),
                    lng: parseFloat(lng)
                };
            }

            var map = new google.maps.Map(document.getElementById('map'), {
                center: initialLatLng,
                zoom: 12
            });

            if (typeof lat !== 'undefined' && typeof lng !== 'undefined') {
                new google.maps.Marker({
                    position: initialLatLng,
                    map: map,
                    title: 'Localização do IP'
                });
            }

            downloadUrl('xml.php', function(data) {
                var xml = data.responseXML;
                var markers = xml.documentElement.getElementsByTagName('marker');
                Array.prototype.forEach.call(markers, function(markerElem) {
                    var name = markerElem.getAttribute('name');
                    var address = markerElem.getAttribute('address');
                    var type = markerElem.getAttribute('type');
                    var lat = markerElem.getAttribute('lat');
                    var lng = markerElem.getAttribute('lng');

                    var point = new google.maps.LatLng(
                        parseFloat(lat),
                        parseFloat(lng)
                    );

                    var marker = new google.maps.Marker({
                        map: map,
                        position: point,
                        label: type.charAt(0).toUpperCase()
                    });

                    var lastClickedMarker = null;

                    function clearLastMarker() {
                        if (lastClickedMarker) {
                            lastClickedMarker.setIcon('http://maps.google.com/mapfiles/ms/icons/red-dot.png');
                            lastClickedMarker.clickedState = 2;
                        }
                    }

                    marker.addListener('click', function() {
                        if (!marker.clickedState) {
                            marker.clickedState = 0;
                        }

                        var detailsSection = document.querySelector('.host-click-details');

                        if (marker.clickedState === 0) {
                            marker.setIcon('http://maps.google.com/mapfiles/ms/icons/green-dot.png');
                            marker.clickedState = 1;

                            detailsSection.innerHTML = `
            <h2 style="color:green; margin-top: 20px;">Marcador Ativo</h2>
            <h3>Detalhes</h3>
            <p><strong>Nome:</strong> ${name}</p>
            <p><strong>Endereço:</strong> ${address}</p>
            <p><strong>Tipo:</strong> ${type}</p>
            <p><strong>Latitude:</strong> ${lat}</p>
            <p><strong>Longitude:</strong> ${lng}</p>
        `;
                            detailsSection.style.display = 'block';

                        } else if (marker.clickedState === 1) {
                            marker.setIcon('http://maps.google.com/mapfiles/ms/icons/red-dot.png');
                            marker.clickedState = 2;
                            detailsSection.style.display = 'none';

                        } else if (marker.clickedState === 2) {
                            marker.setIcon(null);
                            marker.clickedState = 0;
                        }
                    });

                });
            });
        }

        function downloadUrl(url, callback) {
            var request = window.ActiveXObject ?
                new ActiveXObject('Microsoft.XMLHTTP') :
                new XMLHttpRequest;

            request.onreadystatechange = function() {
                if (request.readyState == 4) {
                    request.onreadystatechange = doNothing;
                    callback(request, request.status);
                }
            };

            request.open('GET', url, true);
            request.send(null);
        }

        function doNothing() {}
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=&callback=initMap" async defer></script>
</body>

</html>