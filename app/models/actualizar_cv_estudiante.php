<?php
// app/models/actualizar_cv_estudiante.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Asegúrate de que la ruta a tu archivo de conexión sea correcta
// Asumo que 'conexion.php' establece una variable $con que es una instancia de mysqli.
require_once __DIR__ . '/../config/conexion.php'; 

// Asegúrate de que la conexión a la BD sea válida antes de continuar
if (!isset($con) || $con->connect_error) {
    // Si la conexión falla aquí, enviamos la respuesta y salimos
    sendJsonResponse(false, 'Error de conexión a la base de datos.', 'No se pudo establecer conexión con la base de datos.');
}

$response = []; // Inicializa la variable $response

// --- Función para generar una respuesta JSON estandarizada y terminar la ejecución ---
// Esta función se encargará de establecer el encabezado y el echo.
function sendJsonResponse($success, $message, $error = null) {
    header('Content-Type: application/json'); // Asegurarse del header
    echo json_encode([
        'success' => $success,
        'msg' => $message,
        'error' => $error
    ]);
    exit(); // Termina la ejecución del script inmediatamente
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_successful = false; // Bandera para controlar la transacción

    // --- Iniciar la transacción ---
    $con->autocommit(false); 

    try {
        // --- 1. Validar la existencia del archivo subido ---
        if (!isset($_FILES['cvNuevo']) || $_FILES['cvNuevo']['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = 'Error al subir el archivo.';
            switch ($_FILES['cvNuevo']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage = 'El archivo es demasiado grande (límites del servidor/formulario).';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage = 'El archivo no se subió completamente.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMessage = 'No se seleccionó ningún archivo.';
                    break;
                default:
                    $errorMessage = 'Ocurrió un error desconocido durante la subida.';
                    break;
            }
            throw new Exception($errorMessage . ' (Código: ' . $_FILES['cvNuevo']['error'] . ')');
        }

        $file = $_FILES['cvNuevo'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileType = $file['type']; // Tipo MIME reportado por el navegador

        // --- 2. Validaciones de seguridad y negocio (esenciales en el servidor) ---

        // a. Tamaño del archivo (2MB = 2 * 1024 * 1024 bytes)
        $maxFileSize = 2 * 1024 * 1024;
        if ($fileSize > $maxFileSize) {
            throw new Exception('El archivo es demasiado grande. El tamaño máximo permitido para el CV es 2MB.');
        }

        // b. Tipo de archivo (validar MIME types para mayor seguridad)
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword', // .doc
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // .docx
        ];
        if (!in_array($fileType, $allowedMimeTypes)) {
            throw new Exception('Formato de archivo no permitido. Formatos de archivo permitidos: PDF, DOC, DOCX.');
        }

        // c. Extensión del archivo (adicional a la validación MIME)
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx'];
        if (!in_array($fileExt, $allowedExtensions)) {
            throw new Exception('Extensión de archivo no permitida. Las extensiones permitidas son PDF, DOC, DOCX.');
        }

        // --- 3. Obtener el ID del estudiante de forma SEGURA (desde la sesión) ---
        if (!isset($_SESSION['id_usuario'])) { 
            throw new Exception('Usuario no autenticado. Por favor, inicie sesión de nuevo.');
        }
        $idUsuario = $_SESSION['id_usuario']; // Usar 'id_usuario' como en tu ejemplo

        // Buscar el ID_Perfil_Estudiante asociado a este ID_Usuario.
        $idPerfilEstudiante = null;
        $sql_get_perfil_id = "SELECT ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = ?";
        $stmt_get_perfil_id = $con->prepare($sql_get_perfil_id);

        if (!$stmt_get_perfil_id) {
            throw new Exception('Error al preparar la consulta para obtener ID de perfil de estudiante: ' . $con->error);
        }
        $stmt_get_perfil_id->bind_param('i', $idUsuario);
        $stmt_get_perfil_id->execute();
        $stmt_get_perfil_id->bind_result($idPerfilEstudiante);
        $stmt_get_perfil_id->fetch();
        $stmt_get_perfil_id->close();

        if (is_null($idPerfilEstudiante)) {
            throw new Exception('No se encontró un perfil de estudiante asociado a este usuario.');
        }

        // --- 4. Definir la ruta de almacenamiento del archivo en el servidor ---
        $uploadDir = __DIR__ . '/../../public/cvs/'; 

        // Crear el directorio si no existe
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Error interno del servidor. No se pudo crear el directorio de subida de CVs. Verifique permisos.');
            }
        }

        // --- 5. Generar un nombre de archivo único ---
        $newFileName = uniqid('cv_', true) . '.' . $fileExt;
        $filePath = $uploadDir . $newFileName;
        $relativePathForDB = '/public/cvs/' . $newFileName; 

        // --- 6. Mover el archivo subido a su destino final ---
        if (!move_uploaded_file($fileTmpName, $filePath)) {
            throw new Exception('No se pudo mover el archivo subido al destino final. Verifique permisos y rutas.');
        }

        // --- 7. Actualizar/Insertar la información del CV en la base de datos ---
        $sql_check_exists = "SELECT ID_cv_estudiante, RutaArchivoCV FROM cv_estudiante WHERE perfil_estudiante_ID_Perfil_Estudiante = ?";
        $stmt_check_exists = $con->prepare($sql_check_exists);
        if (!$stmt_check_exists) {
            throw new Exception('Error al preparar la consulta de verificación de CV existente: ' . $con->error);
        }
        $stmt_check_exists->bind_param('i', $idPerfilEstudiante);
        $stmt_check_exists->execute();
        $stmt_check_exists->bind_result($existing_cv_id, $existing_cv_ruta);
        $stmt_check_exists->fetch();
        $stmt_check_exists->close();

        if ($existing_cv_id) {
            // Ya existe un CV, así que lo actualizamos
            $sql_cv_action = "UPDATE cv_estudiante
                                SET RutaArchivoCV = ?,
                                    NombreOriginalArchivo = ?,
                                    TipoMime = ?,
                                    TamanoArchivoKB = ?,
                                    FechaSubida = NOW()
                                WHERE ID_cv_estudiante = ?";
            $stmt_cv_action = $con->prepare($sql_cv_action);
            if (!$stmt_cv_action) {
                throw new Exception('Error al preparar la consulta de actualización de CV: ' . $con->error);
            }
            $fileSizeKB = round($fileSize / 1024);
            $stmt_cv_action->bind_param('sssii', $relativePathForDB, $fileName, $fileType, $fileSizeKB, $existing_cv_id);

            // Opcional: Eliminar el archivo antiguo del servidor
            if (!empty($existing_cv_ruta)) {
                $oldFilePath = __DIR__ . '/../..' . $existing_cv_ruta; 
                if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                    unlink($oldFilePath); 
                }
            }

        } else {
            // No existe un CV, así que insertamos uno nuevo
            $sql_cv_action = "INSERT INTO cv_estudiante (perfil_estudiante_ID_Perfil_Estudiante, RutaArchivoCV, NombreOriginalArchivo, TipoMime, TamanoArchivoKB, FechaSubida)
                                VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt_cv_action = $con->prepare($sql_cv_action);
            if (!$stmt_cv_action) {
                throw new Exception('Error al preparar la consulta de inserción de CV: ' . $con->error);
            }
            $fileSizeKB = round($fileSize / 1024);
            $stmt_cv_action->bind_param('isssi', $idPerfilEstudiante, $relativePathForDB, $fileName, $fileType, $fileSizeKB);
        }
        
        // Ejecutar la consulta SQL (UPDATE o INSERT)
        if (!$stmt_cv_action->execute()) {
            throw new Exception('Error al ejecutar la acción del CV en la base de datos: ' . $stmt_cv_action->error);
        }
        $stmt_cv_action->close();

        $con->commit(); // Confirmar la transacción si todo salió bien
        $transaction_successful = true;

        $response = [
            'success' => true,
            'msg' => 'Tu CV ha sido actualizado exitosamente.'
        ];

    } catch (Exception $e) {
        // Bloque CATCH para cualquier excepción lanzada
        $con->rollback(); // Deshacer cualquier cambio en la base de datos
        
        // Si el archivo ya se movió al servidor antes de la excepción, elimínalo
        if (isset($filePath) && file_exists($filePath)) {
            unlink($filePath);
        }
        
        error_log("Error al actualizar CV: " . $e->getMessage()); // Loguea el error real
        $response = [
            'success' => false,
            'error' => $e->getMessage(), // Muestra el mensaje de la excepción al usuario
            'msg' => 'Ocurrió un error al procesar tu CV.'
        ];

    } finally {
        // Restaurar el autocommit al finalizar, independientemente del resultado
        if (isset($con)) {
            $con->autocommit(true); 
        }
    }
} else {
    // Si la solicitud no es POST
    $response = [
        'success' => false,
        'error' => 'Método de solicitud no permitido.',
        'msg' => 'Se esperaba una solicitud POST.'
    ];
}

// Llama a la función sendJsonResponse al final para enviar la respuesta y salir.
sendJsonResponse($response['success'], $response['msg'], $response['error'] ?? null);

// La conexión se cierra automáticamente cuando el script termina, o si tu 'conexion.php'
// maneja un cierre explícito. No es necesario un $con->close() aquí.
?>