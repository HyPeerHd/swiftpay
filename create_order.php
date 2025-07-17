<?php

// AGREGAR AL INICIO DE create_order.php PARA DEBUG:
if (isset($_GET['test'])) {
    echo json_encode([
        'database' => $pdo ? 'Connected' : 'Failed',
        'wallets' => $wallets,
        'server' => $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}


// create_order.php - Crear nuevo pedido
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Obtener y validar datos de entrada
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validar campos requeridos
$requiredFields = ['email', 'details', 'paymentMethod', 'totalAmount', 'cryptoAmount', 'items'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Validar email
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validar método de pago
if (!in_array($input['paymentMethod'], ['bitcoin', 'monero'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit;
}

// Validar montos
if (!is_numeric($input['totalAmount']) || $input['totalAmount'] <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid total amount']);
    exit;
}

// Generar ID de orden único
$orderId = 'SP' . time() . rand(1000, 9999);

// Preparar datos
$customerEmail = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$deliveryDetails = htmlspecialchars($input['details'], ENT_QUOTES, 'UTF-8');
$paymentMethod = $input['paymentMethod'];
$totalAmount = round(floatval($input['totalAmount']), 2);
$cryptoAmount = $input['cryptoAmount'];
$items = json_encode($input['items']);

try {
    // Insertar orden en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_id, customer_email, delivery_details, payment_method, 
            total_amount, crypto_amount, items, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $orderId, $customerEmail, $deliveryDetails, $paymentMethod, 
        $totalAmount, $cryptoAmount, $items
    ]);
    
    // Log de la transacción
    logTransaction($orderId, "Order created - Amount: $totalAmount USD, Crypto: $cryptoAmount $paymentMethod");

    //  SOLUCIÓN: Usar las variables globales correctamente
    global $wallets, $payment_tolerance;
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'orderId' => $orderId,
        'cryptoAmount' => $cryptoAmount,
        'walletAddress' => $wallets[$paymentMethod],
        'tolerance' => $payment_tolerance[$paymentMethod]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    logTransaction($orderId, "Order creation failed: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Error creating order: ' . $e->getMessage()]);
}
?>
