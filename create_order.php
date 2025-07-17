<?php
// create_order.php — Crear nueva orden
require_once 'config.php';

// Solo aceptar POST (OPTIONS se manejó en config.php)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Leer input JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Campos requeridos
$required = ['email','details','paymentMethod','totalAmount','cryptoAmount','items'];
foreach ($required as $f) {
    if (empty($input[$f])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $f"]);
        exit;
    }
}

// Validaciones
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}
if (!in_array($input['paymentMethod'], ['bitcoin','monero'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit;
}
if (!is_numeric($input['totalAmount']) || $input['totalAmount'] <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid total amount']);
    exit;
}

// Preparar datos
$orderId = 'SP' . time() . rand(1000,9999);
$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$details = htmlspecialchars($input['details'], ENT_QUOTES, 'UTF-8');
$pm = $input['paymentMethod'];
$total = round(floatval($input['totalAmount']), 2);
$crypto = floatval($input['cryptoAmount']);
$items = json_encode($input['items']);

try {
    $stmt = $pdo->prepare("INSERT INTO orders (order_id,customer_email,delivery_details,payment_method,total_amount,crypto_amount,items,status,created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$orderId, $email, $details, $pm, $total, $crypto, $items]);
    logTransaction($orderId, "Created: $total USD, $crypto $pm");
    global $wallets, $payment_tolerance;
    echo json_encode([
        'success'       => true,
        'orderId'       => $orderId,
        'paymentMethod' => $pm,
        'cryptoAmount'  => $crypto,
        'walletAddress' => $wallets[$pm],
        'tolerance'     => $payment_tolerance[$pm]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    logTransaction($orderId, "Create error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Order creation failed']);
}
