<?php
session_start();

if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php?acao=listar');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    try {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("SELECT id, email, senha_hash, perfil FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id']     = $usuario['id'];
            $_SESSION['usuario_email']  = $usuario['email'];
            $_SESSION['usuario_perfil'] = $usuario['perfil'];

            header('Location: index.php?acao=listar');
            exit;
        } else {
            $erro = 'E-mail ou senha inválidos.';
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $erro = 'E-mail ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login — Sistema de Aeroporto</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f7f9fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #fff; padding: 30px; border: 1px solid #ddd; border-radius: 6px; width: 100%; max-width: 360px; }
        h2 { margin-top: 0; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #0066cc; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .alert-error { background-color: #ffe6e6; color: #cc0000; padding: 10px; border: 1px solid #f5c2c7; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login — Aeroporto</h2>

        <?php if ($erro !== ''): ?>
            <div class="alert-error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>