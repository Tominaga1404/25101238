<?php

require_once __DIR__ . '/../models/Voo.php';

class VooController
{
    private Voo $vooModel;

    public function __construct()
    {
        $this->vooModel = new Voo();
    }

    // ------------------------------------------------------------------
    // Utilitários: mensagens de retorno (flash) e proteção CSRF
    // ------------------------------------------------------------------

    private function flash(string $tipo, string $mensagem): void
    {
        $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }

    private function redirecionar(string $acao): void
    {
        header('Location: index.php?acao=' . $acao);
        exit;
    }

    private function tokenCsrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    private function csrfValido(): bool
    {
        return isset($_POST['csrf'], $_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $_POST['csrf']);
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

    /** Erro 1062 = violação do índice único (mesmo código e partida entre voos ativos). */
    private function mensagemErroBanco(PDOException $e, string $padrao): string
    {
        if (($e->errorInfo[1] ?? null) === 1062) {
            return 'Já existe um voo ativo com esse código e esse horário de partida.';
        }
        return $padrao;
    }

    // ------------------------------------------------------------------
    // Listagens
    // ------------------------------------------------------------------

    public function listar(): void
    {
        $voos = $this->vooModel->listar();
        require __DIR__ . '/../views/voos/listar.php';
    }

    public function excluidos(): void
    {
        $voos = $this->vooModel->listarExcluidos();
        $csrf = $this->tokenCsrf();
        require __DIR__ . '/../views/voos/excluidos.php';
    }

    // ------------------------------------------------------------------
    // Cadastro e edição
    // ------------------------------------------------------------------

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
            $this->redirecionar('criar');
        }

        $dados = $_POST;
        $erros = $this->validar($dados);

        if (empty($erros)) {
            try {
                $dados['codigo_voo'] = strtoupper(trim($dados['codigo_voo']));
                $this->vooModel->criar($dados);
                $this->flash('sucesso', 'Voo cadastrado com sucesso.');
                $this->redirecionar('listar');
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $erros[] = $this->mensagemErroBanco($e, 'Não foi possível salvar o voo.');
            }
        }

        $aeronaves = $this->vooModel->listarAeronaves();
        require __DIR__ . '/../views/voos/criar.php';
    }

    public function editar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $voo = $this->vooModel->buscar($id);   // só encontra voos ativos

        if (!$voo) {
            $this->flash('erro', 'Voo não encontrado.');
            $this->redirecionar('listar');
        }

        $erros = [];
        $aeronaves = $this->vooModel->listarAeronaves();
        require __DIR__ . '/../views/voos/editar.php';
    }

    public function atualizar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            $this->redirecionar('listar');
        }

        $dados = $_POST;
        $erros = $this->validar($dados);

        if (empty($erros)) {
            try {
                $dados['codigo_voo'] = strtoupper(trim($dados['codigo_voo']));
                $this->vooModel->editar($id, $dados);
                $this->flash('sucesso', 'Voo atualizado com sucesso.');
                $this->redirecionar('listar');
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $erros[] = $this->mensagemErroBanco($e, 'Não foi possível salvar o voo.');
            }
        }

        $voo = array_merge(['id_voo' => $id], $dados);
        $aeronaves = $this->vooModel->listarAeronaves();
        require __DIR__ . '/../views/voos/editar.php';
    }

    // ------------------------------------------------------------------
    // Soft delete
    // ------------------------------------------------------------------

    /**
     * GET  index.php?acao=excluir&id=N  -> tela de confirmação
     * POST index.php?acao=excluir       -> executa o soft delete
     */
    public function excluir(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $id  = (int) ($_GET['id'] ?? 0);
            $voo = $this->vooModel->buscar($id);

            if (!$voo) {
                $this->flash('erro', 'Voo não encontrado ou já excluído.');
                $this->redirecionar('listar');
            }

            $csrf = $this->tokenCsrf();
            require __DIR__ . '/../views/voos/confirmar_exclusao.php';
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);

        if (!$this->csrfValido()) {
            $this->flash('erro', 'Requisição inválida. Tente novamente.');
            $this->redirecionar('listar');
        }

        try {
            $this->vooModel->excluir($id, (int) $_SESSION['usuario_id']);
            $this->flash('sucesso', 'Voo excluído. Ele pode ser restaurado em "Voos excluídos".');
        } catch (PDOException $e) {
            // Vem antes de RuntimeException, porque PDOException é subclasse dela
            error_log($e->getMessage());
            $this->flash('erro', 'Não foi possível excluir o voo. Nenhuma alteração foi gravada.');
        } catch (RuntimeException $e) {
            $this->flash('erro', $e->getMessage());
        }

        $this->redirecionar('listar');
    }

    /** POST index.php?acao=restaurar -> volta o voo para a lista de ativos */
    public function restaurar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->csrfValido()) {
            $this->flash('erro', 'Requisição inválida. Tente novamente.');
            $this->redirecionar('excluidos');
        }

        $id = (int) ($_POST['id'] ?? 0);

        try {
            $this->vooModel->restaurar($id, (int) $_SESSION['usuario_id']);
            $this->flash('sucesso', 'Voo restaurado com sucesso.');
            $this->redirecionar('listar');
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $this->flash('erro', $this->mensagemErroBanco($e, 'Não foi possível restaurar o voo.'));
        } catch (RuntimeException $e) {
            $this->flash('erro', $e->getMessage());
        }

        $this->redirecionar('excluidos');
    }
}
