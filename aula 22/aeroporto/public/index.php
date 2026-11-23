<?php

require_once __DIR__ . '/../includes/auth.php';
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
    case 'excluir':            // GET = confirmação, POST = soft delete
        $controller->excluir();
        break;
    case 'excluidos':          // tela "Voos excluídos"
        $controller->excluidos();
        break;
    case 'restaurar':          // POST = restaura o voo
        $controller->restaurar();
        break;
    default:
        $controller->listar();
        break;
}
