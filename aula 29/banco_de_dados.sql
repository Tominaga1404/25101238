-- =====================================================================
-- Aula 29 - Atividade prática: lendo o plano de execução com EXPLAIN
-- Banco: lab_otimizacao (locadora de carros) - MySQL 8.0.18+ (Laragon)
-- =====================================================================

-- ---------------------------------------------------------------------
-- PARTE 0 - Preparação
-- ---------------------------------------------------------------------
DROP DATABASE IF EXISTS lab_otimizacao;
CREATE DATABASE lab_otimizacao;
USE lab_otimizacao;

CREATE TABLE cliente (
  id     INT PRIMARY KEY,
  nome   VARCHAR(60) NOT NULL,
  cpf    VARCHAR(11) NOT NULL,
  cidade VARCHAR(30) NOT NULL
);

CREATE TABLE locacao (
  id             INT PRIMARY KEY,
  id_cliente     INT NOT NULL,
  id_veiculo     INT NOT NULL,
  data_retirada  DATE NOT NULL,
  valor          DECIMAL(8,2) NOT NULL,
  status         VARCHAR(10) NOT NULL
);

SET SESSION cte_max_recursion_depth = 1000000;

INSERT INTO cliente (id, nome, cpf, cidade)
WITH RECURSIVE seq AS (
  SELECT 1 AS n UNION ALL SELECT n + 1 FROM seq WHERE n < 50000
)
SELECT n,
       CONCAT('Cliente ', n),
       LPAD(n, 11, '0'),
       ELT(1 + n % 5, 'Brasilia', 'Goiania', 'Anapolis', 'Taguatinga', 'Gama')
FROM seq;

INSERT INTO locacao (id, id_cliente, id_veiculo, data_retirada, valor, status)
WITH RECURSIVE seq AS (
  SELECT 1 AS n UNION ALL SELECT n + 1 FROM seq WHERE n < 500000
)
SELECT n,
       1 + (n * 7919) % 50000,
       1 + (n * 31) % 200,
       DATE_ADD('2023-01-01', INTERVAL n % 1095 DAY),
       100 + n % 400,
       CASE WHEN n % 50 = 0 THEN 'cancelada'
            WHEN n % 4  = 0 THEN 'aberta'
            ELSE 'concluida' END
FROM seq;

ANALYZE TABLE cliente, locacao;

-- Conferência
SELECT (SELECT COUNT(*) FROM cliente) AS clientes,
       (SELECT COUNT(*) FROM locacao) AS locacoes;
SHOW INDEX FROM locacao;   -- apenas PRIMARY

-- ---------------------------------------------------------------------
-- PARTE 1 - Diagnóstico sem índices
-- ---------------------------------------------------------------------
-- Q1
EXPLAIN SELECT * FROM locacao WHERE id_cliente = 4242;

-- Q2
EXPLAIN
SELECT c.nome, l.data_retirada, l.valor
FROM cliente c
JOIN locacao l ON l.id_cliente = c.id
WHERE c.cpf = '00000004242';

-- Tempo real da Q1
EXPLAIN ANALYZE SELECT * FROM locacao WHERE id_cliente = 4242;

-- ---------------------------------------------------------------------
-- PARTE 2 - Criando índices e comparando
-- ---------------------------------------------------------------------
CREATE INDEX idx_loc_cliente ON locacao(id_cliente);
CREATE INDEX idx_cli_cpf     ON cliente(cpf);
ANALYZE TABLE cliente, locacao;

EXPLAIN SELECT * FROM locacao WHERE id_cliente = 4242;

EXPLAIN
SELECT c.nome, l.data_retirada, l.valor
FROM cliente c
JOIN locacao l ON l.id_cliente = c.id
WHERE c.cpf = '00000004242';

EXPLAIN ANALYZE SELECT * FROM locacao WHERE id_cliente = 4242;

-- Seletividade na prática
CREATE INDEX idx_loc_status ON locacao(status);
ANALYZE TABLE locacao;

EXPLAIN SELECT * FROM locacao WHERE status = 'cancelada';
EXPLAIN SELECT * FROM locacao WHERE status = 'concluida';

-- ---------------------------------------------------------------------
-- PARTE 3 - Índices que o MySQL ignora
-- ---------------------------------------------------------------------
CREATE INDEX idx_loc_data ON locacao(data_retirada);
CREATE INDEX idx_cli_nome ON cliente(nome);
ANALYZE TABLE cliente, locacao;

-- 3A - Função aplicada na coluna (ANTES)
EXPLAIN SELECT * FROM locacao
WHERE DATE_FORMAT(data_retirada, '%Y-%m') = '2025-06';
-- 3A - Reescrita com faixa de datas (DEPOIS)
EXPLAIN SELECT * FROM locacao
WHERE data_retirada >= '2025-06-01' AND data_retirada < '2025-07-01';

-- 3B - LIKE com % no início (ANTES)
EXPLAIN SELECT * FROM cliente WHERE nome LIKE '%123';
-- 3B - Reescrita: nome começa com 'Cliente 123' (DEPOIS)
EXPLAIN SELECT * FROM cliente WHERE nome LIKE 'Cliente 123%';

-- 3C - Conversão implícita de tipo (ANTES)
EXPLAIN SELECT * FROM cliente WHERE cpf = 4242;
-- 3C - Reescrita comparando com texto (DEPOIS)
EXPLAIN SELECT * FROM cliente WHERE cpf = '00000004242';

-- ---------------------------------------------------------------------
-- PARTE 4 - Índice composto
-- ---------------------------------------------------------------------
CREATE INDEX idx_loc_cli_data ON locacao(id_cliente, data_retirada);
ANALYZE TABLE locacao;

-- (a) filtra pelas duas colunas
EXPLAIN SELECT * FROM locacao
WHERE id_cliente = 4242 AND data_retirada >= '2025-01-01';

-- (b) filtra só pela segunda coluna do índice composto
EXPLAIN SELECT * FROM locacao
WHERE data_retirada >= '2025-06-01' AND data_retirada < '2025-07-01';

-- Desafio: índice simples de data invisível
ALTER TABLE locacao ALTER INDEX idx_loc_data INVISIBLE;
EXPLAIN SELECT * FROM locacao
WHERE data_retirada >= '2025-06-01' AND data_retirada < '2025-07-01';

-- volta ao normal
ALTER TABLE locacao ALTER INDEX idx_loc_data VISIBLE;
