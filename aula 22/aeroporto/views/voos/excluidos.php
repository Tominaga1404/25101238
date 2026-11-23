<?php
require_once __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/header.php';
?>

<h2>Voos Excluídos</h2>
<p>Voos marcados como excluídos (soft delete). Eles não aparecem nas listagens, mas podem ser restaurados.</p>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Origem</th>
            <th>Destino</th>
            <th>Partida</th>
            <th>Aeronave</th>
            <th>Excluído em</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($voos)): ?>
            <tr>
                <td colspan="7">Nenhum voo excluído.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($voos as $v): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['codigo_voo']) ?></strong></td>
                    <td><?= htmlspecialchars($v['origem']) ?></td>
                    <td><?= htmlspecialchars($v['destino']) ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['data_hora_partida']))) ?></td>
                    <td><?= htmlspecialchars($v['modelo_aeronave'] . ' (' . $v['matricula'] . ')') ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['deleted_at']))) ?></td>
                    <td>
                        <form action="index.php?acao=restaurar" method="POST" class="inline"
                              onsubmit="return confirm('Restaurar o voo <?= htmlspecialchars($v['codigo_voo'], ENT_QUOTES) ?>?');">
                            <input type="hidden" name="id" value="<?= (int) $v['id_voo'] ?>">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <button type="submit" class="btn btn-success">Restaurar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
