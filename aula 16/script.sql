-- ====================================================================
-- ATIVIDADE: CONCORRÊNCIA, LOCKING E PROCEDIMENTO ATÔMICO DE RESERVA
-- Aluno: Rafael Tominaga
-- Disciplina: Banco de Dados 
-- Professor: Me. Moises Silva de Sousa
-- Banco de Dados: bd_aeroporto
-- ====================================================================

USE bd_aeroporto;

DROP TABLE IF EXISTS reservas;
DROP TABLE IF EXISTS assentos;

CREATE TABLE assentos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    voo_id INT NOT NULL,
    numero VARCHAR(10) NOT NULL,
    classe VARCHAR(30) NOT NULL DEFAULT 'ECONOMICA',
    status VARCHAR(30) NOT NULL DEFAULT 'DISPONIVEL',
    CONSTRAINT fk_assento_voo FOREIGN KEY (voo_id) REFERENCES voo(ID_VOO) ON DELETE CASCADE,
    CONSTRAINT uq_assento_voo UNIQUE (voo_id, numero)
) ENGINE=InnoDB;

CREATE TABLE reservas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    passageiro_id INT NOT NULL,
    assento_id BIGINT NOT NULL,
    data_reserva TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) NOT NULL DEFAULT 'CONFIRMADA',
    CONSTRAINT fk_reserva_passageiro FOREIGN KEY (passageiro_id) REFERENCES passageiro(ID_PASSAGEIRO) ON DELETE CASCADE,
    CONSTRAINT fk_reserva_assento FOREIGN KEY (assento_id) REFERENCES assentos(id) ON DELETE CASCADE,
    CONSTRAINT uq_reserva_assento UNIQUE (assento_id)
) ENGINE=InnoDB;


INSERT INTO assentos (voo_id, numero, classe, status) VALUES
(1, '10A', 'ECONOMICA', 'DISPONIVEL'),
(1, '10B', 'ECONOMICA', 'DISPONIVEL'),
(1, '10C', 'ECONOMICA', 'DISPONIVEL');

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_reservar_assento$$

CREATE PROCEDURE sp_reservar_assento(
    IN p_passageiro_id INT,
    IN p_voo_id INT,
    IN p_numero_assento VARCHAR(10),
    OUT p_resultado VARCHAR(100)
)
sp_bloco: BEGIN
    DECLARE v_assento_id BIGINT;
    DECLARE v_status VARCHAR(30);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_resultado = 'ERRO: Falha na transação ou conflito de integridade.';
    END;

    START TRANSACTION;

    SELECT id, status INTO v_assento_id, v_status
    FROM assentos
    WHERE voo_id = p_voo_id AND numero = p_numero_assento
    FOR UPDATE;

    IF v_assento_id IS NULL THEN
        ROLLBACK;
        SET p_resultado = 'ERRO: O assento informado não existe para este voo.';
        LEAVE sp_bloco;
    END IF;

    IF v_status <> 'DISPONIVEL' THEN
        ROLLBACK;
        SET p_resultado = 'ERRO: O assento já se encontra reservado ou indisponível.';
        LEAVE sp_bloco;
    END IF;

    UPDATE assentos 
    SET status = 'RESERVADO' 
    WHERE id = v_assento_id;

    INSERT INTO reservas (passageiro_id, assento_id, status)
    VALUES (p_passageiro_id, v_assento_id, 'CONFIRMADA');

    COMMIT;
    SET p_resultado = 'SUCESSO: Assento reservado com êxito!';
END$$

DELIMITER ;

-- Teste 1: Primeira tentativa de reserva no assento 10A (Passageiro 1)
CALL sp_reservar_assento(1, 1, '10A', @resultado1);
SELECT @resultado1 AS status_reserva_1;

-- Teste 2: Segunda tentativa concorrente no mesmo assento 10A (Passageiro 2)
CALL sp_reservar_assento(2, 1, '10A', @resultado2);
SELECT @resultado2 AS status_reserva_2;

-- Teste 3: Reserva em assento diferente (10B para o Passageiro 2)
CALL sp_reservar_assento(2, 1, '10B', @resultado3);
SELECT @resultado3 AS status_reserva_3;

-- Conferência final dos dados persistidos
SELECT * FROM assentos WHERE voo_id = 1;
SELECT * FROM reservas;
