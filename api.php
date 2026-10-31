<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
require __DIR__ . '/config.php';

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (!isset($_SERVER['HTTP_X_LOCAPRO_KEY']) ||
    !hash_equals((string)LOCAPRO_API_PASSWORD, (string)$_SERVER['HTTP_X_LOCAPRO_KEY'])) {
    respond(401, ['error' => 'Senha de sincronização incorreta.']);
}
$action = $_GET['action'] ?? '';
$file = __DIR__ . '/dados/locapro_data.json';
if ($action === 'load' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_file($file)) respond(200, ['data' => null]);
    $raw = file_get_contents($file);
    $data = json_decode($raw ?: 'null', true);
    if (!is_array($data)) respond(500, ['error' => 'Arquivo de dados inválido no servidor.']);
    respond(200, ['data' => $data]);
}
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 12 * 1024 * 1024) respond(413, ['error' => 'Arquivo de dados excede 12 MB.']);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data) || !isset($data['quotes'], $data['orders'], $data['payments'])) {
        respond(400, ['error' => 'Estrutura de dados inválida.']);
    }
    $dir = __DIR__ . '/dados';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) respond(500, ['error' => 'Não foi possível criar a pasta dados.']);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($file, $json, LOCK_EX) === false) {
        respond(500, ['error' => 'Sem permissão para gravar em dados/.']);
    }
    respond(200, ['ok' => true, 'saved_at' => date(DATE_ATOM)]);
}
respond(400, ['error' => 'Ação ou método não permitido.']);
