<?php
// app/models/registrar_empresa.php

// Incluir el archivo de conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php'; 

header('Content-Type: application/json');

$response = ['success' => false, 'msg' => '', 'error' => ''];

if (!$con) {
    $response['error'] = 'Error al conectar con la base de datos.';
    echo json_encode($response);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Quité los error_log extensos para simplificar la respuesta, pero son útiles para depurar.

switch ($action) {
    case 'get_categorias':
        try {
            $stmt = $con->prepare("SELECT id_categoria, Nombre_Categoria FROM categoria ORDER BY Nombre_Categoria ASC");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta de categorías: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $response = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $response = ['success' => false, 'error' => 'Error interno del servidor al cargar las categorías.'];
            // http_response_code(500); // Puedes reintroducir esto si quieres manejar HTTP status codes
        }
        break;

    // Puedes mantener o quitar los casos para paises, departamentos, municipios, distritos si no los usas aquí.
    // Los dejé para que el PHP sea completo, asumiendo que los usas en alguna parte.
    case 'get_departamentos':
        $paisId = filter_var($_GET['paisId'] ?? '', FILTER_VALIDATE_INT);
        if ($paisId === false || $paisId <= 0) {
            $response = ['success' => false, 'error' => 'ID de país inválido.'];
            echo json_encode($response);
            exit();
        }
        try {
            $stmt = $con->prepare("SELECT id_departamento, nombre_departamento FROM departamento WHERE pais_id_pais = ? AND estado = 'activo' ORDER BY nombre_departamento ASC");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta de departamentos: " . $con->error);
            }
            $stmt->bind_param("i", $paisId);
            $stmt->execute();
            $result = $stmt->get_result();
            $response = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $response = ['success' => false, 'error' => 'Error interno del servidor al cargar los departamentos.'];
        }
        break;

    case 'get_municipios':
        $departamentoId = filter_var($_GET['departamentoId'] ?? '', FILTER_VALIDATE_INT);
        if ($departamentoId === false || $departamentoId <= 0) {
            $response = ['success' => false, 'error' => 'ID de departamento inválido.'];
            echo json_encode($response);
            exit();
        }
        try {
            $stmt = $con->prepare("SELECT id_municipio, municipio FROM municipio WHERE departamento_id_departamento = ? AND estado = 'activo' ORDER BY municipio ASC");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta de municipios: " . $con->error);
            }
            $stmt->bind_param("i", $departamentoId);
            $stmt->execute();
            $result = $stmt->get_result();
            $response = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $response = ['success' => false, 'error' => 'Error interno del servidor al cargar los municipios.'];
        }
        break;

    case 'get_distritos':
        $municipioId = filter_var($_GET['municipioId'] ?? '', FILTER_VALIDATE_INT);
        if ($municipioId === false || $municipioId <= 0) {
            $response = ['success' => false, 'error' => 'ID de municipio inválido.'];
            echo json_encode($response);
            exit();
        }
        try {
            $stmt = $con->prepare("SELECT id_distrito, nombre_distrito FROM distrito WHERE municipio_id_municipio = ? AND estado = 'activo' ORDER BY nombre_distrito ASC");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta de distritos: " . $con->error);
            }
            $stmt->bind_param("i", $municipioId);
            $stmt->execute();
            $result = $stmt->get_result();
            $response = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $response = ['success' => false, 'error' => 'Error interno del servidor al cargar los distritos.'];
        }
        break;

    case 'registro_empresa':
        $nombre = filter_var($_POST['nombre'] ?? '', FILTER_UNSAFE_RAW);
        $telefono = filter_var($_POST['telefono'] ?? '', FILTER_UNSAFE_RAW);
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $categoria_id = filter_var($_POST['categoria'] ?? '', FILTER_VALIDATE_INT);
        $pais_id = filter_var($_POST['pais'] ?? '', FILTER_VALIDATE_INT);
        $departamento_id = filter_var($_POST['departamento'] ?? '', FILTER_VALIDATE_INT);
        $municipio_id = filter_var($_POST['municipio'] ?? '', FILTER_VALIDATE_INT);
        $distrito_id = filter_var($_POST['distrito'] ?? '', FILTER_VALIDATE_INT);
        $clave = $_POST['clave'] ?? '';
        $repetirClave = $_POST['repetirClave'] ?? '';
        $terminos = ($_POST['terminos'] ?? 'false') === 'true';
        $notificaciones = ($_POST['notificaciones'] ?? 'false') === 'true';

        $errors = [];

        // Validaciones básicas que ya tenías
        if (empty($nombre)) $errors[] = 'El nombre es obligatorio.';
        if (empty($telefono)) $errors[] = 'El teléfono es obligatorio.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido.';
        if ($categoria_id === false || $categoria_id <= 0) $errors[] = 'Seleccione una categoría válida.';
        if ($pais_id === false || $pais_id <= 0) $errors[] = 'Seleccione un país válido.';
        if ($departamento_id === false || $departamento_id <= 0) $errors[] = 'Seleccione un departamento válido.';
        if ($municipio_id === false || $municipio_id <= 0) $errors[] = 'Seleccione un municipio válido.';
        if ($distrito_id === false || $distrito_id <= 0) $errors[] = 'Seleccione un distrito válido.';
        if (empty($clave) || strlen($clave) < 8) $errors[] = 'La clave debe tener al menos 8 caracteres.';
        if ($clave !== $repetirClave) $errors[] = 'Las claves no coinciden.';
        if (!$terminos) $errors[] = 'Debe aceptar los términos y condiciones.';

        if (!empty($errors)) {
            $response['error'] = implode("\n", $errors);
            echo json_encode($response);
            exit();
        }

        $con->begin_transaction();

        try {
            // 1. Verificar si el email ya existe en la tabla 'usuario'
            $stmt = $con->prepare("SELECT ID_Usuario FROM usuario WHERE Correo_Electronico = ?");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta de verificación de email: " . $con->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                // **CAMBIO CLAVE AQUÍ:** Mensaje de error específico para el JS
                $response['error'] = 'El correo electrónico ya está registrado.';
                $response['success'] = false; // Asegúrate de que sea false
                echo json_encode($response);
                $stmt->close();
                $con->rollback(); // Revertir cualquier transacción abierta si se sale aquí
                exit(); // Salir para no continuar con la inserción
            }
            $stmt->close();

            // 2. Insertar en la tabla 'usuario'
            $rol_empresa_id = 1; // Asegúrate que '1' es el ID correcto para el rol 'empresa'
            $estado_usuario = 'Activo'; 
            
            $stmt = $con->prepare("INSERT INTO usuario (Nombre, Apellido, Correo_Electronico, ID_Rol_FK, estado_us) VALUES (?, ?, ?, ?, ?)");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la inserción de usuario: " . $con->error);
            }
            $apellido_empresa = ''; 
            if (!$stmt->bind_param("sssis", $nombre, $apellido_empresa, $email, $rol_empresa_id, $estado_usuario)) {
                throw new Exception("Error al vincular parámetros para usuario: " . $stmt->error);
            }
            if (!$stmt->execute()) {
                throw new Exception("Error al ejecutar inserción de usuario: " . $stmt->error);
            }
            $usuario_id = $con->insert_id;
            $stmt->close();

            if (!$usuario_id) {
                throw new Exception("No se pudo obtener el ID del usuario insertado.");
            }

            // 3. Insertar en 'contrasenas' con password_hash()
            $clave_hash = password_hash($clave, PASSWORD_DEFAULT); 
            $stmt = $con->prepare("INSERT INTO contrasenas (ID_Usuario, Contrasena_Hash) VALUES (?, ?)"); 
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la inserción de contraseña: " . $con->error);
            }
            if (!$stmt->bind_param("is", $usuario_id, $clave_hash)) { 
                throw new Exception("Error al vincular parámetros para contraseña: " . $stmt->error);
            }
            if (!$stmt->execute()) {
                throw new Exception("Error al ejecutar inserción de contraseña: " . $stmt->error);
            }
            $stmt->close();

            // 4. Insertar en 'perfil_empresa'
            $descripcion_empresa = ''; 
            $foto_perfil = NULL; 
            
            $stmt = $con->prepare("INSERT INTO perfil_empresa (ID_Categoria, Descripción, Foto_Perfil, usuario_ID_Usuario) VALUES (?, ?, ?, ?)");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la inserción de perfil_empresa: " . $con->error);
            }
            
            if (!$stmt->bind_param("issi", $categoria_id, $descripcion_empresa, $foto_perfil, $usuario_id)) { 
                throw new Exception("Error al vincular parámetros para perfil_empresa: " . $stmt->error);
            }
            if (!$stmt->execute()) {
                throw new Exception("Error al ejecutar inserción de perfil_empresa: " . $stmt->error);
            }
            $id_empresa_insertada = $con->insert_id;
            $stmt->close();

            if (!$id_empresa_insertada) {
                throw new Exception("No se pudo obtener el ID del perfil de empresa insertado.");
            }

            // 5. Insertar en 'contactos_empresa'
            $stmt = $con->prepare("INSERT INTO contactos_empresa (ID_Empresa, Teléfono) VALUES (?, ?)"); 
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la inserción de contacto: " . $con->error);
            }
            if (!$stmt->bind_param("is", $id_empresa_insertada, $telefono)) { 
                throw new Exception("Error al vincular parámetros para contacto: " . $stmt->error);
            }
            if (!$stmt->execute()) {
                throw new Exception("Error al ejecutar inserción de contacto: " . $stmt->error);
            }
            $stmt->close();

            // Si todas las operaciones fueron exitosas, confirma la transacción
            $con->commit();
            $response['success'] = true;
            $response['msg'] = 'Empresa registrada exitosamente.';

        } catch (Exception $e) {
            // Si ocurre cualquier error, revierte la transacción
            $con->rollback();
            $response['error'] = 'Error interno del servidor al registrar la empresa: ' . $e->getMessage();
            // No cambiamos el http_response_code aquí para mantenerlo simple.
        } finally {
            if ($con) {
                $con->close();
            }
        }
        break;

    default:
        $response['error'] = 'Acción no válida.';
        // http_response_code(400);
        break;
}

echo json_encode($response);
?>