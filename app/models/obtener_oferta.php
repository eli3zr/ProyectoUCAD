<?php
// obtener_oferta.php
// Este script obtiene datos de ofertas de la base de datos, con opciones de filtrado.

// Habilitar la visualización de errores para depuración (QUITAR EN PRODUCCIÓN)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión si no está iniciada (debe ser la primera línea ejecutable después de error_reporting)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON

// --- INICIO DEPURACIÓN DE SESIÓN (para entender el contexto) ---
error_log("----------------------------------------------------");
error_log("--- DEBUG DE SESIÓN EN obtener_oferta.php ---");
error_log("Timestamp: " . date('Y-m-d H:i:s'));
error_log("Contenido de \$_SESSION al iniciar obtener_oferta.php: " . print_r($_SESSION, true));
error_log("----------------------------------------------------");
// --- FIN DEPURACIÓN DE SESIÓN ---

// Incluimos el archivo de conexión a la base de datos.
// Este archivo DEBE establecer la conexión en la variable global $con.
require_once __DIR__ . '/../config/conexion.php';

// *******************************************************************
// CORRECCIÓN: Usar directamente la variable global $con que tu conexion.php ya define
// NO INTENTAMOS LLAMAR A getConexion() porque no está definida en tu conexion.php
// *******************************************************************

// Verificamos que la variable global $con esté disponible y sea un objeto de conexión
if (!isset($con) || !is_object($con) || mysqli_connect_errno()) {
    error_log("FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida después de incluir conexion.php.");
    $response['success'] = false;
    $response['message'] = 'Error interno del servidor: La conexión a la base de datos no está disponible.';
    echo json_encode($response);
    exit(); // Terminar la ejecución si la conexión no es válida
}

// Inicializamos la respuesta por defecto
$response = ['success' => false, 'message' => ''];

// Usamos la conexión $con que viene de conexion.php
$conn = $con;

// Obtener el ID de oferta específica si se envía (para edición)
$idOferta = $_GET['id'] ?? null;

// Si se solicita una oferta específica por ID
if ($idOferta !== null) {
    $sql = "SELECT ID_Oferta, Titulo_Puesto, Descripción_Trabajo, Requisitos, Salario_Minimo, Salario_Maximo, Modalidad, Ubicación, fecha_publicacion, estado 
            FROM oferta_laboral 
            WHERE ID_Oferta = ?";
    
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        $response['message'] = 'Error al preparar la consulta para oferta específica: ' . $conn->error;
    } else {
        $stmt->bind_param("i", $idOferta);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $oferta = $result->fetch_assoc();
            if ($oferta) {
                $response['success'] = true;
                $response['data'] = $oferta;
                $response['message'] = 'Oferta específica obtenida exitosamente.';
            } else {
                $response['message'] = 'Oferta no encontrada con el ID proporcionado.';
            }
        } else {
            $response['message'] = 'Error al ejecutar la consulta para oferta específica: ' . $stmt->error;
        }
        $stmt->close();
    }

} else { // Si no se solicita un ID específico, obtener todas las ofertas para la empresa logueada (con filtros opcionales)
    
    // Obtener el ID_perfil_empresa de la sesión
    $id_perfil_empresa = $_SESSION['ID_perfil_empresa'] ?? null;

    // Validación de que el ID de la empresa esté disponible en la sesión para la LISTA de ofertas
    if ($id_perfil_empresa === null || $id_perfil_empresa <= 0) {
        http_response_code(401); // 401 Unauthorized
        $response = [
            'success' => false,
            'message' => 'No autorizado. Por favor, inicia sesión para ver las ofertas de tu empresa.'
        ];
        // No salimos aquí con exit(), sino que dejamos que el script termine de forma controlada
        // para que se envíe la respuesta JSON al final.
    } else {
        // Obtener filtros de la solicitud GET
        $filtroPuesto = $_GET['puesto'] ?? '';
        $filtroEstado = $_GET['estado'] ?? '';
        $filtroFecha = $_GET['fecha'] ?? '';

        // Construir la consulta base para las ofertas de la empresa
        $sql = "SELECT ID_Oferta, Titulo_Puesto, Descripción_Trabajo, Requisitos, Salario_Minimo, Salario_Maximo, Modalidad, Ubicación, fecha_publicacion, estado 
                FROM oferta_laboral 
                WHERE ID_perfil_empresa = ?";
        $params = [$id_perfil_empresa];
        $types = "i";

        // Aplicar filtros adicionales
        if (!empty($filtroPuesto)) {
            $sql .= " AND Titulo_Puesto LIKE ?";
            $params[] = "%" . $filtroPuesto . "%";
            $types .= "s";
        }
        if (!empty($filtroEstado)) {
            $sql .= " AND estado = ?";
            $params[] = $filtroEstado;
            $types .= "s";
        }
        if (!empty($filtroFecha)) {
            $sql .= " AND DATE(fecha_publicacion) = ?";
            $params[] = $filtroFecha;
            $types .= "s";
        }

        $sql .= " ORDER BY fecha_publicacion DESC";

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $response['message'] = 'Error al preparar la consulta para lista de ofertas: ' . $conn->error;
        } else {
            // Usar call_user_func_array para bind_param con un número variable de argumentos
            $bind_names = [$types];
            for ($i = 0; $i < count($params); $i++) {
                $bind_name = 'param' . $i;
                $$bind_name = &$params[$i]; // Crear variables por referencia
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_names);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $ofertas = [];
                while ($row = $result->fetch_assoc()) {
                    $ofertas[] = $row;
                }
                $response['success'] = true;
                $response['data'] = $ofertas;
                $response['message'] = 'Ofertas obtenidas exitosamente.';
            } else {
                $response['message'] = 'Error al ejecutar la consulta para lista de ofertas: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Cerramos la conexión a la base de datos si está abierta
if ($conn && $conn->ping()) {
    $conn->close();
}

echo json_encode($response);
?>
