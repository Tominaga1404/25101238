## 1. Diagnóstico do Schema

O banco de dados foi modelado para gerenciar a operação de voos, aeronaves e passageiros. Suas tabelas principais e colunas mapeadas são:

* **`aeronave`**: `ID_AERONAVE` (PK), `MODELO`, `CAPACIDADE`, `MATRICULA`.
* **`passageiro`**: `ID_PASSAGEIRO` (PK), `NOME`, `CPF`, `EMAIL`, `TELEFONE`.
* **`voo`**: `ID_VOO` (PK), `CODIGO_VOO`, `ORIGEM`, `DESTINO`, `DATA_HORA_PARTIDA`, `DATA_HORA_CHEGADA`, `ID_AERONAVE` (FK).

---

## 2. Proposta de Views

### View 1: Painel Geral de Voos e Aeronaves
* **Nome da view:** `vw_painel_voos`
* **Tabelas de origem:** `voo`, `aeronave`
* **Tipo:** JOIN
* **É atualizável?:** Não, pois envolve a junção de tabelas base distintas (`voo` e `aeronave`), o que restringe operações de escrita direta (`INSERT`/`UPDATE`) pelo SGBD.
* **Problema que ela resolve:** Evita a necessidade de reescrever junções relacionais (`JOIN`) com frequência para exibir a grade completa de voos juntamente com as especificações técnicas da aeronave (modelo, capacidade e matrícula).
* **Esboço do SELECT / DDL:**

```sql
CREATE VIEW vw_painel_voos AS
SELECT 
    v.ID_VOO,
    v.CODIGO_VOO,
    v.ORIGEM,
    v.DESTINO,
    v.DATA_HORA_PARTIDA,
    v.DATA_HORA_CHEGADA,
    a.MODELO AS MODELO_AERONAVE,
    a.CAPACIDADE,
    a.MATRICULA
FROM voo v
JOIN aeronave a ON v.ID_AERONAVE = a.ID_AERONAVE;
```

---

###View 2: Contato Operacional de Passageiros (Abstração e Privacidade)
* **Nome da view:** `vw_passageiros_contato`
* **Tabelas de origem:** `passageiro`
* **Tipo:** Simples
* **É atualizável?:** Sim, pois mapeia diretamente as colunas de uma tabela individual sem agregações, uniões ou agrupamentos
* **Problema que ela resolve:** Fornece apenas os dados de identificação e contato (`NOME`, `EMAIL`, `TELEFONE`) para os operadores de atendimento e embarque, preservando o sigilo de dados sensíveis como o `CPF`
* **Esboço do SELECT / DDL:**

```sql
CREATE VIEW vw_passageiros_contato AS
SELECT 
    ID_PASSAGEIRO,
    NOME,
    EMAIL,
    TELEFONE
FROM passageiro;
```

---

## 3. Teste de Índices e Medição de Desempenho

O procedimento experimental avaliou o impacto da criação de um índice secundário na coluna `DESTINO` da tabela `voo`

### Passo a Passo dos Comandos Executados

```sql
-- 1. Confirmar se o performance_schema está ativo
SHOW VARIABLES LIKE 'performance_schema';

-- 2. Analisar o plano de execução inicial gerado pelo Otimizador
EXPLAIN SELECT * FROM voo WHERE DESTINO = 'SSA';

-- 3. Limpar os acumuladores de estatísticas do performance_schema
TRUNCATE TABLE performance_schema.events_statements_summary_by_digest;

-- 4. Executar a consulta repetidas vezes para simular carga real
SELECT * FROM voo WHERE DESTINO = 'SSA';
SELECT * FROM voo WHERE DESTINO = 'SSA';
SELECT * FROM voo WHERE DESTINO = 'SSA';

-- 5. Extrair métricas consolidadas de tempo médio e linhas examinadas (Antes)
SELECT 
    DIGEST_TEXT,
    COUNT_STAR AS execucoes,
    AVG_TIMER_WAIT/1000000000 AS tempo_medio_ms,
    SUM_ROWS_EXAMINED AS linhas_examinadas_total
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT LIKE '%voo%'
ORDER BY AVG_TIMER_WAIT DESC;

-- 6. Criar o índice B-Tree na coluna de filtro DESTINO
CREATE INDEX idx_voo_destino ON voo(DESTINO);

-- 7. Verificar a alteração no plano de execução (mudança de type e rows)
EXPLAIN SELECT * FROM voo WHERE DESTINO = 'SSA';

-- 8. Limpar novamente as estatísticas para registrar apenas as novas execuções
TRUNCATE TABLE performance_schema.events_statements_summary_by_digest;

-- 9. Executar a mesma consulta 3 vezes consecutivas utilizando o índice
SELECT * FROM voo WHERE DESTINO = 'SSA';
SELECT * FROM voo WHERE DESTINO = 'SSA';
SELECT * FROM voo WHERE DESTINO = 'SSA';

-- 10. Extrair métricas consolidadas de tempo médio e linhas examinadas (Depois)
SELECT 
    DIGEST_TEXT,
    COUNT_STAR AS execucoes,
    AVG_TIMER_WAIT/1000000000 AS tempo_medio_ms,
    SUM_ROWS_EXAMINED AS linhas_examinadas_total
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT LIKE '%voo%'
ORDER BY AVG_TIMER_WAIT DESC;

-- 11. Comprovar que o índice criado está sofrendo leituras reais no SGBD
SELECT 
    OBJECT_NAME AS tabela,
    INDEX_NAME AS indice,
    COUNT_READ AS leituras,
    COUNT_FETCH AS buscas
FROM performance_schema.table_io_waits_summary_by_index_usage
WHERE OBJECT_NAME = 'voo'
ORDER BY COUNT_READ DESC;
```

---

## 4. Registro de Resultados

| Consulta testada | `type` antes | `rows` antes | tempo médio antes (ms) | `type` depois | `rows` depois | tempo médio depois (ms) | Conclusão |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| `SELECT * FROM voo WHERE DESTINO = 'SSA';` | ALL | 5 | 1.8420 | ref | 1 | 0.3810 | O índice evitou a varredura sequencial (Full Table Scan), reduzindo as linhas examinadas de 5 para 1. Como a tabela de testes possui poucos registros, o ganho em milissegundos é sutil, mas a mudança estrutural para busca indexada (B-Tree) comprova que o otimizador escalará eficientemente com o aumento do volume de voos. |
