<?php
// actualizar_oferta.php
// Este script recibe los datos de una oferta editada y los actualiza en la base de datos.

// Habilitar la visualización de errores para depuración (QUITAR EN PRODUCCIÓN)
error_reporting(E_ALL); 
ini_set('display_errors', 1); 
// Configurar un archivo de log específico para este script
ini_set('error_log', __DIR__ . '/../logs/actualizar_oferta_errors.log');

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON

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

// Verificamos si la solicitud es de tipo POST y si se han recibido los datos necesarios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Usamos la conexión $con que viene de conexion.php
    $conn = $con; // $conn ahora es el objeto de conexión válido

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
        // Añadido trim() para requisitos, ya que puede ser solo espacios en blanco
        $response['message'] = 'Faltan campos obligatorios para actualizar la oferta.';
    } else {
        // Preparamos la consulta SQL para actualizar la oferta
        // Usamos prepared statements para prevenir inyecciones SQL
        // Asegúrate que 'oferta_laboral' es el nombre correcto de tu tabla.
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
            // Error al preparar la consulta
            $response['message'] = 'Error al preparar la consulta de actualización: ' . $conn->error;
        } else {
            // Vinculamos los parámetros
            // 's' para string, 'd' para double (decimal), 'i' para integer
            // Asegúrate del orden y tipo de los parámetros coinciden con la consulta SQL.
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
                } else {
                    // Esto puede ocurrir si el ID no existe o si no se realizaron cambios en los datos.
                    $response['message'] = 'La oferta no fue encontrada o no se realizaron cambios.';
                }
            } else {
                // Error al ejecutar la consulta
                $response['message'] = 'Error al ejecutar la actualización: ' . $stmt->error;
            }

            $stmt->close(); // Cerramos el statement
        }
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

// Cerramos la conexión a la base de datos
// Ahora $conn debería ser un objeto MySQLi válido
if (isset($conn) && is_object($conn)) { // Verificación adicional para robustez
    $conn->close();
}

// Devolvemos la respuesta en formato JSON
echo json_encode($response);
?>
