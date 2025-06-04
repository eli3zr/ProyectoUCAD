<?php
session_start(); // Inicia la sesión de PHP

// Configuración de errores para depuración (¡Cambia a 0 en producción!)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [];

// --- DEBUG: Log del contenido de la sesión al inicio del script ---
error_log("DEBUG (guardar_oferta.php): Contenido de la sesión al inicio: " . print_r($_SESSION, true));
if (isset($_SESSION['ID_Perfil_Empresa'])) { // CAMBIO: 'P' y 'E' mayúsculas
    error_log("DEBUG (guardar_oferta.php): ID_Perfil_Empresa en sesión: " . $_SESSION['ID_Perfil_Empresa']); // CAMBIO: 'P' y 'E' mayúsculas
} else {
    error_log("DEBUG (guardar_oferta.php): ID_Perfil_Empresa NO está seteado en la sesión."); // CAMBIO: 'P' y 'E' mayúsculas
}
// -----------------------------------------------------------------

// --- OBTENER ID_perfil_empresa DE LA SESIÓN ---
$id_perfil_empresa = null; // Mantenemos esta variable en minúsculas para el uso interno del script
if (isset($_SESSION['ID_Perfil_Empresa'])) { // CAMBIO: 'P' y 'E' mayúsculas
    $id_perfil_empresa = (int)$_SESSION['ID_Perfil_Empresa']; // CAMBIO: 'P' y 'E' mayúsculas
}

// Validación de que el ID de la empresa esté disponible en la sesión
if ($id_perfil_empresa === null || $id_perfil_empresa <= 0) {
    http_response_code(401); // 401 Unauthorized
    $response = [
        'success' => false,
        'message' => 'No autorizado. Por favor, inicia sesión para publicar una oferta.'
    ];
    echo json_encode($response);
    exit();
}
// ---------------------------------------------


$datos = $_POST;

if (
    empty($datos['nombrePuesto']) ||
    empty($datos['descripcion']) ||
    empty($datos['requisitos']) ||
    empty($datos['modalidad'])
) {
    http_response_code(400);
    $response = [
        'success' => false,
        'message' => 'Faltan campos obligatorios: título, descripción, requisitos o modalidad.'
    ];
} else {
    $titulo_puesto = mysqli_real_escape_string($con, $datos['nombrePuesto']);
    $descripcion_trabajo = mysqli_real_escape_string($con, $datos['descripcion']);
    $requisitos = mysqli_real_escape_string($con, $datos['requisitos']);
    $modalidad = mysqli_real_escape_string($con, $datos['modalidad']);

    $salario_minimo = (!empty($datos['salarioMinimo'])) ? (float)$datos['salarioMinimo'] : null;
    $salario_maximo = (!empty($datos['salarioMaximo'])) ? (float)$datos['salarioMaximo'] : null;
    $ubicacion = (!empty($datos['ubicacion'])) ? mysqli_real_escape_string($con, $datos['ubicacion']) : null;

    $estado = 'activa';

    $sql = "INSERT INTO oferta_laboral (Titulo_Puesto, Descripción_Trabajo, Requisitos, Salario_Minimo, Salario_Maximo, Modalidad, Ubicación, ID_perfil_empresa, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($con, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            "sssdsssis",
            $titulo_puesto,
            $descripcion_trabajo,
            $requisitos,
            $salario_minimo,
            $salario_maximo,
            $modalidad,
            $ubicacion,
            $id_perfil_empresa, // Usamos el ID de la sesión
            $estado
        );

        if (mysqli_stmt_execute($stmt)) {
            http_response_code(201);
            $response = [
                'success' => true,
                'message' => 'La oferta fue publicada exitosamente.'
            ];
        } else {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'No se pudo publicar la oferta en este momento. Error: ' . mysqli_stmt_error($stmt)
            ];
            error_log("Error al ejecutar la inserción de oferta: " . mysqli_stmt_error($stmt));
        }

        mysqli_stmt_close($stmt);

    } else {
        http_response_code(500);
        $response = [
            'success' => false,
            'message' => 'Ocurrió un error inesperado al procesar la solicitud. Error: ' . mysqli_error($con)
        ];
        error_log("Error al preparar la consulta de oferta: " . mysqli_error($con));
    }
}

if (isset($con) && is_object($con) && $con->ping()) {
    mysqli_close($con);
}

echo json_encode($response);

?>
