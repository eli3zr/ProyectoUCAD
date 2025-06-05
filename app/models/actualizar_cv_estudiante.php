<?php

// C:\xampp\htdocs\Jobtrack_Ucad\app\models\actualizar_cv_estudiante.php

// Muestra todos los errores y advertencias para depuración (SOLO EN DESARROLLO)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/actualizar_cv_estudiante_errors.log'); // Log específico para este script

// Iniciar sesión si no está iniciada (necesario para $_SESSION['ID_Usuario'])
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir la conexión a la base de datos y el helper de bitácora
require_once __DIR__ . '/../config/conexion.php'; 
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// ** Obtener el ID del usuario logueado de la sesión. Usar 0 como default para la bitácora si no está logueado. **
$loggedInUserId = $_SESSION['ID_Usuario'] ?? 0;

// --- Función para generar una respuesta JSON estandarizada y terminar la ejecución ---
// Se define aquí para ser usada en este script, y para incluir la bitácora en la salida de errores.
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($success, $message, $error = null, $con = null, $loggedInUserId = 0, $objetoId = 0, $tipoObjeto = 'sistema', $evento = 'ERROR_DESCONOCIDO', $datosAnterior = NULL, $datosNuevo = NULL) {
        // Solo bitacorar si se proporciona una conexión y no es un éxito (o si es un éxito y se requiere)
        if ($con && $success === false) {
            registrarEventoBitacora($con, $objetoId, $tipoObjeto, $evento, $loggedInUserId, $datosAnterior, $datosNuevo . ' - ' . $message);
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'msg' => $message,
            'error' => $error
        ]);
        exit();
    }
}

// Verificar que la conexión a la BD sea válida al inicio del script.
// Si no hay conexión, loguear un error fatal y salir, sin intentar bitacorar en DB.
if (!isset($con) || !is_object($con) || mysqli_connect_errno()) {
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida en actualizar_cv_estudiante.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg); // Loggear en el archivo de error configurado
    // No podemos usar sendJsonResponse con bitácora porque la conexión no existe
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => 'Error interno del servidor: La conexión a la base de datos no está disponible.', 'error' => mysqli_connect_error()]);
    exit(); // Terminar la ejecución
}

// Solo procesar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar que el usuario esté logueado
    if ($loggedInUserId === 0) {
        sendJsonResponse(false, 'Usuario no autenticado. Por favor, inicie sesión.', null, $con, 0, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', NULL, 'Intento de subir CV sin sesión activa.');
    }
    $idUsuario = (int)$loggedInUserId; // Usamos $loggedInUserId que ya se validó

    // 2. Validar que se recibió el archivo 'cvNuevo' (nombre del input file en el formulario HTML)
    if (!isset($_FILES['cvNuevo']) || $_FILES['cvNuevo']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['cvNuevo']['error'] ?? 'N/A';
        $error_msg_detail = '';
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg_detail = 'El archivo CV es demasiado grande (excede el límite del servidor/formulario).';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_msg_detail = 'El archivo CV solo se subió parcialmente.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_msg_detail = 'No se seleccionó ningún archivo CV.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_msg_detail = 'Falta una carpeta temporal del servidor para la subida.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_msg_detail = 'Fallo al escribir el archivo en disco del servidor.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_msg_detail = 'Una extensión de PHP detuvo la subida del archivo.';
                break;
            default:
                $error_msg_detail = 'Error desconocido durante la subida del archivo CV.';
                break;
        }
        sendJsonResponse(false, $error_msg_detail, 'Código de error: ' . $error_code, $con, $idUsuario, 0, 'cv_estudiante', 'SUBIDA_FALLIDA', NULL, 'Error de subida inicial: ' . $error_msg_detail);
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
        $stmt_get_perfil_id = $con->prepare("SELECT ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = ? LIMIT 1"); // Añadir LIMIT 1
        if (!$stmt_get_perfil_id) {
            throw new Exception('Error al preparar la consulta para obtener ID de perfil de estudiante: ' . $con->error);
        }
        $stmt_get_perfil_id->bind_param('i', $idUsuario);
        $stmt_get_perfil_id->execute();
        $stmt_get_perfil_id->bind_result($idPerfilEstudiante);
        $stmt_get_perfil_id->fetch();
        $stmt_get_perfil_id->close();

        // Si el perfil no existe, se crea uno básico automáticamente
        if (is_null($idPerfilEstudiante)) {
            $stmt_insert_perfil = $con->prepare("INSERT INTO perfil_estudiante (ID_Usuario, Nombre_Completo_Estudiante) VALUES (?, ?)");
            if (!$stmt_insert_perfil) {
                throw new Exception('Error al preparar la consulta de inserción de perfil de estudiante: ' . $con->error);
            }
            // Puedes intentar obtener el nombre del usuario de la tabla 'usuario' para un nombre por defecto más útil
            $nombre_usuario_default = "Estudiante " . $idUsuario; // Valor de fallback
            $stmt_get_user_name = $con->prepare("SELECT Nombre, Apellido FROM usuario WHERE ID_Usuario = ? LIMIT 1");
            if ($stmt_get_user_name) {
                $stmt_get_user_name->bind_param("i", $idUsuario);
                $stmt_get_user_name->execute();
                $result_user_name = $stmt_get_user_name->get_result();
                if ($row_user_name = $result_user_name->fetch_assoc()) {
                    $nombre_usuario_default = trim($row_user_name['Nombre'] . ' ' . $row_user_name['Apellido']);
                }
                $stmt_get_user_name->close();
            }

            $stmt_insert_perfil->bind_param('is', $idUsuario, $nombre_usuario_default);

            if (!$stmt_insert_perfil->execute()) {
                throw new Exception('Error al crear un perfil de estudiante por defecto: ' . $stmt_insert_perfil->error);
            }
            $idPerfilEstudiante = $con->insert_id; // Obtiene el ID del perfil recién creado
            $stmt_insert_perfil->close();
            // Bitácora: Perfil de estudiante creado
            registrarEventoBitacora($con, $idPerfilEstudiante, 'perfil_estudiante', 'CREATE', $idUsuario, NULL, 'Perfil de estudiante creado automáticamente para ID_Usuario: ' . $idUsuario);
        }

        // Si después de todo aún no tenemos un ID de perfil, algo está mal
        if (is_null($idPerfilEstudiante)) {
            throw new Exception('No se pudo obtener o crear el perfil del estudiante necesario para el CV.');
        }

        // 5. Definir la ruta de almacenamiento del archivo en el servidor y moverlo
        $uploadDir = __DIR__ . '/../../public/cvs/';
        // Asegurarse de que el directorio exista y tenga permisos de escritura
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Error interno del servidor. No se pudo crear el directorio de subida de CVs. Verifique los permisos.');
            }
        }
        $newFileName = uniqid('cv_', true) . '.' . $fileExt; // Nombre de archivo único para evitar colisiones
        $cv_uploaded_file_path = $uploadDir . $newFileName; // Ruta completa en el servidor
        $relativePathForDB = '/public/cvs/' . $newFileName; // Ruta relativa para guardar en la base de datos

        if (!move_uploaded_file($fileTmpName, $cv_uploaded_file_path)) {
            // Un error común aquí es que el archivo ya no exista en $fileTmpName (por ejemplo, doble ejecución o limpieza del temp).
            // O que los permisos de escritura del $uploadDir no sean correctos.
            throw new Exception('No se pudo mover el archivo CV subido al destino final. Revise permisos del directorio ' . $uploadDir . ' y si el archivo temporal existe.');
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
        $newCvId = $con->insert_id; // Obtener el ID del CV recién insertado
        $stmt_cv_insert->close();

        $con->commit(); // Confirmar la transacción si todo el proceso fue exitoso

        // Bitácora: CV subido exitosamente
        $datos_nuevo_cv = json_encode([
            'ID_CV' => $newCvId,
            'RutaArchivoCV' => $relativePathForDB,
            'NombreOriginalArchivo' => $fileName,
            'TamanoArchivoKB' => $fileSizeKB
        ]);
        registrarEventoBitacora($con, $newCvId, 'cv_estudiante', 'CREATE', $idUsuario, NULL, $datos_nuevo_cv);

        sendJsonResponse(true, 'Tu CV ha sido subido exitosamente.', null, $con, $idUsuario, $newCvId, 'cv_estudiante', 'SUBIDA_EXITOSA', NULL, 'CV subido exitosamente.');

    } catch (Exception $e) {
        $con->rollback(); // Deshacer cualquier cambio en la base de datos si ocurre un error

        // Si el archivo ya se movió al servidor y hubo un error en la DB o lógica, eliminar el archivo
        if (isset($cv_uploaded_file_path) && file_exists($cv_uploaded_file_path)) {
            unlink($cv_uploaded_file_path);
            error_log("Archivo CV temporal eliminado debido a error en la DB/lógica: " . $cv_uploaded_file_path);
        }

        $log_message = "Excepción en actualizar_cv_estudiante.php (ID_Usuario: {$idUsuario}): " . $e->getMessage();
        error_log($log_message); // Loguear el error real en el archivo de log

        // Devolver un mensaje de error genérico al usuario en producción, pero el detalle para depuración
        sendJsonResponse(
            false, 
            'Ocurrió un error al procesar tu CV. Por favor, inténtalo de nuevo más tarde.', 
            $e->getMessage(), // Se devuelve el mensaje de la excepción para depuración
            $con, 
            $idUsuario, 
            0, // No hay un ID de CV si falló
            'cv_estudiante', 
            'ERROR_SISTEMA', 
            NULL, 
            $log_message
        );
    } finally {
        // Restaurar el modo autocommit y cerrar la conexión
        if (isset($con)) {
            $con->autocommit(true);
            $con->close();
        }
    }
} else {
    // Si la solicitud no es POST, devolver un error
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.', $con, $loggedInUserId, 0, 'sistema', 'METODO_NO_PERMITIDO', NULL, 'Intento de acceso con método no POST.');
}
?>