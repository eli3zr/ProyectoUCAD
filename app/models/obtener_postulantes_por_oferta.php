<?php
// Establece el encabezado para que el navegador sepa que la respuesta es JSON
header('Content-Type: application/json');

// Comprueba si la solicitud HTTP es de tipo GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
require_once __DIR__ . '/../config/conexion.php';

    // Si $con no está definida o la conexión falló en conexion.php, el script debe terminar.
    if (!isset($con) || $con->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Error: La conexión a la base de datos no está disponible.']);
        exit();
    }

    // Obtiene el ID de la oferta de los parámetros GET.
    // Si no se proporciona o es inválido, será 0.
       $ofertaId = isset($_GET['oferta_id']) ? intval($_GET['oferta_id']) : 0;

    $postulantes = []; // Array to store applicant data

    // Prepare the base SQL query with correct table and column names
    // 'ao' for aplicacion_oferta, 'u' for usuario, 'ol' for oferta_laboral
    $sql = "
        SELECT
            ao.ID_Aplicacion AS ID_Postulacion,            -- Application ID, mapped for consistency in JS
            u.ID_Usuario,
            CONCAT(u.Nombre, ' ', u.Apellido) AS NombrePostulante, -- Concatenate Name and Apellido for full name
            u.Correo_Electronico AS Email,                 -- Map Correo_Electronico to Email
            u.ID_Rol_FK,                                   -- User Role ID (to display in profile)
            u.estado_us,                                   -- User status (to display in profile)
            ao.Fecha_Aplicacion AS Fecha_Postulacion,      -- Application Date
            ao.Estado_Aplicacion AS Estado_Postulacion,    -- Application Status
            ao.Ruta_CV AS CV_Path,                         -- CV Path (INCLUDED AGAIN)
            ol.Titulo_Puesto AS TituloOferta,              -- Job Offer Title
            ol.ID_Oferta as ID_Oferta_Relacionada          -- Related Job Offer ID
        FROM
            aplicacion_oferta ao                           -- Applications table
        JOIN
            usuario u ON ao.ID_Estudiante = u.ID_Usuario   -- User table (students), joined by ID_Estudiante
        JOIN
            oferta_laboral ol ON ao.ID_Oferta = ol.ID_Oferta -- Job Offers table
    ";

    $params = []; // Array for prepared statement parameters
    $types = '';  // String for parameter types

    // Add the WHERE clause if a valid offer ID is provided to filter
    if ($ofertaId > 0) {
        $sql .= " WHERE ao.ID_Oferta = ?";
        $params[] = $ofertaId;
        $types .= 'i'; // 'i' for integer
    }

    // Order the results by application date in descending order
    $sql .= " ORDER BY ao.Fecha_Aplicacion DESC";

    // Prepare the SQL statement
    $stmt = $con->prepare($sql);

    // Verify if query preparation failed and return the SQL error
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Error preparing the SQL query: ' . $con->error . '. SQL attempted: ' . $sql]);
        exit();
    }

    // If there are parameters to bind, bind them
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    // Execute the query
    $stmt->execute();

    // Get the query results
    $result = $stmt->get_result();

    // Check if rows were found
    if ($result->num_rows > 0) {
        // Iterate over each result row and add it to the applicants array
        while ($row = $result->fetch_assoc()) {
            $postulantes[] = $row;
        }
        // If there are applicants, send a JSON response with success and data
        echo json_encode(['success' => true, 'data' => $postulantes]);
    } else {
        // If no applicants were found, send a success JSON response but with an empty array
        echo json_encode(['success' => false, 'message' => 'No applicants found for this offer or in general.', 'data' => []]);
    }

    // Close the prepared statement.
    $stmt->close();
} else {
    // If the request is not GET, return a JSON error message
    $response = array(
        'success' => false,
        'message' => 'Access not allowed. This script only accepts GET requests.'
    );
    echo json_encode($response);
}
?>