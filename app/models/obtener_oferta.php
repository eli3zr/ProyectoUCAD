<?php
session_start(); // Inicia la sesión de PHP

// Configuración para entorno de producción (sin mostrar errores al usuario)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/../config/conexion.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [];

// --- OBTENER ID_perfil_empresa DE LA SESIÓN ---
$id_empresa_logueada = null;
// CAMBIO: Usamos 'ID_Perfil_Empresa' con 'P' y 'E' mayúsculas para coincidir con cómo se guarda en el login
if (isset($_SESSION['ID_Perfil_Empresa'])) {
    $id_empresa_logueada = (int)$_SESSION['ID_Perfil_Empresa'];
}

// Validación de que el ID de la empresa esté disponible en la sesión
if ($id_empresa_logueada === null || $id_empresa_logueada <= 0) {
    http_response_code(401); // 401 Unauthorized
    $response = [
        'success' => false,
        'message' => 'No autorizado. Por favor, inicia sesión para ver las ofertas.'
    ];
    echo json_encode($response);
    exit();
}
// ---------------------------------------------

try {
    // Consulta SQL para seleccionar las ofertas de la empresa logueada.
    $sql = "SELECT ID_Oferta, Titulo_Puesto, fecha_publicacion, estado, Descripción_Trabajo, Requisitos, Salario_Minimo, Salario_Maximo, Modalidad, Ubicación
            FROM oferta_laboral
            WHERE ID_perfil_empresa = ?
            ORDER BY fecha_publicacion DESC";

    $stmt = mysqli_prepare($con, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_empresa_logueada);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            $ofertas = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $ofertas[] = $row;
            }

            http_response_code(200);
            $response = [
                'success' => true,
                'message' => 'Ofertas obtenidas exitosamente.',
                'data' => $ofertas
            ];
        } else {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Error al ejecutar la consulta para obtener ofertas: ' . mysqli_stmt_error($stmt)
            ];
            error_log("Error al ejecutar la consulta para obtener ofertas: " . mysqli_stmt_error($stmt));
        }

        mysqli_stmt_close($stmt);

    } else {
        http_response_code(500);
        $response = [
            'success' => false,
            'message' => 'Error al preparar la consulta para obtener ofertas: ' . mysqli_error($con)
        ];
        error_log("Error al preparar la consulta para obtener ofertas: " . mysqli_error($con));
    }

} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Ocurrió un error inesperado en el servidor: ' . $e->getMessage()
    ];
    error_log("Excepción en obtener_oferta.php: " . $e->getMessage());
} finally {
    if (isset($con) && is_object($con) && $con->ping()) {
        mysqli_close($con);
    }
}

echo json_encode($response);
