<?php

require_once __DIR__ . '/../config/database.php';

class Voo
{
    private PDO $db;

    // Escopos de consulta: o filtro de soft delete fica SÓ aqui.
    private const ATIVOS    = 'v.deleted_at IS NULL';
    private const EXCLUIDOS = 'v.deleted_at IS NOT NULL';

    // Colunas devolvidas para as views (apelidos em minúsculas).
    private const COLUNAS = "v.id_voo, v.codigo_voo, v.origem, v.destino,
                             v.data_hora_partida, v.data_hora_chegada,
                             v.id_aeronave, v.deleted_at";

    public function __construct()
    {
        $this->db = Database::conectar();
    }

    /**
     * Ponto único de leitura da tabela VOO.
     * Toda listagem e busca passa por aqui, sempre com um escopo
     * (ativos ou excluídos). Assim não há consulta "sem filtro".
     */
    private function consultar(string $escopo, string $filtroExtra = '', array $params = [], string $ordem = 'v.data_hora_partida ASC'): array
    {
        $sql = "SELECT " . self::COLUNAS . ",
                       a.modelo AS modelo_aeronave, a.matricula
                FROM VOO v
                INNER JOIN AERONAVE a ON v.id_aeronave = a.id_aeronave
                WHERE {$escopo} {$filtroExtra}
                ORDER BY {$ordem}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Leituras
    // ------------------------------------------------------------------

    /** Lista apenas os voos ativos. */
    public function listar(): array
    {
        return $this->consultar(self::ATIVOS);
    }

    /** Lista apenas os voos excluídos (tela "Voos excluídos"). */
    public function listarExcluidos(): array
    {
        return $this->consultar(self::EXCLUIDOS, '', [], 'v.deleted_at DESC');
    }

    /** Busca um voo ATIVO pelo id. Voo excluído não pode ser editado. */
    public function buscar(int $id): ?array
    {
        $voos = $this->consultar(self::ATIVOS, 'AND v.id_voo = :id', [':id' => $id]);

        return $voos[0] ?? null;
    }

    /** Busca um voo EXCLUÍDO pelo id. */
    public function buscarExcluido(int $id): ?array
    {
        $voos = $this->consultar(self::EXCLUIDOS, 'AND v.id_voo = :id', [':id' => $id]);

        return $voos[0] ?? null;
    }

    // ------------------------------------------------------------------
    // Escritas
    // ------------------------------------------------------------------

    public function criar(array $dados): bool
    {
        $sql = "INSERT INTO VOO (codigo_voo, origem, destino, data_hora_partida, data_hora_chegada, id_aeronave)
                VALUES (:codigo_voo, :origem, :destino, :data_hora_partida, :data_hora_chegada, :id_aeronave)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':codigo_voo'        => $dados['codigo_voo'],
            ':origem'            => strtoupper(trim($dados['origem'])),
            ':destino'           => strtoupper(trim($dados['destino'])),
            ':data_hora_partida' => $dados['data_hora_partida'],
            ':data_hora_chegada' => $dados['data_hora_chegada'],
            ':id_aeronave'       => (int) $dados['id_aeronave'],
        ]);
    }

    /** Atualiza somente voos ativos. */
    public function editar(int $id, array $dados): bool
    {
        $sql = "UPDATE VOO v
                SET v.codigo_voo = :codigo_voo,
                    v.origem = :origem,
                    v.destino = :destino,
                    v.data_hora_partida = :data_hora_partida,
                    v.data_hora_chegada = :data_hora_chegada,
                    v.id_aeronave = :id_aeronave
                WHERE v.id_voo = :id AND " . self::ATIVOS;

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':codigo_voo'        => $dados['codigo_voo'],
            ':origem'            => strtoupper(trim($dados['origem'])),
            ':destino'           => strtoupper(trim($dados['destino'])),
            ':data_hora_partida' => $dados['data_hora_partida'],
            ':data_hora_chegada' => $dados['data_hora_chegada'],
            ':id_aeronave'       => (int) $dados['id_aeronave'],
            ':id'                => $id,
        ]);
    }

    /**
     * Soft delete: marca deleted_at com a data/hora atual (nunca DELETE).
     * A marcação e o registro na auditoria acontecem na MESMA transação:
     * se qualquer passo falhar, o rollback desfaz tudo.
     *
     * @throws RuntimeException se o voo não existir ou já estiver excluído
     * @throws PDOException     se o banco falhar (a exclusão é desfeita)
     */
    public function excluir(int $id, int $usuarioId): void
    {
        $this->alterarSituacao($id, $usuarioId, 'EXCLUIR');
    }

    /**
     * Restaura um voo excluído: volta deleted_at para NULL,
     * também registrando a ação na auditoria na mesma transação.
     */
    public function restaurar(int $id, int $usuarioId): void
    {
        $this->alterarSituacao($id, $usuarioId, 'RESTAURAR');
    }

    private function alterarSituacao(int $id, int $usuarioId, string $acao): void
    {
        $excluir = ($acao === 'EXCLUIR');
        $escopo  = $excluir ? self::ATIVOS : self::EXCLUIDOS;
        $novoValor = $excluir ? 'NOW()' : 'NULL';

        $this->db->beginTransaction();

        try {
            // 1. Trava a linha e guarda a "foto" do registro antes da ação
            $stmt = $this->db->prepare(
                "SELECT " . self::COLUNAS . " FROM VOO v
                 WHERE v.id_voo = :id AND {$escopo}
                 FOR UPDATE"
            );
            $stmt->execute([':id' => $id]);
            $antes = $stmt->fetch();

            if (!$antes) {
                throw new RuntimeException($excluir
                    ? 'Voo não encontrado ou já excluído.'
                    : 'Voo não encontrado entre os excluídos.');
            }

            // 2. Soft delete / restauração (UPDATE, nunca DELETE)
            $stmt = $this->db->prepare(
                "UPDATE VOO v SET v.deleted_at = {$novoValor}
                 WHERE v.id_voo = :id AND {$escopo}"
            );
            $stmt->execute([':id' => $id]);

            // 3. Auditoria: quem fez, o quê e quando
            $stmt = $this->db->prepare(
                "INSERT INTO auditoria (tabela, registro_id, acao, usuario_id, dados_antes)
                 VALUES ('VOO', :registro_id, :acao, :usuario_id, :dados_antes)"
            );
            $stmt->execute([
                ':registro_id' => $id,
                ':acao'        => $acao,
                ':usuario_id'  => $usuarioId,
                ':dados_antes' => json_encode($antes, JSON_UNESCAPED_UNICODE),
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            // Falhou o UPDATE ou o log: nada fica gravado
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function listarAeronaves(): array
    {
        $sql = "SELECT id_aeronave, modelo, matricula FROM AERONAVE ORDER BY modelo";
        return $this->db->query($sql)->fetchAll();
    }
}
