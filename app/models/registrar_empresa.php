<?php
// App/models/registro_empresas.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/conexion.php'; // Asegúrate de que este path es correcto y que $con es global o está disponible

$response = [];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_categorias':
        try {
            // Se asume que $con está disponible desde conexion.php
            $stmt = $con->prepare("SELECT ID_Categoria, Nombre_Categoria FROM categoria ORDER BY ID_Categoria ASC");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta de categorías: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result(); // Obtener el resultado
            $response = $result->fetch_all(MYSQLI_ASSOC); // Obtener todos los resultados como array asociativo
            $stmt->close(); // Cerrar el statement
        } catch (Exception $e) {
            error_log("Error al obtener categorías: " . $e->getMessage());
            $response = ['success' => false, 'error' => 'Error interno del servidor al cargar las categorías: ' . $e->getMessage()];
            http_response_code(500);
        }
        break;

    case 'get_departamentos':
        if (isset($_GET['paisId'])) {
            $paisId = (int)$_GET['paisId'];
            try {
                // MySQLi: Usando $con y placeholders '?'
                $stmt = $con->prepare("SELECT id_departamento, nombre_departamento FROM departamento WHERE pais_id_pais = ? AND estado = 'activo' ORDER BY nombre_departamento ASC");
                if ($stmt === false) {
                    throw new Exception("Error en la preparación de la consulta de departamentos: " . $con->error);
                }
                $stmt->bind_param('i', $paisId); // 'i' para entero (integer)
                $stmt->execute();
                $result = $stmt->get_result();
                $response = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            } catch (Exception $e) {
                error_log("Error al obtener departamentos: " . $e->getMessage());
                $response = ['success' => false, 'error' => 'Error interno del servidor al cargar los departamentos: ' . $e->getMessage()];
                http_response_code(500);
            }
        } else {
            $response = ['success' => false, 'error' => 'ID de país no proporcionado para obtener departamentos.'];
            http_response_code(400);
        }
        break;

    case 'get_municipios':
        if (isset($_GET['departamentoId'])) {
            $departamentoId = (int)$_GET['departamentoId'];
            try {
                // MySQLi: Usando $con y placeholders '?'
                $stmt = $con->prepare("SELECT id_municipio, municipio FROM municipio WHERE departamento_id_departamento = ? AND estado = 'activo' ORDER BY municipio ASC");
                if ($stmt === false) {
                    throw new Exception("Error en la preparación de la consulta de municipios: " . $con->error);
                }
                $stmt->bind_param('i', $departamentoId); // 'i' para entero
                $stmt->execute();
                $result = $stmt->get_result();
                $response = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            } catch (Exception $e) {
                error_log("Error al obtener municipios: " . $e->getMessage());
                $response = ['success' => false, 'error' => 'Error interno del servidor al cargar los municipios: ' . $e->getMessage()];
                http_response_code(500);
            }
        } else {
            $response = ['success' => false, 'error' => 'ID de departamento no proporcionado para obtener municipios.'];
            http_response_code(400);
        }
        break;

    case 'get_distritos':
        if (isset($_GET['municipioId'])) {
            $municipioId = (int)$_GET['municipioId'];
            try {
                // MySQLi: Usando $con y placeholders '?'
                $stmt = $con->prepare("SELECT id_distrito, nombre_distrito FROM distrito WHERE municipio_id_municipio = ? AND estado = 'activo' ORDER BY nombre_distrito ASC");
                if ($stmt === false) {
                    throw new Exception("Error en la preparación de la consulta de distritos: " . $con->error);
                }
                $stmt->bind_param('i', $municipioId); // 'i' para entero
                $stmt->execute();
                $result = $stmt->get_result();
                $response = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            } catch (Exception $e) {
                error_log("Error al obtener distritos: " . $e->getMessage());
                $response = ['success' => false, 'error' => 'Error interno del servidor al cargar los distritos: ' . $e->getMessage()];
                http_response_code(500);
            }
        } else {
            $response = ['success' => false, 'error' => 'ID de municipio no proporcionado para obtener distritos.'];
            http_response_code(400);
        }
        break;

    case 'registro_empresa':
        $datos = $_POST;

        if (
            empty($datos['nombre']) ||
            empty($datos['telefono']) ||
            empty($datos['email']) ||
            empty($datos['categoria']) ||
            empty($datos['pais']) ||
            empty($datos['departamento']) ||
            empty($datos['municipio']) ||
            empty($datos['distrito']) ||
            empty($datos['clave']) ||
            empty($datos['repetirClave']) ||
            !isset($datos['terminos']) || $datos['terminos'] !== 'true'
        ) {
            $response = [
                'success' => false,
                'error' => 'Todos los campos obligatorios deben ser completados y los términos deben ser aceptados.'
            ];
        } elseif (!preg_match('/^[0-9]{8}$/', $datos['telefono'])) {
            $response = [
                'success' => false,
                'error' => 'El número de teléfono debe tener 8 dígitos.'
            ];
        } elseif ($datos['clave'] !== $datos['repetirClave']) {
            $response = [
                'success' => false,
                'error' => 'Las claves no coinciden.'
            ];
        } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $response = [
                'success' => false,
                'error' => 'El correo electrónico no es válido.'
            ];
        } else {
            try {
                $sql = "INSERT INTO empresas (nombre, telefono, email, ID_Categoria, pais_id, departamento_id, municipio_id, distrito_id, clave, notificaciones, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')";
                $stmt = $con->prepare($sql);
                if ($stmt === false) {
                    throw new Exception("Error en la preparación de la consulta de registro de empresa: " . $con->error);
                }

                $claveHash = password_hash($datos['clave'], PASSWORD_DEFAULT);
                $notificaciones = ($datos['notificaciones'] === 'true') ? 1 : 0;

                // bind_param: la primera cadena indica los tipos de cada parámetro:
                // s = string, i = integer, d = double, b = blob
                // Orden: nombre(s), telefono(s), email(s), ID_Categoria(i), pais_id(i), departamento_id(i), municipio_id(i), distrito_id(i), clave(s), notificaciones(i)
                $stmt->bind_param(
                    'sssiisiiis',
                    $datos['nombre'],
                    $datos['telefono'],
                    $datos['email'],
                    $datos['categoria'], // Asumiendo que es INT
                    $datos['pais'],      // Asumiendo que es INT
                    $datos['departamento'], // Asumiendo que es INT
                    $datos['municipio'], // Asumiendo que es INT
                    $datos['distrito'],  // Asumiendo que es INT
                    $claveHash,
                    $notificaciones // Asumiendo que es INT (0 o 1)
                );

                $stmt->execute();
                $stmt->close(); // Cerrar el statement

                $response = [
                    'success' => true,
                    'msg' => 'Tu empresa se ha registrado correctamente.'
                ];

            } catch (Exception $e) {
                error_log("Error al registrar empresa: " . $e->getMessage());
                $response = [
                    'success' => false,
                    'error' => 'Error interno del servidor al registrar la empresa: ' . $e->getMessage()
                ];
                http_response_code(500);
            }
        }
        break;

    default:
        $response = ['success' => false, 'error' => 'Acción no válida o no especificada.'];
        http_response_code(400);
        break;
}

echo json_encode($response);
?>