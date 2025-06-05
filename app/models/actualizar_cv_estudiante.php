<?php
// C:\xampp\htdocs\Jobtrack_Ucad\app\models\actualizar_cv_estudiante.php

// Iniciar sesión si no está iniciada (necesario para $_SESSION['ID_Usuario'])
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir la conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php';

// --- Función para generar una respuesta JSON estandarizada y terminar la ejecución ---
// Se define aquí para ser usada en este script.
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($success, $message, $error = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'msg' => $message, // 'msg' es usado para consistencia con tu código original
            'error' => $error
        ]);
        exit();
    }
}

// Verificar que la conexión a la BD sea válida (ya manejado en conexion.php, pero es una doble verificación)
if (!isset($con) || $con->connect_error) {
    sendJsonResponse(false, 'Error de conexión a la base de datos. (Verifique conexion.php)');
}

// Solo procesar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar que el usuario esté logueado
    if (!isset($_SESSION['ID_Usuario']) || empty($_SESSION['ID_Usuario'])) {
        sendJsonResponse(false, 'Usuario no autenticado. Por favor, inicie sesión.');
    }
    $idUsuario = (int)$_SESSION['ID_Usuario']; // Casteo a entero para seguridad

    // 2. Validar que se recibió el archivo 'cvNuevo' (nombre del input file en el formulario HTML)
    if (!isset($_FILES['cvNuevo']) || $_FILES['cvNuevo']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'No se recibió ningún archivo CV o hubo un error durante la subida.';
        if (isset($_FILES['cvNuevo'])) {
             // Detalle del error de PHP si está disponible
            switch ($_FILES['cvNuevo']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error_msg = 'El archivo CV es demasiado grande (excede el límite del servidor/formulario).';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_msg = 'El archivo CV solo se subió parcialmente.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_msg = 'No se seleccionó ningún archivo CV.';
                    break;
                default:
                    $error_msg = 'Error desconocido al subir el archivo CV.';
                    break;
            }
        }
        sendJsonResponse(false, $error_msg, 'Código de error: ' . ($_FILES['cvNuevo']['error'] ?? 'N/A'));
    }

    $fileData = $_FILES['cvNuevo'];
    $fileName = basename($fileData['name']); // `basename` para evitar inyección de ruta
    $fileTmpName = $fileData['tmp_name'];
    $fileSize = $fileData['size'];
    $fileType = $fileData['type'];

    // Iniciar transacción de base de datos
    $con->autocommit(false);
    $cv_uploaded_file_path = null; // Variable para mantener la ruta del archivo si algo falla

    try {
        // 3. Validaciones de seguridad y negocio (tamaño y tipo de archivo)
        $maxFileSize = 2 * 1024 * 1024; // 2MB en bytes
        if ($fileSize > $maxFileSize) {
            throw new Exception('El archivo CV es demasiado grande. El tamaño máximo permitido es 2MB.');
        }

        $allowedExtensions = ['pdf', 'doc', 'docx'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExtensions)) {
            throw new Exception('Formato de archivo no permitido. Los formatos permitidos son PDF, DOC, DOCX.');
        }

        // 4. Obtener ID_Perfil_Estudiante asociado al ID_Usuario (crearlo si no existe)
        $idPerfilEstudiante = null;
        $stmt_get_perfil_id = $con->prepare("SELECT ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = ?");
        if (!$stmt_get_perfil_id) {
            throw new Exception('Error al preparar la consulta para obtener ID de perfil de estudiante: ' . $con->error);
        }
        $stmt_get_perfil_id->bind_param('i', $idUsuario);
        $stmt_get_perfil_id->execute();
        $stmt_get_perfil_id->bind_result($idPerfilEstudiante);
        $stmt_get_perfil_id->fetch();
        $stmt_get_perfil_id->close();

        if (is_null($idPerfilEstudiante)) {
            // Si el perfil no existe, se crea uno básico automáticamente
            $stmt_insert_perfil = $con->prepare("INSERT INTO perfil_estudiante (ID_Usuario, Nombre_Completo_Estudiante) VALUES (?, ?)");
            if (!$stmt_insert_perfil) {
                throw new Exception('Error al preparar la consulta de inserción de perfil de estudiante: ' . $con->error);
            }
            $default_nombre_completo = "Estudiante " . $idUsuario; // Nombre por defecto
            $stmt_insert_perfil->bind_param('is', $idUsuario, $default_nombre_completo);

            if (!$stmt_insert_perfil->execute()) {
                throw new Exception('Error al crear un perfil de estudiante por defecto: ' . $stmt_insert_perfil->error);
            }
            $idPerfilEstudiante = $con->insert_id; // Obtiene el ID del perfil recién creado
            $stmt_insert_perfil->close();
        }

        // Si después de todo aún no tenemos un ID de perfil, algo está mal
        if (is_null($idPerfilEstudiante)) {
            throw new Exception('No se pudo obtener o crear el perfil del estudiante necesario para el CV.');
        }

        // 5. Definir la ruta de almacenamiento del archivo en el servidor y moverlo
        $uploadDir = __DIR__ . '/../../public/cvs/';
        // Asegurarse de que el directorio exista y tenga permisos de escritura
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) { // 0755: permisos de lectura/escritura/ejecución para propietario, lectura/ejecución para grupo y otros
                throw new Exception('Error interno del servidor. No se pudo crear el directorio de subida de CVs. Verifique los permisos.');
            }
        }
        $newFileName = uniqid('cv_', true) . '.' . $fileExt; // Nombre de archivo único para evitar colisiones
        $cv_uploaded_file_path = $uploadDir . $newFileName; // Ruta completa en el servidor
        $relativePathForDB = '/public/cvs/' . $newFileName; // Ruta relativa para guardar en la base de datos

        if (!move_uploaded_file($fileTmpName, $cv_uploaded_file_path)) {
            throw new Exception('No se pudo mover el archivo CV subido al destino final. Verifique permisos y rutas.');
        }

        // 6. Insertar la información del CV en la base de datos (cada subida crea una nueva entrada)
        $fileSizeKB = round($fileSize / 1024); // Convertir bytes a KB
        $stmt_cv_insert = $con->prepare("INSERT INTO cv_estudiante (perfil_estudiante_ID_Perfil_Estudiante, RutaArchivoCV, NombreOriginalArchivo, TipoMime, TamanoArchivoKB, FechaSubida) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt_cv_insert) {
            throw new Exception('Error al preparar la consulta de inserción de CV: ' . $con->error);
        }
        $stmt_cv_insert->bind_param('isssi', $idPerfilEstudiante, $relativePathForDB, $fileName, $fileType, $fileSizeKB);

        if (!$stmt_cv_insert->execute()) {
            throw new Exception('Error al guardar el CV en la base de datos: ' . $stmt_cv_insert->error);
        }
        $stmt_cv_insert->close();

        $con->commit(); // Confirmar la transacción si todo el proceso fue exitoso
        sendJsonResponse(true, 'Tu CV ha sido subido exitosamente.');

    } catch (Exception $e) {
        $con->rollback(); // Deshacer cualquier cambio en la base de datos si ocurre un error

        // Si el archivo ya se movió al servidor y hubo un error en la DB, eliminar el archivo
        if (isset($cv_uploaded_file_path) && file_exists($cv_uploaded_file_path)) {
            unlink($cv_uploaded_file_path);
        }

        error_log("Error en actualizar_cv_estudiante.php: " . $e->getMessage()); // Loguear el error real
        // Devolver un mensaje de error genérico al usuario en producción
        sendJsonResponse(false, 'Ocurrió un error al procesar tu CV. Por favor, inténtalo de nuevo más tarde.', $e->getMessage());
    } finally {
        // Restaurar el modo autocommit y cerrar la conexión
        if (isset($con)) {
            $con->autocommit(true);
            $con->close();
        }
    }
} else {
    // Si la solicitud no es POST, devolver un error
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.');
}
?>