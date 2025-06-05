<?php
// guardar_oferta.php
// Este script recibe los datos de una nueva oferta y los inserta en la base de datos.

// Configuración de errores para depuración (¡Cambia a 0 en producción!)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
// Asegúrate de que la ruta para el log de errores sea accesible y escribible por el servidor web
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

session_start(); // Inicia la sesión de PHP

require_once __DIR__ . '/../config/conexion.php';

header('Content-Type: application/json');

// Manejo de solicitudes OPTIONS para CORS (si es necesario)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [];

// --- DEBUG: Log del contenido de la sesión al inicio del script ---
error_log("DEBUG (guardar_oferta.php): Contenido de la sesión al inicio: " . print_r($_SESSION, true));
// -----------------------------------------------------------------

// --- OBTENER ID_perfil_empresa ---
$id_perfil_empresa = null;

// 1. Intentar obtener de la sesión (primera y preferida opción)
if (isset($_SESSION['ID_Perfil_Empresa']) && $_SESSION['ID_Perfil_Empresa'] > 0) {
    $id_perfil_empresa = (int)$_SESSION['ID_Perfil_Empresa'];
    error_log("DEBUG (guardar_oferta.php): ID_Perfil_Empresa encontrado en sesión: " . $id_perfil_empresa);
} else {
    error_log("DEBUG (guardar_oferta.php): ID_Perfil_Empresa NO está seteado o es inválido en la sesión. Intentando buscar en BD.");

    // 2. Si no está en sesión, intentar obtenerlo de la base de datos usando ID_Usuario
    if (isset($_SESSION['ID_Usuario']) && $_SESSION['ID_Usuario'] > 0) {
        $id_usuario = (int)$_SESSION['ID_Usuario'];
        error_log("DEBUG (guardar_oferta.php): ID_Usuario encontrado en sesión: " . $id_usuario);

        $query_perfil_empresa = "SELECT ID_Perfil_Empresa FROM perfil_empresa WHERE usuario_ID_Usuario = ?";
        $stmt_perfil_empresa = mysqli_prepare($con, $query_perfil_empresa);

        if ($stmt_perfil_empresa) {
            mysqli_stmt_bind_param($stmt_perfil_empresa, 'i', $id_usuario);
            mysqli_stmt_execute($stmt_perfil_empresa);
            mysqli_stmt_bind_result($stmt_perfil_empresa, $perfil_empresa_id_encontrado);
            if (mysqli_stmt_fetch($stmt_perfil_empresa)) {
                $id_perfil_empresa = $perfil_empresa_id_encontrado;
                $_SESSION['ID_Perfil_Empresa'] = $id_perfil_empresa; // Almacenarlo para futuras peticiones en esta sesión
                error_log("DEBUG (guardar_oferta.php): ID_Perfil_Empresa encontrado en BD y guardado en sesión: " . $id_perfil_empresa);
            } else {
                error_log("ADVERTENCIA (guardar_oferta.php): Usuario (ID: " . $id_usuario . ") no tiene un perfil de empresa asociado en la tabla perfil_empresa.");
            }
            mysqli_stmt_close($stmt_perfil_empresa);
        } else {
            error_log("ERROR (guardar_oferta.php): Error al preparar la consulta de perfil de empresa desde ID_Usuario: " . mysqli_error($con));
        }
    } else {
        error_log("DEBUG (guardar_oferta.php): ID_Usuario NO está seteado o es inválido en la sesión.");
    }
}

// Validación final de que el ID de la empresa esté disponible
if ($id_perfil_empresa === null || $id_perfil_empresa <= 0) {
    http_response_code(401); // 401 Unauthorized
    $response = [
        'success' => false,
        'message' => 'No autorizado. Por favor, inicia sesión como empresa para publicar una oferta.'
    ];
    echo json_encode($response);
    exit(); // Salimos si no está autorizado
}
// ---------------------------------------------

$datos = $_POST;

// Validar campos obligatorios del formulario
if (
    empty($datos['nombrePuesto']) ||
    empty($datos['descripcion']) ||
    empty($datos['requisitos']) ||
    empty($datos['modalidad'])
) {
    http_response_code(400); // 400 Bad Request
    $response = [
        'success' => false,
        'message' => 'Faltan campos obligatorios: título, descripción, requisitos o modalidad.'
    ];
} else {
    // Sanitizar y preparar los datos para la inserción
    $titulo_puesto = mysqli_real_escape_string($con, $datos['nombrePuesto']);
    $descripcion_trabajo = mysqli_real_escape_string($con, $datos['descripcion']);
    $requisitos = mysqli_real_escape_string($con, $datos['requisitos']);
    $modalidad = mysqli_real_escape_string($con, $datos['modalidad']);

    // Los salarios pueden ser nulos, se manejan con un operador ternario
    $salario_minimo = (!empty($datos['salarioMinimo'])) ? (float)$datos['salarioMinimo'] : null;
    $salario_maximo = (!empty($datos['salarioMaximo'])) ? (float)$datos['salarioMaximo'] : null;
    $ubicacion = (!empty($datos['ubicacion'])) ? mysqli_real_escape_string($con, $datos['ubicacion']) : null;

    $estado = 'activa'; // Estado por defecto para nuevas ofertas

    // Consulta SQL para insertar la nueva oferta
    // Asegúrate de que los nombres de las columnas coincidan exactamente con tu base de datos
    $sql = "INSERT INTO oferta_laboral (Titulo_Puesto, Descripción_Trabajo, Requisitos, Salario_Minimo, Salario_Maximo, Modalidad, Ubicación, ID_perfil_empresa, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($con, $sql);

    if ($stmt) {
        // Vinculamos los parámetros a la consulta preparada
        // s: string, d: double (float), i: integer
        // El orden y los tipos deben coincidir con los placeholders (?) en la consulta SQL
        mysqli_stmt_bind_param(
            $stmt,
            "sssdsssis", // 3 strings, 2 doubles, 2 strings, 1 integer, 1 string
            $titulo_puesto,
            $descripcion_trabajo,
            $requisitos,
            $salario_minimo,
            $salario_maximo,
            $modalidad,
            $ubicacion,
            $id_perfil_empresa, // Usamos el ID de la empresa (obtenido de sesión o BD)
            $estado
        );

        // Ejecutamos la consulta
        if (mysqli_stmt_execute($stmt)) {
            http_response_code(201); // 201 Created
            $response = [
                'success' => true,
                'message' => 'La oferta fue publicada exitosamente.'
            ];
        } else {
            http_response_code(500); // 500 Internal Server Error
            $response = [
                'success' => false,
                'message' => 'No se pudo publicar la oferta en este momento. Error: ' . mysqli_stmt_error($stmt)
            ];
            error_log("Error al ejecutar la inserción de oferta: " . mysqli_stmt_error($stmt));
        }

        mysqli_stmt_close($stmt); // Cerramos el statement
    } else {
        http_response_code(500); // 500 Internal Server Error
        $response = [
            'success' => false,
            'message' => 'Ocurrió un error inesperado al preparar la solicitud. Error: ' . mysqli_error($con)
        ];
        error_log("Error al preparar la consulta de oferta: " . mysqli_error($con));
    }
}

// Cerramos la conexión a la base de datos si está abierta
if (isset($con) && is_object($con) && $con->ping()) {
    mysqli_close($con);
}

// Devolvemos la respuesta en formato JSON
echo json_encode($response);

?>