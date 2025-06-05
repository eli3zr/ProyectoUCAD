<?php
// get_ofertas_publicas.php
// Este script obtiene todas las ofertas de empleo activas desde la base de datos.

// Configuración de cabeceras para permitir CORS y especificar JSON
header("Access-Control-Allow-Origin: *"); // Permite cualquier origen (ajustar en producción a dominios específicos si es necesario)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET"); // Solo permite solicitudes GET
header("Access-Control-Max-Age: 3600"); // Tiempo de caché para solicitudes pre-vuelo de CORS
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Habilitar visualización de errores para depuración (¡IMPORTANTE: DESHABILITAR EN PRODUCCIÓN!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluye el archivo de conexión a la base de datos.
// Desde 'app/models/', subimos dos niveles (a 'Jobtrack_Ucad/') y luego entramos a 'config/conexion.php'.
require_once '../config/conexion.php';

// Prepara la respuesta por defecto que se enviará al frontend
$response = [
    'success' => false,
    'message' => '',
    'ofertas' => [] // Este array contendrá los datos de las ofertas
];

// Solo procesa si la solicitud es GET (como debería ser para obtener datos)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verifica que la conexión a la base de datos sea exitosa.
    // Asumimos que $con es la variable de conexión definida en conexion.php.
    if (!isset($con) || !$con) {
        $response['message'] = 'Error de conexión a la base de datos.';
        // Para depuración, puedes añadir más detalles del error de conexión si $con existe:
        if (isset($con) && mysqli_connect_error()) {
            $response['message'] .= ' Detalle: ' . mysqli_connect_error();
        }
        error_log("Error de conexión en get_ofertas_publicas.php: " . $response['message']);
        echo json_encode($response);
        exit(); // Detiene la ejecución si no hay conexión.
    }

    // Consulta SQL para obtener las ofertas activas.
    // Ajusta los nombres de las tablas y columnas según tu base de datos.
    // ASUMIMOS:
    // - Tabla `oferta_laboral` con campos como ID_Oferta, Titulo_Puesto, Descripcion_Trabajo, Requisitos, etc.
    // - Tabla `perfil_empresa` con ID_perfil_empresa, nombre_empresa, categoria_interes.
    // - Relación entre 'oferta_laboral' y 'perfil_empresa' por 'ID_perfil_empresa'.
    // - 'Requisitos' es una cadena de texto con habilidades separadas por comas (ej: "HTML,CSS,JavaScript").
        $sql = "SELECT 
                ol.ID_Oferta, 
                ol.Titulo_Puesto, 
                ol.Descripción_Trabajo, 
                ol.Requisitos, 
                ol.Salario_Minimo, 
                ol.Salario_Maximo, 
                ol.Modalidad, 
                ol.Ubicación, 
                ol.fecha_publicacion, 
                ol.estado,
                u.Nombre AS nombre_empresa_oferente,             
                cat.Nombre_Categoria AS categoria_interes_empresa 
            FROM 
                oferta_laboral ol
            JOIN 
                perfil_empresa pe ON ol.ID_perfil_empresa = pe.ID_Perfil_Empresa 
            JOIN
                usuario u ON pe.usuario_ID_Usuario = u.ID_Usuario  
            LEFT JOIN
                categoria cat ON pe.ID_Categoria = cat.id_categoria
            WHERE 
                ol.estado = 'activa'
            ORDER BY ol.fecha_publicacion DESC";
    
    $result = mysqli_query($con, $sql);

    if ($result) {
        // Si la consulta fue exitosa, procesa los resultados.
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                // Formatea los datos para que coincidan con la estructura que tu JavaScript espera
                $oferta_formateada = [
                    "id" => $row['ID_Oferta'],
                    "titulo" => $row['Titulo_Puesto'],
                    "empresa" => $row['nombre_empresa_oferente'],
                    "vigencia" => ($row['estado'] === 'activa' ? 'activo' : 'inactivo'), // Mapea 'activa' de BD a 'activo' de JS
                    "oferente" => $row['nombre_empresa_oferente'],
                    "interes" => $row['categoria_interes_empresa'] ?? 'General', // Usa 'General' si el campo es NULL o no existe
                    "habilidad" => explode(",", $row['Requisitos']), // Convierte la cadena de habilidades a un array
                    "descripcion" => $row['Descripción_Trabajo'],
                    "linkDetalle" => "../views/oferta_detalle.html?id=" . $row['ID_Oferta'], // Enlace al detalle de la oferta
                    "salario_minimo" => $row['Salario_Minimo'],
                    "salario_maximo" => $row['Salario_Maximo'],
                    "modalidad" => $row['Modalidad'],
                    "ubicacion" => $row['Ubicación']
                ];
                array_push($response['ofertas'], $oferta_formateada);
            }
            $response['success'] = true;
            $response['message'] = 'Ofertas obtenidas exitosamente.';
        } else {
            $response['success'] = true; // La consulta fue exitosa, pero no encontró resultados.
            $response['message'] = 'No se encontraron ofertas activas.';
        }
    } else {
        // Si hubo un error en la ejecución de la consulta SQL.
        $response['message'] = 'Error en la ejecución de la consulta SQL: ' . mysqli_error($con);
        error_log("Error SQL en get_ofertas_publicas.php: " . mysqli_error($con));
    }

    // Cierra la conexión a la base de datos
    mysqli_close($con);

} else {
    // Si la solicitud no es GET, devuelve un error.
    $response['message'] = 'Método de solicitud no permitido. Solo se acepta GET.';
    http_response_code(405); // Método no permitido
}

// Envía la respuesta final en formato JSON
echo json_encode($response);
?>