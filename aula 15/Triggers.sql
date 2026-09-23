-- ====================================================================
-- ATIVIDADE PRÁTICA: TRIGGERS EM SGBD (MYSQL)
-- Aluno: Rafael Tominaga
-- Disciplina: Banco de Dados
-- Professor: Me. Moises Silva de Sousa
-- Banco de Dados: bd_aeroporto
--
-- DESCRIÇÃO SINTÉTICA DAS TABELAS:
-- 1. aeronave: Cadastro das aeronaves da frota, modelos e capacidade máxima de passageiros.
-- 2. passageiro: Cadastro individual dos clientes com dados cadastrais e de contato.
-- 3. voo: Planejamento operacional contendo rota (origem/destino), cronograma e aeronave alocada.
-- 4. passagem: Registro de emissão de bilhetes vinculando passageiro e voo com assento marcado.
-- 5. log_alteracao_voo: Tabela de auditoria para rastreabilidade de remarcações de horários.
-- ====================================================================

USE bd_aeroporto;
-- Tabela para o Desafio 2 (Auditoria)
DROP TABLE IF EXISTS log_alteracao_voo;
CREATE TABLE log_alteracao_voo (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    id_voo INT NOT NULL,
    codigo_voo VARCHAR(20) NOT NULL,
    partida_anterior DATETIME,
    partida_nova DATETIME,
    chegada_anterior DATETIME,
    chegada_nova DATETIME,
    usuario_responsavel VARCHAR(100) NOT NULL,
    data_hora_modificacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_voo FOREIGN KEY (id_voo) REFERENCES voo(ID_VOO) ON DELETE CASCADE
);

-- Tabela para o Desafio 3 (Sincronização em Cascata)
DROP TABLE IF EXISTS passagem;
CREATE TABLE passagem (
    id_passagem INT AUTO_INCREMENT PRIMARY KEY,
    num_assento VARCHAR(10) NOT NULL,
    id_voo INT NOT NULL,
    id_passageiro INT NOT NULL,
    CONSTRAINT fk_passagem_voo FOREIGN KEY (id_voo) REFERENCES voo(ID_VOO) ON DELETE CASCADE,
    CONSTRAINT fk_passagem_passageiro FOREIGN KEY (id_passageiro) REFERENCES passageiro(ID_PASSAGEIRO) ON DELETE CASCADE
);

DELIMITER $$

-- --------------------------------------------------------------------
-- DESAFIO 1: Validação de Regra de Negócio Operacional (BEFORE)
-- Propósito: Impedir inconsistência cronológica garantindo que DATA_HORA_PARTIDA < DATA_HORA_CHEGADA.
-- --------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_valida_horario_voo_ins$$
CREATE TRIGGER trg_valida_horario_voo_ins
BEFORE INSERT ON voo
FOR EACH ROW
BEGIN
    IF NEW.DATA_HORA_PARTIDA >= NEW.DATA_HORA_CHEGADA THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro de Coerência Temporal: A data/hora de partida não pode ser posterior ou igual à data/hora de chegada.';
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_valida_horario_voo_upd$$
CREATE TRIGGER trg_valida_horario_voo_upd
BEFORE UPDATE ON voo
FOR EACH ROW
BEGIN
    IF NEW.DATA_HORA_PARTIDA >= NEW.DATA_HORA_CHEGADA THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erro de Coerência Temporal: A nova data/hora de partida não pode ser posterior ou igual à de chegada.';
    END IF;
END$$

-- --------------------------------------------------------------------
-- DESAFIO 2: Auditoria e Rastreabilidade de Modificações (AFTER UPDATE)
-- Propósito: Capturar modificações de datas/horários de voos, registrando
--            estados OLD e NEW, carimbo de data/hora e o usuário do SGBD.
-- --------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_auditoria_horario_voo$$
CREATE TRIGGER trg_auditoria_horario_voo
AFTER UPDATE ON voo
FOR EACH ROW
BEGIN
    IF (OLD.DATA_HORA_PARTIDA <> NEW.DATA_HORA_PARTIDA OR OLD.DATA_HORA_CHEGADA <> NEW.DATA_HORA_CHEGADA) THEN
        INSERT INTO log_alteracao_voo (
            id_voo,
            codigo_voo,
            partida_anterior,
            partida_nova,
            chegada_anterior,
            chegada_nova,
            usuario_responsavel,
            data_hora_modificacao
        ) VALUES (
            OLD.ID_VOO,
            OLD.CODIGO_VOO,
            OLD.DATA_HORA_PARTIDA,
            NEW.DATA_HORA_PARTIDA,
            OLD.DATA_HORA_CHEGADA,
            NEW.DATA_HORA_CHEGADA,
            CURRENT_USER(),
            NOW()
        );
    END IF;
END$$

-- --------------------------------------------------------------------
-- DESAFIO 3: Sincronização Automática em Cascata (AFTER INSERT)
-- Propósito: Ao emitir uma passagem, decrementar de forma automática
--            a capacidade de assentos da aeronave associada àquele voo.
-- --------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_sincroniza_assentos_aeronave$$
CREATE TRIGGER trg_sincroniza_assentos_aeronave
AFTER INSERT ON passagem
FOR EACH ROW
BEGIN
    UPDATE aeronave
    SET CAPACIDADE = CAPACIDADE - 1
    WHERE ID_AERONAVE = (SELECT ID_AERONAVE FROM voo WHERE ID_VOO = NEW.id_voo);
END$$

DELIMITER ;

-- --------------------------------------------------------------------
-- TESTES DO DESAFIO 1: Validação de Coerência Temporal
-- --------------------------------------------------------------------

-- Teste 1.1: Cenário Inválido (Esperado: Bloqueio via SIGNAL SQLSTATE '45000')
-- Partida posterior à chegada
INSERT INTO voo (CODIGO_VOO, ORIGEM, DESTINO, DATA_HORA_PARTIDA, DATA_HORA_CHEGADA, ID_AERONAVE)
VALUES ('TESTE-INV', 'BSB', 'GIG', '2026-11-20 15:00:00', '2026-11-20 14:00:00', 1);

-- Teste 1.2: Cenário Válido (Esperado: Inserção bem-sucedida)
INSERT INTO voo (CODIGO_VOO, ORIGEM, DESTINO, DATA_HORA_PARTIDA, DATA_HORA_CHEGADA, ID_AERONAVE)
VALUES ('TESTE-OK', 'BSB', 'GIG', '2026-11-20 14:00:00', '2026-11-20 15:45:00', 1);

-- --------------------------------------------------------------------
-- TESTES DO DESAFIO 2: Auditoria de Modificações
-- --------------------------------------------------------------------

-- Teste 2.1: Executar um UPDATE na tabela VOO alterando os horários
UPDATE voo 
SET DATA_HORA_PARTIDA = '2026-11-20 14:30:00',
    DATA_HORA_CHEGADA = '2026-11-20 16:15:00'
WHERE CODIGO_VOO = 'TESTE-OK';

-- Teste 2.2: Verificar o registro gravado pelo trigger na tabela de log
SELECT * FROM log_alteracao_voo;

-- --------------------------------------------------------------------
-- TESTES DO DESAFIO 3: Sincronização em Cascata
-- --------------------------------------------------------------------

-- Teste 3.1: Consultar a capacidade inicial da Aeronave 1 antes da venda
SELECT ID_AERONAVE, MODELO, CAPACIDADE FROM aeronave WHERE ID_AERONAVE = 1;

-- Teste 3.2: Inserir a venda de uma passagem (garantindo FK de passageiro existente)
INSERT INTO passagem (num_assento, id_voo, id_passageiro)
VALUES (
    '12A',
    (SELECT ID_VOO FROM voo WHERE CODIGO_VOO = 'TESTE-OK' LIMIT 1),
    (SELECT ID_PASSAGEIRO FROM passageiro LIMIT 1)
);

-- Teste 3.3: Comprovar o decremento automático da capacidade
SELECT ID_AERONAVE, MODELO, CAPACIDADE FROM aeronave WHERE ID_AERONAVE = 1;
