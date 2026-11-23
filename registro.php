<?php
session_start();
require_once 'conexion.php';
$mensaje = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nombre === '' || $correo === '' || $password === '') {
        $mensaje = "Completa todos los campos.";
    } else {
        // Verificar si el correo ya existe
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $check->execute([$correo]);

        if ($check->fetch()) {
            $mensaje = "Ese correo ya está registrado. Intenta iniciar sesión.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, correo, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$nombre, $correo, $hash])) {
                $exito = "¡Cuenta creada! Ya puedes iniciar sesión.";
            } else {
                $mensaje = "Error al registrar el usuario.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear Cuenta - Agenda Personal</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1e3a8a, #3b82f6);
      padding: 20px;
    }
    .card {
      background: #ffffff;
      width: 100%;
      max-width: 400px;
      padding: 36px 32px;
      border-radius: 16px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    }
    .card h2 {
      text-align: center;
      color: #1e293b;
      margin-bottom: 8px;
      font-size: 24px;
    }
    .card p.subtitulo {
      text-align: center;
      color: #64748b;
      font-size: 14px;
      margin-bottom: 24px;
    }
    .alerta {
      background: #fee2e2;
      color: #b91c1c;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 14px;
      margin-bottom: 18px;
      text-align: center;
      border: 1px solid #fecaca;
    }
    .exito {
      background: #dcfce7;
      color: #15803d;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 14px;
      margin-bottom: 18px;
      text-align: center;
      border: 1px solid #bbf7d0;
    }
    .campo {
      margin-bottom: 18px;
    }
    .campo label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #334155;
      margin-bottom: 6px;
    }
    .campo input {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid #cbd5e1;
      border-radius: 10px;
      font-size: 15px;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .campo input:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    .btn {
      width: 100%;
      padding: 12px;
      background: #2563eb;
      color: #ffffff;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn:hover {
      background: #1d4ed8;
    }
    .pie {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
      color: #475569;
    }
    .pie a {
      color: #2563eb;
      font-weight: 600;
      text-decoration: none;
    }
    .pie a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="card">
    <h2>Crear Cuenta</h2>
    <p class="subtitulo">Regístrate para usar tu Agenda Personal</p>

    <?php if ($mensaje !== ''): ?>
      <div class="alerta"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($exito !== ''): ?>
      <div class="exito"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <form method="POST" action="registro.php">
      <div class="campo">
        <label for="nombre">Nombre completo</label>
        <input type="text" id="nombre" name="nombre" placeholder="Tu nombre" required>
      </div>

      <div class="campo">
        <label for="correo">Correo electrónico</label>
        <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" required>
      </div>

      <div class="campo">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn">Registrarse</button>
    </form>

    <div class="pie">
      ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
    </div>
  </div>
</body>
</html>