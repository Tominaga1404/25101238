<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Aeroporto</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background-color: #f7f9fa; color: #333; }
        header { margin-bottom: 25px; padding-bottom: 10px; border-bottom: 2px solid #ddd; }
        nav a { margin-right: 15px; text-decoration: none; color: #0066cc; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button, .btn { padding: 8px 16px; background-color: #0066cc; color: #fff; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .btn-danger { background-color: #cc0000; }
    </style>
</head>
<body>
    <header>
        <h1>Aeroporto — Painel de Controle</h1>
        <nav>
            <a href="index.php?acao=listar">Voos Cadastrados</a>
            <a href="index.php?acao=criar">+ Novo Voo</a>
        </nav>
    </header>
    <main>
