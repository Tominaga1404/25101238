## Perguntas para Discussão (Controle de Concorrência, MVCC e Locks)

### 1. Por que uma consulta simples de disponibilidade não é suficiente para proteger uma reserva?
Uma consulta simples (`SELECT ... WHERE status = 'DISPONIVEL'`) apenas realiza uma leitura estática no tempo. Em sistemas concorrentes, ocorre o problema de **Time-of-Check to Time-of-Use (TOCTOU)**: entre o instante da checagem e o comando de escrita (`UPDATE`), outra transação simultânea pode ler o mesmo assento como livre e confirmar a reserva primeiro, gerando uma condição de corrida (*race condition*).

### 2. Qual é a finalidade do `FOR UPDATE`?
O `FOR UPDATE` aplica um bloqueio exclusivo de linha (*Row-Level Exclusive Lock* / *X-Lock*). Ele instrui o motor de armazenamento (InnoDB) a travar as linhas retornadas pela consulta, impedindo que qualquer outra transação concorrente modifique ou tente obter travas sobre esses mesmos registros até o término da transação corrente.

### 3. Quando o bloqueio é liberado?
O bloqueio adquirido por comandos de trava pessimista só é liberado quando a transação é formalmente encerrada por meio de uma instrução **`COMMIT`** (confirmação e persistência) ou **`ROLLBACK`** (reversão e cancelamento).

### 4. O que acontece com a segunda sessão enquanto a primeira mantém o bloqueio?
A segunda sessão entra em estado de espera suspensa (*Lock Wait*). A execução do comando fica retida no SGBD até que a primeira transação finalize e libere a trava, ou até que seja atingido o tempo limite configurado no servidor (`innodb_lock_wait_timeout`), disparando um erro caso o tempo expire.

### 5. Por que a segunda transação precisa validar novamente o status do assento?
Porque enquanto a segunda sessão estava na fila de espera, a primeira sessão provavelmente alterou o estado dos dados (de `DISPONIVEL` para `RESERVADO`) e confirmou o `COMMIT`. Ao receber a liberação do recurso, a segunda transação precisa verificar se o assento ainda atende às condições de negócio esperadas antes de tentar realizar a reserva.

### 6. O que é uma condição de corrida?
É uma falha de sincronização em sistemas com concorrência na qual múltiplos fluxos de execução tentam acessar e modificar um recurso compartilhado simultaneamente, fazendo com que o resultado final dependa imprevisivelmente da ordem temporal, do escalonamento ou da velocidade relativa de execução das transações.

### 7. Qual é a diferença entre bloqueio e MVCC?
* **Bloqueio (Locking):** Estratégia pessimista que força a exclusão mútua. Leitores ou escritores bloqueiam o acesso físico aos dados para evitar modificações concorrentes.
* **MVCC (Multi-Version Concurrency Control):** Estratégia não-bloqueante baseada em controle de versões lógicas (*snapshots*). O motor permite que leituras não bloqueiem escritas e escritas não bloqueiem leituras simples, entregando visões consistentes baseadas no log de desfazer (*Undo Log*).

### 8. Como ocorre um deadlock?
Um deadlock (impasse) surge quando duas ou mais transações concorrentes entram em uma dependência circular de recursos bloqueados. Por exemplo: a Transação A bloqueia o registro 1 e tenta travar o registro 2; simultaneamente, a Transação B bloqueia o registro 2 e tenta travar o registro 1. Nenhuma transação pode avançar nem liberar seu bloqueio, exigindo que o motor do SGBD aborte uma delas.

### 9. Por que adquirir bloqueios sempre na mesma ordem pode reduzir deadlocks?
Porque a ordenação global e padronizada (por exemplo, travar sempre registros ordenados por `id` crescente) quebra a condição de espera circular de Coffman. Se todas as transações seguirem a mesma sequência para solicitar recursos, uma transação que requisitar o registro 1 forçará as demais a aguardarem na fila antes de disputarem o registro 2.

### 10. Qual é a diferença entre `READ COMMITTED`, `REPEATABLE READ` e `SERIALIZABLE`?
* **`READ COMMITTED`:** Cada comando `SELECT` dentro da transação cria seu próprio *snapshot*, enxergando novos dados gravados e confirmados por outras transações durante a sessão (permite leituras não-repetíveis).
* **`REPEATABLE READ` (Padrão do InnoDB):** O *snapshot* de leitura é fixado na primeira leitura da transação. Todas as consultas subsequentes enxergam exatamente a mesma fotografia dos dados, evitando leituras não-repetíveis.
* **`SERIALIZABLE`:** Nível de isolamento estrito que força todas as leituras comuns a operarem implicitamente como `LOCK IN SHARE MODE` / travas de intervalo (*Gap Locks*), garantindo que as operações se comportem como se fossem executadas sequencialmente em fila única.

### 11. Por que uma restrição `UNIQUE` pode ser importante mesmo quando a aplicação já valida a disponibilidade?
A aplicação cliente opera em camadas externas e distribuídas (múltiplas instâncias, containers ou threads), estando sujeita a falhas de rede, bugs de validação ou condições de corrida. A restrição `UNIQUE` reside no núcleo relacional do SGBD, atuando como a última e definitiva barreira de consistência atômica caso a validação da camada de software falhe.

### 12. Como a aplicação deve tratar uma transação abortada por deadlock ou falha de serialização?
A aplicação deve capturar o código de erro retornado pelo banco (no MySQL, o erro `1213: Deadlock found when trying to get lock`), emitir um `ROLLBACK` explícito para limpar o contexto da conexão e executar uma rotina de reprocessamento (*retry policy*) com uma espera aleatória exponencial (*exponential backoff*).
