<?php
require_once __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/header.php';
?>

<h2>Cadastrar Novo Voo</h2>

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

<form action="index.php?acao=salvar" method="POST">
    <div class="form-group">
        <label for="codigo_voo">Código do Voo (ex: LA1234):</label>
        <input type="text" id="codigo_voo" name="codigo_voo" required maxlength="6"
               value="<?= htmlspecialchars($dados['codigo_voo'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="origem">Origem (Sigla IATA, ex: GRU):</label>
        <input type="text" id="origem" name="origem" required maxlength="3"
               value="<?= htmlspecialchars($dados['origem'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="destino">Destino (Sigla IATA, ex: BSB):</label>
        <input type="text" id="destino" name="destino" required maxlength="3"
               value="<?= htmlspecialchars($dados['destino'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="data_hora_partida">Data e Hora de Partida:</label>
        <input type="datetime-local" id="data_hora_partida" name="data_hora_partida" required
               value="<?= htmlspecialchars($dados['data_hora_partida'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="data_hora_chegada">Data e Hora de Chegada:</label>
        <input type="datetime-local" id="data_hora_chegada" name="data_hora_chegada" required
               value="<?= htmlspecialchars($dados['data_hora_chegada'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="id_aeronave">Aeronave:</label>
        <select id="id_aeronave" name="id_aeronave" required>
            <option value="">Selecione uma aeronave...</option>
            <?php foreach ($aeronaves as $a): ?>
                <option value="<?= (int)$a['id_aeronave'] ?>"
                    <?= (($dados['id_aeronave'] ?? '') == $a['id_aeronave']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['modelo'] . ' - ' . $a['matricula']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn">Salvar Voo</button>
    <a href="index.php?acao=listar">Cancelar</a>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>