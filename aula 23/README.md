# Relatório Prático: SQL Injection no Sistema de Aeroporto 

## 1. Tabela de Comparação de Resultados

> **Nota sobre o Schema:** Os destinos utilizam siglas IATA (ex: `BSB` / `GRU`) e a consulta principal possui 8 colunas (`id_voo`, `codigo_voo`, `origem`, `destino`, `data_hora_partida`, `data_hora_chegada`, `modelo_aeronave`, `matricula`), utilizando a tabela `PASSAGEIRO` (ou `usuarios`) como alvo de extração no teste 4.

| # | Payload no campo `destino` | Versão A (sem prepared statements) | Versão B (com prepared statements) |
| :-: | :--- | :--- | :--- |
| **1** | `BSB` *(ou `Lisboa`)* | **Funcionamento normal:** Retorna apenas os voos cujo destino corresponde ao informado. | **Funcionamento normal:** Retorna apenas os voos cujo destino corresponde ao informado. |
| **2** | `'` | **Erro Fatal (Quebra da aplicação):** O PDO lança uma exceção `PDOException: SQLSTATE[42000]: Syntax error or access violation` porque a aspa simples extra abre uma string não fechada no comando SQL (`WHERE v.destino = '''`). | **Trata como texto literal:** A consulta não quebra e retorna uma tabela vazia ("Nenhum voo registrado"), pois procura literalmente por um destino cujo nome seja o caractere `'`. |
| **3** | `' OR '1'='1` | **Bypass do filtro:** Ignora a restrição de destino e **lista todos os voos cadastrados no banco**, pois a condição `OR '1'='1'` torna a cláusula `WHERE` sempre verdadeira para todas as linhas. | **Trata como texto literal:** Nenhum voo é retornado, pois o banco busca literalmente por um aeroporto de destino chamado `' OR '1'='1`. |
| **4** | `x' UNION SELECT id_passageiro, nome, cpf, email, NOW(), NOW(), telefone, 'VAZADO' FROM PASSAGEIRO -- ` | **Vazamento de dados (Data Exfiltration):** A consulta original retorna vazio para `'x'`, mas o `UNION` anexa e exibe na tela de voos todos os nomes, CPFs, e-mails e telefones da tabela `PASSAGEIRO` (ou `id`, `email` e `senha_hash` da tabela `usuarios`). | **Trata como texto literal:** O ataque é neutralizado; o banco apenas procura por um destino com o texto exato do payload e retorna uma lista vazia. |

---

## 2. Respostas às Questões

### 1. Por que a versão B se comporta diferente com o mesmo texto de entrada?
Porque a Versão B utiliza *Prepared Statements* (consultas parametrizadas), que separam o envio da estrutura do comando SQL do envio dos parâmetros em duas etapas distintas no protocolo do banco de dados. Assim, o SGBD compila a árvore sintática da query antes de receber o valor de `:destino`, impossibilitando que caracteres especiais como `'` ou comandos como `UNION` alterem a lógica original da consulta.

### 2. O que o banco recebe como comando e o que recebe como dado em cada versão?
Na **Versão A**, o PHP concatena a variável diretamente na string SQL, fazendo o banco receber uma única instrução onde dado e comando se misturam (ex: `...WHERE destino = '' OR '1'='1'`), interpretando o input do usuário como código SQL executável. Na **Versão B**, o banco recebe primeiro o molde estrutural fixo (`...WHERE destino = ?`) como comando e, separadamente, recebe a entrada do usuário estritamente tipada como um **dado literal** (uma string pura de busca), sem jamais executá-la como instrução.

### 3. Se só a versão A existisse em produção, qual seria o pior dano do payload 4?
Um atacante poderia consultar as tabelas de metadados `information_schema.tables` e `information_schema.columns` via `UNION SELECT` para mapear toda a estrutura do banco e, em seguida, extrair todas as informações sensíveis do sistema (como `email` e `senha_hash` de administradores na tabela `usuarios`, além de CPFs, passaportes e contatos na tabela `PASSAGEIRO`). Isso resultaria no comprometimento total de contas administrativas, violação grave da LGPD e vazamento completo da base de dados.
