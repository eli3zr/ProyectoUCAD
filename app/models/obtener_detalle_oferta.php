<?php
// C:\xampp\htdocs\Jobtrack_Ucad\app\models\obtener_detalle_oferta.php
header('Content-Type: application/json');

// Incluimos el archivo de conexión.
// Este archivo establecerá la conexión y la guardará en la variable $con.
// Si la conexión falla, conexion.php ya maneja la salida y el script se detendrá.
require_once __DIR__ . '/../config/conexion.php';

// Inicializamos la respuesta para enviar al cliente
$response = ['success' => false, 'message' => ''];

// Usamos directamente la variable $con que fue creada en conexion.php
// No necesitamos la comprobación 'if ($conn)' aquí porque conexion.php ya se encarga
// de salir si la conexión falla.
// La variable $con ya está disponible y contiene la conexión activa.
$conn = $con;

// Verificamos si se ha proporcionado un ID para obtener una oferta específica
if (isset($_GET['id']) && !empty($_GET['id'])) {
    // Obtener una oferta específica por ID
    $ofertaId = $_GET['id'];
    // Preparamos la consulta SQL para evitar inyecciones SQL
    // Se ha cambiado 'ofertas' a 'oferta_laboral' para que coincida con tu base de datos
    $stmt = $conn->prepare("SELECT * FROM oferta_laboral WHERE ID_Oferta = ?"); 
    
    // Verificamos si la preparación de la consulta fue exitosa
    if ($stmt === false) {
        $response['message'] = 'Error al preparar la consulta para obtener oferta por ID: ' . $conn->error;
    } else {
        // Vinculamos el parámetro ID a la consulta ('i' indica tipo entero)
        $stmt->bind_param("i", $ofertaId);
        // Ejecutamos la consulta
        $stmt->execute();
        // Obtenemos el resultado de la consulta
        $result = $stmt->get_result();

        // Verificamos si se encontró la oferta
        if ($result->num_rows > 0) {
            $oferta = $result->fetch_assoc(); // Obtenemos la fila como un array asociativo
            $response['success'] = true;
            $response['data'] = $oferta;
        } else {
            $response['message'] = 'Oferta no encontrada.';
        }
        $stmt->close(); // Cerramos el statement
    }

} else {
    // Lógica para obtener TODAS las ofertas o filtradas si no se proporciona un ID
    // Se ha cambiado 'ofertas' a 'oferta_laboral' para que coincida con tu base de datos
    $sql = "SELECT * FROM oferta_laboral WHERE 1"; // Consulta base para todas las ofertas
    $params = []; // Array para almacenar los parámetros de la consulta
    $types = ""; // String para almacenar los tipos de los parámetros (e.g., "ssi" para string, string, int)

    // Agregamos condiciones de filtro si se proporcionan en la URL
    if (isset($_GET['puesto']) && !empty($_GET['puesto'])) {
        $sql .= " AND Titulo_Puesto LIKE ?";
        $params[] = '%' . $_GET['puesto'] . '%';
        $types .= "s"; // 's' para string
    }
    if (isset($_GET['estado']) && !empty($_GET['estado'])) {
        $sql .= " AND estado = ?";
        $params[] = $_GET['estado'];
        $types .= "s"; // 's' para string
    }
    if (isset($_GET['fecha']) && !empty($_GET['fecha'])) {
        $sql .= " AND DATE(fecha_publicacion) = ?";
        $params[] = $_GET['fecha'];
        $types .= "s"; // 's' para string
    }

    // Preparamos la consulta SQL
    $stmt = $conn->prepare($sql);

    // Verificamos si la preparación de la consulta fue exitosa
    if ($stmt === false) {
        $response['message'] = 'Error al preparar la consulta para obtener ofertas: ' . $conn->error;
    } else {
        // Vinculamos los parámetros si existen
        if (!empty($params)) {
            // Usamos el operador de expansión (...) para pasar los elementos del array como argumentos separados
            $stmt->bind_param($types, ...$params);
        }
        // Ejecutamos la consulta
        $stmt->execute();
        // Obtenemos el resultado
        $result = $stmt->get_result();

        $ofertas = []; // Array para almacenar todas las ofertas
        // Recorremos los resultados y los añadimos al array
        while ($row = $result->fetch_assoc()) {
            $ofertas[] = $row;
        }
        $response['success'] = true;
        $response['data'] = $ofertas;
        $stmt->close(); // Cerramos el statement
    }
}

// Enviamos la respuesta JSON al cliente
echo json_encode($response);

// Cerramos la conexión a la base de datos al finalizar el script
// Esto es importante para liberar recursos
$conn->close();
?>
