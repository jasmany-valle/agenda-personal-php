<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = (int) $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$mensaje = '';
$error = '';

// Cargar categorías
$stmtCat = $pdo->query("SELECT id, nombre FROM categorias ORDER BY id ASC");
$categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
$mapaCategorias = [];
foreach ($categorias as $cat) {
    $mapaCategorias[(int)$cat['id']] = $cat['nombre'];
}

// Alternar favorito
if (isset($_GET['fav'])) {
    $contacto_id = (int) $_GET['fav'];
    $stmt = $pdo->prepare("UPDATE contactos SET favorito = IF(favorito = 1, 0, 1) WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$contacto_id, $usuario_id]);
    header("Location: dashboard.php");
    exit();
}

// Eliminar contacto
if (isset($_GET['eliminar'])) {
    $contacto_id = (int) $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM contactos WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$contacto_id, $usuario_id]);
    header("Location: dashboard.php?msg=eliminado");
    exit();
}

// Crear o actualizar contacto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_editar = (int) ($_POST['id_contacto'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $categoria_id = (int) ($_POST['categoria_id'] ?? 5);
    $favorito = isset($_POST['favorito']) ? 1 : 0;

    if (!isset($mapaCategorias[$categoria_id])) {
        $categoria_id = 5;
    }

    if ($nombre === '') {
        $error = "El nombre del contacto es obligatorio.";
    } else {
        if ($id_editar > 0) {
            $stmt = $pdo->prepare("
                UPDATE contactos
                SET nombre = ?, telefono = ?, correo = ?, ciudad = ?, categoria_id = ?, favorito = ?
                WHERE id = ? AND id_usuario = ?
            ");
            $stmt->execute([$nombre, $telefono, $correo, $ciudad, $categoria_id, $favorito, $id_editar, $usuario_id]);
            header("Location: dashboard.php?msg=actualizado");
            exit();
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO contactos (id_usuario, nombre, telefono, correo, ciudad, categoria_id, favorito)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $nombre, $telefono, $correo, $ciudad, $categoria_id, $favorito]);
            header("Location: dashboard.php?msg=guardado");
            exit();
        }
    }
}

// Cargar contacto para editar si aplica
$contactoEditar = null;
if (isset($_GET['editar'])) {
    $id_ed = (int) $_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM contactos WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id_ed, $usuario_id]);
    $contactoEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Mensajes por URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'guardado') $mensaje = "Contacto guardado correctamente.";
    if ($_GET['msg'] === 'actualizado') $mensaje = "Contacto actualizado correctamente.";
    if ($_GET['msg'] === 'eliminado') $mensaje = "Contacto eliminado.";
}

// Búsqueda, categoría, favoritos y ordenamiento
$buscar = trim($_GET['q'] ?? '');
$filtro_cat = (int) ($_GET['categoria'] ?? 0);
$solo_fav = isset($_GET['solo_fav']) && $_GET['solo_fav'] === '1';
$orden = $_GET['orden'] ?? 'fecha_desc';

$sql = "
    SELECT c.*, cat.nombre AS categoria_nombre
    FROM contactos c
    LEFT JOIN categorias cat ON c.categoria_id = cat.id
    WHERE c.id_usuario = ?
";
$params = [$usuario_id];

if ($buscar !== '') {
    $sql .= " AND (c.nombre LIKE ? OR c.correo LIKE ? OR c.ciudad LIKE ?)";
    $like = "%$buscar%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filtro_cat > 0 && isset($mapaCategorias[$filtro_cat])) {
    $sql .= " AND c.categoria_id = ?";
    $params[] = $filtro_cat;
}

if ($solo_fav) {
    $sql .= " AND c.favorito = 1";
}

switch ($orden) {
    case 'nombre_asc':
        $sql .= " ORDER BY c.nombre ASC";
        break;
    case 'nombre_desc':
        $sql .= " ORDER BY c.nombre DESC";
        break;
    case 'ciudad_asc':
        $sql .= " ORDER BY c.ciudad ASC, c.nombre ASC";
        break;
    case 'fecha_asc':
        $sql .= " ORDER BY c.creado_en ASC";
        break;
    default:
        $sql .= " ORDER BY c.favorito DESC, c.creado_en DESC";
        break;
}

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$contactos = $stmtList->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi Agenda - Dashboard</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    body { background: #f1f5f9; color: #1e293b; padding: 24px; }
    .contenedor { max-width: 1100px; margin: 0 auto; }
    .topbar {
      background: linear-gradient(135deg, #1e3a8a, #2563eb);
      color: #fff;
      padding: 20px 24px;
      border-radius: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 20px;
      box-shadow: 0 8px 20px rgba(30, 58, 138, 0.2);
    }
    .topbar a.salir {
      background: rgba(255,255,255,0.18);
      color: #fff;
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 8px;
      font-weight: 600;
    }
    .topbar a.salir:hover { background: rgba(255,255,255,0.28); }
    .panel {
      background: #fff;
      padding: 20px;
      border-radius: 14px;
      margin-bottom: 20px;
      box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    }
    .panel h3 { margin-bottom: 14px; font-size: 18px; color: #0f172a; }
    .grid-form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      align-items: end;
    }
    label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; }
    input, select {
      width: 100%;
      padding: 10px 12px;
      border: 1.5px solid #cbd5e1;
      border-radius: 8px;
      font-size: 14px;
    }
    .check-inline { display: flex; align-items: center; gap: 8px; padding: 10px 0; font-size: 14px; font-weight: 600; }
    .check-inline input { width: auto; }
    .btn {
      background: #2563eb;
      color: #fff;
      border: none;
      padding: 10px 16px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      font-size: 14px;
    }
    .btn:hover { background: #1d4ed8; }
    .btn-gris { background: #64748b; }
    .btn-gris:hover { background: #475569; }
    .msg-ok { background: #dcfce7; color: #15803d; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; }
    .msg-err { background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; }
    .tabla-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 14px; }
    th { background: #f8fafc; color: #334155; font-weight: 700; }
    .badge { background: #e0e7ff; color: #1e40af; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .fav-link { font-size: 18px; text-decoration: none; }
    .acciones a { margin-right: 8px; text-decoration: none; font-weight: 600; font-size: 13px; }
    .editar { color: #2563eb; }
    .eliminar { color: #dc2626; }
  </style>
</head>
<body>
<div class="contenedor">
  <div class="topbar">
    <div>
      <h2>Agenda Personal</h2>
      <p>Hola, <?= htmlspecialchars($nombre_usuario) ?> (<?= count($contactos) ?> contactos)</p>
    </div>
    <a class="salir" href="logout.php">Cerrar sesión</a>
  </div>

  <?php if ($mensaje !== ''): ?>
    <div class="msg-ok"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="msg-err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="panel">
    <h3><?= $contactoEditar ? 'Editar contacto' : 'Nuevo contacto' ?></h3>
    <form method="POST" action="dashboard.php">
      <?php if ($contactoEditar): ?>
        <input type="hidden" name="id_contacto" value="<?= (int)$contactoEditar['id'] ?>">
      <?php endif; ?>

      <div class="grid-form">
        <div>
          <label for="nombre">Nombre *</label>
          <input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($contactoEditar['nombre'] ?? '') ?>">
        </div>
        <div>
          <label for="telefono">Teléfono</label>
          <input type="text" id="telefono" name="telefono" value="<?= htmlspecialchars($contactoEditar['telefono'] ?? '') ?>">
        </div>
        <div>
          <label for="correo">Correo</label>
          <input type="email" id="correo" name="correo" value="<?= htmlspecialchars($contactoEditar['correo'] ?? '') ?>">
        </div>
        <div>
          <label for="ciudad">Ciudad</label>
          <input type="text" id="ciudad" name="ciudad" value="<?= htmlspecialchars($contactoEditar['ciudad'] ?? '') ?>">
        </div>
        <div>
          <label for="categoria_id">Categoría</label>
          <select id="categoria_id" name="categoria_id">
            <?php $catSel = (int)($contactoEditar['categoria_id'] ?? 5); ?>
            <?php foreach ($categorias as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>" <?= $catSel === (int)$cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="check-inline">
          <input type="checkbox" id="favorito" name="favorito" value="1" <?= !empty($contactoEditar['favorito']) ? 'checked' : '' ?>>
          <label for="favorito" style="margin:0;">Favorito</label>
        </div>
        <div>
          <button type="submit" class="btn"><?= $contactoEditar ? 'Guardar cambios' : 'Agregar contacto' ?></button>
          <?php if ($contactoEditar): ?>
            <a href="dashboard.php" class="btn btn-gris">Cancelar</a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <div class="panel">
    <h3>Buscar, filtrar y ordenar</h3>
    <form method="GET" action="dashboard.php" class="grid-form">
      <div>
        <label for="q">Buscar (nombre, correo, ciudad)</label>
        <input type="text" id="q" name="q" value="<?= htmlspecialchars($buscar) ?>" placeholder="Ej. Ana, Macará...">
      </div>
      <div>
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria">
          <option value="0">Todas</option>
          <?php foreach ($categorias as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $filtro_cat === (int)$cat['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="orden">Ordenar por</label>
        <select id="orden" name="orden">
          <option value="fecha_desc" <?= $orden === 'fecha_desc' ? 'selected' : '' ?>>Favoritos y más recientes</option>
          <option value="nombre_asc" <?= $orden === 'nombre_asc' ? 'selected' : '' ?>>Nombre (A - Z)</option>
          <option value="nombre_desc" <?= $orden === 'nombre_desc' ? 'selected' : '' ?>>Nombre (Z - A)</option>
          <option value="ciudad_asc" <?= $orden === 'ciudad_asc' ? 'selected' : '' ?>>Ciudad (A - Z)</option>
          <option value="fecha_asc" <?= $orden === 'fecha_asc' ? 'selected' : '' ?>>Más antiguos primero</option>
        </select>
      </div>
      <div class="check-inline">
        <input type="checkbox" id="solo_fav" name="solo_fav" value="1" <?= $solo_fav ? 'checked' : '' ?>>
        <label for="solo_fav" style="margin:0;">Solo favoritos</label>
      </div>
      <div>
        <button type="submit" class="btn">Aplicar</button>
        <a href="dashboard.php" class="btn btn-gris">Limpiar</a>
      </div>
    </form>
  </div>

  <div class="panel">
    <h3>Tus contactos</h3>
    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Fav</th>
            <th>Nombre</th>
            <th>Teléfono</th>
            <th>Correo</th>
            <th>Ciudad</th>
            <th>Categoría</th>
            <th>Creado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($contactos) === 0): ?>
            <tr><td colspan="8" style="text-align:center; color:#64748b;">No hay contactos para mostrar.</td></tr>
          <?php else: ?>
            <?php foreach ($contactos as $c): ?>
              <tr>
                <td>
                  <a class="fav-link" href="dashboard.php?fav=<?= (int)$c['id'] ?>" title="Cambiar favorito">
                    <?= ((int)$c['favorito'] === 1) ? '⭐' : '☆' ?>
                  </a>
                </td>
                <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                <td><?= htmlspecialchars($c['telefono'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['correo'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['ciudad'] ?? '') ?></td>
                <td><span class="badge"><?= htmlspecialchars($c['categoria_nombre'] ?? 'Otro') ?></span></td>
                <td><?= htmlspecialchars($c['creado_en'] ?? '') ?></td>
                <td class="acciones">
                  <a class="editar" href="dashboard.php?editar=<?= (int)$c['id'] ?>">Editar</a>
                  <a class="eliminar" href="dashboard.php?eliminar=<?= (int)$c['id'] ?>" onclick="return confirm('¿Eliminar este contacto?');">Eliminar</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>