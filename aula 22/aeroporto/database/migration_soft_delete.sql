-- =====================================================================
-- Aula 22 — Soft delete de voos + auditoria
-- Banco: aeroporto (MySQL 8.0 / Laragon)
-- Rodar UMA vez, depois do script de criação das tabelas.
-- =====================================================================
USE aeroporto;

-- ---------------------------------------------------------------------
-- 1. Migration: coluna deleted_at na tabela VOO
--    NULL = voo ativo; data/hora preenchida = voo excluído (soft delete)
-- ---------------------------------------------------------------------
ALTER TABLE VOO
  ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL;

-- Índice em deleted_at: todas as consultas filtram por essa coluna
CREATE INDEX idx_voo_deleted_at ON VOO (deleted_at);

-- ---------------------------------------------------------------------
-- 2. Unicidade só entre voos ATIVOS
--    O mesmo código de voo se repete em dias diferentes (LA1234 de hoje e
--    de amanhã), então a regra é: não pode haver dois voos ativos com o
--    mesmo código na mesma partida.
--    A coluna gerada `ativo` vale 1 para voo ativo e NULL para excluído.
--    Como o índice único ignora linhas com NULL, um voo excluído não
--    bloqueia o cadastro de um novo voo com o mesmo código e horário.
-- ---------------------------------------------------------------------
ALTER TABLE VOO
  ADD COLUMN ativo TINYINT
    GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) VIRTUAL;

CREATE UNIQUE INDEX uq_voo_codigo_partida_ativo
  ON VOO (CODIGO_VOO, DATA_HORA_PARTIDA, ativo);

-- ---------------------------------------------------------------------
-- 3. Tabela de auditoria (gravada na mesma transação da exclusão)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  tabela      VARCHAR(50) NOT NULL,
  registro_id INT         NOT NULL,
  acao        VARCHAR(20) NOT NULL,      -- 'EXCLUIR' ou 'RESTAURAR'
  usuario_id  INT         NOT NULL,      -- id do usuário logado (tabela usuarios)
  dados_antes JSON        NULL,          -- foto do registro antes da ação
  criado_em   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auditoria_registro (tabela, registro_id)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 4. Consultas de referência
-- ---------------------------------------------------------------------
-- Ativos:     SELECT * FROM VOO WHERE deleted_at IS NULL;
-- Excluídos:  SELECT * FROM VOO WHERE deleted_at IS NOT NULL;
-- Histórico:  SELECT * FROM auditoria WHERE tabela = 'VOO' ORDER BY criado_em DESC;

-- ---------------------------------------------------------------------
-- 5. Política de purga (NÃO rodar sem decisão do negócio / LGPD)
--    Os voos "excluídos" continuam no banco. Uma rotina periódica pode
--    apagar de vez o que foi excluído há muito tempo, por exemplo:
--
-- DELETE FROM VOO
--  WHERE deleted_at < NOW() - INTERVAL 5 YEAR;
--
--    Atenção: a auditoria guarda uma cópia dos dados em dados_antes.
--    A purga precisa tratar também essa tabela.
-- ---------------------------------------------------------------------
