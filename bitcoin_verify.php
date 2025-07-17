<?php
// bitcoin_verify.php - Verificación de pagos Bitcoin
require_once 'config.php';

function verifyBitcoinPayment($address, $expectedAmount, $timeFrom, $orderId) {
    global $apis, $payment_tolerance;
    
    try {
        // Obtener transacciones de la dirección
        $url = $apis['bitcoin'] . "address/$address/txs";
        $response = makeHttpRequest($url);
        $transactions = json_decode($response, true);
        
        if (!$transactions || !is_array($transactions)) {
            logTransaction($orderId, "No transactions found or invalid response from API", 'WARNING');
            return false;
        }
        
        $totalReceived = 0;
        $validTransactions = [];
        
        foreach ($transactions as $tx) {
            // Solo procesar transacciones confirmadas y después del tiempo especificado
            if (!$tx['status']['confirmed'] || !isset($tx['status']['block_time'])) {
                continue;
            }
            
            if ($tx['status']['block_time'] < $timeFrom) {
                continue;
            }
            
            // Verificar outputs para encontrar pagos a nuestra dirección
            foreach ($tx['vout'] as $output) {
                if (isset($output['scriptpubkey_address']) && $output['scriptpubkey_address'] === $address) {
                    $receivedAmount = $output['value'] / 100000000; // Convertir de satoshis a BTC
                    $totalReceived += $receivedAmount;
                    
                    $validTransactions[] = [
                        'txid' => $tx['txid'],
                        'amount' => $receivedAmount,
                        'time' => $tx['status']['block_time'],
                        'confirmations' => $tx['status']['confirmed'] ? 1 : 0
                    ];
                }
            }
        }
        
        logTransaction($orderId, "Bitcoin verification - Expected: $expectedAmount BTC, Received: $totalReceived BTC");
        
        // Verificar si el monto total recibido es igual o mayor al esperado
        // Usamos tolerancia mínima para forzar exactitud
        if ($totalReceived >= $expectedAmount && 
            abs($totalReceived - $expectedAmount) <= $payment_tolerance['bitcoin']) {
            
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
            logTransaction($orderId, "Bitcoin payment insufficient - Shortfall: $shortfall BTC", 'WARNING');
        }
        
        return false;
        
    } catch (Exception $e) {
        logTransaction($orderId, "Bitcoin verification error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Endpoint para verificar pago
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
    
    $result = verifyBitcoinPayment($address, $expectedAmount, $timeFrom, $orderId);
    
    if ($result && $result['success']) {
        // Actualizar estado del pedido en la base de datos
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'paid', txid = ?, paid_amount = ?, confirmed_at = NOW() 
            WHERE order_id = ?
        ");
        $stmt->execute([$result['txid'], $result['amount'], $orderId]);
        
        logTransaction($orderId, "Bitcoin payment confirmed - TXID: " . $result['txid']);
        
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