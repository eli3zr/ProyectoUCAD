<?php

// Habilitar errores para depuración (quitar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuración de errores para depuración (¡Cambia a 0 en producción!)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // Asegúrate de que la carpeta 'logs' exista y sea escribible.

session_start(); // ¡SIEMPRE AL PRINCIPIO!

header('Content-Type: application/json');

// Incluir el helper de bitácora
require_once __DIR__ . '/../utils/bitacora_helper.php'; 

// --- DEPURACIÓN TEMPORAL (Mantén esto un momento para verificar) ---
error_log("--- DEBUG DE SESIÓN EN actualizar_ubicacion_empresa.php ---");
error_log("Contenido de \$_SESSION: " . print_r($_SESSION, true));
error_log("Valor de ID_Usuario en sesión: " . ($_SESSION['ID_Usuario'] ?? 'ID_Usuario NO ESTABLECIDO'));
error_log("Valor de ID_Rol en sesión: " . ($_SESSION['ID_Rol'] ?? 'ID_Rol NO ESTABLECIDO'));
// --- FIN DEPURACIÓN TEMPORAL ---


$usuario_id = $_SESSION['ID_Usuario'] ?? 0; // Cambiado a 0 por defecto para el registro de bitácora
$usuario_rol_id = $_SESSION['ID_Rol'] ?? null; // Asegúrate de que tu login guarda ID_Rol

$response = ['success' => false, 'data' => null, 'error' => '', 'msg' => ''];

// RUTA CORRECTA: Desde 'app/models/' necesitas ir un nivel arriba a 'app/' y luego a 'config/'
// Este archivo ahora espera que 'conexion.php' defina una variable $con de tipo mysqli
require_once __DIR__ . '/../config/conexion.php';

// Asegúrate de que $con es un objeto mysqli válido antes de intentar usarlo
// Consistente con actualizar_informacion_empresa.php: no se bitacora directamente aquí si falla la conexión.
if (!isset($con) || !($con instanceof mysqli) || $con->connect_error) {
    $response['error'] = 'Error al conectar con la base de datos. Detalles: ' . ($con->connect_error ?? 'Conexión no establecida o inválida');
    echo json_encode($response);
    exit();
}

// Verificar autorización: el usuario debe estar logueado Y tener el rol de empresa (ID_Rol = 1)
if (!$usuario_id) {
    $response['error'] = 'No autorizado. Por favor, inicie sesión.';
    // Registrar ACCESO_NO_AUTORIZADO
    registrarEventoBitacora($con, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', 0, NULL, 'Intento de acceso a actualización de ubicación sin sesión activa.');
    echo json_encode($response);
    exit();
}

// Suponiendo que el ID_Rol para 'empresa' es 1, basado en tu captura de pantalla de roles
if ($usuario_rol_id !== 1) { // 1 es el ID_Rol para 'empresa'
    $response['error'] = 'Acceso denegado. Este perfil es solo para usuarios con rol de empresa.';
    // Registrar ACCESO_NO_AUTORIZADO por rol incorrecto
    registrarEventoBitacora($con, $usuario_id, 'usuario', 'ACCESO_NO_AUTORIZADO', $usuario_id, NULL, 'Intento de acceso a ubicación con rol incorrecto. Rol: ' . $usuario_rol_id);
    echo json_encode($response);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'get_paises':
                $stmt = $con->query("SELECT id_pais, nombre_pais FROM pais ORDER BY nombre_pais");
                if ($stmt) {
                    $data = $stmt->fetch_all(MYSQLI_ASSOC);
                    $response['success'] = true;
                    $response['data'] = $data;
                    $stmt->close();
                    // Consistente con actualizar_informacion_empresa.php: no se bitacora GETs de listas.
                } else {
                    $error_msg = 'Error al obtener países: ' . $con->error;
                    $response['error'] = $error_msg;
                    // Registrar ERROR_SISTEMA en consulta de países si hay error
                    registrarEventoBitacora($con, 0, 'pais', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al obtener países: ' . $error_msg);
                }
                break;

            case 'get_departamentos':
                $paisId = $_GET['pais_id'] ?? null;
                if ($paisId) {
                    $stmt = $con->prepare("SELECT id_departamento, nombre_departamento FROM departamento WHERE pais_id_pais = ? ORDER BY nombre_departamento");
                    if ($stmt) {
                        $stmt->bind_param('i', $paisId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                        $response['success'] = true;
                        $response['data'] = $data;
                        $stmt->close();
                        // Consistente con actualizar_informacion_empresa.php: no se bitacora GETs de listas.
                    } else {
                        $error_msg = 'Error al preparar la consulta de departamentos: ' . $con->error;
                        $response['error'] = $error_msg;
                        registrarEventoBitacora($con, 0, 'departamento', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar consulta de departamentos: ' . $error_msg);
                    }
                } else {
                    $error_msg = 'ID de país requerido para obtener departamentos.';
                    $response['error'] = $error_msg;
                    registrarEventoBitacora($con, 0, 'departamento', 'ADVERTENCIA', $usuario_id, NULL, 'Consulta de departamentos fallida: ID de país requerido.');
                }
                break;

            case 'get_municipios':
                $departamentoId = $_GET['departamento_id'] ?? null;
                if ($departamentoId) {
                    $stmt = $con->prepare("SELECT id_municipio, municipio FROM municipio WHERE departamento_id_departamento = ? ORDER BY municipio");
                    if ($stmt) {
                        $stmt->bind_param('i', $departamentoId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                        $response['success'] = true;
                        $response['data'] = $data;
                        $stmt->close();
                        // Consistente con actualizar_informacion_empresa.php: no se bitacora GETs de listas.
                    } else {
                        $error_msg = 'Error al preparar la consulta de municipios: ' . $con->error;
                        $response['error'] = $error_msg;
                        registrarEventoBitacora($con, 0, 'municipio', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar consulta de municipios: ' . $error_msg);
                    }
                } else {
                    $error_msg = 'ID de departamento requerido para obtener municipios.';
                    $response['error'] = $error_msg;
                    registrarEventoBitacora($con, 0, 'municipio', 'ADVERTENCIA', $usuario_id, NULL, 'Consulta de municipios fallida: ID de departamento requerido.');
                }
                break;

            case 'get_distritos':
                $municipioId = $_GET['municipio_id'] ?? null;
                if ($municipioId) {
                    $stmt = $con->prepare("SELECT id_distrito, nombre_distrito FROM distrito WHERE municipio_id_municipio = ? ORDER BY nombre_distrito");
                    if ($stmt) {
                        $stmt->bind_param('i', $municipioId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                        $response['success'] = true;
                        $response['data'] = $data;
                        $stmt->close();
                        // Consistente con actualizar_informacion_empresa.php: no se bitacora GETs de listas.
                    } else {
                        $error_msg = 'Error al preparar la consulta de distritos: ' . $con->error;
                        $response['error'] = $error_msg;
                        registrarEventoBitacora($con, 0, 'distrito', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar consulta de distritos: ' . $error_msg);
                    }
                } else {
                    $error_msg = 'ID de municipio requerido para obtener distritos.';
                    $response['error'] = $error_msg;
                    registrarEventoBitacora($con, 0, 'distrito', 'ADVERTENCIA', $usuario_id, NULL, 'Consulta de distritos fallida: ID de municipio requerido.');
                }
                break;

            case 'get_perfil_empresa':
                $stmt = $con->prepare("
                    SELECT
                        ID_Perfil_Empresa,
                        usuario_ID_Usuario,
                        id_pais_fk,
                        id_departamento_fk,
                        id_municipio_fk,
                        id_distrito_fk,
                        direccion_detallada         
                    FROM perfil_empresa
                    WHERE usuario_ID_Usuario = ?
                    LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param('i', $usuario_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $perfil = $result->fetch_assoc();
                    $stmt->close();

                    if ($perfil) {
                        $response['success'] = true;
                        $response['data'] = $perfil;
                        // Consistente con actualizar_informacion_empresa.php: no se bitacora el GET del perfil.
                    } else {
                        $response['success'] = true; // No es un error si no hay perfil aún, solo que no existe
                        $response['data'] = null;
                        $response['msg'] = 'No se encontró un perfil de empresa existente para este usuario.';
                        // Consistente con actualizar_informacion_empresa.php: no se bitacora si el perfil no existe.
                    }
                } else {
                    $error_msg = 'Error al preparar la consulta de perfil de empresa: ' . $con->error;
                    $response['error'] = $error_msg;
                    registrarEventoBitacora($con, 0, 'perfil_empresa', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar consulta de perfil de empresa (ubicación): ' . $error_msg);
                }
                break;

            default:
                $response['error'] = 'Acción GET no válida.';
                http_response_code(400);
                registrarEventoBitacora($con, 0, 'sistema', 'ADVERTENCIA', $usuario_id, NULL, 'Acción GET no válida en actualizar_ubicacion_empresa: ' . ($action ?: 'vacío'));
                break;
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Lógica para actualizar la ubicación
        $idPais = $_POST['pais'] ?? null;
        $idDepartamento = $_POST['departamento'] ?? null;
        $idMunicipio = $_POST['municipio'] ?? null;
        $idDistrito = $_POST['distrito'] ?? null;
        $direccionDetallada = $_POST['direccionDetallada'] ?? '';

        // Asegurarse de que los IDs de ubicación sean enteros o NULL
        $idPais = ($idPais !== null && $idPais !== '') ? (int)$idPais : null;
        $idDepartamento = ($idDepartamento !== null && $idDepartamento !== '') ? (int)$idDepartamento : null;
        $idMunicipio = ($idMunicipio !== null && $idMunicipio !== '') ? (int)$idMunicipio : null;
        $idDistrito = ($idDistrito !== null && $idDistrito !== '') ? (int)$idDistrito : null;


        if (empty($idPais) || empty($idDepartamento) || empty($idMunicipio) || empty($idDistrito)) {
            $response['error'] = 'Por favor, seleccione País, Departamento, Municipio y Distrito.';
            // Consistente con actualizar_informacion_empresa.php: LOGIN_FALLIDO para validación fallida
            registrarEventoBitacora($con, $usuario_id, 'perfil_empresa', 'LOGIN_FALLIDO', $usuario_id, NULL, 'Actualización de ubicación fallida: Campos obligatorios de ubicación vacíos.');
        } else {
            // Antes de la actualización, obtener los datos actuales del perfil para la bitácora
            $stmt_get_perfil_existente = $con->prepare("SELECT ID_Perfil_Empresa, id_pais_fk, id_departamento_fk, id_municipio_fk, id_distrito_fk, direccion_detallada FROM perfil_empresa WHERE usuario_ID_Usuario = ? LIMIT 1");
            $stmt_get_perfil_existente->bind_param("i", $usuario_id);
            $stmt_get_perfil_existente->execute();
            $result_get_perfil_existente = $stmt_get_perfil_existente->get_result();
            $perfil_existente_data = $result_get_perfil_existente->fetch_assoc();
            $stmt_get_perfil_existente->close();

            $id_perfil_empresa_afectado = $perfil_existente_data['ID_Perfil_Empresa'] ?? 0; // Para la bitácora

            // --- BITACOLA: Obtener datos antes de la actualización en tabla 'perfil_empresa' ---
            // Solo si se encontró un perfil existente
            $datos_perfil_antes = null;
            if ($perfil_existente_data) {
                $datos_perfil_antes = json_encode($perfil_existente_data);
            }


            $stmt = $con->prepare("
                UPDATE perfil_empresa
                SET
                    id_pais_fk = ?,
                    id_departamento_fk = ?,
                    id_municipio_fk = ?,
                    id_distrito_fk = ?,
                    direccion_detallada = ?
                WHERE usuario_ID_Usuario = ?
            ");

            if ($stmt) {
                $stmt->bind_param('iiiisi', $idPais, $idDepartamento, $idMunicipio, $idDistrito, $direccionDetallada, $usuario_id);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response['success'] = true;
                        $response['msg'] = 'La ubicación de la empresa ha sido actualizada correctamente.';
                        // --- BITACOLA: Registrar UPDATE exitoso ---
                        $datos_perfil_despues = extraerDatosParaBitacora($con, $id_perfil_empresa_afectado, 'perfil_empresa');
                        registrarEventoBitacora(
                            $con, 
                            $id_perfil_empresa_afectado, 
                            'perfil_empresa', 
                            'UPDATE', // Usamos el tipo genérico 'UPDATE'
                            $usuario_id, 
                            $datos_perfil_antes, 
                            $datos_perfil_despues
                        );
                    } else {
                        $response['success'] = false;
                        $error_msg = 'No se realizaron cambios en la ubicación o no se encontró el perfil para actualizar.';
                        $response['error'] = $error_msg;
                        // Consistente con actualizar_informacion_empresa.php: ADVERTENCIA si no hay cambios
                        registrarEventoBitacora($con, $id_perfil_empresa_afectado, 'perfil_empresa', 'ADVERTENCIA', $usuario_id, NULL, 'Actualización de ubicación: No se realizaron cambios o perfil no encontrado para usuario ID: ' . $usuario_id);
                    }
                } else {
                    $response['success'] = false;
                    $error_msg = 'Error al ejecutar la actualización: ' . $stmt->error;
                    $response['error'] = $error_msg;
                    // Registrar ERROR_SISTEMA en la ejecución del UPDATE
                    registrarEventoBitacora($con, $id_perfil_empresa_afectado, 'perfil_empresa', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al ejecutar UPDATE de ubicación: ' . $error_msg);
                }
                $stmt->close();
            } else {
                $response['success'] = false;
                $error_msg = 'Error al preparar la consulta de actualización: ' . $con->error;
                $response['error'] = $error_msg;
                // Registrar ERROR_SISTEMA en la preparación del UPDATE
                registrarEventoBitacora($con, 0, 'perfil_empresa', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar UPDATE de ubicación: ' . $error_msg);
            }
        }
    } else {
        header('Allow: GET, POST');
        http_response_code(405);
        $response['error'] = 'Método HTTP no permitido.';
        registrarEventoBitacora($con, 0, 'sistema', 'ADVERTENCIA', $usuario_id, NULL, 'Método HTTP no permitido en actualizar_ubicacion_empresa: ' . $_SERVER['REQUEST_METHOD']);
    }
} catch (Exception $e) {
    error_log("Error en actualizar_ubicacion_empresa: " . $e->getMessage());
    $response['success'] = false;
    $response['error'] = 'Error interno del servidor: ' . $e->getMessage();
    registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $usuario_id, NULL, 'Excepción general en actualizar_ubicacion_empresa: ' . $e->getMessage());
} finally {
    if (isset($con) && $con instanceof mysqli) {
        $con->close();
    }
}

echo json_encode($response);
exit();
?>