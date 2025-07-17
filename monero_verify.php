<?php
// monero_verify.php — Verificar pago XMR
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!$in || !isset($in['orderId'],$in['amount'],$in['address'],$in['timeFrom'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing params']);
    exit;
}

$orderId = $in['orderId'];
$expected = floatval($in['amount']);
$addr = $in['address'];
$timeFrom = intval($in['timeFrom']);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id=? AND status='pending'");
$stmt->execute([$orderId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Order not found or processed']);
    exit;
}

function verifyXMR($addr,$expected,$timeFrom,$orderId) {
    global $payment_tolerance;
    try {
        $url = "https://xmrchain.net/api/outputs?address=$addr&limit=50";
        $resp = makeHttpRequest($url);
        $d = json_decode($resp, true);
        if (empty($d['outputs']) || !is_array($d['outputs'])) {
            logTransaction($orderId, "Invalid XMR API response", 'WARNING');
            return false;
        }
        $total = 0; $valid = [];
        foreach ($d['outputs'] as $out) {
            if ($out['timestamp'] < $timeFrom || $out['confirmations'] < 1) continue;
            $amt = $out['amount'] / 1e12;
            $total += $amt;
            $valid[] = ['txid'=>$out['tx_hash'],'amount'=>$amt,'confirmations'=>$out['confirmations']];
        }
        logTransaction($orderId, "Expected $expected, received $total XMR");
        if ($total >= $expected && abs($total - $expected) <= $payment_tolerance['monero']) {
            return ['success'=>true,'txid'=>$valid[0]['txid'],'amount'=>$total,'transactions'=>$valid];
        }
        if ($total < $expected) logTransaction($orderId, "Underpaid by ".($expected-$total)." XMR", 'WARNING');
        return false;
    } catch (Exception $e) {
        logTransaction($orderId, "Error XMR verify: ".$e->getMessage(), 'ERROR');
        return false;
    }
}

$res = verifyXMR($addr,$expected,$timeFrom,$orderId);
if ($res['success'] ?? false) {
    $stmt = $pdo->prepare("UPDATE orders SET status='paid', txid=?, paid_amount=?, confirmed_at=NOW() WHERE order_id=?");
    $stmt->execute([$res['txid'],$res['amount'],$orderId]);
    logTransaction($orderId, "XMR confirmed TXID: ".$res['txid']);
    echo json_encode(['success'=>true,'data'=>$res,'message'=>'XMR payment verified']);
} else {
    echo json_encode(['success'=>false,'message'=>'Payment not found or incorrect.']);
}
