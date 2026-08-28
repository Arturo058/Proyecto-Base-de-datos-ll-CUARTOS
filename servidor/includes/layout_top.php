<?php
/**
 * Encabezado compartido: abre el documento HTML y dibuja la barra lateral.
 * Cada página debe definir $titulo y $activo ANTES de incluir este archivo.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'RentaCuartos') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; margin: 0; }
        .sidebar { width: 250px; min-height: 100vh; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); border-radius: .5rem; }
        .sidebar .nav-link:hover { color: #fff; background-color: rgba(255,255,255,.08); }
        .sidebar .nav-link.active { color: #fff; background-color: #0d6efd; }
        .card-metric { border: none; border-radius: .75rem; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
        .table td, .table th { vertical-align: middle; }
        main { min-height: 100vh; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php require __DIR__ . '/../assets/sidebar.php'; ?>
    <main class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><?= htmlspecialchars($titulo ?? '') ?></h3>
            <span class="text-muted">👤 <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
        </div>
