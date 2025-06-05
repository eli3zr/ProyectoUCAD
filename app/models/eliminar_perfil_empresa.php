<?php
// app/models/eliminar_perfil_empresa.php

// Muestra todos los errores y advertencias para depuración (SOLO EN DESARROLLO)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/eliminar_perfil_empresa_errors.log'); // Log específico para este script

session_start(); // Iniciar sesión si no está iniciada

// Incluir la conexión a la base de datos y el helper de bitácora
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// ** Obtener el ID del usuario logueado de la sesión. Usar 0 como default para la bitácora si no está logueado. **
// Este es el ID del usuario que REALIZA la acción (la propia empresa logueada).
$loggedInUserId = $_SESSION['ID_Usuario'] ?? 0;
$idPerfilEmpresa = $_SESSION['ID_Perfil_Empresa'] ?? 0; // Obtener ID de perfil de empresa de la sesión

// --- Función para generar una respuesta JSON estandarizada, bitacorar y terminar la ejecución ---
// Se define aquí para ser usada en este script.
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($success, $message, $error = null, $con = null, $loggedInUserId = 0, $objetoId = 0, $tipoObjeto = 'sistema', $evento = 'ERROR_DESCONOCIDO', $datosAnterior = NULL, $datosNuevo = NULL)
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
            'error' => $error
        ]);
        exit();
    }
}

// Verificar que la conexión a la BD sea válida al inicio del script.
// Si no hay conexión, loguear un error fatal y salir, sin intentar bitacorar en DB.
if (!isset($con) || !is_object($con) || mysqli_connect_errno()) {
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida en eliminar_perfil_empresa.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg); // Loggear en el archivo de error configurado
    // No podemos usar sendJsonResponse con bitácora porque la conexión no existe
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => 'Error interno del servidor: La conexión a la base de datos no está disponible.', 'error' => mysqli_connect_error()]);
    exit(); // Terminar la ejecución
}

// Establecer el Content-Type para todas las respuestas JSON tan pronto como sea posible
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar autenticación del usuario y la existencia del perfil de empresa en sesión
    if ($loggedInUserId === 0 || $idPerfilEmpresa === 0) {
        sendJsonResponse(false, 'Sesión inválida. Por favor, inicie sesión con su cuenta de empresa.', 'ID de Usuario o Perfil de Empresa requeridos en sesión.', $con, 0, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', NULL, 'Intento de eliminar perfil de empresa sin sesión válida.');
    }

    // Capturar la contraseña de confirmación enviada desde el formulario
    $confirmPassword = $_POST['deletePassword'] ?? '';

    // --- Validación de la contraseña de confirmación ---
    if (empty($confirmPassword)) {
        sendJsonResponse(false, 'Debe introducir su contraseña para confirmar la eliminación.', null, $con, $loggedInUserId, $loggedInUserId, 'contrasena', 'VALIDACION_FALLIDA', NULL, 'Contraseña de confirmación vacía al eliminar perfil empresa.');
    }

    // Iniciar una transacción para asegurar que todas las operaciones de la BD sean atómicas
    mysqli_begin_transaction($con);

    try {
        // --- PASO 1: Obtener datos ANTES de la eliminación para la bitácora ---
        $datos_usuario_antes = null;
        $datos_contrasena_antes = null;
        $datos_perfil_empresa_antes = null;
        $datos_ofertas_antes = null;

        // Obtener datos del usuario (Nombre, Correo, Tipo)
        $stmt_get_usuario = $con->prepare("SELECT Nombre, Correo_Electronico, Tipo FROM usuario WHERE ID_Usuario = ? LIMIT 1");
        if ($stmt_get_usuario) {
            $stmt_get_usuario->bind_param("i", $loggedInUserId);
            $stmt_get_usuario->execute();
            $result_usuario = $stmt_get_usuario->get_result();
            if ($row_usuario = $result_usuario->fetch_assoc()) {
                $datos_usuario_antes = json_encode($row_usuario);
                // Validar que el usuario sea realmente una empresa si es necesario
                if ($row_usuario['Tipo'] !== 'empresa') {
                    mysqli_rollback($con);
                    sendJsonResponse(false, 'Acceso denegado. Este perfil no es de empresa.', null, $con, $loggedInUserId, $loggedInUserId, 'usuario', 'ACCESO_NO_AUTORIZADO', NULL, 'Usuario no tipo empresa intentó eliminar perfil empresa. Tipo: ' . $row_usuario['Tipo']);
                }
            } else {
                mysqli_rollback($con);
                sendJsonResponse(false, 'Usuario no encontrado en la base de datos.', null, $con, $loggedInUserId, $loggedInUserId, 'usuario', 'NO_ENCONTRADO', NULL, 'Usuario ID: ' . $loggedInUserId . ' no encontrado en DB al eliminar perfil empresa.');
            }
            $stmt_get_usuario->close();
        } else {
            throw new Exception("Error al preparar SELECT usuario para bitácora: " . $con->error);
        }

        // Obtener hash de contraseña
        $stmt_get_contrasena = $con->prepare("SELECT Contrasena_Hash FROM contrasenas WHERE ID_Usuario = ? LIMIT 1");
        if ($stmt_get_contrasena) {
            $stmt_get_contrasena->bind_param("i", $loggedInUserId);
            $stmt_get_contrasena->execute();
            $result_contrasena = $stmt_get_contrasena->get_result();
            if ($row_contrasena = $result_contrasena->fetch_assoc()) {
                $datos_contrasena_antes = json_encode($row_contrasena);
                $hashedPasswordFromDB = $row_contrasena['Contrasena_Hash'];
            } else {
                mysqli_rollback($con);
                sendJsonResponse(false, 'No se encontró la contraseña del usuario en la base de datos.', null, $con, $loggedInUserId, $loggedInUserId, 'contrasena', 'NO_ENCONTRADO', NULL, 'Contraseña de usuario ID: ' . $loggedInUserId . ' no encontrada.');
            }
            $stmt_get_contrasena->close();
        } else {
            throw new Exception("Error al preparar SELECT contrasenas para bitácora: " . $con->error);
        }

        // Verificar la contraseña de confirmación
        if (!password_verify($confirmPassword, $hashedPasswordFromDB)) {
            mysqli_rollback($con);
            sendJsonResponse(false, 'Contraseña de confirmación incorrecta.', null, $con, $loggedInUserId, $loggedInUserId, 'contrasena', 'INTENTO_FALLIDO', NULL, 'Contraseña incorrecta al eliminar perfil empresa.');
        }

        // Obtener datos del perfil de empresa
        $stmt_get_perfil_empresa = $con->prepare("SELECT Nombre_Empresa, Correo_Electronico_Empresa, Sitio_Web_Empresa, Estado_Empresa FROM perfil_empresa WHERE ID_Perfil_Empresa = ? LIMIT 1");
        if ($stmt_get_perfil_empresa) {
            $stmt_get_perfil_empresa->bind_param("i", $idPerfilEmpresa);
            $stmt_get_perfil_empresa->execute();
            $result_perfil_empresa = $stmt_get_perfil_empresa->get_result();
            if ($row_perfil_empresa = $result_perfil_empresa->fetch_assoc()) {
                $datos_perfil_empresa_antes = json_encode($row_perfil_empresa);
            } else {
                // Si el perfil de empresa no existe (aunque el ID de sesión lo indique)
                mysqli_rollback($con);
                sendJsonResponse(false, 'Perfil de empresa no encontrado en la base de datos.', null, $con, $loggedInUserId, $idPerfilEmpresa, 'perfil_empresa', 'NO_ENCONTRADO', NULL, 'Perfil de empresa ID: ' . $idPerfilEmpresa . ' no encontrado.');
            }
            $stmt_get_perfil_empresa->close();
        } else {
            throw new Exception("Error al preparar SELECT perfil_empresa para bitácora: " . $con->error);
        }

        // Obtener datos de ofertas laborales
        $stmt_get_ofertas = $con->prepare("SELECT ID_Oferta, Titulo_Puesto, Descripción_Trabajo FROM oferta_laboral WHERE ID_Empresa = ?");
        if ($stmt_get_ofertas) {
            $stmt_get_ofertas->bind_param("i", $idPerfilEmpresa);
            $stmt_get_ofertas->execute();
            $result_ofertas = $stmt_get_ofertas->get_result();
            $ofertas_array = [];
            while ($row_oferta = $result_ofertas->fetch_assoc()) {
                $ofertas_array[] = $row_oferta;
            }
            $datos_ofertas_antes = json_encode($ofertas_array);
            $stmt_get_ofertas->close();
        } else {
            // No es un error fatal si no se pueden obtener las ofertas para bitácora
            error_log("Error al preparar SELECT oferta_laboral para bitácora (no fatal): " . $con->error);
            registrarEventoBitacora($con, $idPerfilEmpresa, 'oferta_laboral', 'ADVERTENCIA', $loggedInUserId, NULL, 'Error al obtener ofertas para bitácora antes de eliminar perfil empresa: ' . $con->error);
        }

        // --- PASO 2: Realizar las eliminaciones (orden inverso a la creación por FK) ---

        // Eliminar ofertas de trabajo
        $stmt_ofertas = $con->prepare("DELETE FROM oferta_laboral WHERE ID_Empresa = ?");
        if (!$stmt_ofertas) {
            throw new Exception("Error al preparar la eliminación de ofertas de trabajo: " . $con->error);
        }
        $stmt_ofertas->bind_param('i', $idPerfilEmpresa);
        if (!$stmt_ofertas->execute()) {
            throw new Exception("Error al ejecutar la eliminación de ofertas de trabajo: " . $stmt_ofertas->error);
        }
        $filas_ofertas_eliminadas = $stmt_ofertas->affected_rows;
        $stmt_ofertas->close();
        if ($filas_ofertas_eliminadas > 0) {
            registrarEventoBitacora($con, $idPerfilEmpresa, 'oferta_laboral', 'DELETE', $loggedInUserId, $datos_ofertas_antes, 'Número de ofertas eliminadas: ' . $filas_ofertas_eliminadas);
        }

        // Eliminar redes de empresa (si existe una tabla para ello)
        // Agrega esta sección si tienes una tabla `redes_empresas` o similar
        $stmt_redes = $con->prepare("DELETE FROM redes_empresas WHERE ID_Perfil_Empresa = ?");
        if ($stmt_redes) {
            $stmt_redes->bind_param('i', $idPerfilEmpresa);
            $stmt_redes->execute();
            $filas_redes_eliminadas = $stmt_redes->affected_rows;
            $stmt_redes->close();
            if ($filas_redes_eliminadas > 0) {
                registrarEventoBitacora($con, $idPerfilEmpresa, 'redes_empresas', 'DELETE', $loggedInUserId, NULL, 'Número de redes de empresa eliminadas: ' . $filas_redes_eliminadas);
            }
        } else {
            error_log("Advertencia: Error al preparar DELETE de redes_empresas. Posiblemente la tabla no existe o hay un error de sintaxis: " . $con->error);
            registrarEventoBitacora($con, $idPerfilEmpresa, 'redes_empresas', 'ADVERTENCIA', $loggedInUserId, NULL, 'Error al preparar DELETE de redes_empresas: ' . $con->error);
        }

        // Eliminar el perfil de la empresa
        $stmt_perfil_empresa = $con->prepare("DELETE FROM perfil_empresa WHERE ID_Perfil_Empresa = ?");
        if (!$stmt_perfil_empresa) {
            throw new Exception("Error al preparar la eliminación del perfil de empresa: " . $con->error);
        }
        $stmt_perfil_empresa->bind_param('i', $idPerfilEmpresa);
        if (!$stmt_perfil_empresa->execute()) {
            throw new Exception("Error al ejecutar la eliminación del perfil de empresa: " . $stmt_perfil_empresa->error);
        }
        $filas_perfil_empresa_eliminadas = $stmt_perfil_empresa->affected_rows;
        $stmt_perfil_empresa->close();
        if ($filas_perfil_empresa_eliminadas > 0) {
            registrarEventoBitacora($con, $idPerfilEmpresa, 'perfil_empresa', 'DELETE', $loggedInUserId, $datos_perfil_empresa_antes, NULL);
        }


        // Eliminar la contraseña del usuario
        $stmt_contrasena = $con->prepare("DELETE FROM contrasenas WHERE ID_Usuario = ?");
        if (!$stmt_contrasena) {
            throw new Exception("Error al preparar la eliminación de la contraseña del usuario: " . $con->error);
        }
        $stmt_contrasena->bind_param('i', $loggedInUserId);
        if (!$stmt_contrasena->execute()) {
            throw new Exception("Error al ejecutar la eliminación de la contraseña del usuario: " . $stmt_contrasena->error);
        }
        $filas_contrasena_eliminadas = $stmt_contrasena->affected_rows;
        $stmt_contrasena->close();
        if ($filas_contrasena_eliminadas > 0) {
            registrarEventoBitacora($con, $loggedInUserId, 'contrasena', 'DELETE', $loggedInUserId, $datos_contrasena_antes, NULL);
        }


        // Eliminar el registro del usuario
        $stmt_usuario = $con->prepare("DELETE FROM usuario WHERE ID_Usuario = ?");
        if (!$stmt_usuario) {
            throw new Exception("Error al preparar la eliminación del usuario: " . $con->error);
        }
        $stmt_usuario->bind_param('i', $loggedInUserId);
        if (!$stmt_usuario->execute()) {
            throw new Exception("Error al ejecutar la eliminación del usuario: " . $stmt_usuario->error);
        }
        $filas_usuario_eliminadas = $stmt_usuario->affected_rows;
        $stmt_usuario->close();
        if ($filas_usuario_eliminadas > 0) {
            registrarEventoBitacora($con, $loggedInUserId, 'usuario', 'DELETE', $loggedInUserId, $datos_usuario_antes, NULL);
        }


        // Si todas las operaciones fueron exitosas, confirmar la transacción
        mysqli_commit($con);

        // Destruir la sesión del usuario (para desloguearlo)
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

        // Enviar respuesta final de éxito
        sendJsonResponse(
            true,
            'Perfil de empresa eliminado exitosamente. Su sesión ha sido cerrada.',
            null,
            $con,
            $loggedInUserId,
            $loggedInUserId,
            'empresa',
            'DELETE_COMPLETO',
            ['usuario' => $datos_usuario_antes, 'contrasena' => $datos_contrasena_antes, 'perfil_empresa' => $datos_perfil_empresa_antes, 'ofertas' => $datos_ofertas_antes],
            'Perfil de empresa y datos asociados eliminados.'
        );

    } catch (Exception $e) {
        // Si ocurre algún error, revertir la transacción
        mysqli_rollback($con);
        $log_message = 'Excepción en eliminar_perfil_empresa.php (ID_Usuario: ' . $loggedInUserId . ', ID_Perfil_Empresa: ' . $idPerfilEmpresa . '): ' . $e->getMessage();
        error_log($log_message); // Loguear el error real
        sendJsonResponse(false, 'Error en la base de datos al eliminar el perfil de empresa.', 'Detalle: ' . $e->getMessage(), $con, $loggedInUserId, $idPerfilEmpresa, 'empresa', 'ERROR_SISTEMA', NULL, $log_message);
    } finally {
        // Restaurar el modo autocommit y cerrar la conexión
        if (isset($con) && $con instanceof mysqli) {
            $con->autocommit(true); // Asegurarse de restaurar el autocommit
            $con->close();
        }
    }

} else {
    // Si la solicitud no es POST
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.', $con, $loggedInUserId, 0, 'sistema', 'METODO_NO_PERMITIDO', NULL, 'Intento de acceso con método no POST.');
}
?>