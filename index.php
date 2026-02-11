<?php

declare(strict_types=1);

$dbDir = __DIR__ . '/data';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}
$dbPath = $dbDir . '/peluqueria.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function migrate(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS collaborators (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            price REAL NOT NULL,
            collaborator_percent REAL NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS work_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            work_date TEXT NOT NULL,
            collaborator_id INTEGER NOT NULL,
            service_id INTEGER NOT NULL,
            service_price REAL NOT NULL,
            collaborator_percent REAL NOT NULL,
            collaborator_amount REAL NOT NULL,
            owner_amount REAL NOT NULL,
            notes TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY(collaborator_id) REFERENCES collaborators(id),
            FOREIGN KEY(service_id) REFERENCES services(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            cost_price REAL NOT NULL,
            sale_price REAL NOT NULL,
            stock INTEGER NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ledger_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_date TEXT NOT NULL,
            description TEXT NOT NULL,
            type TEXT NOT NULL CHECK(type IN ("debe", "haber")),
            amount REAL NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
}

migrate($pdo);

function nowDateTime(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function money(float $value): string
{
    return '$' . number_format($value, 2);
}

function redirectTo(string $view, string $message = ''): void
{
    $qs = ['view' => $view];
    if ($message !== '') {
        $qs['msg'] = $message;
    }

    header('Location: ?' . http_build_query($qs));
    exit;
}

$view = $_GET['view'] ?? 'colaboradores';
$allowedViews = ['colaboradores', 'servicios', 'productos', 'contabilidad', 'reportes'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'colaboradores';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_collaborator') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('El nombre del colaborador es obligatorio.');
            }

            $stmt = $pdo->prepare('INSERT INTO collaborators(name, created_at) VALUES(:name, :created_at)');
            $stmt->execute([':name' => $name, ':created_at' => nowDateTime()]);
            redirectTo('colaboradores', 'Colaborador agregado.');
        }

        if ($action === 'update_collaborator') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                throw new RuntimeException('Datos inválidos para editar colaborador.');
            }

            $stmt = $pdo->prepare('UPDATE collaborators SET name = :name WHERE id = :id');
            $stmt->execute([':name' => $name, ':id' => $id]);
            redirectTo('colaboradores', 'Colaborador actualizado.');
        }

        if ($action === 'delete_collaborator') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Colaborador inválido.');
            }

            $stmt = $pdo->prepare('DELETE FROM collaborators WHERE id = :id');
            $stmt->execute([':id' => $id]);
            redirectTo('colaboradores', 'Colaborador eliminado.');
        }

        if ($action === 'add_service') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $price = (float) ($_POST['price'] ?? 0);
            $percent = (float) ($_POST['collaborator_percent'] ?? 0);
            if ($name === '' || $price <= 0 || $percent <= 0 || $percent >= 100) {
                throw new RuntimeException('Datos inválidos para servicio.');
            }

            $stmt = $pdo->prepare('INSERT INTO services(name, price, collaborator_percent, created_at) VALUES(:name, :price, :percent, :created_at)');
            $stmt->execute([
                ':name' => $name,
                ':price' => $price,
                ':percent' => $percent,
                ':created_at' => nowDateTime(),
            ]);
            redirectTo('servicios', 'Servicio agregado.');
        }

        if ($action === 'update_service') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $price = (float) ($_POST['price'] ?? 0);
            $percent = (float) ($_POST['collaborator_percent'] ?? 0);
            if ($id <= 0 || $name === '' || $price <= 0 || $percent <= 0 || $percent >= 100) {
                throw new RuntimeException('Datos inválidos para editar servicio.');
            }

            $stmt = $pdo->prepare('UPDATE services SET name = :name, price = :price, collaborator_percent = :percent WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':price' => $price,
                ':percent' => $percent,
            ]);
            redirectTo('servicios', 'Servicio actualizado.');
        }

        if ($action === 'delete_service') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Servicio inválido.');
            }

            $stmt = $pdo->prepare('DELETE FROM services WHERE id = :id');
            $stmt->execute([':id' => $id]);
            redirectTo('servicios', 'Servicio eliminado.');
        }

        if ($action === 'add_work_log') {
            $workDate = trim((string) ($_POST['work_date'] ?? ''));
            $collaboratorId = (int) ($_POST['collaborator_id'] ?? 0);
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $percent = (float) ($_POST['collaborator_percent'] ?? 0);
            $notes = trim((string) ($_POST['notes'] ?? ''));

            if ($workDate === '' || $collaboratorId <= 0 || $serviceId <= 0 || $percent <= 0 || $percent >= 100) {
                throw new RuntimeException('Datos inválidos para registrar trabajo.');
            }

            $serviceStmt = $pdo->prepare('SELECT price, name FROM services WHERE id = :id');
            $serviceStmt->execute([':id' => $serviceId]);
            $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
            if (!$service) {
                throw new RuntimeException('Servicio no encontrado.');
            }

            $price = (float) $service['price'];
            $collaboratorAmount = $price * ($percent / 100);
            $ownerAmount = $price - $collaboratorAmount;

            $insert = $pdo->prepare(
                'INSERT INTO work_logs(
                    work_date, collaborator_id, service_id, service_price, collaborator_percent,
                    collaborator_amount, owner_amount, notes, created_at
                ) VALUES(
                    :work_date, :collaborator_id, :service_id, :service_price, :collaborator_percent,
                    :collaborator_amount, :owner_amount, :notes, :created_at
                )'
            );
            $insert->execute([
                ':work_date' => $workDate,
                ':collaborator_id' => $collaboratorId,
                ':service_id' => $serviceId,
                ':service_price' => $price,
                ':collaborator_percent' => $percent,
                ':collaborator_amount' => $collaboratorAmount,
                ':owner_amount' => $ownerAmount,
                ':notes' => $notes,
                ':created_at' => nowDateTime(),
            ]);

            $ledger = $pdo->prepare('INSERT INTO ledger_entries(entry_date, description, type, amount, created_at) VALUES(:entry_date, :description, "haber", :amount, :created_at)');
            $ledger->execute([
                ':entry_date' => $workDate,
                ':description' => 'Ingreso por servicio: ' . $service['name'],
                ':amount' => $ownerAmount,
                ':created_at' => nowDateTime(),
            ]);

            redirectTo('colaboradores', 'Trabajo registrado y haber actualizado.');
        }

        if ($action === 'add_product') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $cost = (float) ($_POST['cost_price'] ?? 0);
            $sale = (float) ($_POST['sale_price'] ?? 0);
            $stock = (int) ($_POST['stock'] ?? 0);

            if ($name === '' || $cost <= 0 || $sale <= 0 || $stock < 0) {
                throw new RuntimeException('Datos inválidos para producto.');
            }

            $stmt = $pdo->prepare('INSERT INTO products(name, description, cost_price, sale_price, stock, created_at) VALUES(:name, :description, :cost, :sale, :stock, :created_at)');
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':cost' => $cost,
                ':sale' => $sale,
                ':stock' => $stock,
                ':created_at' => nowDateTime(),
            ]);
            redirectTo('productos', 'Producto agregado.');
        }

        if ($action === 'update_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $cost = (float) ($_POST['cost_price'] ?? 0);
            $sale = (float) ($_POST['sale_price'] ?? 0);
            $stock = (int) ($_POST['stock'] ?? 0);

            if ($id <= 0 || $name === '' || $cost <= 0 || $sale <= 0 || $stock < 0) {
                throw new RuntimeException('Datos inválidos para editar producto.');
            }

            $stmt = $pdo->prepare('UPDATE products SET name = :name, description = :description, cost_price = :cost, sale_price = :sale, stock = :stock WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':description' => $description,
                ':cost' => $cost,
                ':sale' => $sale,
                ':stock' => $stock,
            ]);
            redirectTo('productos', 'Producto actualizado.');
        }

        if ($action === 'delete_product') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Producto inválido.');
            }

            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute([':id' => $id]);
            redirectTo('productos', 'Producto eliminado.');
        }

        if ($action === 'add_ledger_entry') {
            $entryDate = trim((string) ($_POST['entry_date'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $type = trim((string) ($_POST['type'] ?? ''));
            $amount = (float) ($_POST['amount'] ?? 0);
            if ($entryDate === '' || $description === '' || !in_array($type, ['debe', 'haber'], true) || $amount <= 0) {
                throw new RuntimeException('Datos inválidos para contabilidad.');
            }

            $stmt = $pdo->prepare('INSERT INTO ledger_entries(entry_date, description, type, amount, created_at) VALUES(:entry_date, :description, :type, :amount, :created_at)');
            $stmt->execute([
                ':entry_date' => $entryDate,
                ':description' => $description,
                ':type' => $type,
                ':amount' => $amount,
                ':created_at' => nowDateTime(),
            ]);
            redirectTo('contabilidad', 'Movimiento agregado.');
        }

        throw new RuntimeException('Acción no válida.');
    } catch (Throwable $e) {
        redirectTo($view, 'Error: ' . $e->getMessage());
    }
}

$collaborators = $pdo->query('SELECT * FROM collaborators ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$services = $pdo->query('SELECT * FROM services ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$workLogs = $pdo->query('SELECT wl.*, c.name AS collaborator_name, s.name AS service_name FROM work_logs wl JOIN collaborators c ON c.id = wl.collaborator_id JOIN services s ON s.id = wl.service_id ORDER BY wl.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$ledgerEntries = $pdo->query('SELECT * FROM ledger_entries ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);

$today = (new DateTimeImmutable('now'))->format('Y-m-d');
$weekStart = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');

$dailyByCollaborator = $pdo->query('SELECT c.name, COALESCE(SUM(wl.collaborator_amount), 0) AS total_colaborador, COALESCE(SUM(wl.owner_amount), 0) AS total_negocio FROM work_logs wl JOIN collaborators c ON c.id = wl.collaborator_id WHERE wl.work_date = ' . $pdo->quote($today) . ' GROUP BY c.id ORDER BY c.name ASC')->fetchAll(PDO::FETCH_ASSOC);
$weeklyReport = $pdo->query('SELECT c.name, COALESCE(SUM(wl.collaborator_amount), 0) AS total_colaborador, COALESCE(SUM(wl.owner_amount), 0) AS total_negocio FROM work_logs wl JOIN collaborators c ON c.id = wl.collaborator_id WHERE wl.work_date >= ' . $pdo->quote($weekStart) . ' GROUP BY c.id ORDER BY c.name ASC')->fetchAll(PDO::FETCH_ASSOC);
$monthlyReport = $pdo->query('SELECT c.name, COALESCE(SUM(wl.collaborator_amount), 0) AS total_colaborador, COALESCE(SUM(wl.owner_amount), 0) AS total_negocio FROM work_logs wl JOIN collaborators c ON c.id = wl.collaborator_id WHERE wl.work_date >= ' . $pdo->quote($monthStart) . ' GROUP BY c.id ORDER BY c.name ASC')->fetchAll(PDO::FETCH_ASSOC);

$totalDebe = 0.0;
$totalHaber = 0.0;
foreach ($ledgerEntries as $entry) {
    if ($entry['type'] === 'debe') {
        $totalDebe += (float) $entry['amount'];
    } else {
        $totalHaber += (float) $entry['amount'];
    }
}

$totalInventoryCost = 0.0;
$totalInventorySale = 0.0;
foreach ($products as $p) {
    $totalInventoryCost += ((float) $p['cost_price']) * ((int) $p['stock']);
    $totalInventorySale += ((float) $p['sale_price']) * ((int) $p['stock']);
}

$message = trim((string) ($_GET['msg'] ?? ''));
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Peluquería Manager PHP</title>
    <link rel="stylesheet" href="styles.css" />
  </head>
  <body>
    <header>
      <h1>Peluquería Manager (PHP)</h1>
      <p>Gestión de colaboradores, servicios, productos, contabilidad y reportes.</p>
    </header>

    <nav class="tabs">
      <a class="tab-btn <?= $view === 'colaboradores' ? 'active' : '' ?>" href="?view=colaboradores">Colaboradores</a>
      <a class="tab-btn <?= $view === 'servicios' ? 'active' : '' ?>" href="?view=servicios">Servicios</a>
      <a class="tab-btn <?= $view === 'productos' ? 'active' : '' ?>" href="?view=productos">Productos</a>
      <a class="tab-btn <?= $view === 'contabilidad' ? 'active' : '' ?>" href="?view=contabilidad">Contabilidad</a>
      <a class="tab-btn <?= $view === 'reportes' ? 'active' : '' ?>" href="?view=reportes">Reportes</a>
    </nav>

    <main>
      <?php if ($message !== ''): ?>
        <section class="card notice"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></section>
      <?php endif; ?>

      <?php if ($view === 'colaboradores'): ?>
        <section class="split-grid">
          <article class="card">
            <h2>Agregar colaborador</h2>
            <form method="post" class="grid-form">
              <input type="hidden" name="action" value="add_collaborator" />
              <input type="text" name="name" placeholder="Nombre del colaborador" required />
              <button type="submit">Agregar</button>
            </form>

            <h3>Editar / Eliminar colaboradores</h3>
            <table>
              <thead><tr><th>Nombre</th><th>Editar</th><th>Eliminar</th></tr></thead>
              <tbody>
                <?php foreach ($collaborators as $c): ?>
                  <tr>
                    <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <form method="post" class="inline-form compact">
                        <input type="hidden" name="action" value="update_collaborator" />
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                        <input type="text" name="name" value="<?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>" required />
                        <button type="submit">Guardar</button>
                      </form>
                    </td>
                    <td>
                      <form method="post">
                        <input type="hidden" name="action" value="delete_collaborator" />
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                        <button class="danger" type="submit">Eliminar</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </article>

          <article class="card">
            <h2>Registrar trabajo del colaborador</h2>
            <form method="post" class="grid-form">
              <input type="hidden" name="action" value="add_work_log" />
              <input type="date" name="work_date" value="<?= $today ?>" required />
              <select name="collaborator_id" required>
                <option value="">Selecciona colaborador</option>
                <?php foreach ($collaborators as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
              <select name="service_id" required>
                <option value="">Selecciona servicio</option>
                <?php foreach ($services as $s): ?>
                  <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?> (<?= money((float) $s['price']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <input type="number" step="0.01" min="1" max="99" name="collaborator_percent" placeholder="% colaborador" required />
              <input type="text" name="notes" placeholder="Observaciones (opcional)" />
              <button type="submit">Registrar trabajo</button>
            </form>
            <p class="muted">Tip: usa el % definido para el servicio o ajusta aquí según acuerdo puntual.</p>

            <h3>Últimos trabajos</h3>
            <table>
              <thead>
                <tr><th>Fecha</th><th>Colaborador</th><th>Servicio</th><th>%</th><th>Pago colaborador</th><th>Haber negocio</th></tr>
              </thead>
              <tbody>
                <?php foreach ($workLogs as $w): ?>
                  <tr>
                    <td><?= htmlspecialchars($w['work_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($w['collaborator_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($w['service_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= number_format((float) $w['collaborator_percent'], 2) ?>%</td>
                    <td><?= money((float) $w['collaborator_amount']) ?></td>
                    <td><?= money((float) $w['owner_amount']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </article>
        </section>

        <section class="card">
          <h2>Acumulado del día por colaborador (pago diario)</h2>
          <table>
            <thead><tr><th>Colaborador</th><th>Total colaborador</th><th>Total negocio</th></tr></thead>
            <tbody>
              <?php foreach ($dailyByCollaborator as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= money((float) $row['total_colaborador']) ?></td>
                  <td><?= money((float) $row['total_negocio']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      <?php endif; ?>

      <?php if ($view === 'servicios'): ?>
        <section class="card">
          <h2>Servicios: agregar, editar y eliminar</h2>
          <form method="post" class="grid-form">
            <input type="hidden" name="action" value="add_service" />
            <input type="text" name="name" placeholder="Nombre del servicio" required />
            <input type="number" name="price" min="0.01" step="0.01" placeholder="Precio" required />
            <input type="number" name="collaborator_percent" min="1" max="99" step="0.01" placeholder="% colaborador" required />
            <button type="submit">Agregar servicio</button>
          </form>

          <table>
            <thead><tr><th>Servicio</th><th>Precio</th><th>% colaborador</th><th>Editar</th><th>Eliminar</th></tr></thead>
            <tbody>
              <?php foreach ($services as $s): ?>
                <tr>
                  <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= money((float) $s['price']) ?></td>
                  <td><?= number_format((float) $s['collaborator_percent'], 2) ?>%</td>
                  <td>
                    <form method="post" class="inline-form compact">
                      <input type="hidden" name="action" value="update_service" />
                      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>" />
                      <input type="text" name="name" value="<?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>" required />
                      <input type="number" name="price" min="0.01" step="0.01" value="<?= htmlspecialchars((string) $s['price'], ENT_QUOTES, 'UTF-8') ?>" required />
                      <input type="number" name="collaborator_percent" min="1" max="99" step="0.01" value="<?= htmlspecialchars((string) $s['collaborator_percent'], ENT_QUOTES, 'UTF-8') ?>" required />
                      <button type="submit">Guardar</button>
                    </form>
                  </td>
                  <td>
                    <form method="post">
                      <input type="hidden" name="action" value="delete_service" />
                      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>" />
                      <button class="danger" type="submit">Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      <?php endif; ?>

      <?php if ($view === 'productos'): ?>
        <section class="card">
          <h2>Productos: inventario y contabilidad</h2>
          <form method="post" class="grid-form">
            <input type="hidden" name="action" value="add_product" />
            <input type="text" name="name" placeholder="Nombre del producto" required />
            <input type="text" name="description" placeholder="Descripción breve" />
            <input type="number" name="cost_price" min="0.01" step="0.01" placeholder="Precio de compra" required />
            <input type="number" name="sale_price" min="0.01" step="0.01" placeholder="Precio de venta" required />
            <input type="number" name="stock" min="0" step="1" placeholder="Stock" required />
            <button type="submit">Agregar producto</button>
          </form>

          <div class="totals">
            <div><strong>Valor inventario (costo):</strong> <?= money($totalInventoryCost) ?></div>
            <div><strong>Valor inventario (venta):</strong> <?= money($totalInventorySale) ?></div>
            <div><strong>Margen potencial:</strong> <?= money($totalInventorySale - $totalInventoryCost) ?></div>
          </div>

          <table>
            <thead><tr><th>Producto</th><th>Descripción</th><th>Costo</th><th>Venta</th><th>Stock</th><th>Editar</th><th>Eliminar</th></tr></thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= money((float) $p['cost_price']) ?></td>
                  <td><?= money((float) $p['sale_price']) ?></td>
                  <td><?= (int) $p['stock'] ?></td>
                  <td>
                    <form method="post" class="inline-form compact">
                      <input type="hidden" name="action" value="update_product" />
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>" />
                      <input type="text" name="name" value="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>" required />
                      <input type="text" name="description" value="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                      <input type="number" name="cost_price" min="0.01" step="0.01" value="<?= htmlspecialchars((string) $p['cost_price'], ENT_QUOTES, 'UTF-8') ?>" required />
                      <input type="number" name="sale_price" min="0.01" step="0.01" value="<?= htmlspecialchars((string) $p['sale_price'], ENT_QUOTES, 'UTF-8') ?>" required />
                      <input type="number" name="stock" min="0" step="1" value="<?= (int) $p['stock'] ?>" required />
                      <button type="submit">Guardar</button>
                    </form>
                  </td>
                  <td>
                    <form method="post">
                      <input type="hidden" name="action" value="delete_product" />
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>" />
                      <button class="danger" type="submit">Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      <?php endif; ?>

      <?php if ($view === 'contabilidad'): ?>
        <section class="card">
          <h2>Contabilidad general</h2>
          <form method="post" class="grid-form">
            <input type="hidden" name="action" value="add_ledger_entry" />
            <input type="date" name="entry_date" value="<?= $today ?>" required />
            <input type="text" name="description" placeholder="Descripción" required />
            <select name="type" required>
              <option value="debe">Debe</option>
              <option value="haber">Haber</option>
            </select>
            <input type="number" name="amount" min="0.01" step="0.01" placeholder="Monto" required />
            <button type="submit">Agregar movimiento</button>
          </form>

          <div class="totals">
            <div><strong>Total Debe:</strong> <?= money($totalDebe) ?></div>
            <div><strong>Total Haber:</strong> <?= money($totalHaber) ?></div>
            <div><strong>Balance:</strong> <?= money($totalHaber - $totalDebe) ?></div>
          </div>

          <table>
            <thead><tr><th>Fecha</th><th>Descripción</th><th>Tipo</th><th>Monto</th></tr></thead>
            <tbody>
              <?php foreach ($ledgerEntries as $entry): ?>
                <tr>
                  <td><?= htmlspecialchars($entry['entry_date'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($entry['type'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= money((float) $entry['amount']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      <?php endif; ?>

      <?php if ($view === 'reportes'): ?>
        <section class="split-grid">
          <article class="card">
            <h2>Reporte semanal por colaborador</h2>
            <p class="muted">Desde <?= htmlspecialchars($weekStart, ENT_QUOTES, 'UTF-8') ?></p>
            <table>
              <thead><tr><th>Colaborador</th><th>Ganancia colaborador</th><th>Ganancia negocio</th></tr></thead>
              <tbody>
                <?php foreach ($weeklyReport as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= money((float) $row['total_colaborador']) ?></td>
                    <td><?= money((float) $row['total_negocio']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </article>

          <article class="card">
            <h2>Reporte mensual por colaborador</h2>
            <p class="muted">Desde <?= htmlspecialchars($monthStart, ENT_QUOTES, 'UTF-8') ?></p>
            <table>
              <thead><tr><th>Colaborador</th><th>Ganancia colaborador</th><th>Ganancia negocio</th></tr></thead>
              <tbody>
                <?php foreach ($monthlyReport as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= money((float) $row['total_colaborador']) ?></td>
                    <td><?= money((float) $row['total_negocio']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </article>
        </section>
      <?php endif; ?>
    </main>

    <footer>
      <p>Base inicial en PHP + SQLite. Seguimos iterando módulo por módulo.</p>
    </footer>
  </body>
</html>
