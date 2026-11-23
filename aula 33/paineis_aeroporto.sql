-- =====================================================================
-- Prática: Painéis do Aeroporto — modelo estrela, ETL e consultas
-- Origem (OLTP): banco `aeroporto` do projeto (VOO, AERONAVE, PASSAGEIRO_VOO)
-- Destino (DW):  banco `aeroporto_dw` (separado; não altera o OLTP)
-- MySQL 8.0 (Laragon)
-- =====================================================================

-- Aeroporto monitorado pelos painéis (o OLTP não guarda essa informação: lacuna L1)
SET @aeroporto_sede = 'BSB';

DROP DATABASE IF EXISTS aeroporto_dw;
CREATE DATABASE aeroporto_dw;
USE aeroporto_dw;

-- ---------------------------------------------------------------------
-- 1. MODELO ESTRELA
-- Grão: uma linha da fato representa UM VOO (um registro de VOO no OLTP).
-- ---------------------------------------------------------------------

CREATE TABLE dim_tempo (
  sk_tempo      INT PRIMARY KEY,          -- AAAAMMDD
  data          DATE NOT NULL,
  dia           TINYINT NOT NULL,
  mes           TINYINT NOT NULL,
  nome_mes      VARCHAR(10) NOT NULL,
  ano_mes       CHAR(7) NOT NULL,         -- '2026-11' (eixo dos gráficos mensais)
  trimestre     TINYINT NOT NULL,
  ano           SMALLINT NOT NULL,
  dia_semana    VARCHAR(10) NOT NULL,
  fim_de_semana CHAR(1) NOT NULL          -- 'S' / 'N'
);

CREATE TABLE dim_aeroporto (                -- dimensão de papel duplo: origem e destino
  sk_aeroporto  INT AUTO_INCREMENT PRIMARY KEY,
  codigo_iata   CHAR(3) NOT NULL UNIQUE,
  nome          VARCHAR(80) NOT NULL,
  cidade        VARCHAR(50) NOT NULL,
  uf            CHAR(2) NULL
);

CREATE TABLE dim_companhia (
  sk_companhia  INT AUTO_INCREMENT PRIMARY KEY,
  codigo_iata   CHAR(2) NOT NULL UNIQUE,
  nome          VARCHAR(60) NOT NULL
);

CREATE TABLE dim_aeronave (
  sk_aeronave   INT AUTO_INCREMENT PRIMARY KEY,
  id_aeronave   INT NOT NULL UNIQUE,      -- chave natural do OLTP
  matricula     VARCHAR(10) NOT NULL,
  modelo        VARCHAR(50) NOT NULL
);

CREATE TABLE fato_voo (
  sk_tempo        INT NOT NULL,           -- data da partida
  sk_origem       INT NOT NULL,
  sk_destino      INT NOT NULL,
  sk_companhia    INT NOT NULL,
  sk_aeronave     INT NOT NULL,
  id_voo          INT NOT NULL PRIMARY KEY, -- dimensão degenerada (garante 1 linha por voo)
  codigo_voo      VARCHAR(6) NOT NULL,    -- dimensão degenerada
  sentido         VARCHAR(8) NOT NULL,    -- 'PARTIDA' / 'CHEGADA' / 'OUTRO' em relação ao aeroporto sede
  qtd_voos        TINYINT NOT NULL DEFAULT 1,  -- aditiva
  qtd_passageiros INT NOT NULL,           -- aditiva (passageiros distintos daquele voo)
  duracao_min     INT NOT NULL,           -- aditiva (soma = minutos voados)
  FOREIGN KEY (sk_tempo)     REFERENCES dim_tempo(sk_tempo),
  FOREIGN KEY (sk_origem)    REFERENCES dim_aeroporto(sk_aeroporto),
  FOREIGN KEY (sk_destino)   REFERENCES dim_aeroporto(sk_aeroporto),
  FOREIGN KEY (sk_companhia) REFERENCES dim_companhia(sk_companhia),
  FOREIGN KEY (sk_aeronave)  REFERENCES dim_aeronave(sk_aeronave)
);

-- ---------------------------------------------------------------------
-- 4. ETL  (Extract do OLTP -> Transform -> Load: dimensões antes da fato)
-- ---------------------------------------------------------------------

-- 4.1 Tabelas de apoio para dados que o OLTP não tem (lacunas L2 e L3).
--     Completar com os códigos usados no projeto.
CREATE TABLE apoio_aeroporto (codigo_iata CHAR(3) PRIMARY KEY, nome VARCHAR(80), cidade VARCHAR(50), uf CHAR(2));
INSERT INTO apoio_aeroporto VALUES
 ('BSB','Aeroporto Internacional de Brasília','Brasília','DF'),
 ('GRU','Aeroporto Internacional de Guarulhos','Guarulhos','SP'),
 ('CGH','Aeroporto de Congonhas','São Paulo','SP'),
 ('GIG','Aeroporto Internacional do Galeão','Rio de Janeiro','RJ'),
 ('SDU','Aeroporto Santos Dumont','Rio de Janeiro','RJ'),
 ('CNF','Aeroporto Internacional de Confins','Belo Horizonte','MG'),
 ('GYN','Aeroporto de Goiânia','Goiânia','GO'),
 ('SSA','Aeroporto Internacional de Salvador','Salvador','BA'),
 ('REC','Aeroporto Internacional do Recife','Recife','PE'),
 ('FOR','Aeroporto Internacional de Fortaleza','Fortaleza','CE'),
 ('POA','Aeroporto Internacional Salgado Filho','Porto Alegre','RS'),
 ('MAO','Aeroporto Internacional de Manaus','Manaus','AM');

CREATE TABLE apoio_companhia (codigo_iata CHAR(2) PRIMARY KEY, nome VARCHAR(60));
INSERT INTO apoio_companhia VALUES
 ('LA','LATAM'), ('AD','Azul'), ('G3','GOL'), ('TP','TAP'), ('AA','American Airlines');
-- Obs.: a validação do projeto (2 letras + números) recusa 'G3', então voos da GOL não entram hoje (lacuna L3).

-- 4.2 dim_tempo: um dia por linha, do primeiro ao último voo
SET SESSION cte_max_recursion_depth = 100000;
INSERT INTO dim_tempo
WITH RECURSIVE dias AS (
  SELECT DATE(MIN(data_hora_partida)) AS d, DATE(MAX(data_hora_partida)) AS fim FROM aeroporto.VOO
  UNION ALL
  SELECT d + INTERVAL 1 DAY, fim FROM dias WHERE d < fim
)
SELECT CAST(DATE_FORMAT(d, '%Y%m%d') AS UNSIGNED), d, DAY(d), MONTH(d),
       ELT(MONTH(d),'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho',
                    'Agosto','Setembro','Outubro','Novembro','Dezembro'),
       DATE_FORMAT(d, '%Y-%m'), QUARTER(d), YEAR(d),
       ELT(DAYOFWEEK(d),'Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'),
       IF(DAYOFWEEK(d) IN (1,7), 'S', 'N')
FROM dias
WHERE d IS NOT NULL;

-- 4.3 dim_aeroporto: todos os códigos que aparecem como origem ou destino
INSERT INTO dim_aeroporto (codigo_iata, nome, cidade, uf)
SELECT x.cod,
       COALESCE(a.nome,   CONCAT('Aeroporto ', x.cod)),
       COALESCE(a.cidade, 'Não informada'),
       a.uf
FROM (SELECT UPPER(TRIM(origem)) AS cod FROM aeroporto.VOO
      UNION
      SELECT UPPER(TRIM(destino)) FROM aeroporto.VOO) x
LEFT JOIN apoio_aeroporto a ON a.codigo_iata = x.cod;

-- 4.4 dim_companhia: derivada das 2 letras iniciais do codigo_voo
INSERT INTO dim_companhia (codigo_iata, nome)
SELECT x.cod, COALESCE(c.nome, CONCAT('Companhia ', x.cod))
FROM (SELECT DISTINCT UPPER(LEFT(codigo_voo, 2)) AS cod FROM aeroporto.VOO) x
LEFT JOIN apoio_companhia c ON c.codigo_iata = x.cod;

-- 4.5 dim_aeronave
INSERT INTO dim_aeronave (id_aeronave, matricula, modelo)
SELECT id_aeronave, matricula, modelo FROM aeroporto.AERONAVE;

-- 4.6 fato_voo (por último): troca chaves naturais por surrogate keys
--     e pré-agrega os passageiros por voo (evita contar voos em duplicidade).
INSERT INTO fato_voo (sk_tempo, sk_origem, sk_destino, sk_companhia, sk_aeronave,
                      id_voo, codigo_voo, sentido, qtd_voos, qtd_passageiros, duracao_min)
SELECT CAST(DATE_FORMAT(v.data_hora_partida, '%Y%m%d') AS UNSIGNED),
       o.sk_aeroporto, d.sk_aeroporto, c.sk_companhia, a.sk_aeronave,
       v.id_voo, v.codigo_voo,
       CASE WHEN UPPER(v.origem)  = @aeroporto_sede THEN 'PARTIDA'
            WHEN UPPER(v.destino) = @aeroporto_sede THEN 'CHEGADA'
            ELSE 'OUTRO' END,
       1,
       COALESCE(p.qtd, 0),
       TIMESTAMPDIFF(MINUTE, v.data_hora_partida, v.data_hora_chegada)
FROM aeroporto.VOO v
JOIN dim_aeroporto o ON o.codigo_iata = UPPER(TRIM(v.origem))
JOIN dim_aeroporto d ON d.codigo_iata = UPPER(TRIM(v.destino))
JOIN dim_companhia c ON c.codigo_iata = UPPER(LEFT(v.codigo_voo, 2))
JOIN dim_aeronave  a ON a.id_aeronave = v.id_aeronave
LEFT JOIN (SELECT id_voo, COUNT(DISTINCT id_passageiro) AS qtd
           FROM aeroporto.PASSAGEIRO_VOO
           GROUP BY id_voo) p ON p.id_voo = v.id_voo;

-- ---------------------------------------------------------------------
-- 3. CONSULTAS DOS PAINÉIS (todas partem da fato)
-- ---------------------------------------------------------------------

-- Painel 1 — Destinos mais frequentes (ranking) · slice em sentido = 'PARTIDA'
SELECT   d.codigo_iata, d.cidade, SUM(f.qtd_voos) AS voos
FROM     fato_voo f
JOIN     dim_aeroporto d ON d.sk_aeroporto = f.sk_destino
WHERE    f.sentido = 'PARTIDA'
GROUP BY d.codigo_iata, d.cidade
ORDER BY voos DESC
LIMIT    10;

-- Painel 2 — Passageiros por mês (evolução) · roll up de dia para mês
SELECT   t.ano_mes, SUM(f.qtd_passageiros) AS passageiros
FROM     fato_voo f
JOIN     dim_tempo t ON t.sk_tempo = f.sk_tempo
WHERE    t.ano = 2026
GROUP BY t.ano_mes
ORDER BY t.ano_mes;

-- Painel 3 — Número de voos (quanto?) · dice: período + sentido
SELECT   SUM(f.qtd_voos) AS total_voos
FROM     fato_voo f
JOIN     dim_tempo t ON t.sk_tempo = f.sk_tempo
WHERE    t.ano = 2026
  AND    f.sentido IN ('PARTIDA', 'CHEGADA');

-- Painel 4 — Companhia mais atuante (ranking / participação)
SELECT   c.nome AS companhia,
         SUM(f.qtd_voos) AS voos,
         ROUND(100 * SUM(f.qtd_voos) / SUM(SUM(f.qtd_voos)) OVER (), 1) AS participacao_pct
FROM     fato_voo f
JOIN     dim_companhia c ON c.sk_companhia = f.sk_companhia
JOIN     dim_tempo t     ON t.sk_tempo     = f.sk_tempo
WHERE    t.ano = 2026
GROUP BY c.nome
ORDER BY voos DESC
LIMIT    5;

-- Painel 5 (livre) — Uso da frota: voos e horas voadas por modelo de aeronave
--   (drill down: modelo -> matrícula, acrescentando a.matricula no SELECT e no GROUP BY)
SELECT   a.modelo,
         SUM(f.qtd_voos)                                   AS voos,
         ROUND(SUM(f.duracao_min) / 60, 1)                 AS horas_voadas,
         ROUND(SUM(f.duracao_min) / SUM(f.qtd_voos), 0)    AS duracao_media_min
FROM     fato_voo f
JOIN     dim_aeronave a ON a.sk_aeronave = f.sk_aeronave
JOIN     dim_tempo t    ON t.sk_tempo    = f.sk_tempo
WHERE    t.ano = 2026
GROUP BY a.modelo
ORDER BY horas_voadas DESC
LIMIT    10;
