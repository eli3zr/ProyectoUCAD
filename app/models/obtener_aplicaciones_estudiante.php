<?php
// C:\xampp\htdocs\Jobtrack_Ucad\app\models\obtener_aplicaciones_estudiante.php

// 1. Iniciar sesión SOLO UNA VEZ y al principio del script
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Configuración de encabezados - SOLO Content-Type como solicitaste
// ADVERTENCIA: Esto probablemente causará errores de CORS si tu frontend no se carga desde un servidor web.
header("Content-Type: application/json; charset=UTF-8");

// Configuración de errores (cambiar a 0 en producción para no mostrar errores al usuario)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 3. Incluir la conexión a la base de datos
// La ruta más probable, basada en tus errores anteriores, es que 'config' esté dentro de 'app'.
// Si 'config' está en la raíz de Jobtrack_Ucad (Jobtrack_Ucad/config/), la ruta sería '../../config/conexion.php'.
require_once __DIR__ . '/../config/conexion.php';


// Función de ayuda para enviar respuesta JSON
function sendJsonResponse($success, $message, $data = [], $error = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'error' => $error
    ]);
    exit();
}

// 4. Verificar conexión a la BD
// $con es la variable de conexión de mysqli que se espera venga de conexion.php
if (!isset($con) || $con->connect_error) {
    sendJsonResponse(false, 'Error de conexión a la base de datos.', [], isset($con) ? $con->connect_error : 'Conexión no establecida.');
}

// 5. Verificar que el usuario esté logueado
if (!isset($_SESSION['ID_Usuario']) || empty($_SESSION['ID_Usuario'])) {
    sendJsonResponse(false, 'Acceso denegado. Usuario no autenticado.', [], 'AUTH_ERROR');
}

$id_estudiante = (int)$_SESSION['ID_Usuario']; // ID del estudiante logueado

// Construir la consulta SQL para obtener las aplicaciones del estudiante
// Obtenemos el nombre de la empresa a través de la tabla 'usuarios'
// La secuencia de JOINs es: aplicacion_oferta -> oferta_laboral -> perfil_empresa -> usuarios
$sql = "
    SELECT
        ao.ID_Aplicacion,
        oe.Titulo_Puesto,
        u.Nombre AS Nombre_Empresa, -- Obtenemos el nombre del usuario (empresa) y lo aliasamos
        ao.Fecha_Aplicacion,
        ao.Estado_Aplicacion,
        ao.Carta_Presentacion,
        ao.Ruta_CV
    FROM
        aplicacion_oferta ao
    JOIN
        oferta_laboral oe ON ao.ID_Oferta = oe.ID_Oferta
    JOIN
        perfil_empresa pe ON oe.ID_perfil_empresa = pe.ID_Perfil_Empresa
    JOIN
        usuario u ON pe.usuario_ID_Usuario = u.ID_Usuario 
    WHERE
        ao.ID_Estudiante = ?
    ORDER BY
        ao.Fecha_Aplicacion DESC;
"; // Punto y coma que cierra la asignación de la cadena SQL

$stmt = null; // Inicializar para el bloque finally

try {
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        // En caso de un error en la consulta SQL, mostrar el error de MySQL
        throw new Exception('Error al preparar la consulta de aplicaciones: ' . $con->error);
    }

    $stmt->bind_param('i', $id_estudiante);
    $stmt->execute();
    $result = $stmt->get_result();

    $aplicaciones = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $aplicaciones[] = [
                'ID_Aplicacion' => $row['ID_Aplicacion'],
                'Puesto' => $row['Titulo_Puesto'],
                'Empresa' => $row['Nombre_Empresa'], // Usamos el alias 'Nombre_Empresa'
                'Fecha_Aplicacion' => $row['Fecha_Aplicacion'],
                'Estado_Aplicacion' => $row['Estado_Aplicacion'],
                'Carta_Presentacion' => $row['Carta_Presentacion'],
                'Ruta_CV' => $row['Ruta_CV']
            ];
        }
    }
    sendJsonResponse(true, 'Aplicaciones obtenidas exitosamente.', $aplicaciones);

} catch (Exception $e) {
    error_log("Error en obtener_aplicaciones_estudiante.php: " . $e->getMessage());
    sendJsonResponse(false, 'Ocurrió un error al obtener las aplicaciones.', [], $e->getMessage());
} finally {
    if ($stmt) {
        $stmt->close();
    }
    if (isset($con)) {
        $con->close();
    }
}
?>