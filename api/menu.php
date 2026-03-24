<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/app.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->prepare("SELECT id, name, description, price, img FROM menu WHERE active = 1 ORDER BY id ASC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        if (!empty($r['img'])) {
            $r['img'] = app_url($r['img']);
        }
    }
    echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { error_log($e->getMessage()); echo json_encode(['success'=>false]); }
