<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$db   = 'escola_db';
$user = 'root';
$pass = ''; // Altere conforme suas credenciais locais
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    $stmt = $pdo->query('SELECT id, nome, nota FROM alunos');
    $alunos = $stmt->fetchAll();

    // Regra de Negócio implementada no Backend
    $dadosProcessados = array_map(function ($aluno) {
        $nota = (float)$aluno['nota'];
        return [
            'id'     => (int)$aluno['id'],
            'nome'   => $aluno['nome'],
            'nota'   => $nota,
            'status' => ($nota >= 7.0) ? 'Aprovado' : 'Reprovado'
        ];
    }, $alunos);

    echo json_encode($dadosProcessados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => true,
        'mensagem' => 'Erro ao conectar ou consultar o banco de dados.'
    ]);
}
