<?php
// actualizar_oferta.php
// Este script recibe los datos de una oferta editada y los actualiza en la base de datos.

// Habilitar la visualización de errores para depuración (QUITAR EN PRODUCCIÓN)
error_reporting(E_ALL); 
ini_set('display_errors', 1); 
// Configurar un archivo de log específico para este script
ini_set('error_log', __DIR__ . '/../logs/actualizar_oferta_errors.log');

// Iniciar la sesión (debe ser lo primero)
session_start();

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON

// Incluimos el archivo de conexión a la base de datos y el helper de bitácora.
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// Inicializamos la respuesta por defecto
$response = ['success' => false, 'message' => ''];

// Obtener el ID de usuario de la sesión para la bitácora. Usar 0 si no está logueado.
$usuario_id = $_SESSION['ID_Usuario'] ?? 0;

// Verificamos que la variable global $con esté disponible y sea un objeto de conexión
if (!isset($con) || !is_object($con) || mysqli_connect_errno()) {
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida después de incluir conexion.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg);
    $response['success'] = false;
    $response['message'] = 'Error interno del servidor: La conexión a la base de datos no está disponible.';
    // No podemos bitacorar aquí si la conexión falló
    echo json_encode($response);
    exit(); // Terminar la ejecución si la conexión no es válida
}

// Usamos la conexión $con que viene de conexion.php
$conn = $con; // $conn ahora es el objeto de conexión válido


// Verificamos si la solicitud es de tipo POST y si se han recibido los datos necesarios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recogemos los datos enviados por el formulario
    // Es crucial que los 'name' de los inputs en el HTML del modal coincidan con estas claves
    $idOferta = $_POST['ID_Oferta'] ?? null;
    $tituloPuesto = $_POST['Titulo_Puesto'] ?? null;
    $descripcionTrabajo = $_POST['Descripción_Trabajo'] ?? null; 
    $requisitos = $_POST['Requisitos'] ?? null;
    $salarioMinimo = $_POST['Salario_Minimo'] ?? null;
    $salarioMaximo = $_POST['Salario_Maximo'] ?? null;
    $modalidad = $_POST['Modalidad'] ?? null;
    $ubicacion = $_POST['Ubicación'] ?? null;
    $estado = $_POST['estado'] ?? null;

    // Validaciones básicas (puedes añadir más validaciones según tus necesidades)
    if (empty($idOferta) || empty($tituloPuesto) || empty($descripcionTrabajo) || empty(trim($requisitos)) || empty($modalidad) || empty($estado)) {
        $response['message'] = 'Faltan campos obligatorios para actualizar la oferta.';
        // Consistente: LOGIN_FALLIDO para errores de validación de formulario
        registrarEventoBitacora($conn, $idOferta ?? 0, 'oferta_laboral', 'LOGIN_FALLIDO', $usuario_id, NULL, 'Actualización de oferta fallida: Campos obligatorios vacíos.');
    } else {
        // --- BITACOLA: Obtener datos antes de la actualización en 'oferta_laboral' ---
        $datos_antes = null;
        $stmt_get_antes = $conn->prepare("SELECT Titulo_Puesto, Descripción_Trabajo, Requisitos, Salario_Minimo, Salario_Maximo, Modalidad, Ubicación, estado FROM oferta_laboral WHERE ID_Oferta = ? LIMIT 1");
        if ($stmt_get_antes) {
            $stmt_get_antes->bind_param("i", $idOferta);
            $stmt_get_antes->execute();
            $result_get_antes = $stmt_get_antes->get_result();
            $datos_antes_array = $result_get_antes->fetch_assoc();
            $stmt_get_antes->close();
            if ($datos_antes_array) {
                $datos_antes = json_encode($datos_antes_array);
            }
        } else {
            error_log("Error al preparar consulta de datos antes de la actualización de oferta: " . $conn->error);
            // Registrar error al obtener datos para bitácora (no fatal, se puede continuar)
            registrarEventoBitacora($conn, $idOferta ?? 0, 'oferta_laboral', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al obtener datos previos de oferta para bitácora: ' . $conn->error);
        }

        // Preparamos la consulta SQL para actualizar la oferta
        $sql = "UPDATE oferta_laboral SET 
                    Titulo_Puesto = ?, 
                    Descripción_Trabajo = ?, 
                    Requisitos = ?, 
                    Salario_Minimo = ?, 
                    Salario_Maximo = ?, 
                    Modalidad = ?, 
                    Ubicación = ?, 
                    estado = ? 
                WHERE ID_Oferta = ?";

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $response['message'] = 'Error al preparar la consulta de actualización: ' . $conn->error;
            // Registrar ERROR_SISTEMA al preparar la consulta
            registrarEventoBitacora($conn, $idOferta ?? 0, 'oferta_laboral', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar la consulta de actualización de oferta: ' . $conn->error);
        } else {
            // Vinculamos los parámetros
            // 's' para string, 'd' para double (decimal), 'i' para integer
            $stmt->bind_param(
                "sssddsssi", // Tipos de datos: 3 strings, 2 doubles, 2 strings, 1 string, 1 integer
                $tituloPuesto,
                $descripcionTrabajo,
                $requisitos,
                $salarioMinimo,
                $salarioMaximo,
                $modalidad,
                $ubicacion,
                $estado,
                $idOferta
            );

            // Ejecutamos la consulta
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Oferta actualizada exitosamente.';
                    // --- BITACOLA: Registrar UPDATE exitoso ---
                    $datos_despues = extraerDatosParaBitacora($conn, $idOferta, 'oferta_laboral');
                    registrarEventoBitacora($conn, $idOferta, 'oferta_laboral', 'UPDATE', $usuario_id, $datos_antes, $datos_despues);
                } else {
                    // Esto puede ocurrir si el ID no existe o si no se realizaron cambios en los datos.
                    $response['message'] = 'La oferta no fue encontrada o no se realizaron cambios.';
                    // Registrar ADVERTENCIA si no se encuentran cambios o la oferta no existe
                    registrarEventoBitacora($conn, $idOferta, 'oferta_laboral', 'ADVERTENCIA', $usuario_id, NULL, 'Actualización de oferta: No se realizaron cambios o oferta no encontrada (ID: ' . $idOferta . ').');
                }
            } else {
                // Error al ejecutar la consulta
                $response['message'] = 'Error al ejecutar la actualización: ' . $stmt->error;
                // Registrar ERROR_SISTEMA al ejecutar la actualización
                registrarEventoBitacora($conn, $idOferta, 'oferta_laboral', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al ejecutar la actualización de oferta: ' . $stmt->error);
            }

            $stmt->close(); // Cerramos el statement
        }
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
    // Registrar ADVERTENCIA si el método HTTP no es POST
    registrarEventoBitacora($conn, 0, 'sistema', 'ADVERTENCIA', $usuario_id, NULL, 'Método HTTP no permitido en actualizar_oferta.php: ' . $_SERVER['REQUEST_METHOD']);
}

// Cerramos la conexión a la base de datos
if (isset($conn) && is_object($conn)) { 
    $conn->close();
}

// Devolvemos la respuesta en formato JSON
echo json_encode($response);
?>