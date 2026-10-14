<?php

require_once __DIR__ . '/../controllers/VooController.php';

$controller = new VooController();
$acao = $_GET['acao'] ?? 'listar';

switch ($acao) {
    case 'listar':
        $controller->listar();
        break;
    case 'criar':
        $controller->criar();
        break;
    case 'salvar':
        $controller->salvar();
        break;
    case 'editar':
        $controller->editar();
        break;
    case 'atualizar':
        $controller->atualizar();
        break;
    case 'excluir':
        $controller->excluir();
        break;
    default:
        $controller->listar();
        break;
}
