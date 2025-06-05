<?php
session_start(); // ¡DEBE ESTAR AL PRINCIPIO DEL ARCHIVO!
header('Content-Type: application/json');

// Incluye tu archivo de conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php'; // ASEGÚRATE DE QUE ESTA RUTA ES CORRECTA

$response = array('success' => false, 'message' => '', 'data' => []);

try {
    // Verificar si la conexión a la base de datos es válida
    if (!isset($con) || !$con instanceof mysqli || $con->connect_error) {
        throw new Exception("Error de conexión a la base de datos.");
    }

    // *** VERIFICACIÓN DE SESIÓN PARA ESTUDIANTE ***
    // Usa las variables de sesión que realmente estableces en tu login.php
    // Asumo que:
    // - El ID del usuario está en $_SESSION['ID_Usuario']
    // - El rol del usuario está en $_SESSION['Nombre_Rol'] (ej. 'estudiante')
    if (!isset($_SESSION['ID_Usuario']) || strtolower($_SESSION['Nombre_Rol']) !== 'estudiante') {
        $response['message'] = 'Acceso denegado: Usuario no autenticado o no es un estudiante.';
        echo json_encode($response);
        exit(); // Termina el script si no hay sesión válida de estudiante
    }

    $id_usuario_logueado = $_SESSION['ID_Usuario']; // ID del usuario logueado desde la sesión

    $dashboardData = [];

    // Primero, obtener el ID_Perfil_Estudiante basado en el ID_Usuario
    // Asumo que tu tabla 'perfil_estudiante' tiene una columna 'ID_Usuario' que se enlaza a 'usuario.ID_Usuario'
    $stmt_perfil_estudiante = $con->prepare("SELECT ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = ?");
    if (!$stmt_perfil_estudiante) {
        throw new Exception("Error al preparar consulta de perfil de estudiante: " . $con->error);
    }
    $stmt_perfil_estudiante->bind_param('i', $id_usuario_logueado);
    $stmt_perfil_estudiante->execute();
    $resultado_perfil_estudiante = $stmt_perfil_estudiante->get_result();
    $perfil_estudiante = $resultado_perfil_estudiante->fetch_assoc();
    $stmt_perfil_estudiante->close();

    if (!$perfil_estudiante) {
        $response['message'] = 'Perfil de estudiante no encontrado para el usuario logueado. Asegúrate de que el usuario tiene un perfil de estudiante asociado.';
        echo json_encode($response);
        exit();
    }

    $id_perfil_estudiante = $perfil_estudiante['ID_Perfil_Estudiante']; // Este es el ID real del perfil de estudiante


    // 1. Obtener Ofertas Destacadas (o las 3 más recientes)
    // ¡CORREGIDO AQUÍ para que coincida con tu esquema de BD para la unión empresa-usuario!
    $stmtOfertas = $con->prepare("SELECT
                                    ol.ID_Oferta,
                                    ol.Titulo_Puesto,
                                    u.Nombre AS Nombre_Empresa
                                  FROM
                                    oferta_laboral ol
                                  JOIN
                                    perfil_empresa pe ON ol.ID_perfil_empresa = pe.ID_Perfil_Empresa
                                  JOIN
                                    usuario u ON pe.usuario_ID_Usuario = u.ID_Usuario -- ¡CORREGIDO! Usando 'usuario_ID_Usuario'
                                  ORDER BY ol.fecha_publicacion DESC LIMIT 3");

    if (!$stmtOfertas) {
        throw new Exception("Error al preparar consulta de ofertas: " . $con->error);
    }
    $stmtOfertas->execute();
    $resultadoOfertas = $stmtOfertas->get_result();
    $ofertasDestacadas = [];
    while ($fila = $resultadoOfertas->fetch_assoc()) {
        $ofertasDestacadas[] = $fila;
    }
    $stmtOfertas->close();
    $dashboardData['ofertas_destacadas'] = $ofertasDestacadas;

    // 2. Obtener Resumen de Aplicaciones del Estudiante
    // Usamos $id_perfil_estudiante, el ID del perfil de estudiante obtenido de la sesión.
    $stmtAplicaciones = $con->prepare("SELECT Estado_Aplicacion, COUNT(*) AS total
                                        FROM aplicacion_oferta
                                        WHERE ID_Estudiante = ?
                                        GROUP BY Estado_Aplicacion");
    if (!$stmtAplicaciones) {
        throw new Exception("Error al preparar consulta de aplicaciones: " . $con->error);
    }
    $stmtAplicaciones->bind_param('i', $id_perfil_estudiante); // ¡Usar el ID real del perfil de estudiante!
    $stmtAplicaciones->execute();
    $resultadoAplicaciones = $stmtAplicaciones->get_result();
    $resumenAplicaciones = [
        'Pendiente' => 0,
        'Revisado' => 0,
        'Aceptado' => 0,
        'Rechazado' => 0
    ];
    while ($fila = $resultadoAplicaciones->fetch_assoc()) {
        $estado = $fila['Estado_Aplicacion'];
        if (array_key_exists($estado, $resumenAplicaciones)) {
            $resumenAplicaciones[$estado] = $fila['total'];
        }
    }
    $stmtAplicaciones->close();
    $dashboardData['resumen_aplicaciones'] = $resumenAplicaciones;

    $response['success'] = true;
    $response['data'] = $dashboardData;

} catch (Exception $e) {
    error_log("Error en obtener_dashboard_estudiante.php: " . $e->getMessage()); // Para depuración
    $response['message'] = 'Error en el servidor: ' . $e->getMessage();
} finally {
    if (isset($con) && $con instanceof mysqli && $con->ping()) {
        $con->close();
    }
}

echo json_encode($response);
?>