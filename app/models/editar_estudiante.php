<?php
// app/models/actualizar_estudiante.php (o el nombre que tenga este script)

// Muestra todos los errores y advertencias para depuración (SOLO EN DESARROLLO)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/actualizar_estudiante_errors.log'); // Log específico para este script

session_start(); // Iniciar sesión si no está iniciada (necesario para $_SESSION['ID_Usuario'])

// Incluir la conexión a la base de datos y el helper de bitácora
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// ** Obtener el ID del usuario logueado de la sesión. Usar 0 como default para la bitácora si no está logueado. **
// Este es el ID del usuario que REALIZA la acción (un administrador, por ejemplo).
$loggedInUserId = $_SESSION['ID_Usuario'] ?? 0;

// --- Función para generar una respuesta JSON estandarizada, bitacorar y terminar la ejecución ---
// Se define aquí para ser usada en este script.
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($success, $message, $error = null, $con = null, $loggedInUserId = 0, $objetoId = 0, $tipoObjeto = 'sistema', $evento = 'ERROR_DESCONOCIDO', $datosAnterior = NULL, $datosNuevo = NULL)
    {
        // Solo bitacorar si se proporciona una conexión y no es un éxito (o si es un éxito y se requiere registrar)
        if ($con) {
            // Asegurarse de que el mensaje de bitácora sea conciso pero informativo
            $bitacora_message = $message;
            if ($error) {
                $bitacora_message .= " - Error: " . $error;
            }
            if ($datosNuevo) {
                $bitacora_message .= " - Datos: " . (is_array($datosNuevo) ? json_encode($datosNuevo) : $datosNuevo);
            }
            if ($datosAnterior) {
                $bitacora_message .= " - Anterior: " . (is_array($datosAnterior) ? json_encode($datosAnterior) : $datosAnterior);
            }
            registrarEventoBitacora($con, $objetoId, $tipoObjeto, $evento, $loggedInUserId, $datosAnterior, $bitacora_message);
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'msg' => $message,
            'error' => $error
        ]);
        exit();
    }
}

// Verificar que la conexión a la BD sea válida al inicio del script.
// Si no hay conexión, loguear un error fatal y salir, sin intentar bitacorar en DB.
if (!isset($con) || !is_object($con) || mysqli_connect_errno()) {
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida en actualizar_estudiante.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg); // Loggear en el archivo de error configurado
    // No podemos usar sendJsonResponse con bitácora porque la conexión no existe
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => 'Error interno del servidor: La conexión a la base de datos no está disponible.', 'error' => mysqli_connect_error()]);
    exit(); // Terminar la ejecución
}

// Establecer el Content-Type para todas las respuestas JSON tan pronto como sea posible
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar autenticación del usuario que realiza la acción (ej. administrador)
    if ($loggedInUserId === 0) {
        sendJsonResponse(false, 'Usuario no autenticado para realizar esta acción.', 'Por favor, inicie sesión.', $con, 0, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', NULL, 'Intento de actualizar estudiante sin sesión activa.');
    }

    // El ID_Usuario es crucial para saber qué registros actualizar
    $idUsuarioAfectado = $_POST['editar_id_usuario'] ?? null; // ID del estudiante a actualizar

    // Datos para la tabla 'usuario'
    $nombreUsuario = $_POST['editar_nombre_estudiante'] ?? '';
    $correoElectronico = $_POST['editar_correo_esudiante'] ?? ''; // Mantengo el typo 'esudiante' si lo usas
    $estadoUsuario = $_POST['editar_estado_estudiante'] ?? '';

    // Datos para la tabla 'perfil_estudiante'
    $carrera = $_POST['editar_carrera'] ?? '';
    $fechaNacimiento = $_POST['editar_fecha_nacimiento'] ?? null;
    $genero = $_POST['editar_genero'] ?? null;
    $experienciaLaboral = $_POST['editar_experiencia_laboral'] ?? null;
    $fotoPerfil = $_POST['editar_foto_perfil'] ?? null; // Esto sería una ruta de archivo si ya está subida.

    // --- Validaciones de Entrada ---
    if (empty($idUsuarioAfectado) || !is_numeric($idUsuarioAfectado)) {
        sendJsonResponse(false, 'Error: ID de Usuario del estudiante inválido.', null, $con, $loggedInUserId, 0, 'usuario', 'VALIDACION_FALLIDA', NULL, 'ID de usuario afectado inválido: ' . ($idUsuarioAfectado ?? 'NULL'));
    }
    $idUsuarioAfectado = (int) $idUsuarioAfectado; // Castear a entero para seguridad

    if (empty($nombreUsuario)) {
        sendJsonResponse(false, 'Error: El nombre del Estudiante es requerido.', null, $con, $loggedInUserId, $idUsuarioAfectado, 'usuario', 'VALIDACION_FALLIDA', NULL, 'Nombre de estudiante vacío.');
    }
    if (empty($correoElectronico) || !filter_var($correoElectronico, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Error: El correo electrónico no es válido.', null, $con, $loggedInUserId, $idUsuarioAfectado, 'usuario', 'VALIDACION_FALLIDA', NULL, 'Correo electrónico inválido: ' . $correoElectronico);
    }
    // Validar estado del usuario (asumiendo valores esperados, ej. 'activo', 'inactivo', etc.)
    $allowed_user_states = ['Activo', 'Inactivo', 'Pendiente']; // Ajusta según tu BD
    if (!in_array($estadoUsuario, $allowed_user_states)) {
        sendJsonResponse(false, 'Error: El estado del usuario no es válido.', null, $con, $loggedInUserId, $idUsuarioAfectado, 'usuario', 'VALIDACION_FALLIDA', NULL, 'Estado de usuario inválido: ' . $estadoUsuario);
    }

    if (empty($carrera)) {
        sendJsonResponse(false, 'Error: La carrera es requerida.', null, $con, $loggedInUserId, $idUsuarioAfectado, 'perfil_estudiante', 'VALIDACION_FALLIDA', NULL, 'Carrera vacía.');
    }
    // Validar fecha de nacimiento
    if (!empty($fechaNacimiento) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fechaNacimiento)) {
        sendJsonResponse(false, 'Error: Formato de fecha de nacimiento inválido (YYYY-MM-DD).', null, $con, $loggedInUserId, $idUsuarioAfectado, 'perfil_estudiante', 'VALIDACION_FALLIDA', NULL, 'Fecha de nacimiento con formato incorrecto: ' . $fechaNacimiento);
    }
    // Validar género (asumiendo 'Masculino', 'Femenino', 'Otro')
    $allowed_genders = ['Masculino', 'Femenino', 'Otro', 'No especificado']; // Ajusta según tu BD
    if (!empty($genero) && !in_array($genero, $allowed_genders)) {
        sendJsonResponse(false, 'Error: El género proporcionado no es válido.', null, $con, $loggedInUserId, $idUsuarioAfectado, 'perfil_estudiante', 'VALIDACION_FALLIDA', NULL, 'Género inválido: ' . $genero);
    }
    // Para experiencia laboral y foto de perfil, las validaciones pueden ser más complejas (ej. longitud max, tipo de archivo)
    // Por ahora, solo nos aseguramos de que no sean NULL en la BD si deben serlo.

    // --- Iniciar Transacción ---
    $con->begin_transaction();

    try {
        // --- Obtener datos 'antes' para la bitácora ---
        $datos_usuario_anterior = null;
        $datos_perfil_anterior = null;

        $stmt_get_usuario = $con->prepare("SELECT Nombre, Correo_Electronico, estado_us FROM usuario WHERE ID_Usuario = ? LIMIT 1");
        if ($stmt_get_usuario) {
            $stmt_get_usuario->bind_param("i", $idUsuarioAfectado);
            $stmt_get_usuario->execute();
            $result_usuario = $stmt_get_usuario->get_result();
            if ($row_usuario = $result_usuario->fetch_assoc()) {
                $datos_usuario_anterior = json_encode($row_usuario);
            }
            $stmt_get_usuario->close();
        }

        $stmt_get_perfil = $con->prepare("SELECT Carrera, Fecha_Nacimiento, Genero, Experiencia_Laboral, Foto_Perfil FROM perfil_estudiante WHERE ID_Usuario = ? LIMIT 1");
        if ($stmt_get_perfil) {
            $stmt_get_perfil->bind_param("i", $idUsuarioAfectado);
            $stmt_get_perfil->execute();
            $result_perfil = $stmt_get_perfil->get_result();
            if ($row_perfil = $result_perfil->fetch_assoc()) {
                $datos_perfil_anterior = json_encode($row_perfil);
            }
            $stmt_get_perfil->close();
        }

        // Si el usuario o perfil no existe, lanzar error.
        if (is_null($datos_usuario_anterior)) {
            $con->rollback();
            sendJsonResponse(false, 'Error: Usuario no encontrado.', null, $con, $loggedInUserId, $idUsuarioAfectado, 'usuario', 'NO_ENCONTRADO', NULL, 'Intento de actualizar usuario inexistente. ID: ' . $idUsuarioAfectado);
        }
        // Nota: Un perfil podría no existir para un usuario recién creado. Considera si debe ser un error o si se debe crear el perfil.
        // Para este script de "actualizar", asumimos que ya existe.

        // 1. Actualizar la tabla 'usuario'
        $stmt_usuario = $con->prepare("UPDATE usuario SET Nombre = ?, Correo_Electronico = ?, estado_us = ? WHERE ID_Usuario = ?");
        if ($stmt_usuario === false) {
            throw new Exception("Error al preparar la consulta de actualización de usuario: " . $con->error);
        }
        $stmt_usuario->bind_param("sssi", $nombreUsuario, $correoElectronico, $estadoUsuario, $idUsuarioAfectado);

        if (!$stmt_usuario->execute()) {
            throw new Exception("Error al actualizar el usuario: " . $stmt_usuario->error);
        }
        $stmt_usuario->close();

        // 2. Actualizar la tabla 'perfil_estudiante'
        // Es importante verificar si el perfil_estudiante existe para este ID_Usuario.
        // Si no existe, deberíamos insertarlo en lugar de actualizarlo.
        $perfil_existe = false;
        $stmt_check_perfil = $con->prepare("SELECT ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = ? LIMIT 1");
        $stmt_check_perfil->bind_param("i", $idUsuarioAfectado);
        $stmt_check_perfil->execute();
        $stmt_check_perfil->store_result();
        if ($stmt_check_perfil->num_rows > 0) {
            $perfil_existe = true;
        }
        $stmt_check_perfil->close();

        if ($perfil_existe) {
            $stmt_perfil = $con->prepare("UPDATE perfil_estudiante SET Carrera = ?, Fecha_Nacimiento = ?, Genero = ?, Experiencia_Laboral = ?, Foto_Perfil = ? WHERE ID_Usuario = ?");
            if ($stmt_perfil === false) {
                throw new Exception("Error al preparar la consulta de actualización de perfil_estudiante: " . $con->error);
            }
            // 'sssssi' - sssss para los 5 campos (STRING), i para ID_Usuario (INT)
            $stmt_perfil->bind_param("sssssi", $carrera, $fechaNacimiento, $genero, $experienciaLaboral, $fotoPerfil, $idUsuarioAfectado);

            if (!$stmt_perfil->execute()) {
                throw new Exception("Error al actualizar el perfil del estudiante: " . $stmt_perfil->error);
            }
            $stmt_perfil->close();
        } else {
            // Si el perfil no existe, insertarlo (o manejar como error si siempre debe existir)
            $stmt_insert_perfil = $con->prepare("INSERT INTO perfil_estudiante (ID_Usuario, Carrera, Fecha_Nacimiento, Genero, Experiencia_Laboral, Foto_Perfil) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt_insert_perfil === false) {
                throw new Exception("Error al preparar la consulta de inserción de perfil_estudiante: " . $con->error);
            }
            $stmt_insert_perfil->bind_param("isssss", $idUsuarioAfectado, $carrera, $fechaNacimiento, $genero, $experienciaLaboral, $fotoPerfil);

            if (!$stmt_insert_perfil->execute()) {
                throw new Exception("Error al insertar el perfil del estudiante: " . $stmt_insert_perfil->error);
            }
            $stmt_insert_perfil->close();
            // Bitácora para la creación del perfil si no existía
            $datos_perfil_nuevo_creado = json_encode([
                'Carrera' => $carrera,
                'Fecha_Nacimiento' => $fechaNacimiento,
                'Genero' => $genero,
                'Experiencia_Laboral' => $experienciaLaboral,
                'Foto_Perfil' => $fotoPerfil
            ]);
            registrarEventoBitacora($con, $idUsuarioAfectado, 'perfil_estudiante', 'CREATE', $loggedInUserId, NULL, $datos_perfil_nuevo_creado);
        }

        // --- Obtener datos 'después' para la bitácora ---
        $datos_usuario_actualizado = null;
        $datos_perfil_actualizado = null;

        $stmt_get_usuario_after = $con->prepare("SELECT Nombre, Correo_Electronico, estado_us FROM usuario WHERE ID_Usuario = ? LIMIT 1");
        if ($stmt_get_usuario_after) {
            $stmt_get_usuario_after->bind_param("i", $idUsuarioAfectado);
            $stmt_get_usuario_after->execute();
            $result_usuario_after = $stmt_get_usuario_after->get_result();
            if ($row_usuario_after = $result_usuario_after->fetch_assoc()) {
                $datos_usuario_actualizado = json_encode($row_usuario_after);
            }
            $stmt_get_usuario_after->close();
        }

        $stmt_get_perfil_after = $con->prepare("SELECT Carrera, Fecha_Nacimiento, Genero, Experiencia_Laboral, Foto_Perfil FROM perfil_estudiante WHERE ID_Usuario = ? LIMIT 1");
        if ($stmt_get_perfil_after) {
            $stmt_get_perfil_after->bind_param("i", $idUsuarioAfectado);
            $stmt_get_perfil_after->execute();
            $result_perfil_after = $stmt_get_perfil_after->get_result();
            if ($row_perfil_after = $result_perfil_after->fetch_assoc()) {
                $datos_perfil_actualizado = json_encode($row_perfil_after);
            }
            $stmt_get_perfil_after->close();
        }

        // Si ambas actualizaciones fueron exitosas, confirmar la transacción
        $con->commit();

        // Bitácora de la actualización exitosa
        $mensaje_bitacora_usuario = "Usuario: " . ($datos_usuario_anterior ? $datos_usuario_anterior : 'N/A') . " -> " . ($datos_usuario_actualizado ? $datos_usuario_actualizado : 'N/A');
        $mensaje_bitacora_perfil = "Perfil: " . ($datos_perfil_anterior ? $datos_perfil_anterior : 'N/A') . " -> " . ($datos_perfil_actualizado ? $datos_perfil_actualizado : 'N/A');

        sendJsonResponse(
            true,
            'Estudiante actualizado exitosamente.',
            null,
            $con,
            $loggedInUserId,
            $idUsuarioAfectado,
            'estudiante',
            'UPDATE',
            ['usuario_antes' => $datos_usuario_anterior, 'perfil_antes' => $datos_perfil_anterior],
            ['usuario_despues' => $datos_usuario_actualizado, 'perfil_despues' => $datos_perfil_actualizado]
        );

    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $con->rollback();
        $log_message = 'Excepción en actualizar_estudiante.php (Usuario que realiza la acción: ' . $loggedInUserId . ', Usuario afectado: ' . $idUsuarioAfectado . '): ' . $e->getMessage();
        error_log($log_message); // Loguear el error real
        sendJsonResponse(false, 'Error en la operación de actualización: ' . $e->getMessage(), $e->getMessage(), $con, $loggedInUserId, $idUsuarioAfectado, 'estudiante', 'ERROR_SISTEMA', NULL, $log_message);
    } finally {
        if (isset($con) && $con instanceof mysqli) {
            $con->close();
        }
    }

} else {
    sendJsonResponse(false, 'Acceso no permitido. Este script solo acepta solicitudes POST.', null, $con, $loggedInUserId, 0, 'sistema', 'METODO_NO_PERMITIDO', NULL, 'Intento de acceso con método no POST.');
}
?>