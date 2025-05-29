<?php
// Establecer el encabezado para que la respuesta sea JSON
header('Content-Type: application/json');

// Incluir tu archivo de conexión a la base de datos
// Asegúrate de que esta ruta sea correcta para tu proyecto
require_once __DIR__ . '/../config/conexion.php'; // Ajusta esta ruta a tu archivo de conexión

// Función para generar una respuesta JSON estandarizada
function sendJsonResponse($success, $message, $error = null) {
    echo json_encode([
        'success' => $success,
        'msg' => $message,
        'error' => $error
    ]);
    exit(); // Termina la ejecución del script
}

// --- 1. Validar la solicitud y la existencia del archivo ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.');
}

if (!isset($_FILES['cvNuevo']) || $_FILES['cvNuevo']['error'] !== UPLOAD_ERR_OK) {
    // Manejar errores de subida específicos
    $errorMessage = 'Error al subir el archivo.';
    switch ($_FILES['cvNuevo']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMessage = 'El archivo es demasiado grande.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMessage = 'El archivo no se subió completamente.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMessage = 'No se seleccionó ningún archivo.';
            break;
        // Puedes añadir más casos para otros errores de UPLOAD_ERR_...
    }
    sendJsonResponse(false, $errorMessage, 'Error de subida de archivo.');
}

$file = $_FILES['cvNuevo'];
$fileName = $file['name'];
$fileTmpName = $file['tmp_name'];
$fileSize = $file['size'];
$fileType = $file['type']; // Tipo MIME reportado por el navegador
$fileError = $file['error'];

// --- 2. Validaciones de seguridad y negocio (esenciales en el servidor) ---

// a. Tamaño del archivo (2MB = 2 * 1024 * 1024 bytes)
$maxFileSize = 2 * 1024 * 1024;
if ($fileSize > $maxFileSize) {
    sendJsonResponse(false, 'El archivo es demasiado grande.', 'El tamaño máximo permitido para el CV es 2MB.');
}

// b. Tipo de archivo (validar MIME types para mayor seguridad)
$allowedMimeTypes = [
    'application/pdf',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // .docx
];

if (!in_array($fileType, $allowedMimeTypes)) {
    sendJsonResponse(false, 'Formato de archivo no permitido.', 'Formatos de archivo permitidos: PDF, DOC, DOCX.');
}

// c. Extensión del archivo (adicional a la validación MIME, por si acaso)
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'doc', 'docx'];
if (!in_array($fileExt, $allowedExtensions)) {
    sendJsonResponse(false, 'Extensión de archivo no permitida.', 'Las extensiones permitidas son PDF, DOC, DOCX.');
}

// --- 3. Obtener el ID del estudiante ---
// Este es un paso CRÍTICO. Debes obtener el ID del estudiante de forma SEGURA.
// NO confíes en datos enviados directamente desde el cliente para el ID del usuario.
// Lo más común es obtenerlo de la sesión del usuario logueado.
session_start();
if (!isset($_SESSION['user_id'])) { // Asume que guardas el ID del usuario en $_SESSION['user_id']
    sendJsonResponse(false, 'Acceso denegado. No se pudo identificar al usuario.', 'Usuario no autenticado.');
}
$id_usuario_logueado = $_SESSION['user_id'];

// Aquí necesitarías obtener el ID_Perfil_Estudiante asociado a este ID_Usuario.
// Asumo que tu tabla perfil_estudiante tiene una columna ID_Usuario.
try {
    $stmt_perfil = $conn->prepare("SELECT ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = :id_usuario");
    $stmt_perfil->bindParam(':id_usuario', $id_usuario_logueado);
    $stmt_perfil->execute();
    $perfil_estudiante = $stmt_perfil->fetch(PDO::FETCH_ASSOC);

    if (!$perfil_estudiante) {
        sendJsonResponse(false, 'No se encontró un perfil de estudiante asociado a tu cuenta.', 'Perfil no encontrado.');
    }
    $id_perfil_estudiante = $perfil_estudiante['ID_Perfil_Estudiante'];

} catch (PDOException $e) {
    error_log("Error al buscar perfil de estudiante: " . $e->getMessage());
    sendJsonResponse(false, 'Error interno del servidor al buscar el perfil.', 'Database error: ' . $e->getMessage());
}


// --- 4. Definir la ruta de almacenamiento del archivo ---
// Asegúrate de que esta carpeta tenga permisos de escritura para el servidor web.
// Es buena práctica que sea fuera del directorio raíz del documento si no necesitas acceso directo.
// Para este ejemplo, lo pondremos en un subdirectorio de 'uploads' dentro de tu app.
$uploadDir = __DIR__ . '/../../public/cvs/'; // Directorio para guardar CVs, ajústalo según tu estructura
                                            // Por ejemplo: /var/www/html/tu_app/public/cvs/

// Crear el directorio si no existe
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        sendJsonResponse(false, 'Error al crear el directorio de CVs.', 'No se pudo crear el directorio de subida.');
    }
}

// --- 5. Generar un nombre de archivo único ---
// Evita colisiones y problemas de seguridad.
$newFileName = uniqid('cv_', true) . '.' . $fileExt;
$filePath = $uploadDir . $newFileName;
$relativePathForDB = '/public/cvs/' . $newFileName; // Ruta que se guardará en la BD para acceso web

// --- 6. Mover el archivo subido a su destino final ---
if (move_uploaded_file($fileTmpName, $filePath)) {
    // --- 7. Actualizar la base de datos ---
    try {
        // Verificar si ya existe un CV para este perfil de estudiante
        $stmt_check = $conn->prepare("SELECT ID_cv_estudiante, RutaArchivoCV FROM cv_estudiante WHERE perfil_estudiante_ID_Perfil_Estudiante = :id_perfil");
        $stmt_check->bindParam(':id_perfil', $id_perfil_estudiante);
        $stmt_check->execute();
        $existingCv = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existingCv) {
            // Si ya existe, actualiza el registro
            $stmt_update = $conn->prepare("UPDATE cv_estudiante
                                            SET RutaArchivoCV = :ruta,
                                                NombreOriginalArchivo = :nombre_original,
                                                TipoMime = :tipo_mime,
                                                TamanoArchivoKB = :tamano_kb,
                                                FechaSubida = NOW()
                                            WHERE ID_cv_estudiante = :id_cv_existente");

            // Opcional: Eliminar el archivo antiguo del servidor
            if (!empty($existingCv['RutaArchivoCV'])) {
                $oldFilePath = __DIR__ . '/../..' . $existingCv['RutaArchivoCV']; // Ajusta esta ruta si es necesario
                if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                    unlink($oldFilePath); // Elimina el archivo
                }
            }

            $stmt_update->bindParam(':id_cv_existente', $existingCv['ID_cv_estudiante']);

        } else {
            // Si no existe, inserta un nuevo registro
            $stmt_insert = $conn->prepare("INSERT INTO cv_estudiante (perfil_estudiante_ID_Perfil_Estudiante, RutaArchivoCV, NombreOriginalArchivo, TipoMime, TamanoArchivoKB, FechaSubida)
                                            VALUES (:id_perfil, :ruta, :nombre_original, :tipo_mime, :tamano_kb, NOW())");
            $stmt_insert->bindParam(':id_perfil', $id_perfil_estudiante);
            $stmt_update = $stmt_insert; // Usamos la misma variable para bindParam
        }

        // Bindear los parámetros comunes
        $stmt_update->bindParam(':ruta', $relativePathForDB);
        $stmt_update->bindParam(':nombre_original', $fileName);
        $stmt_update->bindParam(':tipo_mime', $fileType);
        $fileSizeKB = round($fileSize / 1024); // Convertir a KB
        $stmt_update->bindParam(':tamano_kb', $fileSizeKB);

        $stmt_update->execute();

        sendJsonResponse(true, 'Tu CV ha sido actualizado exitosamente.');

    } catch (PDOException $e) {
        // Si hay un error en la BD, eliminar el archivo subido para no dejar basura
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        error_log("Error al guardar CV en BD: " . $e->getMessage());
        sendJsonResponse(false, 'Error interno del servidor al actualizar el CV.', 'Database error: ' . $e->getMessage());
    }

} else {
    sendJsonResponse(false, 'Error al guardar el archivo en el servidor.', 'Problema de permisos o ruta.');
}

// Cierre de la conexión (si no usas autoconexión persistente)
$conn = null;

?>