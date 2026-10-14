<?php

require_once __DIR__ . '/../models/Voo.php';

class VooController
{
    private Voo $vooModel;

    public function __construct()
    {
        $this->vooModel = new Voo();
    }

    public function listar(): void
    {
        $voos = $this->vooModel->listar();
        require __DIR__ . '/../views/voos/listar.php';
    }

    public function criar(): void
    {
        $aeronaves = $this->vooModel->listarAeronaves();
        require __DIR__ . '/../views/voos/criar.php';
    }

    public function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->vooModel->criar($_POST);
        }
        header('Location: index.php?acao=listar');
        exit;
    }

    public function editar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $voo = $this->vooModel->buscar($id);

        if (!$voo) {
            header('Location: index.php?acao=listar');
            exit;
        }

        $aeronaves = $this->vooModel->listarAeronaves();
        require __DIR__ . '/../views/voos/editar.php';
    }

    public function atualizar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $this->vooModel->editar($id, $_POST);
        }
        header('Location: index.php?acao=listar');
        exit;
    }

    public function excluir(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $this->vooModel->excluir($id);
        }
        header('Location: index.php?acao=listar');
        exit;
    }
}
