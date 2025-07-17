<?php
// monero_verify.php - Verificación de pagos Monero
require_once 'config.php';

function verifyMoneroPayment($address, $expectedAmount, $timeFrom, $orderId) {
    global $payment_tolerance;
    
    try {
        // Para Monero usamos XMRChain.net API
        $url = "https://xmrchain.net/api/outputs?address=$address&limit=50";
        $response = makeHttpRequest($url);
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['outputs']) || !is_array($data['outputs'])) {
            logTransaction($orderId, "No Monero outputs found or invalid API response", 'WARNING');
            return false;
        }
        
        $totalReceived = 0;
        $validTransactions = [];
        
        foreach ($data['outputs'] as $output) {
            // Solo procesar transacciones después del tiempo especificado
            if (!isset($output['timestamp']) || $output['timestamp'] < $timeFrom) {
                continue;
            }
            
            // Solo procesar transacciones confirmadas
            if (!isset($output['confirmations']) || $output['confirmations'] < 1) {
                continue;
            }
            
            $receivedAmount = $output['amount'] / 1000000000000; // Convertir de piconeros a XMR
            $totalReceived += $receivedAmount;
            
            $validTransactions[] = [
                'txid' => $output['tx_hash'],
                'amount' => $receivedAmount,
                'time' => $output['timestamp'],
                'confirmations' => $output['confirmations']
            ];
        }
        
        logTransaction($orderId, "Monero verification - Expected: $expectedAmount XMR, Received: $totalReceived XMR");
        
        // Verificar si el monto total recibido es igual o mayor al esperado
        if ($totalReceived >= $expectedAmount && 
            abs($totalReceived - $expectedAmount) <= $payment_tolerance['monero']) {
            
            return [
                'success' => true,
                'txid' => $validTransactions[0]['txid'] ?? '',
                'amount' => $totalReceived,
                'expected' => $expectedAmount,
                'confirmations' => $validTransactions[0]['confirmations'] ?? 0,
                'transactions' => $validTransactions
            ];
        }
        
        // Si el pago es insuficiente, log detallado
        if ($totalReceived < $expectedAmount) {
            $shortfall = $expectedAmount - $totalReceived;
            logTransaction($orderId, "Monero payment insufficient - Shortfall: $shortfall XMR", 'WARNING');
        }
        
        return false;
        
    } catch (Exception $e) {
        logTransaction($orderId, "Monero verification error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Endpoint para verificar pago Monero
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['orderId'], $input['amount'], $input['address'], $input['timeFrom'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    $orderId = $input['orderId'];
    $expectedAmount = floatval($input['amount']);
    $address = $input['address'];
    $timeFrom = intval($input['timeFrom']);
    
    // Verificar que la orden existe y está pendiente
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND status = 'pending'");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or already processed']);
        exit;
    }
    
    $result = verifyMoneroPayment($address, $expectedAmount, $timeFrom, $orderId);
    
    if ($result && $result['success']) {
        // Actualizar estado del pedido en la base de datos
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'paid', txid = ?, paid_amount = ?, confirmed_at = NOW() 
            WHERE order_id = ?
        ");
        $stmt->execute([$result['txid'], $result['amount'], $orderId]);
        
        logTransaction($orderId, "Monero payment confirmed - TXID: " . $result['txid']);
        
        echo json_encode([
            'success' => true, 
            'data' => $result,
            'message' => 'Payment verified and confirmed'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Payment not found or amount incorrect. Please ensure you send the exact amount.'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>