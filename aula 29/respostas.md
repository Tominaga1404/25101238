# Aula 29 — Atividade: lendo o plano de execução com EXPLAIN

Respostas obtidas rodando o script `banco_de_dados.sql` (MySQL 8.0). A saída completa está em `saida.txt`.
Os tempos (ms) e os valores de `rows` mudam um pouco de máquina para máquina, porque `rows` é uma estimativa. Os valores de `type` e `key` devem ser os mesmos no Laragon.

Conferência da carga: **50.000 clientes** e **500.000 locações**. `SHOW INDEX FROM locacao` mostra apenas `PRIMARY`.

---

## Parte 1 — Diagnóstico sem índices

| Consulta | Tabela  | type   | key     | rows    | Observações (Extra) |
|----------|---------|--------|---------|---------|---------------------|
| Q1       | locacao | ALL    | NULL    | 498.636 | Using where (varredura completa, filtra linha a linha) |
| Q2       | cliente | eq_ref | PRIMARY | 1       | Using where (acessada pela PK para cada locação; o filtro de CPF é aplicado depois) |
| Q2       | locacao | ALL    | NULL    | 498.636 | — (tabela lida inteira; é a primeira tabela do join) |

**Tempo real da Q1 (actual time do EXPLAIN ANALYZE): ≈ 137 ms** (o scan leu as 500.000 linhas para devolver só 10).

**Por que o MySQL não teve alternativa a ler a tabela inteira?**
Porque não havia nenhum índice em `locacao.id_cliente` nem em `cliente.cpf`, só as chaves primárias. Sem uma estrutura ordenada pela coluna do filtro, o único jeito de achar as linhas com `id_cliente = 4242` é ler todas e testar uma por uma (`type = ALL`, `possible_keys = NULL`). Na Q2 o MySQL até inverteu a ordem do join para pelo menos usar a PK de `cliente`, mas `locacao` continuou sendo lida inteira.

---

## Parte 2 — Criando índices e comparando

| Consulta | Tabela  | type | key             | rows |
|----------|---------|------|-----------------|------|
| Q1       | locacao | ref  | idx_loc_cliente | 10   |
| Q2       | cliente | ref  | idx_cli_cpf     | 1    |
| Q2       | locacao | ref  | idx_loc_cliente | 9    |

**Tempo real da Q1 agora: ≈ 0,05 ms** (`Index lookup on locacao using idx_loc_cliente`). Antes eram ≈ 137 ms, uma redução de mais de 2.000 vezes.

Na Q2 a ordem do join se inverteu: agora o MySQL começa por `cliente` (acha o CPF pelo índice, 1 linha) e depois busca as locações desse cliente pelo `idx_loc_cliente`.

### Seletividade na prática

| Filtro                 | Parcela aproximada da tabela | type | key            |
|------------------------|------------------------------|------|----------------|
| status = 'cancelada'   | cerca de 2% (10.000 linhas)  | ref  | idx_loc_status |
| status = 'concluida'   | cerca de 74% (370.000 linhas)| ref* | idx_loc_status*|

\* **Resposta esperada pela atividade: `type = ALL`, `key = NULL`.** No teste com MySQL 8.0.46 o otimizador ainda escolheu o índice (`ref`). Mas o `EXPLAIN ANALYZE` mostra que essa foi uma escolha **pior**:

| Plano para status = 'concluida'     | Tempo real |
|-------------------------------------|------------|
| Usando idx_loc_status (ref)         | ≈ 606 ms   |
| Ignorando o índice (full scan, ALL) | ≈ 185 ms   |

Ou seja, ler a tabela inteira foi cerca de 3 vezes mais rápido. O otimizador errou porque estimou 249.318 linhas (metade da tabela, o teto que o InnoDB usa nessa estimativa) quando na verdade são 370.000. Dependendo da versão do MySQL do seu Laragon, pode aparecer `ALL` direto, que é o comportamento descrito na aula.

**Pergunta: o índice existe nos dois casos. Por que o plano é diferente?**
Por causa da **seletividade**. 'cancelada' é cerca de 2% da tabela: o índice leva direto a poucas linhas e compensa. 'concluida' é cerca de 74% da tabela: usar o índice significaria, para cada uma das centenas de milhares de entradas, ir de novo à tabela buscar a linha completa (acesso aleatório). Isso custa mais do que ler a tabela inteira em sequência. O otimizador estima o custo dos dois caminhos e, quando o filtro é pouco seletivo, o full scan tende a vencer. Uma coluna com poucos valores distintos (como `status`) só é boa candidata a índice para os valores raros.

---

## Parte 3 — Índices que o MySQL ignora

### 3A — Função aplicada na coluna
Antes: `WHERE DATE_FORMAT(data_retirada, '%Y-%m') = '2025-06'` → `type = ALL`, `key = NULL`, 498.636 linhas.

Reescrita:
```sql
SELECT * FROM locacao
WHERE data_retirada >= '2025-06-01' AND data_retirada < '2025-07-01';
```
→ `type = range`, `key = idx_loc_data`, ≈ 24.756 linhas (Using index condition).

### 3B — LIKE com % no início
Antes: `WHERE nome LIKE '%123'` → `type = ALL`, `key = NULL`, ≈ 47.761 linhas.

Reescrita:
```sql
SELECT * FROM cliente WHERE nome LIKE 'Cliente 123%';
```
→ `type = range`, `key = idx_cli_nome`, ≈ 111 linhas.

### 3C — Conversão implícita de tipo
Antes: `WHERE cpf = 4242` → `type = ALL`, `key = NULL`. Repare que `possible_keys` mostra `idx_cli_cpf`, mas `key` fica `NULL`.

Reescrita:
```sql
SELECT * FROM cliente WHERE cpf = '00000004242';
```
→ `type = ref`, `key = idx_cli_cpf`, 1 linha.
# Atividade prática: lendo o plano de execução com EXPLAIN

**Ambiente:** MySQL 8.0.46 (Laragon) · banco `lab_otimizacao` · script: `atividade_explain.sql` · saída completa: `saida_laragon.txt`

---

## Parte 0 — Preparação

| clientes | locacoes |
|---------:|---------:|
| 50000    | 500000   |

`SHOW INDEX FROM locacao` → apenas `PRIMARY`.

---

## Parte 1 — Diagnóstico sem índices

### Registro (sem índices)

| Consulta | Tabela  | type   | key     | rows   | Observações (Extra) |
|----------|---------|--------|---------|--------|---------------------|
| Q1       | locacao | ALL    | NULL    | 498636 | Using where         |
| Q2       | cliente | eq_ref | PRIMARY | 1      | Using where         |
| Q2       | locacao | ALL    | NULL    | 498636 | —                   |

**Tempo real da Q1 (actual time do EXPLAIN ANALYZE): 151 ms**

**Pergunta: por que o MySQL não teve alternativa a ler a tabela inteira?**

Porque não existia índice em `locacao.id_cliente` nem em `cliente.cpf`, só as chaves primárias. Sem índice na coluna filtrada, o MySQL precisa ler todas as 500 mil linhas e testar uma por uma (`type = ALL`, `key = NULL`). Na Q2 ele só conseguiu usar a PK de `cliente` no join, mas `locacao` continuou sendo lida inteira.

---

## Parte 2 — Criando índices e comparando

### Registro (com índices)

| Consulta | Tabela  | type | key             | rows |
|----------|---------|------|-----------------|------|
| Q1       | locacao | ref  | idx_loc_cliente | 10   |
| Q2       | cliente | ref  | idx_cli_cpf     | 1    |
| Q2       | locacao | ref  | idx_loc_cliente | 9    |

**Tempo real da Q1 agora: 0,054 ms** (antes: 151 ms)

### Seletividade na prática

| Filtro               | Parcela aproximada da tabela | type | key            |
|----------------------|------------------------------|------|----------------|
| status = 'cancelada' | cerca de 2%                  | ref  | idx_loc_status |
| status = 'concluida' | cerca de 73%                 | ref  | idx_loc_status |

> **Obs.:** a atividade espera `ALL` / `NULL` para 'concluida'. No MySQL 8.0.46 o otimizador ainda escolheu o índice, porque estimou só metade da tabela (249.318 linhas) quando na verdade são 370.000. Em um teste à parte com EXPLAIN ANALYZE, esse plano foi cerca de 3 vezes mais lento do que ler a tabela inteira.

**Pergunta: o índice idx_loc_status existe nos dois casos. Por que o resultado do plano é diferente?**

Por causa da seletividade. 'cancelada' representa cerca de 2% da tabela, então o índice leva direto a poucas linhas e compensa. 'concluida' representa cerca de 73%: usar o índice obrigaria o MySQL a voltar à tabela para buscar a linha completa centenas de milhares de vezes, o que custa mais do que ler a tabela inteira em sequência. Por isso o otimizador tende a preferir o full scan quando o filtro é pouco seletivo.

---

## Parte 3 — Índices que o MySQL ignora

### 3A — Função aplicada na coluna

Reescreva usando uma faixa de datas e compare:

```sql
EXPLAIN SELECT * FROM locacao
WHERE data_retirada >= '2025-06-01' AND data_retirada < '2025-07-01';
```

### 3B — LIKE com % no início

Reescreva para buscar clientes cujo nome começa com Cliente 123:

```sql
EXPLAIN SELECT * FROM cliente WHERE nome LIKE 'Cliente 123%';
```

### 3C — Conversão implícita de tipo

Reescreva comparando com texto:

```sql
EXPLAIN SELECT * FROM cliente WHERE cpf = '00000004242';
```

### Registro

| Caso | type antes | type depois | Por que o índice não era usado? |
|------|------------|-------------|---------------------------------|
| 3A   | ALL        | range       | A função `DATE_FORMAT` foi aplicada na coluna. O índice guarda os valores originais da data, não o resultado da função. |
| 3B   | ALL        | range       | Com `%` no início, o começo do texto é desconhecido e o índice (ordenado da esquerda para a direita) não tem por onde começar a busca. |
| 3C   | ALL        | ref         | `cpf` é VARCHAR e foi comparado com número. O MySQL converte cada valor para número antes de comparar (conversão implícita), o que impede o uso do índice. |

---

## Parte 4 — Índice composto

| Consulta | type | key | rows |
|----------|------|-----|------|
| (a) id_cliente + data_retirada | range | idx_loc_cli_data | 3 |
| (b) só data_retirada | range | idx_loc_data | 24756 |
| Desafio: (b) com idx_loc_data invisível | ALL | NULL | 498636 |

**Na consulta (a), qual índice o MySQL escolheu (key)?** `idx_loc_cli_data` (o índice composto).

**Desafio: o composto ajuda na consulta (b)? Por quê?**

Não. Com `idx_loc_data` invisível, a consulta (b) vira `type = ALL`. O índice composto `(id_cliente, data_retirada)` é ordenado primeiro por `id_cliente`, então só pode ser usado quando o filtro inclui a primeira coluna (regra do prefixo à esquerda). Como (b) filtra só pela segunda coluna, o composto não serve.

---

## Parte 5 — Fechamento

**Qual consulta teve o maior ganho com o índice e como você percebeu isso no plano?**

A Q1. O `type` passou de `ALL` para `ref`, o `key` de `NULL` para `idx_loc_cliente` e o `rows` de cerca de 498 mil para 10. O tempo real caiu de 151 ms para 0,054 ms.

**Cite um caso em que existia índice e ele não foi usado, e a razão.**

Na 3C, `WHERE cpf = 4242`: o `idx_cli_cpf` aparece em `possible_keys`, mas `key = NULL`. Como `cpf` é texto e foi comparado com número, o MySQL converte cada valor antes de comparar e não consegue usar o índice.

**Que custo todos esses índices trazem para as operações de escrita (INSERT, UPDATE, DELETE)?**

Cada índice precisa ser atualizado a cada INSERT, DELETE ou UPDATE nas colunas indexadas. Isso deixa as escritas mais lentas e ocupa mais espaço em disco e memória. Índices redundantes, como `idx_loc_cliente`, que já é coberto pelo composto `idx_loc_cli_data`, só aumentam esse custo.

### Registro

| Caso | type antes | type depois | Por que o índice não era usado? |
|------|------------|-------------|---------------------------------|
| 3A   | ALL        | range       | A função `DATE_FORMAT` é aplicada sobre a coluna. O índice guarda os valores originais de `data_retirada`, não o resultado da função, então o MySQL precisa calcular a função linha a linha. Comparar a coluna "pura" com uma faixa de datas permite usar o índice. |
| 3B   | ALL        | range       | O índice B-tree é ordenado da esquerda para a direita do texto. Com `%` no início, o começo do valor é desconhecido e não há ponto de partida na árvore. Com o prefixo fixo (`'Cliente 123%'`) o MySQL lê só a faixa que começa com esse texto. |
| 3C   | ALL        | ref         | `cpf` é VARCHAR e foi comparado com um número. O MySQL converte cada `cpf` para número para comparar (conversão implícita), o que equivale a aplicar uma função na coluna. Além disso, vários textos viram o mesmo número ('4242', '00000004242'...). Comparando com texto (`'00000004242'`), o índice é usado. |

---

## Parte 4 — Índice composto

| Consulta | type  | key              | rows   | Observação |
|----------|-------|------------------|--------|------------|
| (a) id_cliente = 4242 AND data_retirada >= '2025-01-01' | range | idx_loc_cli_data | 3 | possible_keys: idx_loc_cliente, idx_loc_data, idx_loc_cli_data |
| (b) só data_retirada (junho/2025) | range | idx_loc_data | 24.756 | usou o índice simples de data |
| Desafio: (b) com idx_loc_data INVISIBLE | ALL | NULL | 498.636 | o composto **não** foi usado |

**Na consulta (a), qual índice o MySQL escolheu (key)?** `idx_loc_cli_data`, o composto. Ele resolve as duas condições dentro do índice (igualdade em `id_cliente` e faixa em `data_retirada`) e chega a apenas 3 linhas, contra 10 se usasse só `idx_loc_cliente`.

**Desafio: com o índice simples de data invisível, o composto ajuda na (b)? Por quê?**
Não. O plano vira `type = ALL`, `key = NULL`. O índice composto `(id_cliente, data_retirada)` é ordenado primeiro por `id_cliente` e só depois por `data_retirada` dentro de cada cliente. As datas de junho/2025 ficam espalhadas por todo o índice, uma vez para cada cliente. Pela **regra do prefixo à esquerda**, um índice composto só pode ser usado para busca se o filtro incluir a(s) primeira(s) coluna(s). Como (b) filtra só pela segunda coluna, o composto não serve e o MySQL volta para a varredura completa.

---

## Parte 5 — Fechamento

**Qual consulta teve o maior ganho com o índice e como você percebeu isso no plano?**
A Q1 (locações do cliente 4242). No plano, `type` passou de `ALL` para `ref`, `key` de `NULL` para `idx_loc_cliente` e `rows` de cerca de 498.636 para 10. No EXPLAIN ANALYZE o tempo caiu de ≈ 137 ms (`Table scan`) para ≈ 0,05 ms (`Index lookup`).

**Cite um caso em que existia índice e ele não foi usado, e a razão.**
3C: `WHERE cpf = 4242`. O `idx_cli_cpf` aparece em `possible_keys`, mas `key = NULL` e `type = ALL`. Como `cpf` é VARCHAR e foi comparado com um número, o MySQL precisa converter o valor de cada linha para número antes de comparar, e aí o índice (ordenado como texto) não serve. Comparando com `'00000004242'` o índice volta a ser usado. (Outros exemplos válidos: `DATE_FORMAT` na 3A, `LIKE '%123'` na 3B, filtro só pela segunda coluna do índice composto na Parte 4.)

**Que custo todos esses índices trazem para as operações de escrita (INSERT, UPDATE, DELETE)?**
Cada índice é uma estrutura B-tree separada que precisa ser mantida. Todo INSERT e DELETE em `locacao` agora atualiza também `idx_loc_cliente`, `idx_loc_status`, `idx_loc_data` e `idx_loc_cli_data`, e um UPDATE em coluna indexada (por exemplo, mudar o `status`) remove e reinsere a entrada no índice. Isso deixa as escritas mais lentas, ocupa mais disco e memória (buffer pool) e gera mais I/O. Além disso, índices redundantes, como `idx_loc_cliente`, que é prefixo de `idx_loc_cli_data`, pesam nas escritas sem trazer benefício. Por isso só se criam os índices que as consultas reais pedem.
