<?php
// C:\xampp\htdocs\Jobtrack_Ucad\app\models\guardar_aplicacion.php

// Configuración de encabezados para API REST
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start(); // Inicia la sesión para obtener el ID del estudiante

// Configuración de errores (cambiar a 0 en producción para no mostrar errores al usuario)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir la conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php';

// --- Función para generar una respuesta JSON estandarizada y terminar la ejecución ---
// Se define aquí porque es el script principal que envía respuestas.
function sendJsonResponse($success, $message, $error = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'error' => $error
    ]);
    exit();
}

// Verificar que la conexión a la BD sea válida (ya manejado en conexion.php, pero es una doble verificación)
if (!isset($con) || $con->connect_error) {
    sendJsonResponse(false, 'Error de conexión a la base de datos. (Verifique conexion.php)');
}

// Solo procesar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Iniciar una transacción de base de datos para asegurar atomicidad
    $con->autocommit(false);
    $cv_uploaded_file_path = null; // Variable para mantener la ruta del archivo si algo falla

    try {
        // 1. Validar y obtener el ID del estudiante de la sesión
        if (!isset($_SESSION['ID_Usuario']) || empty($_SESSION['ID_Usuario'])) {
            throw new Exception('No se ha iniciado sesión como estudiante. Por favor, inicie sesión.');
        }
        $id_usuario = (int)$_SESSION['ID_Usuario']; // Casteo a entero para seguridad

        // 2. Obtener o crear el ID_Perfil_Estudiante asociado a este ID_Usuario
        $id_perfil_estudiante = null;
        $stmt_get_perfil_id = $con->prepare("SELECT ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = ?");
        if (!$stmt_get_perfil_id) {
            throw new Exception('Error al preparar la consulta para obtener ID de perfil de estudiante: ' . $con->error);
        }
        $stmt_get_perfil_id->bind_param('i', $id_usuario);
        $stmt_get_perfil_id->execute();
        $stmt_get_perfil_id->bind_result($id_perfil_estudiante);
        $stmt_get_perfil_id->fetch();
        $stmt_get_perfil_id->close();

        // Si no se encontró un perfil de estudiante, créalo automáticamente con valores por defecto
        if (is_null($id_perfil_estudiante)) {
            $sql_insert_perfil = "INSERT INTO perfil_estudiante (ID_Usuario, Nombre_Completo_Estudiante) VALUES (?, ?)";
            $stmt_insert_perfil = $con->prepare($sql_insert_perfil);
            if (!$stmt_insert_perfil) {
                throw new Exception('Error al preparar la consulta de inserción de perfil: ' . $con->error);
            }
            $default_nombre_completo = "Estudiante " . $id_usuario;
            $stmt_insert_perfil->bind_param('is', $id_usuario, $default_nombre_completo);

            if (!$stmt_insert_perfil->execute()) {
                throw new Exception('Error al crear un perfil de estudiante por defecto: ' . $stmt_insert_perfil->error);
            }
            $id_perfil_estudiante = $con->insert_id; // Obtiene el ID_Perfil_Estudiante recién insertado
            $stmt_insert_perfil->close();
        }

        // 3. Obtener y sanear datos de la oferta y el mensaje
        $id_oferta = isset($_POST['id_oferta']) ? (int)$_POST['id_oferta'] : 0;
        $mensaje_adicional = isset($_POST['mensaje']) ? trim($_POST['mensaje']) : null; // Eliminar espacios al inicio/final

        if ($id_oferta <= 0) {
            throw new Exception('ID de oferta no proporcionado o no válido.');
        }

        // 4. Lógica para el CV (Integrada aquí, solo si se sube un archivo con esta aplicación)
        $ruta_cv_para_db = null; // Inicializar a null. Solo se asignará si se sube un CV.

        if (isset($_FILES['cvFile']) && $_FILES['cvFile']['error'] === UPLOAD_ERR_OK) {
            $fileData = $_FILES['cvFile'];
            $fileName = basename($fileData['name']); // `basename` para evitar inyección de ruta
            $fileTmpName = $fileData['tmp_name'];
            $fileSize = $fileData['size'];
            $fileType = $fileData['type'];

            // Validaciones de archivo
            $maxFileSize = 2 * 1024 * 1024; // 2MB en bytes
            if ($fileSize > $maxFileSize) {
                throw new Exception('El archivo CV es demasiado grande. Máximo 2MB.');
            }
            $allowedExtensions = ['pdf', 'doc', 'docx'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($fileExt, $allowedExtensions)) {
                throw new Exception('Formato de archivo CV no permitido. Permitidos: PDF, DOC, DOCX.');
            }

            // Mover el archivo a la carpeta de CVs públicos
            $uploadDir = __DIR__ . '/../../public/cvs/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Error al crear el directorio de CVs. Verifique permisos.');
                }
            }
            $newFileName = uniqid('cv_app_', true) . '.' . $fileExt; // Nombre único para CVs de aplicación
            $cv_uploaded_file_path = $uploadDir . $newFileName; // Ruta completa en servidor
            $ruta_cv_para_db = '/public/cvs/' . $newFileName; // Ruta relativa para DB

            if (!move_uploaded_file($fileTmpName, $cv_uploaded_file_path)) {
                throw new Exception('Error al mover el archivo CV al servidor.');
            }

            // Insertar la información del CV en la tabla cv_estudiante (cada subida es una nueva entrada)
            $fileSizeKB = round($fileSize / 1024); // Convertir bytes a KB
            $stmt_cv_insert = $con->prepare("INSERT INTO cv_estudiante (perfil_estudiante_ID_Perfil_Estudiante, RutaArchivoCV, NombreOriginalArchivo, TipoMime, TamanoArchivoKB, FechaSubida) VALUES (?, ?, ?, ?, ?, NOW())");
            if (!$stmt_cv_insert) {
                throw new Exception('Error al preparar la consulta de inserción de CV: ' . $con->error);
            }
            $stmt_cv_insert->bind_param('isssi', $id_perfil_estudiante, $ruta_cv_para_db, $fileName, $fileType, $fileSizeKB);
            if (!$stmt_cv_insert->execute()) {
                // Si la inserción del CV falla, eliminar el archivo que ya se movió
                if (file_exists($cv_uploaded_file_path)) {
                    unlink($cv_uploaded_file_path);
                }
                throw new Exception('Error al guardar el CV en la base de datos: ' . $stmt_cv_insert->error);
            }
            $stmt_cv_insert->close();
        }
        // Nota: Si no se sube un CV, $ruta_cv_para_db permanecerá null y se insertará como NULL en aplicacion_oferta

        // 5. Insertar la aplicación en la tabla 'aplicacion_oferta'
        $fecha_aplicacion = date('Y-m-d H:i:s');
        $estado_aplicacion = 'Pendiente'; // Estado inicial de la aplicación

        // **IMPORTANTE: Se asume que la columna 'Ruta_CV' ya existe en tu tabla 'aplicacion_oferta'**
        $stmt_aplicacion = $con->prepare("INSERT INTO aplicacion_oferta (ID_Oferta, ID_Estudiante, Fecha_Aplicacion, Estado_Aplicacion, Carta_Presentacion, Ruta_CV) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt_aplicacion === false) {
            throw new Exception('Error en la preparación de la consulta de aplicación: ' . $con->error);
        }
        // Usamos $id_usuario para ID_Estudiante en aplicacion_oferta
        $stmt_aplicacion->bind_param("iissss", $id_oferta, $id_usuario, $fecha_aplicacion, $estado_aplicacion, $mensaje_adicional, $ruta_cv_para_db);

        if (!$stmt_aplicacion->execute()) {
            throw new Exception('Error al guardar la aplicación en la base de datos: ' . $stmt_aplicacion->error);
        }
        $stmt_aplicacion->close();

        $con->commit(); // Confirmar la transacción si todo el proceso (CV + Aplicación) fue exitoso
        sendJsonResponse(true, 'Aplicación enviada y CV procesado exitosamente.');

    } catch (Exception $e) {
        $con->rollback(); // Deshacer todos los cambios en la base de datos si ocurre cualquier error

        // Si el CV se subió al servidor pero la transacción falló después, bórralo
        if (isset($cv_uploaded_file_path) && file_exists($cv_uploaded_file_path)) {
            unlink($cv_uploaded_file_path);
        }

        error_log("Error crítico en guardar_aplicacion.php: " . $e->getMessage()); // Loguea el error real en el servidor
        // En producción, evita mostrar $e->getMessage() directamente al usuario para no revelar detalles internos.
        sendJsonResponse(false, 'Ocurrió un error al procesar tu aplicación. Por favor, inténtalo de nuevo.', $e->getMessage());
    } finally {
        // Restaurar el modo autocommit de la conexión a la base de datos y cerrarla
        if (isset($con)) {
            $con->autocommit(true);
            $con->close();
        }
    }
} else {
    // Si la solicitud no es POST, enviar una respuesta de error
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.');
}
?>