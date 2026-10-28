<?php
require_once __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/header.php';
?>

<h2>Editar Voo #<?= htmlspecialchars($voo['codigo_voo']) ?></h2>

<?php if (!empty($erros)): ?>
    <div class="alert-error">
        <strong>Atenção:</strong>
        <ul style="margin: 5px 0 0 20px; padding: 0;">
            <?php foreach ($erros as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="index.php?acao=atualizar&id=<?= (int)$voo['id_voo'] ?>" method="POST">
    <div class="form-group">
        <label for="codigo_voo">Código do Voo (ex: LA1234):</label>
        <input type="text" id="codigo_voo" name="codigo_voo" 
               value="<?= htmlspecialchars($voo['codigo_voo']) ?>" required maxlength="6">
    </div>

    <div class="form-group">
        <label for="origem">Origem:</label>
        <input type="text" id="origem" name="origem" 
               value="<?= htmlspecialchars($voo['origem']) ?>" required maxlength="3">
    </div>

    <div class="form-group">
        <label for="destino">Destino:</label>
        <input type="text" id="destino" name="destino" 
               value="<?= htmlspecialchars($voo['destino']) ?>" required maxlength="3">
    </div>

    <div class="form-group">
        <label for="data_hora_partida">Data e Hora de Partida:</label>
        <input type="datetime-local" id="data_hora_partida" name="data_hora_partida" 
               value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($voo['data_hora_partida']))) ?>" required>
    </div>

    <div class="form-group">
        <label for="data_hora_chegada">Data e Hora de Chegada:</label>
        <input type="datetime-local" id="data_hora_chegada" name="data_hora_chegada" 
               value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($voo['data_hora_chegada']))) ?>" required>
    </div>

    <div class="form-group">
        <label for="id_aeronave">Aeronave:</label>
        <select id="id_aeronave" name="id_aeronave" required>
            <?php foreach ($aeronaves as $a): ?>
                <option value="<?= (int)$a['id_aeronave'] ?>" 
                    <?= $a['id_aeronave'] == $voo['id_aeronave'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['modelo'] . ' - ' . $a['matricula']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn">Atualizar Voo</button>
    <a href="index.php?acao=listar">Cancelar</a>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>