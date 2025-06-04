<?php
// actualizar_oferta.php
// Este script recibe los datos de una oferta editada y los actualiza en la base de datos.

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON

// Incluimos el archivo de conexión a la base de datos.
// Asume que 'conexion.php' ya establece la conexión en la variable $con.
require_once __DIR__ . '/../config/conexion.php';

// Inicializamos la respuesta por defecto
$response = ['success' => false, 'message' => ''];

// Verificamos si la solicitud es de tipo POST y si se han recibido los datos necesarios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Usamos la conexión $con que viene de conexion.php
    $conn = $con;

    // Recogemos los datos enviados por el formulario
    // Es crucial que los 'name' de los inputs en el HTML del modal coincidan con estas claves
    $idOferta = $_POST['ID_Oferta'] ?? null;
    $tituloPuesto = $_POST['Titulo_Puesto'] ?? null;
    $descripcionTrabajo = $_POST['Descripción_Trabajo'] ?? null; // Asegúrate que el nombre de la columna es correcto
    $requisitos = $_POST['Requisitos'] ?? null;
    $salarioMinimo = $_POST['Salario_Minimo'] ?? null;
    $salarioMaximo = $_POST['Salario_Maximo'] ?? null;
    $modalidad = $_POST['Modalidad'] ?? null;
    $ubicacion = $_POST['Ubicación'] ?? null;
    $estado = $_POST['estado'] ?? null;

    // Validaciones básicas (puedes añadir más validaciones según tus necesidades)
    if (empty($idOferta) || empty($tituloPuesto) || empty($descripcionTrabajo) || empty($requisitos) || empty($modalidad) || empty($estado)) {
        $response['message'] = 'Faltan campos obligatorios para actualizar la oferta.';
    } else {
        // Preparamos la consulta SQL para actualizar la oferta
        // Usamos prepared statements para prevenir inyecciones SQL
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
            // Asegúrate del orden y tipo de los parámetros
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
$conn->close();

// Devolvemos la respuesta en formato JSON
echo json_encode($response);
?>
