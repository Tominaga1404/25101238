<?php

require_once __DIR__ . '/../models/Voo.php';

class VooController
{
    private Voo $vooModel;

    public function __construct()
    {
        $this->vooModel = new Voo();
    }

    private function validar(array $dados): array
    {
        $erros = [];

        $codigo   = strtoupper(trim($dados['codigo_voo'] ?? ''));
        $origem   = strtoupper(trim($dados['origem'] ?? ''));
        $destino  = strtoupper(trim($dados['destino'] ?? ''));
        $partida  = $dados['data_hora_partida'] ?? '';
        $chegada  = $dados['data_hora_chegada'] ?? '';
        $aeronave = (int) ($dados['id_aeronave'] ?? 0);

        // 1. Código: duas letras e 3 ou 4 números (ex.: LA1234)
        if (!preg_match('/^[A-Z]{2}[0-9]{3,4}$/', $codigo)) {
            $erros[] = 'O código do voo deve ter 2 letras e 3 ou 4 números (ex.: LA1234).';
        }

        // 2. Origem diferente de destino
        if ($origem === '' || $destino === '') {
            $erros[] = 'Origem e destino são obrigatórios.';
        } elseif ($origem === $destino) {
            $erros[] = 'A origem deve ser diferente do destino.';
        }

        // 3. Data de partida no futuro
        $tsPartida = strtotime($partida);
        if (!$tsPartida || $tsPartida <= time()) {
            $erros[] = 'A data de partida deve ser no futuro.';
        }

        // 4. Chegada posterior à partida e aeronave selecionada
        $tsChegada = strtotime($chegada);
        if (!$tsChegada || ($tsPartida && $tsChegada <= $tsPartida)) {
            $erros[] = 'A data de chegada deve ser posterior à data de partida.';
        }

        if ($aeronave <= 0) {
            $erros[] = 'Selecione uma aeronave.';
        }

        return $erros;
    }

    public function listar(): void
    {
        $voos = $this->vooModel->listar();
        require __DIR__ . '/../views/voos/listar.php';
    }

    public function criar(): void
    {
        $erros = [];
        $dados = [];
        $aeronaves = $this->vooModel->listarAeronaves();
        require __DIR__ . '/../views/voos/criar.php';
    }

    public function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?acao=criar');
            exit;
        }

        $dados = $_POST;
        $erros = $this->validar($dados);

        if (empty($erros)) {
            try {
                $dados['codigo_voo'] = strtoupper(trim($dados['codigo_voo']));
                $this->vooModel->criar($dados);
                header('Location: index.php?acao=listar');
                exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $erros[] = 'Não foi possível salvar o voo.';
            }
        }

        $aeronaves = $this->vooModel->listarAeronaves();
        require __DIR__ . '/../views/voos/criar.php';
    }

    public function editar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $voo = $this->vooModel->buscar($id);

        if (!$voo) {
            header('Location: index.php?acao=listar');
            exit;
        }

        $erros = [];
        $aeronaves = $this->vooModel->listarAeronaves();
        require __DIR__ . '/../views/voos/editar.php';
    }

    public function atualizar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: index.php?acao=listar');
            exit;
        }

        $dados = $_POST;
        $erros = $this->validar($dados);

        if (empty($erros)) {
            try {
                $dados['codigo_voo'] = strtoupper(trim($dados['codigo_voo']));
                $this->vooModel->editar($id, $dados);
                header('Location: index.php?acao=listar');
                exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $erros[] = 'Não foi possível salvar o voo.';
            }
        }

        $voo = array_merge(['id_voo' => $id], $dados);
        $aeronaves = $this->vooModel->listarAeronaves();
        require __DIR__ . '/../views/voos/editar.php';
    }

    public function excluir(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            try {
                $this->vooModel->excluir($id);
            } catch (PDOException $e) {
                error_log($e->getMessage());
            }
        }
        header('Location: index.php?acao=listar');
        exit;
    }
}