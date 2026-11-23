<?php
require_once __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/header.php';
?>

<h2>Confirmar exclusão</h2>

<div class="caixa">
    <p>Deseja realmente excluir o voo abaixo?</p>

    <ul>
        <li><strong>Código:</strong> <?= htmlspecialchars($voo['codigo_voo']) ?></li>
        <li><strong>Trecho:</strong> <?= htmlspecialchars($voo['origem'] . ' → ' . $voo['destino']) ?></li>
        <li><strong>Partida:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($voo['data_hora_partida']))) ?></li>
        <li><strong>Aeronave:</strong> <?= htmlspecialchars($voo['modelo_aeronave'] . ' (' . $voo['matricula'] . ')') ?></li>
    </ul>

    <p>O voo sairá da lista de voos cadastrados, mas continuará no banco
       e poderá ser restaurado na tela <strong>Voos Excluídos</strong>.</p>

    <form action="index.php?acao=excluir" method="POST" class="inline">
        <input type="hidden" name="id" value="<?= (int) $voo['id_voo'] ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn btn-danger">Sim, excluir</button>
    </form>
    <a class="btn btn-secondary" href="index.php?acao=listar">Cancelar</a>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
