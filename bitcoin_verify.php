<?php
// config.php — Configuración y manejo CORS

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Permitir solicitudes desde la misma origen o Tor browser
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Vary: Origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

// Responder preflight CORS antes que cualquier otra lógica
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// JSON como contenido por defecto
header('Content-Type: application/json; charset=utf-8');

// Configuración de tiempo para Tor
ini_set('default_socket_timeout', 60);
set_time_limit(120);

// Conexión PDO a la base de datos
$host = 'localhost';
$dbname = 'swiftpay';
$username = 'hyperhd';
$password = 'chikihyper666';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

// Configuración de wallets y tolerancia de pago
$wallets = [
    'bitcoin' => 'bc1q35afylzfkw7msxdh8459avwcdl653ga9f540cs',
    'monero' => '4AdUndXHHZ6cfufTMvppY6JwXNouMBzSkbLYfpAV5Usx3skxNgYeYTRJ5LkBArVP7oxLLds7LvBpYwVNHt8bQZDJKJKGd'
];
$apis = [
    'bitcoin' => 'https://blockstream.info/api/',
    'monero' => 'https://xmrchain.net/api/'
];
$payment_tolerance = [
    'bitcoin' => 0.00000001,
    'monero' => 0.000000001
];

// Función para peticiones HTTP
function makeHttpRequest($url, $timeout = 60) {
    $ctx = stream_context_create([
        'http' => ['timeout' => $timeout, 'user_agent' => 'SwiftPay/1.0', 'follow_location' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) throw new Exception("HTTP request failed: $url");
    return $resp;
}

// Función de logging
function logTransaction($orderId, $message, $level = 'INFO') {
    $ts = date('Y-m-d H:i:s');
    error_log("[$ts] [$level] Order $orderId – $message\n", 3, 'transactions.log');
}
