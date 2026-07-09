<?php
/**
 * SISFAL - Test de Conexión Remota
 * Elimina este archivo después de verificar la conexión.
 */

define('DB_HOST',    '100.118.224.126');
define('DB_PORT',    '3306');
define('DB_NAME',    'sisfal');
define('DB_USER',    'companero');
define('DB_PASS',    'TuPasswordSegura123!');
define('DB_CHARSET', 'utf8mb4');

header('Content-Type: text/html; charset=utf-8');

$resultado = [];
$exito = false;

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Datos del servidor
    $version   = $pdo->query("SELECT VERSION() AS v")->fetchColumn();
    $dbActual  = $pdo->query("SELECT DATABASE() AS d")->fetchColumn();
    $tablas    = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $exito = true;
    $resultado = [
        'version'   => $version,
        'base_datos'=> $dbActual,
        'tablas'    => $tablas,
    ];

} catch (PDOException $e) {
    $resultado['error'] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SISFAL · Test de Conexión</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh;
         display: flex; align-items: center; justify-content: center; padding: 24px; }
  .card { background: #1e293b; border-radius: 16px; padding: 36px 40px; max-width: 520px; width: 100%;
          box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
  .badge { display: inline-flex; align-items: center; gap: 8px; border-radius: 99px;
           padding: 6px 16px; font-size: 13px; font-weight: 600; margin-bottom: 24px; }
  .badge.ok  { background: #052e16; color: #4ade80; border: 1px solid #166534; }
  .badge.err { background: #2d0a0a; color: #f87171; border: 1px solid #991b1b; }
  h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
  .sub { font-size: 13px; color: #64748b; margin-bottom: 28px; }
  .row { display: flex; justify-content: space-between; align-items: center;
         padding: 12px 0; border-bottom: 1px solid #334155; font-size: 14px; }
  .row:last-child { border-bottom: none; }
  .label { color: #94a3b8; }
  .value { font-weight: 600; color: #f1f5f9; }
  .value.green { color: #4ade80; }
  .value.yellow { color: #facc15; }
  .pill { background: #0f172a; border-radius: 6px; padding: 2px 10px;
          font-size: 12px; border: 1px solid #334155; }
  .tablas-lista { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
  .error-box { background: #2d0a0a; border: 1px solid #991b1b; border-radius: 10px;
               padding: 16px; color: #f87171; font-size: 13px; margin-top: 16px; word-break: break-all; }
  .aviso { margin-top: 24px; background: #1a1a2e; border: 1px solid #334155; border-radius: 8px;
           padding: 12px 16px; font-size: 12px; color: #64748b; }
</style>
</head>
<body>
<div class="card">
  <div class="badge <?= $exito ? 'ok' : 'err' ?>">
    <?= $exito ? '✔ Conexión exitosa' : '✖ Error de conexión' ?>
  </div>

  <h1>SISFAL · Test de Conexión</h1>
  <p class="sub">Verificación de acceso a la base de datos remota</p>

  <?php if ($exito): ?>
  <div class="row">
    <span class="label">Host</span>
    <span class="value"><?= DB_HOST ?>:<?= DB_PORT ?></span>
  </div>
  <div class="row">
    <span class="label">Base de datos</span>
    <span class="value green"><?= htmlspecialchars($resultado['base_datos']) ?></span>
  </div>
  <div class="row">
    <span class="label">Versión MySQL</span>
    <span class="value"><?= htmlspecialchars($resultado['version']) ?></span>
  </div>
  <div class="row">
    <span class="label">Usuario</span>
    <span class="value"><?= DB_USER ?></span>
  </div>
  <div class="row" style="flex-direction:column; align-items:flex-start; gap:10px;">
    <span class="label">Tablas encontradas</span>
    <?php if (empty($resultado['tablas'])): ?>
      <span class="value yellow">Sin tablas aún — base de datos vacía ✓</span>
    <?php else: ?>
      <div class="tablas-lista">
        <?php foreach ($resultado['tablas'] as $t): ?>
          <span class="pill"><?= htmlspecialchars($t) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php else: ?>
  <div class="error-box">
    <strong>Detalle del error:</strong><br>
    <?= htmlspecialchars($resultado['error']) ?>
  </div>
  <?php endif; ?>

  <div class="aviso">
    ⚠ Elimina <code>test_conexion.php</code> del servidor una vez confirmada la conexión.
  </div>
</div>
</body>
</html>
