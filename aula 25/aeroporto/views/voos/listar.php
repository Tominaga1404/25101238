<?php
require_once __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/header.php';
?>

<h2>Voos Cadastrados</h2>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Origem</th>
            <th>Destino</th>
            <th>Partida</th>
            <th>Chegada</th>
            <th>Aeronave</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($voos)): ?>
            <tr>
                <td colspan="7">Nenhum voo registrado no momento.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($voos as $v): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['codigo_voo']) ?></strong></td>
                    <td><?= htmlspecialchars($v['origem']) ?></td>
                    <td><?= htmlspecialchars($v['destino']) ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['data_hora_partida']))) ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['data_hora_chegada']))) ?></td>
                    <td><?= htmlspecialchars($v['modelo_aeronave'] . ' (' . $v['matricula'] . ')') ?></td>
                    <td>
                        <a class="btn" href="index.php?acao=editar&id=<?= (int)$v['id_voo'] ?>">Editar</a>
                        <a class="btn btn-danger" href="index.php?acao=excluir&id=<?= (int)$v['id_voo'] ?>" 
                           onclick="return confirm('Deseja realmente excluir o voo <?= htmlspecialchars($v['codigo_voo']) ?>?');">
                           Excluir
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/footer.php'; ?>