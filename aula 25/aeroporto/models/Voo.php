<?php

require_once __DIR__ . '/../config/database.php';

class Voo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conectar();
    }

    public function listar(): array
    {
        $sql = "SELECT v.id_voo, v.codigo_voo, v.origem, v.destino, 
                       v.data_hora_partida, v.data_hora_chegada,
                       a.modelo AS modelo_aeronave, a.matricula
                FROM VOO v
                INNER JOIN AERONAVE a ON v.id_aeronave = a.id_aeronave
                ORDER BY v.data_hora_partida ASC";

        return $this->db->query($sql)->fetchAll();
    }

    public function buscar(int $id): ?array
    {
        $sql = "SELECT * FROM VOO WHERE id_voo = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $voo = $stmt->fetch();

        return $voo ?: null;
    }

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

    public function editar(int $id, array $dados): bool
    {
        $sql = "UPDATE VOO 
                SET codigo_voo = :codigo_voo,
                    origem = :origem,
                    destino = :destino,
                    data_hora_partida = :data_hora_partida,
                    data_hora_chegada = :data_hora_chegada,
                    id_aeronave = :id_aeronave
                WHERE id_voo = :id";

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

    public function excluir(int $id): bool
    {
        // Se houver registros na tabela associativa PASSAGEIRO_VOO, deve-se avaliar se impede ou remove em cascata
        $stmt = $this->db->prepare("DELETE FROM VOO WHERE id_voo = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function listarAeronaves(): array
    {
        $sql = "SELECT id_aeronave, modelo, matricula FROM AERONAVE ORDER BY modelo";
        return $this->db->query($sql)->fetchAll();
    }
}
