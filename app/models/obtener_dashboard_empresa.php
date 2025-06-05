<?php
session_start(); // ¡IMPORTANTE! Iniciar la sesión para acceder a las variables de sesión
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php'; 

$response = array('success' => false, 'message' => '', 'data' => []);

try {
    // Verificar si la conexión a la base de datos es válida
    if (!isset($con) || !$con instanceof mysqli || $con->connect_error) {
        throw new Exception("Error de conexión a la base de datos.");
    }

    // Verificar si la sesión de empresa está iniciada y es válida
    if (!isset($_SESSION['ID_Usuario']) || strtolower($_SESSION['Nombre_Rol']) !== 'empresa' || !isset($_SESSION['ID_Perfil_Empresa'])) {
        throw new Exception("Acceso no autorizado, sesión de empresa no iniciada o ID de perfil de empresa no disponible en sesión.");
    }
    
    // Obtener el ID_Perfil_Empresa directamente de la sesión
    $id_perfil_empresa = $_SESSION['ID_Perfil_Empresa']; 

    $dashboardData = [];

    // 1. Contar Ofertas Activas de la Empresa
    // La columna para el estado de la oferta es 'estado' y el valor es 'activa' (minúsculas)
    $stmtOfertasActivas = $con->prepare("SELECT COUNT(*) AS total_ofertas_activas
                                         FROM oferta_laboral
                                         WHERE ID_perfil_empresa = ? AND estado = 'activa'"); 
    if (!$stmtOfertasActivas) {
        throw new Exception("Error al preparar consulta de ofertas activas: " . $con->error);
    }
    $stmtOfertasActivas->bind_param('i', $id_perfil_empresa);
    $stmtOfertasActivas->execute();
    $resultadoOfertasActivas = $stmtOfertasActivas->get_result();
    $filaOfertasActivas = $resultadoOfertasActivas->fetch_assoc();
    $dashboardData['ofertas_activas'] = $filaOfertasActivas['total_ofertas_activas'];
    $stmtOfertasActivas->close();

    // 2. Obtener Postulantes Recientes para las ofertas de la Empresa
    // Ahora, con los nombres de tablas y columnas confirmados:
    // - aplicacion_oferta tiene ID_Estudiante
    // - perfil_estudiante tiene ID_Perfil_Estudiante (su PK) y ID_Usuario (FK a tabla usuario)
    // - usuario tiene ID_Usuario, Nombre, Apellido
    $stmtPostulantes = $con->prepare("SELECT
                                        ao.ID_Aplicacion,
                                        ao.Fecha_Aplicacion,
                                        ol.Titulo_Puesto,
                                        pe.ID_Perfil_Estudiante,     -- Selecciona el ID del perfil del estudiante
                                        u.Nombre AS Nombre_Estudiante,
                                        u.Apellido AS Apellido_Estudiante
                                    FROM
                                        aplicacion_oferta ao
                                    JOIN
                                        oferta_laboral ol ON ao.ID_Oferta = ol.ID_Oferta
                                    JOIN
                                        perfil_estudiante pe ON ao.ID_Estudiante = pe.ID_Perfil_Estudiante -- JOIN: ao.ID_Estudiante (de aplicacion_oferta) con pe.ID_Perfil_Estudiante (de perfil_estudiante)
                                    JOIN
                                        usuario u ON pe.ID_Usuario = u.ID_Usuario -- JOIN: pe.ID_Usuario (de perfil_estudiante) con u.ID_Usuario (de usuario)
                                    WHERE
                                        ol.ID_perfil_empresa = ? AND ol.estado = 'activa'
                                    ORDER BY ao.Fecha_Aplicacion DESC
                                    LIMIT 3"); 
    if (!$stmtPostulantes) {
        throw new Exception("Error al preparar consulta de postulantes: " . $con->error);
    }
    $stmtPostulantes->bind_param('i', $id_perfil_empresa);
    $stmtPostulantes->execute();
    $resultadoPostulantes = $stmtPostulantes->get_result();
    $postulantesRecientes = [];
    while ($fila = $resultadoPostulantes->fetch_assoc()) {
        $postulantesRecientes[] = $fila;
    }
    $stmtPostulantes->close();
    $dashboardData['postulantes_recientes'] = $postulantesRecientes;

    $response['success'] = true;
    $response['data'] = $dashboardData;

} catch (Exception $e) {
    $response['message'] = 'Excepción: ' . $e->getMessage();
    error_log("Error en obtener_dashboard_empresa.php: " . $e->getMessage()); // Siempre es bueno loguear
} finally {
    if (isset($con) && $con instanceof mysqli && $con->ping()) {
        $con->close();
    }
}

echo json_encode($response);
?>