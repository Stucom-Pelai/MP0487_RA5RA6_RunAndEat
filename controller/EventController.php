<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class EventController
{
    private PDO $conexion;

    public function __construct()
    {
        try {
            $this->conexion = new PDO(
                "mysql:host=localhost;dbname=mp0487_run_eat;charset=utf8",
                "root",
                ""
            );
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }


    public function inscribir(): void
    {
        $id_usuario = $_SESSION['id_usuario'] ?? null;
        $id_evento  = (int)($_POST['id_evento'] ?? 0);

        if (!$id_usuario) {
            $this->redirectWithError("../view/login.php", "Debes iniciar sesión para inscribirte en un evento.");
            return;
        }

        if (!$id_evento) {
            $this->redirectWithError("../index.php", "Evento no válido.");
            return;
        }

        try {
            // 1. Fetch event data
            $stmt = $this->conexion->prepare(
                "SELECT activo, fecha, plazas_disponibles, titulo FROM EVENTOS WHERE id_evento = ? LIMIT 1"
            );
            $stmt->execute([$id_evento]);
            $ev = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ev) {
                $_SESSION['error'] = 'El evento no existe.';
                header("Location: ../view/evento.php?id=" . $id_evento);
                exit();
            }
            if (!$ev['activo']) {
                $_SESSION['error'] = 'Este evento ya no está disponible.';
                header("Location: ../view/evento.php?id=" . $id_evento);
                exit();
            }
            if ($ev['fecha'] < date('Y-m-d')) {
                $_SESSION['error'] = 'No puedes inscribirte en un evento que ya ha pasado.';
                header("Location: ../view/evento.php?id=" . $id_evento);
                exit();
            }
            if ($ev['plazas_disponibles'] <= 0) {
                $_SESSION['error'] = 'Lo sentimos, no quedan plazas disponibles en este evento.';
                header("Location: ../view/evento.php?id=" . $id_evento);
                exit();
            }

            // 2. Check existing inscription
            $stmt = $this->conexion->prepare(
                "SELECT id_inscripcion, estado FROM INSCRIPCIONES WHERE id_evento = ? AND id_usuario = ? LIMIT 1"
            );
            $stmt->execute([$id_evento, $id_usuario]);
            $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($inscripcion && $inscripcion['estado'] !== 'cancelada') {
                $_SESSION['error'] = 'Ya estás inscrito en este evento.';
                header("Location: ../view/evento.php?id=" . $id_evento);
                exit();
            }

            // 3. Transaction: insert/reactivate + decrement spots
            $this->conexion->beginTransaction();

            if ($inscripcion && $inscripcion['estado'] === 'cancelada') {
                $stmt = $this->conexion->prepare(
                    "UPDATE INSCRIPCIONES SET estado = 'confirmada', fecha_inscripcion = NOW() WHERE id_inscripcion = ?"
                );
                $stmt->execute([$inscripcion['id_inscripcion']]);
            } else {
                $stmt = $this->conexion->prepare(
                    "INSERT INTO INSCRIPCIONES (id_evento, id_usuario, estado) VALUES (?, ?, 'confirmada')"
                );
                $stmt->execute([$id_evento, $id_usuario]);
            }

            $stmt = $this->conexion->prepare(
                "UPDATE EVENTOS SET plazas_disponibles = plazas_disponibles - 1 WHERE id_evento = ? AND plazas_disponibles > 0"
            );
            $stmt->execute([$id_evento]);

            if ($stmt->rowCount() === 0) {
                $this->conexion->rollBack();
                $_SESSION['error'] = 'Lo sentimos, las plazas se agotaron justo antes de tu inscripción.';
                header("Location: ../view/evento.php?id=" . $id_evento);
                exit();
            }

            $this->conexion->commit();

            $_SESSION['success'] = ($inscripcion && $inscripcion['estado'] === 'cancelada')
                ? 'Tu inscripción en "' . $ev['titulo'] . '" ha sido reactivada!'
                : 'Te has inscrito correctamente en "' . $ev['titulo'] . '"!';

            header("Location: ../view/evento.php?id=" . $id_evento);
            exit();
        } catch (PDOException $e) {
            $this->redirectWithError("../view/evento.php?id=" . $id_evento, "Error al procesar la inscripción: " . $e->getMessage());
        }
    }

    public function cancelarInscripcion(): void {}

    public function getCategorias(): array
    {
        try {
            $stmt = $this->conexion->query("SELECT id_categoria, nombre FROM CATEGORIAS ORDER BY nombre ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function crearEvento(): void
    {
        $titulo       = trim($_POST["titulo"] ?? "");
        $descripcion  = trim($_POST["descripcion"] ?? "");
        $fecha        = $_POST["fecha"] ?? "";
        $hora         = $_POST["hora"] ?? "";
        $ciudad       = trim($_POST["ubicacion"] ?? "");
        $direccion    = trim($_POST["direccion"] ?? "");
        $precio       = $_POST["precio"] ?? 0;
        $capacidad    = $_POST["participantes"] ?? 0;
        $tipo_evento  = $_POST["tipo-evento"] ?? "";
        $incluye      = trim($_POST["incluye"] ?? "");
        $que_traer    = trim($_POST["que-traer"] ?? "");
        $notas        = trim($_POST["notas"] ?? "");

        $distancia    = trim($_POST["distancia"] ?? "");
        $distancia    = $distancia !== "" ? (float)$distancia : null;

        $nivel        = trim($_POST["nivel"] ?? "");
        $nivel        = $nivel !== "" ? $nivel : null;

        $id_organizador = $_SESSION["id_usuario"] ?? null;

        if (!$id_organizador) {
            $this->redirectWithError("../view/login.php", "Debes iniciar sesión como organizador.");
            return;
        }

        $id_categoria = (int)($_POST["tipo-evento"] ?? 1);

        $imagenPath = 'public/img/eventos-photos/user.png';
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../public/img/eventos-photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExtension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('evento_') . '.' . $fileExtension;
            $destination = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destination)) {
                $imagenPath = 'public/img/eventos-photos/' . $fileName;
            }
        }

        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO EVENTOS (
                    id_organizador, id_categoria, titulo, descripcion, imagen,
                    fecha, hora, ciudad, direccion_completa,
                    precio, capacidad, plazas_disponibles,
                    distancia, nivel,
                    que_incluye, que_traer, notas_adicionales
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?,
                    ?, ?, ?
                )
            ");
            $stmt->execute([
                $id_organizador,
                $id_categoria,
                $titulo,
                $descripcion,
                $imagenPath,
                $fecha,
                $hora,
                $ciudad,
                $direccion,
                $precio,
                $capacidad,
                $capacidad,  // plazas_disponibles = capacidad on creation
                $distancia,
                $nivel,
                $incluye,
                $que_traer,
                $notas
            ]);

            $_SESSION["success"] = "Evento : " . $titulo . " creado correctamente";
            header("Location: ../view/crear-evento.php");
            exit();
        } catch (PDOException $e) {
            $this->redirectWithError("../view/crear-evento.php", "Error al crear el evento: " . $e->getMessage());
        }
    }

    private function redirectWithError(string $page, string $message): void
    {
        $_SESSION["error"] = $message;
        header("Location: " . $page);
        exit();
    }

    public function eliminarEvento(): void
    {
        $id_evento = (int)($_POST["id_evento"] ?? 0);
        $id_usuario = $_SESSION["id_usuario"] ?? null;

        if (!$id_usuario) {
            $this->redirectWithError("../view/login.php", "Debes iniciar sesión para realizar esta acción.");
            return;
        }

        $evento = $this->verEvento($id_evento);
        if (!$evento) {
            $this->redirectWithError("../index.php", "Evento no encontrado.");
            return;
            }

        if ($_SESSION['tipo_usuario'] !== 'organizador' || $_SESSION['tipo_usuario'] !== 'admin') {
            $this->redirectWithError("../index.php", "Error, no tiene autorización.");
            return;
        }

        try {
            $stmt = $this->conexion->prepare("UPDATE EVENTOS SET activo = 0 WHERE id_evento = ?");
            $stmt->execute([$id_evento]);

            $_SESSION["success"] = "Evento eliminado correctamente.";
            header("Location: ../index.php");
            exit();
        } catch (PDOException $e) {
            $this->redirectWithError("../view/evento.php?id=" . $id_evento, "Error al eliminar el evento: " . $e->getMessage());
        }
    }

    public function modificarEvento(): void
    {
        $id_evento    = (int)($_POST["id_evento"] ?? 0);
        $titulo       = trim($_POST["titulo"] ?? "");
        $descripcion  = trim($_POST["descripcion"] ?? "");
        $fecha        = $_POST["fecha"] ?? "";
        $hora         = $_POST["hora"] ?? "";
        $ciudad       = trim($_POST["ubicacion"] ?? "");
        $direccion    = trim($_POST["direccion"] ?? "");
        $precio       = $_POST["precio"] ?? 0;
        $capacidad    = $_POST["participantes"] ?? 0;
        $tipo_evento  = $_POST["tipo-evento"] ?? "";
        $incluye      = trim($_POST["incluye"] ?? "");
        $que_traer    = trim($_POST["que-traer"] ?? "");
        $notas        = trim($_POST["notas"] ?? "");

        $distancia    = trim($_POST["distancia"] ?? "");
        $distancia    = $distancia !== "" ? (float)$distancia : null;

        $nivel        = trim($_POST["nivel"] ?? "");
        $nivel        = $nivel !== "" ? $nivel : null;

        $id_organizador = $_SESSION["id_usuario"] ?? null;

        if (!$id_organizador) {
            $this->redirectWithError("../view/login.php", "Debes iniciar sesión como organizador.");
            return;
        }

        $id_categoria = (int)($_POST["tipo-evento"] ?? 1);

        $eventoExistente = $this->verEvento($id_evento);
        if (!$eventoExistente) {
            $this->redirectWithError("../view/perfil.php", "Evento no encontrado.");
            return;
        }

        if ($_SESSION['tipo_usuario'] !== 'organizador' && $_SESSION['tipo_usuario'] !== 'admin') {
            $this->redirectWithError("../view/perfil.php", "Error, no tiene autorización.");
            return;
        }

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../public/img/eventos-photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExtension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('evento_') . '.' . $fileExtension;
            $destination = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destination)) {
                $imagenPath = 'public/img/eventos-photos/' . $fileName;
                try {
                    $stmtImg = $this->conexion->prepare("UPDATE EVENTOS SET imagen = ? WHERE id_evento = ?");
                    $stmtImg->execute([$imagenPath, $id_evento]);
                } catch (PDOException $e) {
                }
            }
        }

        try {
            $stmt = $this->conexion->prepare("
                UPDATE EVENTOS SET
                    id_categoria       = ?,
                    titulo             = ?,
                    descripcion        = ?,
                    fecha              = ?,
                    hora               = ?,
                    ciudad             = ?,
                    direccion_completa = ?,
                    precio             = ?,
                    capacidad          = ?,
                    distancia          = ?,
                    nivel              = ?,
                    que_incluye        = ?,
                    que_traer          = ?,
                    notas_adicionales  = ?
                WHERE id_evento = ?
            ");
            $stmt->execute([
                $id_categoria,
                $titulo,
                $descripcion,
                $fecha,
                $hora,
                $ciudad,
                $direccion,
                $precio,
                $capacidad,
                $distancia,
                $nivel,
                $incluye,
                $que_traer,
                $notas,
                $id_evento
            ]);

            $_SESSION["success"] = "Evento : " . $titulo . " modificado correctamente";
            header("Location: ../view/edit-event.php?id=" . $id_evento);
            exit();
        } catch (PDOException $e) {
            $this->redirectWithError("../view/edit-event.php?id=" . $id_evento, "Error al modificar el evento: " . $e->getMessage());
        }
    }

    public function adminCrearInscripcion(): void {}

    public function adminEliminarInscripcion(): void {}

    public function verEvento(int $idEvento): ?array
    {
        try {
            $stmt = $this->conexion->prepare("
                SELECT e.*, u.nombre_completo AS organizador_nombre, u.foto_perfil AS organizador_foto 
                FROM EVENTOS e
                JOIN USUARIOS u ON e.id_organizador = u.id_usuario
                WHERE e.id_evento = ? AND e.activo = 1
            ");
            $stmt->execute([$idEvento]);
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);
            return $evento ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function listarEventos(): array
    {
        try {
            $stmt = $this->conexion->query("
                SELECT e.id_evento, e.titulo, e.descripcion, e.imagen, e.valoracion_promedio, e.precio,
                       u.nombre_completo AS organizador_nombre, u.foto_perfil AS organizador_foto 
                FROM EVENTOS e
                JOIN USUARIOS u ON e.id_organizador = u.id_usuario
                WHERE e.activo = 1 
                ORDER BY e.fecha DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function listarOrganizadoresDestacados(): array
    {
        try {
            $stmt = $this->conexion->query("
                SELECT u.id_usuario, u.nombre_completo, u.foto_perfil, COUNT(e.id_evento) AS total_eventos
                FROM USUARIOS u
                LEFT JOIN EVENTOS e ON u.id_usuario = e.id_organizador AND e.activo = 1
                WHERE u.tipo_usuario = 'organizador'
                GROUP BY u.id_usuario, u.nombre_completo, u.foto_perfil
                ORDER BY total_eventos DESC, u.nombre_completo ASC
                LIMIT 10
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $eventoController = new EventController();

    if (isset($_POST["action"])) {
        if ($_POST["action"] === "crearEvento") {
            $eventoController->crearEvento();
        } elseif ($_POST["action"] === "modificarEvento") {
            $eventoController->modificarEvento();
        } elseif ($_POST["action"] === "eliminarEvento") {
            $eventoController->eliminarEvento();
        } elseif ($_POST["action"] === "inscribirEvento") {
            $eventoController->inscribir();
        }
    }
}
