<?php
// index.php
require_once 'db_config.php';
session_start();

$msg = "";
$cita_editar = null;

// Mensajes tras redirección (evita reenvíos duplicados al pulsar F5)
if (isset($_GET['msg'])) {
    $mensajes = [
        'creada'      => "<div class='alert alert-success'>Cita registrada correctamente en SQLite.</div>",
        'actualizada' => "<div class='alert alert-success'>Cita actualizada / reagendada correctamente.</div>",
        'eliminada'   => "<div class='alert alert-danger'>Cita eliminada de la base de datos.</div>",
        'estado'      => "<div class='alert alert-info'>Estado de la cita actualizado.</div>"
    ];
    $msg = $mensajes[$_GET['msg']] ?? "";
}

// Lógica de Login
if (isset($_POST['login'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND password = ?");
    $stmt->execute([trim($_POST['user']), trim($_POST['pass'])]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['usuario'] = $user['usuario'];
        header("Location: index.php");
        exit;
    } else {
        $msg = "<div class='alert alert-danger'>Acceso denegado. Verifique sus credenciales.</div>";
    }
}

// Cerrar Sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Operaciones CRUD (Solo usuarios autenticados)
if (isset($_SESSION['usuario'])) {

    // 1. Crear nueva cita
    if (isset($_POST['agendar'])) {
        $sql = "INSERT INTO citas (paciente_nombre, especialidad, fecha, hora, estado) VALUES (?, ?, ?, ?, 'Registrada')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            trim($_POST['paciente']),
            $_POST['especialidad'],
            $_POST['fecha'],
            $_POST['hora']
        ]);
        header("Location: index.php?msg=creada");
        exit;
    }

    // 2. Guardar edición / reagendamiento
    if (isset($_POST['actualizar'])) {
        $sql = "UPDATE citas SET paciente_nombre = ?, especialidad = ?, fecha = ?, hora = ?, estado = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            trim($_POST['paciente']),
            $_POST['especialidad'],
            $_POST['fecha'],
            $_POST['hora'],
            $_POST['estado'],
            (int)$_POST['id']
        ]);
        header("Location: index.php?msg=actualizada");
        exit;
    }

    // 3. Cargar datos en el formulario para Editar / Reagendar
    if (isset($_GET['editar'])) {
        $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
        $stmt->execute([(int)$_GET['editar']]);
        $cita_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 4. Eliminar cita
    if (isset($_GET['eliminar'])) {
        $stmt = $pdo->prepare("DELETE FROM citas WHERE id = ?");
        $stmt->execute([(int)$_GET['eliminar']]);
        header("Location: index.php?msg=eliminada");
        exit;
    }

    // 5. Cambio rápido de estado
    if (isset($_GET['estado'], $_GET['id'])) {
        $permitidos = ['Registrada', 'Reagendada', 'Atendida', 'Cancelada'];
        if (in_array($_GET['estado'], $permitidos)) {
            $stmt = $pdo->prepare("UPDATE citas SET estado = ? WHERE id = ?");
            $stmt->execute([$_GET['estado'], (int)$_GET['id']]);
        }
        header("Location: index.php?msg=estado");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Médico Portable</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container">
    <?php if (!isset($_SESSION['usuario'])): ?>
        <div class="login-wrapper">
            <div class="login-form">
                <div class="login-header">
                    <span class="badge-medical">PORTAL CLÍNICO LOCAL</span>
                    <h2>Acceso al Sistema Médico</h2>
                    <p class="subtitle">Ingrese sus credenciales para gestionar la agenda de pacientes</p>
                </div>
                <?= $msg ?>
                <form method="POST">
                    <label for="user">Usuario</label>
                    <input type="text" id="user" name="user" placeholder="Ej. admin" required autocomplete="off">

                    <label for="pass">Contraseña</label>
                    <div class="password-box">
                        <input type="password" id="pass" name="pass" placeholder="••••••••" required>
                        <button type="button" class="btn-toggle" onclick="togglePass()">Ver</button>
                    </div>

                    <button type="submit" name="login" class="btn-primary">Ingresar al Panel</button>
                </form>
                <p class="login-hint">Acceso por defecto: <strong>admin</strong> / <strong>1234</strong></p>
            </div>
        </div>

    <?php else: ?>
        <?php
        // Obtener todas las citas y estadísticas rápidas
        $citas = $pdo->query("SELECT * FROM citas ORDER BY fecha ASC, hora ASC")->fetchAll(PDO::FETCH_ASSOC);
        $total_citas = count($citas);
        $atendidas = count(array_filter($citas, fn($c) => $c['estado'] === 'Atendida'));
        $pendientes = $total_citas - $atendidas;
        ?>

        <div class="header">
            <div>
                <h1 class="brand-title">Gestión de Citas Médicas</h1>
                <span class="user-info">Sesión activa: <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></span>
            </div>
            <a href="?logout=1" class="logout">Cerrar Sesión</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-num"><?= $total_citas ?></span>
                <span class="stat-label">Total Citas</span>
            </div>
            <div class="stat-card">
                <span class="stat-num"><?= $pendientes ?></span>
                <span class="stat-label">Pendientes / Activas</span>
            </div>
            <div class="stat-card">
                <span class="stat-num"><?= $atendidas ?></span>
                <span class="stat-label">Atendidas</span>
            </div>
        </div>

        <?= $msg ?>

        <!-- Formulario Dinámico: Crear o Editar/Reagendar -->
        <div class="appointment-form <?= $cita_editar ? 'editing-mode' : '' ?>">
            <h2><?= $cita_editar ? "Editar / Reagendar Cita #" . $cita_editar['id'] : "Agendar Nueva Cita Médica" ?></h2>
            <form method="POST">
                <?php if ($cita_editar): ?>
                    <input type="hidden" name="id" value="<?= $cita_editar['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre completo del paciente</label>
                        <input type="text" name="paciente" placeholder="Ej. María Fernanda Torres"
                               value="<?= $cita_editar ? htmlspecialchars($cita_editar['paciente_nombre']) : '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Especialidad médica</label>
                        <select name="especialidad">
                            <?php
                            $especialidades = ['Medicina General', 'Pediatría', 'Odontología', 'Cardiología', 'Ginecología'];
                            foreach ($especialidades as $esp):
                                $selected = ($cita_editar && $cita_editar['especialidad'] === $esp) ? 'selected' : '';
                            ?>
                                <option value="<?= $esp ?>" <?= $selected ?>><?= $esp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de la cita</label>
                        <input type="date" name="fecha" value="<?= $cita_editar ? $cita_editar['fecha'] : '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Hora</label>
                        <input type="time" name="hora" value="<?= $cita_editar ? $cita_editar['hora'] : '' ?>" required>
                    </div>
                    <?php if ($cita_editar): ?>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <?php
                            $estados = ['Registrada', 'Reagendada', 'Atendida', 'Cancelada'];
                            foreach ($estados as $est):
                                $sel_est = ($cita_editar['estado'] === $est) ? 'selected' : '';
                            ?>
                                <option value="<?= $est ?>" <?= $sel_est ?>><?= $est ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="btn-group">
                    <?php if ($cita_editar): ?>
                        <button type="submit" name="actualizar" class="btn-warning">Guardar Cambios / Reagendar</button>
                        <a href="index.php" class="btn-secondary">Cancelar Edición</a>
                    <?php else: ?>
                        <button type="submit" name="agendar" class="btn-primary">Guardar en Base de Datos Local</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabla de Citas en la parte inferior -->
        <h2>Reporte y Administración de Citas (Persistencia Local)</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Paciente</th>
                        <th>Especialidad</th>
                        <th>Fecha y Hora</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($citas)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:#777;">No hay citas registradas aún.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($citas as $row): ?>
                            <tr>
                                <td><strong>#<?= $row['id'] ?></strong></td>
                                <td><?= htmlspecialchars($row['paciente_nombre']) ?></td>
                                <td><?= htmlspecialchars($row['especialidad']) ?></td>
                                <td><?= $row['fecha'] ?> | <strong><?= $row['hora'] ?></strong></td>
                                <td>
                                    <span class="badge status-<?= strtolower($row['estado']) ?>">
                                        <?= htmlspecialchars($row['estado']) ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <a href="?editar=<?= $row['id'] ?>" class="btn-action btn-edit" title="Editar o Reagendar">Editar / Reagendar</a>
                                    <?php if ($row['estado'] !== 'Atendida'): ?>
                                        <a href="?estado=Atendida&id=<?= $row['id'] ?>" class="btn-action btn-done" title="Marcar como Atendida">Atender</a>
                                    <?php endif; ?>
                                    <a href="?eliminar=<?= $row['id'] ?>" class="btn-action btn-del"
                                       onclick="return confirm('¿Seguro que deseas eliminar la cita de <?= htmlspecialchars($row['paciente_nombre']) ?>?');">Borrar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function togglePass() {
    const input = document.getElementById('pass');
    const btn = document.querySelector('.btn-toggle');
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Ocultar';
    } else {
        input.type = 'password';
        btn.textContent = 'Ver';
    }
}

let enviando = false;
const formCita = document.querySelector('.appointment-form form');
if (formCita) {
    formCita.addEventListener('submit', function(e) {
        if (enviando) {
            e.preventDefault();
            return;
        }
        enviando = true;
        const btn = this.querySelector('button[type="submit"]');
        if (btn) btn.textContent = 'Guardando...';
    });
}
</script>

</body>
</html>