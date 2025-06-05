<?php
// app/models/eliminar_cuenta.php

error_reporting(E_ALL); // Mantener para depuración
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/eliminar_cuenta_errors.log'); // Log específico para este script

session_start();

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// ** Obtener el ID del usuario logueado de la sesión. Usar 0 como default para la bitácora si no está logueado. **
// Este es el ID del usuario que REALIZA la acción (la propia cuenta de estudiante).
$loggedInUserId = $_SESSION['ID_Usuario'] ?? 0; // Asumo que el ID de sesión es 'ID_Usuario'

// --- Función para generar una respuesta JSON estandarizada, bitacorar y terminar la ejecución ---
// Se modifica para incluir los parámetros de la bitácora
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($success, $message, $error = null, $redirect = null, $con = null, $loggedInUserId = 0, $objetoId = 0, $tipoObjeto = 'sistema', $evento = 'ERROR_DESCONOCIDO', $datosAnterior = NULL, $datosNuevo = NULL)
    {
        // Solo bitacorar si se proporciona una conexión.
        if ($con) {
            // Asegurarse de que el mensaje de bitácora sea conciso pero informativo
            $bitacora_message = $message;
            if ($error) {
                $bitacora_message .= " - Error: " . $error;
            }
            if ($datosNuevo) {
                $bitacora_message .= " - Datos Nuevos: " . (is_array($datosNuevo) ? json_encode($datosNuevo) : $datosNuevo);
            }
            if ($datosAnterior) {
                $bitacora_message .= " - Datos Anteriores: " . (is_array($datosAnterior) ? json_encode($datosAnterior) : $datosAnterior);
            }
            registrarEventoBitacora($con, $objetoId, $tipoObjeto, $evento, $loggedInUserId, $datosAnterior, $bitacora_message);
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'msg' => $message,
            'error' => $error,
            'redirect' => $redirect
        ]);
        exit();
    }
}

// Verificar que la conexión a la BD sea válida al inicio del script.
if (!isset($con) || !is_object($con) || mysqli_connect_errno()) {
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida en eliminar_cuenta.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => 'Error interno del servidor: La conexión a la base de datos no está disponible.', 'error' => mysqli_connect_error()]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($loggedInUserId === 0) {
            sendJsonResponse(false, 'Usuario no autenticado. Por favor, inicie sesión nuevamente para eliminar su cuenta.', null, null, $con, 0, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', NULL, 'Intento de eliminar cuenta sin sesión activa.');
        }
        $idUsuario = $loggedInUserId; // El usuario logueado es quien elimina su propia cuenta

        $confirmDeletePassword = $_POST['confirmDeletePassword'] ?? '';

        if (empty($confirmDeletePassword)) {
            sendJsonResponse(false, 'Por favor, introduce tu contraseña para confirmar la eliminación.', null, null, $con, $idUsuario, $idUsuario, 'contrasena', 'VALIDACION_FALLIDA', NULL, 'Contraseña de confirmación vacía al eliminar cuenta.');
        }

        // --- Obtener datos 'antes' para la bitácora antes de verificar la contraseña ---
        $datos_usuario_antes = null;
        $datos_contrasena_antes = null;
        $datos_perfil_estudiante_antes = null;
        $datos_cv_estudiante_antes = null;
        $datos_contactos_estudiantes_antes = null;
        $datos_experiencias_laborales_estudiantes_antes = null;

        // Obtener datos del usuario
        $stmt_get_user_data = $con->prepare("SELECT Nombre, Correo_Electronico, Tipo, estado_us FROM usuario WHERE ID_Usuario = ? LIMIT 1");
        if ($stmt_get_user_data) {
            $stmt_get_user_data->bind_param('i', $idUsuario);
            $stmt_get_user_data->execute();
            $result_user_data = $stmt_get_user_data->get_result();
            if ($row_user_data = $result_user_data->fetch_assoc()) {
                $datos_usuario_antes = json_encode($row_user_data);
            }
            $stmt_get_user_data->close();
        } else {
            error_log("Error al preparar SELECT usuario para bitácora: " . $con->error);
        }

        // Obtener el hash actual de la contraseña del usuario para verificación
        $stmt_password = $con->prepare("SELECT Contrasena_Hash FROM contrasena WHERE ID_Usuario = ?"); // Asumo que la tabla es 'contrasena' (singular)
        if (!$stmt_password) {
            throw new Exception('Error al preparar verificación de contraseña para eliminar cuenta: ' . $con->error);
        }
        $stmt_password->bind_param('i', $idUsuario);
        $stmt_password->execute();
        $result_password = $stmt_password->get_result();
        $user_password_data = $result_password->fetch_assoc();
        $stmt_password->close();

        if (!$user_password_data || !password_verify($confirmDeletePassword, $user_password_data['Contrasena_Hash'])) {
            sendJsonResponse(false, 'Contraseña incorrecta.', 'La contraseña introducida no coincide con su contraseña actual.', null, $con, $idUsuario, $idUsuario, 'contrasena', 'INTENTO_FALLIDO', NULL, 'Contraseña incorrecta al eliminar cuenta.');
        }
        $datos_contrasena_antes = json_encode(['Contrasena_Hash' => '*****']); // No guardar el hash real, solo indicación

        // Iniciar transacción
        $con->begin_transaction();

        // --- IMPORTANTE: Obtener el ID_Perfil_Estudiante y datos de tablas relacionadas antes de borrar ---
        $idPerfilEstudiante = null;
        $stmt_get_perfil = $con->prepare("SELECT ID_Perfil_Estudiante, Carrera, Fecha_Nacimiento, Genero, Experiencia_Laboral, Foto_Perfil FROM perfil_estudiante WHERE ID_Usuario = ?");
        if ($stmt_get_perfil) {
            $stmt_get_perfil->bind_param('i', $idUsuario);
            $stmt_get_perfil->execute();
            $result_perfil = $stmt_get_perfil->get_result();
            if ($row_perfil = $result_perfil->fetch_assoc()) {
                $idPerfilEstudiante = $row_perfil['ID_Perfil_Estudiante'];
                $datos_perfil_estudiante_antes = json_encode($row_perfil);
            }
            $stmt_get_perfil->close();
        } else {
            error_log("Error al preparar SELECT perfil_estudiante para bitácora: " . $con->error);
        }

        // Verificar si existe un perfil para este usuario
        if (is_null($idPerfilEstudiante)) {
            error_log("No se encontró ID_Perfil_Estudiante para el usuario ID: " . $idUsuario . ". Procediendo con eliminación de contraseña y usuario.");
            // Si el usuario no tiene perfil, se omiten las eliminaciones de tablas dependientes de perfil_estudiante.
            // Bitacorar que no se encontró perfil para eliminar
            registrarEventoBitacora($con, $idUsuario, 'perfil_estudiante', 'NO_ENCONTRADO', $idUsuario, NULL, 'No se encontró perfil de estudiante asociado al usuario. Solo se eliminarán usuario y contraseña.');
        } else {
            // Obtener datos de cv_estudiante
            $stmt_get_cv = $con->prepare("SELECT ID_CV, URL_CV FROM cv_estudiante WHERE perfil_estudiante_ID_Perfil_Estudiante = ?");
            if ($stmt_get_cv) {
                $stmt_get_cv->bind_param('i', $idPerfilEstudiante);
                $stmt_get_cv->execute();
                $result_cv = $stmt_get_cv->get_result();
                $cv_data = [];
                while ($row_cv = $result_cv->fetch_assoc()) {
                    $cv_data[] = $row_cv;
                }
                $datos_cv_estudiante_antes = json_encode($cv_data);
                $stmt_get_cv->close();
            } else {
                error_log("Error al preparar SELECT cv_estudiante para bitácora: " . $con->error);
            }

            // Obtener datos de contactos_estudiantes
            $stmt_get_contactos = $con->prepare("SELECT ID_Contacto, Telefono, Direccion_Contacto, Red_Social FROM contactos_estudiantes WHERE ID_Perfil_Estudiante = ?");
            if ($stmt_get_contactos) {
                $stmt_get_contactos->bind_param('i', $idPerfilEstudiante);
                $stmt_get_contactos->execute();
                $result_contactos = $stmt_get_contactos->get_result();
                $contactos_data = [];
                while ($row_contacto = $result_contactos->fetch_assoc()) {
                    $contactos_data[] = $row_contacto;
                }
                $datos_contactos_estudiantes_antes = json_encode($contactos_data);
                $stmt_get_contactos->close();
            } else {
                error_log("Error al preparar SELECT contactos_estudiantes para bitácora: " . $con->error);
            }

            // Obtener datos de experiencias_laborales_estudiantes
            $stmt_get_experiencias = $con->prepare("SELECT ID_Experiencia, Puesto, Empresa, Fecha_Inicio, Fecha_Fin, Descripcion_Funciones FROM experiencias_laborales_estudiantes WHERE ID_Perfil_Estudiante = ?");
            if ($stmt_get_experiencias) {
                $stmt_get_experiencias->bind_param('i', $idPerfilEstudiante);
                $stmt_get_experiencias->execute();
                $result_experiencias = $stmt_get_experiencias->get_result();
                $experiencias_data = [];
                while ($row_experiencia = $result_experiencias->fetch_assoc()) {
                    $experiencias_data[] = $row_experiencia;
                }
                $datos_experiencias_laborales_estudiantes_antes = json_encode($experiencias_data);
                $stmt_get_experiencias->close();
            } else {
                error_log("Error al preparar SELECT experiencias_laborales_estudiantes para bitácora: " . $con->error);
            }

            // --- Realizar eliminaciones de tablas dependientes del perfil de estudiante ---
            // 1. Eliminar datos de cv_estudiante
            $stmt_delete_cv = $con->prepare("DELETE FROM cv_estudiante WHERE perfil_estudiante_ID_Perfil_Estudiante = ?");
            if (!$stmt_delete_cv) {
                throw new Exception('Error al preparar eliminación de CV: ' . $con->error);
            }
            $stmt_delete_cv->bind_param('i', $idPerfilEstudiante);
            if (!$stmt_delete_cv->execute()) {
                throw new Exception('Error al eliminar CV de estudiante: ' . $stmt_delete_cv->error);
            }
            $filas_cv_eliminadas = $stmt_delete_cv->affected_rows;
            $stmt_delete_cv->close();
            registrarEventoBitacora($con, $idUsuario, 'cv_estudiante', 'DELETE', $idUsuario, $datos_cv_estudiante_antes, 'CVs eliminados: ' . $filas_cv_eliminadas);
            error_log("Eliminado CV para usuario ID: " . $idUsuario);

            // 2. Eliminar datos de contactos_estudiantes
            $stmt_delete_contactos = $con->prepare("DELETE FROM contactos_estudiantes WHERE ID_Perfil_Estudiante = ?");
            if (!$stmt_delete_contactos) {
                throw new Exception('Error al preparar eliminación de contactos de estudiantes: ' . $con->error);
            }
            $stmt_delete_contactos->bind_param('i', $idPerfilEstudiante);
            if (!$stmt_delete_contactos->execute()) {
                throw new Exception('Error al eliminar contactos de estudiante: ' . $stmt_delete_contactos->error);
            }
            $filas_contactos_eliminados = $stmt_delete_contactos->affected_rows;
            $stmt_delete_contactos->close();
            registrarEventoBitacora($con, $idUsuario, 'contactos_estudiantes', 'DELETE', $idUsuario, $datos_contactos_estudiantes_antes, 'Contactos eliminados: ' . $filas_contactos_eliminados);
            error_log("Eliminado contactos de estudiante para usuario ID: " . $idUsuario);

            // 3. Eliminar datos de experiencias_laborales_estudiantes
            $stmt_delete_experiencias = $con->prepare("DELETE FROM experiencias_laborales_estudiantes WHERE ID_Perfil_Estudiante = ?");
            if (!$stmt_delete_experiencias) {
                throw new Exception('Error al preparar eliminación de experiencias laborales: ' . $con->error);
            }
            $stmt_delete_experiencias->bind_param('i', $idPerfilEstudiante);
            if (!$stmt_delete_experiencias->execute()) {
                throw new Exception('Error al eliminar experiencias laborales de estudiante: ' . $stmt_delete_experiencias->error);
            }
            $filas_experiencias_eliminadas = $stmt_delete_experiencias->affected_rows;
            $stmt_delete_experiencias->close();
            registrarEventoBitacora($con, $idUsuario, 'experiencias_laborales_estudiantes', 'DELETE', $idUsuario, $datos_experiencias_laborales_estudiantes_antes, 'Experiencias laborales eliminadas: ' . $filas_experiencias_eliminadas);
            error_log("Eliminado experiencias laborales para usuario ID: " . $idUsuario);

            // 4. Eliminar datos de perfil_estudiante (ahora que sus hijos han sido eliminados)
            $stmt_delete_perfil = $con->prepare("DELETE FROM perfil_estudiante WHERE ID_Usuario = ?");
            if (!$stmt_delete_perfil) {
                throw new Exception('Error al preparar eliminación de perfil de estudiante: ' . $con->error);
            }
            $stmt_delete_perfil->bind_param('i', $idUsuario);
            if (!$stmt_delete_perfil->execute()) {
                throw new Exception('Error al eliminar perfil de estudiante: ' . $stmt_delete_perfil->error);
            }
            $filas_perfil_eliminadas = $stmt_delete_perfil->affected_rows;
            $stmt_delete_perfil->close();
            registrarEventoBitacora($con, $idUsuario, 'perfil_estudiante', 'DELETE', $idUsuario, $datos_perfil_estudiante_antes, 'Perfil de estudiante eliminado: ' . $filas_perfil_eliminadas);
            error_log("Eliminado perfil de estudiante para usuario ID: " . $idUsuario);
        }

        // 5. Eliminar la contraseña (hijo de usuario)
        $stmt_delete_pass = $con->prepare("DELETE FROM contrasena WHERE ID_Usuario = ?");
        if (!$stmt_delete_pass) {
            throw new Exception('Error al preparar eliminación de contraseña: ' . $con->error);
        }
        $stmt_delete_pass->bind_param('i', $idUsuario);
        if (!$stmt_delete_pass->execute()) {
            throw new Exception('Error al eliminar contraseña: ' . $stmt_delete_pass->error);
        }
        $filas_pass_eliminadas = $stmt_delete_pass->affected_rows;
        $stmt_delete_pass->close();
        registrarEventoBitacora($con, $idUsuario, 'contrasena', 'DELETE', $idUsuario, $datos_contrasena_antes, 'Contraseña eliminada: ' . $filas_pass_eliminadas);
        error_log("Eliminado contraseña para usuario ID: " . $idUsuario);

        // 6. Finalmente, eliminar el usuario de la tabla `usuario`
        $stmt_delete_user = $con->prepare("DELETE FROM usuario WHERE ID_Usuario = ?");
        if (!$stmt_delete_user) {
            throw new Exception('Error al preparar eliminación de usuario: ' . $con->error);
        }
        $stmt_delete_user->bind_param('i', $idUsuario);
        if (!$stmt_delete_user->execute()) {
            throw new Exception('Error al eliminar usuario: ' . $stmt_delete_user->error);
        }
        $filas_user_eliminadas = $stmt_delete_user->affected_rows;
        $stmt_delete_user->close();
        registrarEventoBitacora($con, $idUsuario, 'usuario', 'DELETE', $idUsuario, $datos_usuario_antes, 'Usuario eliminado: ' . $filas_user_eliminadas);
        error_log("Eliminado usuario ID: " . $idUsuario);

        // Si todo fue exitoso, confirmar la transacción
        $con->commit();

        // Destruir la sesión después de la eliminación exitosa
        session_unset();
        session_destroy();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        sendJsonResponse(
            true,
            'Tu cuenta y todos los datos asociados han sido eliminados exitosamente.',
            null,
            '../auth/login.php',
            $con,
            $idUsuario,
            $idUsuario,
            'cuenta_estudiante',
            'DELETE_COMPLETO',
            ['usuario' => $datos_usuario_antes, 'contrasena' => $datos_contrasena_antes, 'perfil' => $datos_perfil_estudiante_antes, 'cv' => $datos_cv_estudiante_antes, 'contactos' => $datos_contactos_estudiantes_antes, 'experiencias' => $datos_experiencias_laborales_estudiantes_antes],
            'Eliminación completa de cuenta de estudiante.'
        );

    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        if (isset($con)) {
            $con->rollback();
            // Restaurar el modo autocommit en caso de rollback
            $con->autocommit(true);
        }
        $log_message = "Error en eliminar_cuenta.php (ID_Usuario: " . ($idUsuario ?? 'N/A') . "): " . $e->getMessage();
        error_log($log_message);
        sendJsonResponse(false, 'Error al procesar la solicitud para eliminar la cuenta.', $e->getMessage(), null, $con, $idUsuario ?? 0, $idUsuario ?? 0, 'cuenta_estudiante', 'ERROR_SISTEMA', NULL, $log_message);
    } finally {
        if (isset($con)) {
            $con->close();
        }
    }
} else {
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.', null, $con, $loggedInUserId, 0, 'sistema', 'METODO_NO_PERMITIDO', NULL, 'Acceso no POST a eliminar_cuenta.php');
}
?>