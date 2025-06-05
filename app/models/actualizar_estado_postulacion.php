<?php
// Establece el encabezado para que el navegador sepa que la respuesta es JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php';

// Si $con no está definida o la conexión falló en conexion.php, el script debe terminar.
if (!isset($con) || $con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error: La conexión a la base de datos no está disponible.']);
    exit();
}

// Verifica que la solicitud sea POST y que los datos necesarios estén presentes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ID_Postulacion']) && isset($_POST['Estado_Postulacion'])) {
    // ID_Postulacion en el JS se mapea a ID_Aplicacion en la tabla aplicacion_oferta
    $idAplicacion = intval($_POST['ID_Postulacion']);
    // Estado_Postulacion en el JS se mapea a Estado_Aplicacion en la tabla aplicacion_oferta
    $nuevoEstado = $_POST['Estado_Postulacion'];

    // Validar el nuevo estado para asegurar que sea uno de los permitidos.
    // Estos estados deben coincidir exactamente con los valores definidos en tu columna
    // `Estado_Aplicacion` en la tabla `aplicacion_oferta` (considera mayúsculas/minúsculas si tu DB es sensible).
    $estadosPermitidos = ['Pendiente', 'Revisado', 'Aceptado', 'Rechazado'];
    if (!in_array($nuevoEstado, $estadosPermitidos)) {
        echo json_encode(['success' => false, 'message' => 'Estado de postulación no válido.']);
        exit();
    }

    // Prepara la consulta para actualizar el estado de la aplicación.
    // Usando el nombre de tabla correcto: aplicacion_oferta
    // Y los nombres de columna correctos: Estado_Aplicacion, ID_Aplicacion
    $stmt = $con->prepare("UPDATE aplicacion_oferta SET Estado_Aplicacion = ? WHERE ID_Aplicacion = ?");

    // Verifica si la preparación de la consulta falló y devuelve el error SQL.
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta SQL: ' . $con->error . '. SQL intentado: UPDATE aplicacion_oferta SET Estado_Aplicacion = ? WHERE ID_Aplicacion = ?']);
        exit();
    }

    // Vincula los parámetros a la sentencia preparada.
    $stmt->bind_param('si', $nuevoEstado, $idAplicacion);

    // Ejecuta la sentencia preparada.
    if ($stmt->execute()) {
        // Verifica si alguna fila fue afectada (es decir, si la actualización se realizó).
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Estado de postulación actualizado con éxito.']);
        } else {
            // Si no se afectaron filas, puede ser que la aplicación no exista o el estado ya era el mismo.
            echo json_encode(['success' => false, 'message' => 'No se encontró la aplicación o el estado ya era el mismo.']);
        }
    } else {
        // Si hubo un error en la ejecución, devuelve el error de MySQL.
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado de postulación: ' . $stmt->error]);
    }

    // Cierra la sentencia preparada.
    $stmt->close();
} else {
    // Si la solicitud no es POST o faltan datos, devuelve un mensaje de error.
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no permitido o datos incompletos.']);
}
// You might want to close the database connection here if it's not handled automatically by PHP at script end
// $con->close(); // Optional, as PHP usually closes it on script end
?>