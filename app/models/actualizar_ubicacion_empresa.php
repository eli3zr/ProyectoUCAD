<?php

// Habilitar errores para depuración (quitar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start(); // ¡SIEMPRE AL PRINCIPIO!

// --- DEPURACIÓN TEMPORAL (Mantén esto un momento para verificar) ---
error_log("--- DEBUG DE SESIÓN EN actualizar_ubicacion_empresa.php ---");
error_log("Contenido de \$_SESSION: " . print_r($_SESSION, true));
error_log("Valor de ID_Usuario en sesión: " . ($_SESSION['ID_Usuario'] ?? 'ID_Usuario NO ESTABLECIDO'));
error_log("Valor de ID_Rol en sesión: " . ($_SESSION['ID_Rol'] ?? 'ID_Rol NO ESTABLECIDO'));
// --- FIN DEPURACIÓN TEMPORAL ---


$usuario_id = $_SESSION['ID_Usuario'] ?? null;
$usuario_rol_id = $_SESSION['ID_Rol'] ?? null; // Asegúrate de que tu login guarda ID_Rol

$response = ['success' => false, 'data' => null, 'error' => '', 'msg' => ''];

// Verificar autorización: el usuario debe estar logueado Y tener el rol de empresa (ID_Rol = 1)
if (!$usuario_id) {
    $response['error'] = 'No autorizado. Por favor, inicie sesión.';
    echo json_encode($response);
    exit();
}

// Suponiendo que el ID_Rol para 'empresa' es 1, basado en tu captura de pantalla de roles
if ($usuario_rol_id !== 1) { // 1 es el ID_Rol para 'empresa'
    $response['error'] = 'Acceso denegado. Este perfil es solo para usuarios con rol de empresa.';
    echo json_encode($response);
    exit();
}

// RUTA CORRECTA: Desde 'app/models/' necesitas ir un nivel arriba a 'app/' y luego a 'config/'
// Este archivo ahora espera que 'conexion.php' defina una variable $con de tipo mysqli
require_once __DIR__ . '/../config/conexion.php';

// Asegúrate de que $con es un objeto mysqli válido antes de intentar usarlo
if (!isset($con) || !($con instanceof mysqli)) {
    $response['success'] = false;
    $response['error'] = 'Error: La conexión a la base de datos no está disponible. (MySQLi)';
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
                } else {
                    $response['error'] = 'Error al obtener países: ' . $con->error;
                }
                break;

            case 'get_departamentos':
                $paisId = $_GET['pais_id'] ?? null;
                if ($paisId) {
                    // Corregido: pais_id_pais para coincidir con tu DB
                    $stmt = $con->prepare("SELECT id_departamento, nombre_departamento FROM departamento WHERE pais_id_pais = ? ORDER BY nombre_departamento");
                    if ($stmt) {
                        $stmt->bind_param('i', $paisId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                        $response['success'] = true;
                        $response['data'] = $data;
                        $stmt->close();
                    } else {
                        $response['error'] = 'Error al preparar la consulta de departamentos: ' . $con->error;
                    }
                } else {
                    $response['error'] = 'ID de país requerido para obtener departamentos.';
                }
                break;

            case 'get_municipios':
                $departamentoId = $_GET['departamento_id'] ?? null;
                if ($departamentoId) {
                    // Corregido: departamento_id_departamento para coincidir con tu DB
                    $stmt = $con->prepare("SELECT id_municipio, municipio FROM municipio WHERE departamento_id_departamento = ? ORDER BY municipio");
                    if ($stmt) {
                        $stmt->bind_param('i', $departamentoId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                        $response['success'] = true;
                        $response['data'] = $data;
                        $stmt->close();
                    } else {
                        $response['error'] = 'Error al preparar la consulta de municipios: ' . $con->error;
                    }
                } else {
                    $response['error'] = 'ID de departamento requerido para obtener municipios.';
                }
                break;

            case 'get_distritos':
                $municipioId = $_GET['municipio_id'] ?? null;
                if ($municipioId) {
                    // *** CAMBIO REALIZADO AQUÍ: id_municipio_fk A municipio_id_municipio para coincidir con tu DB ***
                    $stmt = $con->prepare("SELECT id_distrito, nombre_distrito FROM distrito WHERE municipio_id_municipio = ? ORDER BY nombre_distrito");
                    if ($stmt) {
                        $stmt->bind_param('i', $municipioId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                        $response['success'] = true;
                        $response['data'] = $data;
                        $stmt->close();
                    } else {
                        $response['error'] = 'Error al preparar la consulta de distritos: ' . $con->error;
                    }
                } else {
                    $response['error'] = 'ID de municipio requerido para obtener distritos.';
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
                    } else {
                        $response['success'] = true;
                        $response['data'] = null;
                        $response['msg'] = 'No se encontró un perfil de empresa existente para este usuario.';
                    }
                } else {
                    $response['error'] = 'Error al preparar la consulta de perfil de empresa: ' . $con->error;
                }
                break;

            default:
                $response['error'] = 'Acción GET no válida.';
                http_response_code(400);
                break;
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Lógica para actualizar la ubicación
        $idPais = $_POST['pais'] ?? null;
        $idDepartamento = $_POST['departamento'] ?? null;
        $idMunicipio = $_POST['municipio'] ?? null;
        $idDistrito = $_POST['distrito'] ?? null;
        $direccionDetallada = $_POST['direccionDetallada'] ?? '';

        if (empty($idPais) || empty($idDepartamento) || empty($idMunicipio) || empty($idDistrito)) {
            $response['error'] = 'Por favor, seleccione País, Departamento, Municipio y Distrito.';
        } else {
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
                    } else {
                        $response['success'] = false;
                        $response['error'] = 'No se realizaron cambios en la ubicación o no se encontró el perfil para actualizar.';
                    }
                } else {
                    $response['success'] = false;
                    $response['error'] = 'Error al ejecutar la actualización: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $response['success'] = false;
                $response['error'] = 'Error al preparar la consulta de actualización: ' . $con->error;
            }
        }
    } else {
        header('Allow: GET, POST');
        http_response_code(405);
        $response['error'] = 'Método HTTP no permitido.';
    }
} catch (Exception $e) {
    error_log("Error en actualizar_ubicacion_empresa: " . $e->getMessage());
    $response['success'] = false;
    $response['error'] = 'Error interno del servidor: ' . $e->getMessage();
} finally {
    if (isset($con) && $con instanceof mysqli) {
        $con->close();
    }
}

echo json_encode($response);
exit();
?>