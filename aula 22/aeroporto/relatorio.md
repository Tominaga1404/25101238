# Aula 22 — Exclusão de voos com Soft Delete

## O que foi feito

A exclusão de voos deixou de apagar o registro. O voo recebe a data e hora da exclusão na coluna `deleted_at`, sai das listagens e pode ser restaurado na tela **Voos Excluídos**. Cada exclusão e cada restauração ficam registradas na tabela `auditoria`, com o usuário, a ação, a data e uma cópia do registro (`dados_antes`).

**Arquivos alterados ou criados:** `database/migration_soft_delete.sql`, `models/Voo.php`, `controllers/VooController.php`, `public/index.php`, `includes/header.php`, `includes/footer.php`, `views/voos/listar.php`, `views/voos/confirmar_exclusao.php` (nova), `views/voos/excluidos.php` (nova).

## Como o filtro de registros ativos foi garantido

O filtro fica em um único lugar: o Model `Voo`. As condições `deleted_at IS NULL` (ativos) e `deleted_at IS NOT NULL` (excluídos) são constantes da classe, e toda leitura da tabela passa pelo método privado `consultar()`, que exige um desses dois escopos. Por isso não existe consulta "sem filtro": `listar()` e `buscar()` só enxergam voos ativos, e `listarExcluidos()` só enxerga os excluídos. O `editar()` também atualiza apenas voos ativos, então um voo excluído não pode ser editado nem pelo endereço direto (`acao=editar&id=...`).

No banco, `deleted_at` tem índice, e um índice único em `(CODIGO_VOO, DATA_HORA_PARTIDA, ativo)` impede dois voos **ativos** iguais. A coluna gerada `ativo` vale 1 para voo ativo e NULL para excluído; como o índice único ignora NULL, um voo excluído não bloqueia um novo cadastro. Se alguém tentar restaurar um voo que já foi recadastrado, o sistema recusa com uma mensagem clara.

## Como a atomicidade da exclusão com a auditoria foi tratada

`excluir()` e `restaurar()` rodam dentro de uma transação PDO (`beginTransaction` / `commit`):

1. `SELECT ... FOR UPDATE` trava o voo e lê os dados atuais (que viram `dados_antes`);
2. `UPDATE VOO SET deleted_at = NOW()` (ou `NULL`, na restauração), nunca `DELETE`;
3. `INSERT INTO auditoria` com o `usuario_id` da sessão.

Se qualquer passo lança exceção, o `catch` faz `rollBack()` e nada é gravado. Isso foi testado desativando a tabela de auditoria: a exclusão falhou, o voo continuou ativo e o usuário viu "Não foi possível excluir o voo. Nenhuma alteração foi gravada."

## Outros cuidados

- **Confirmação:** o botão Excluir abre uma tela de confirmação. A exclusão só acontece por POST, com token CSRF, e não por um link GET.
- **Mensagens:** sucesso e erro aparecem no topo da página (mensagem flash na sessão).
- **Limites:** soft delete protege contra exclusão acidental de um registro, mas não substitui o backup do banco. Os dados "apagados" continuam guardados, inclusive em `auditoria.dados_antes`. Por isso, uma política de purga periódica, como a sugerida no fim da migration, deve seguir a LGPD.

## Como testar

1. Rodar `database/migration_soft_delete.sql` no banco `aeroporto` (uma vez).
2. Acessar o sistema, excluir um voo, conferir a tela **Voos Excluídos** e restaurá-lo.
3. Conferir o registro: `SELECT * FROM auditoria;`
