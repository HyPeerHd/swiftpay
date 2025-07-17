<?php
// Configuración específica para Tor
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}


// Configuración de tiempo para Tor (conexiones más lentas)
ini_set('default_socket_timeout', 60);
set_time_limit(120);

// config.php - Configuración del sistema
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers para CORS y JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'swiftpay';
$username = 'hyperhd';  // Cambiar por mi usuario real
$password = 'chikihyper666'; // Cambiar por mi password real

// Conexión PDO optimizada
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_SSL_CA => false
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Direcciones de wallets (usar mis direcciones reales)
$wallets = [
    'bitcoin' => 'bc1q35afylzfkw7msxdh8459avwcdl653ga9f540cs',
    'monero' => '4AdUndXHHZ6cfufTMvppY6JwXNouMBzSkbLYfpAV5Usx3skxNgYeYTRJ5LkBArVP7oxLLds7LvBpYwVNHt8bQZDJKJKGd'
];

// APIs para verificación de blockchain
$apis = [
    'bitcoin' => 'https://blockstream.info/api/',
    'monero' => 'https://xmrchain.net/api/'
];

// Tolerancia para pagos (muy baja para forzar exactitud)
$payment_tolerance = [
    'bitcoin' => 0.00000001, // 1 satoshi
    'monero' => 0.000000001  // 1 piconero convertido
];

// Función para hacer peticiones HTTP optimizada
function makeHttpRequest($url, $timeout = 60) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'Mozilla/5.0 (compatible; SwiftPay/1.0)',
            'follow_location' => true,
            'max_redirects' => 3,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception("Failed to connect to API: $url");
    }
    
    return $response;
}

// Función para logging optimizado
function logTransaction($orderId, $message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] Order: $orderId - $message\n";
    error_log($logEntry, 3, 'transactions.log');
}
?>