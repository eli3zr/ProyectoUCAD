<?php
// get_oferta_por_id.php
// Este script obtiene una oferta de empleo específica por su ID.

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/conexion.php';

$response = [
    'success' => false,
    'message' => '',
    'oferta' => null 
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $id_oferta = mysqli_real_escape_string($con, $_GET['id']);

        if (!isset($con) || !$con) {
            $response['message'] = 'Error de conexión a la base de datos: ' . (isset($con) ? mysqli_connect_error() : 'Variable $con no definida.');
            error_log("Error de conexión en get_oferta_por_id.php: " . $response['message']);
            echo json_encode($response);
            exit();
        }

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
                    ol.ID_Oferta = '$id_oferta'
                LIMIT 1";

        $result = mysqli_query($con, $sql);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $oferta_formateada = [
                    "id" => $row['ID_Oferta'],
                    "titulo" => $row['Titulo_Puesto'],
                    "empresa" => $row['nombre_empresa_oferente'], 
                    "vigencia" => ($row['estado'] === 'activa' ? 'activo' : 'inactivo'), 
                    "oferente" => $row['nombre_empresa_oferente'],
                    "interes" => $row['categoria_interes_empresa'] ?? 'General', 
                    "habilidad" => explode(",", $row['Requisitos']), 
                    "descripcion" => $row['Descripción_Trabajo'] ?? null, 
                    "salario_minimo" => $row['Salario_Minimo'],
                    "salario_maximo" => $row['Salario_Maximo'],
                    "modalidad" => $row['Modalidad'],
                    "ubicacion" => $row['Ubicación'],
                    "fecha_publicacion" => $row['fecha_publicacion'] // Añadido para la página de detalle
                ];
                $response['success'] = true;
                $response['message'] = 'Oferta obtenida exitosamente.';
                $response['oferta'] = $oferta_formateada;
            } else {
                $response['success'] = false; 
                $response['message'] = 'No se encontró la oferta con el ID proporcionado.';
            }
        } else {
            $response['message'] = 'Error en la ejecución de la consulta SQL: ' . mysqli_error($con);
            error_log("Error SQL en get_oferta_por_id.php: " . mysqli_error($con));
        }

        mysqli_close($con);

    } else {
        $response['message'] = 'ID de oferta no proporcionado en la URL.';
        http_response_code(400); 
    }
} else {
    $response['message'] = 'Método de solicitud no permitido. Solo se acepta GET.';
    http_response_code(405); 
}

echo json_encode($response);
?>