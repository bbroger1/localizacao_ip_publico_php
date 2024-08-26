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
    <title>Host-Monit</title>
    <style>
        /* Resetting some default styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-color: black;
            color: #333;
        }

        /* Improved Header Style */
        header {
            background-color: #007bff;
            color: #fff;
            padding: 1.5rem;
            text-align: center;
            /* font-size: 2.5rem; */
            border-bottom: 4px solid #0056b3;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-height: 200px;
        }

        /* Main Container Styling */
        .container {
            display: flex;
            flex: 1;
            flex-direction: row;
            height: calc(100vh - 120px);
            /* Adjusts for header and footer height */
            width: 100%;
            gap: 30px;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px;
            flex-wrap: wrap;
        }


        /* Main Content Area */
        main {
            flex: 1;
            max-width: 35%;
            /* Main occupies less space to the left */
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            overflow-y: auto;
        }



        /* Heading Improvements */
        main h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: #007bff;
            border-bottom: 3px solid #e9ecef;
            padding-bottom: 15px;
        }

        /* Details Section */
        .host-details {
            margin-bottom: 25px;
        }

        .host-details pre {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 8px;
            font-size: 1rem;
            color: #495057;
            white-space: pre-wrap;
            border-left: 5px solid #007bff;
        }

        #map {
            flex: 3;
            height: 100%;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            border: 3px solid #007bff;
        }

        /* Form Styling */
        form {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
        }

        form label {
            margin-bottom: 10px;
            font-weight: 600;
            color: #495057;
        }

        form input[type="text"] {
            padding: 15px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1.1rem;
            margin-bottom: 20px;
            transition: border-color 0.3s ease;
        }

        form input[type="text"]:focus {
            border-color: #007bff;
            outline: none;
        }

        form button {
            padding: 15px;
            background-color: #28a745;
            border: none;
            border-radius: 6px;
            color: #ffffff;
            font-size: 1.2rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        form button:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        /* Map Styling */
        #map {
            flex: 3;
            height: 100%;
            /* Map takes up remaining height */
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            border: 3px solid #007bff;
        }

        /* Footer Styling */
        footer {
            background-color: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 1rem;
            border-top: 4px solid #0056b3;
            box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                gap: 20px;
                padding: 15px;
                align-items: stretch;
                /* Garante que os elementos ocupem toda a largura */
            }

            main {
                width: 100%;
                max-width: 100%;
                padding: 20px;
                margin-bottom: 20px;
                box-sizing: border-box;
                /* Garante que padding não cause overflow */
            }

            #map {
                height: 300px;
                /* Altura fixa menor para dispositivos móveis */
                width: 100%;
                max-width: 100%;
                /* Garante que o mapa não ultrapasse a largura */
                margin-bottom: 20px;
                /* Espaçamento inferior para separar visualmente */
                box-sizing: border-box;
                /* Garante que padding não cause overflow */
            }

            main h2 {
                font-size: 1.6rem;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>

<body>
    <header>
        Host-Monit
        <h4>em desenvolvimento...</h4>
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
        <div style="border: 1px solid;
        margin: 10px auto;
        padding: 15px 10px 15px 50px;
        background-repeat: no-repeat;
        background-position: 10px center;
        max-width: 460px; color: #4F8A10;
        background-color: #DFF2BF;
        background-image: url('https://i.imgur.com/Q9BGTuy.png');">
            <?php echo htmlspecialchars($okk, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- Exibir mensagem de erro -->
    <?php if (!empty($erro)): ?>
        <div style="border: 1px solid;
        margin: 10px auto;
        padding: 15px 10px 15px 50px;
        background-repeat: no-repeat;
        background-position: 10px center;
        max-width: 460px; color: #D8000C;
        background-color: #FFBABA;
        background-image: url('https://i.imgur.com/GnyDvKN.png');">
            <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>


    <div class="container">
        <main>
            <h2>Detalhes da Localização do Host</h2>
            <div class="host-details">
                <?php if ($client) : ?>
                    <?php
                    $details = $client->getDetails();
                    echo "--------------------------------------------------------------------------------\n";
                    echo "<pre style='font-size: 1.9em;'>";
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
                    <label for="ip">IP:</label>
                    <input type="text" id="ip" name="ip" required placeholder="Digite o IP">
                    <button type="submit" name="sub1">Localizar</button>

                </form>

            </div>


            <div class="host-click-details" style="border: solid black 3px; text-align:center; padding: 20px; display:none;">

            </div>

        </main>
        <div id="map"></div>
    </div>
    <footer>
        &copy; 2024 Host-Monit
    </footer>
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
            /////
            var map = new google.maps.Map(document.getElementById('map'), {
                center: new google.maps.LatLng(-29.6914, -53.8008),
                zoom: 12
            });
            ////
            var initialLatLng = {
                lat: -29.6914,
                lng: -53.8008
            };

            // Verifica se as variáveis lat e lng estão definidas
            if (typeof lat !== 'undefined' && typeof lng !== 'undefined') {
                initialLatLng = {
                    lat: parseFloat(lat),
                    lng: parseFloat(lng)
                };
            }

            // Cria o mapa centrado no ponto especificado
            var map = new google.maps.Map(document.getElementById('map'), {
                center: initialLatLng,
                zoom: 12
            });

            // Adiciona um marcador no ponto especificado
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
                        label: type.charAt(0).toUpperCase() // Pode personalizar de acordo com seu tipo
                    });

                    var lastClickedMarker = null; // Variável para armazenar o último marcador clicado

                    function clearLastMarker() {
                        if (lastClickedMarker) {
                            // Muda o último marcador para vermelho
                            lastClickedMarker.setIcon('http://maps.google.com/mapfiles/ms/icons/red-dot.png');
                            lastClickedMarker.clickedState = 2;
                        }
                    }

                    marker.addListener('click', function() {
                        // Armazenar o estado de clique
                        if (!marker.clickedState) {
                            marker.clickedState = 0; // 0 = estado original, 1 = verde, 2 = vermelho
                        }

                        var detailsSection = document.querySelector('.host-click-details');

                        if (marker.clickedState === 0) {
                            // Estado original, muda para verde
                            marker.setIcon('http://maps.google.com/mapfiles/ms/icons/green-dot.png');
                            marker.clickedState = 1;

                            // Atualiza a seção de detalhes
                            detailsSection.innerHTML = `
            <h2 style="color:green;">Marcador Ativo</h2>
            <h3>Detalhes</h3>
            <p><strong>Nome:</strong> ${name}</p>
            <p><strong>Endereço:</strong> ${address}</p>
            <p><strong>Tipo:</strong> ${type}</p>
            <p><strong>Latitude:</strong> ${lat}</p>
            <p><strong>Longitude:</strong> ${lng}</p>
        `;
                            detailsSection.style.display = 'block';

                        } else if (marker.clickedState === 1) {
                            // Estado verde, muda para vermelho
                            marker.setIcon('http://maps.google.com/mapfiles/ms/icons/red-dot.png');
                            marker.clickedState = 2;
                            detailsSection.style.display = 'none';

                        } else if (marker.clickedState === 2) {
                            // Estado vermelho, volta ao estado original
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