<?php
// app/models/actualizar_perfil_laboral.php

// ¡Desactiva esto en producción! (cambia a E_ERROR o 0 y comenta display_errors)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/actualizar_perfil_laboral_errors.log'); // Log específico

session_start(); // Inicia la sesión al principio de todo

// Inicializar el array de respuesta que se enviará como JSON
$response = ['success' => false, 'error' => '', 'msg' => ''];

// ** Importante: Obtén el ID del usuario logueado de tu sistema de autenticación. **
// Verifica si el ID del usuario está en la sesión
$loggedInUserId = $_SESSION['ID_Usuario'] ?? 0; // Usar 0 como default para la bitácora

if ($loggedInUserId === 0) {
    // Si el usuario no está logueado (no hay ID en la sesión),
    // devuelve un error y termina el script.
    $response = [
        'success' => false,
        'error' => 'Usuario no autenticado. Por favor, inicia sesión para actualizar tu perfil.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    // Bitácora: Acceso no autorizado
    // Aquí no podemos usar $con aún, se bitacorea si la conexión existe después.
    exit(); // Termina la ejecución aquí
}

require_once __DIR__ . '/../config/conexion.php'; // Asegúrate de que $con se inicialice aquí
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// Verificar que la conexión a la base de datos sea válida
if (!isset($con) || !is_object($con) || mysqli_connect_errno()) {
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida en actualizar_perfil_laboral.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg);
    $response['error'] = 'Error interno del servidor: La conexión a la base de datos no está disponible.';
    // No podemos bitacorar aquí si la conexión falló
    header('Content-Type: application/json');
    echo json_encode($response);
    exit(); // Terminar la ejecución si la conexión no es válida
}

// Establecer el tipo de contenido de la respuesta como JSON
header('Content-Type: application/json');

// Verificar si la solicitud es un POST, que es el método esperado del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Capturar los datos enviados desde el formulario
    $tiene_experiencia = $_POST['tiene_experiencia'] ?? '';
    $descripcion_laboral_resumen = trim($_POST['descripcion_laboral_resumen'] ?? '');

    // --- Validación de los datos recibidos ---
    $errors = [];
    if (empty($tiene_experiencia)) {
        $errors[] = "Por favor, selecciona si tienes experiencia laboral.";
    }

    // Si el usuario indica que SÍ tiene experiencia, la descripción es obligatoria
    if ($tiene_experiencia === 'si' && empty($descripcion_laboral_resumen)) {
        $errors[] = "La descripción de la experiencia laboral es obligatoria si seleccionas 'Sí, tengo experiencia laboral'.";
    }

    // Si se encontraron errores de validación, se prepara la respuesta de error
    if (!empty($errors)) {
        $response = [
            'success' => false,
            'error' => implode("<br>", $errors) // Unir los errores en una sola cadena para el mensaje
        ];
        // Bitácora: Error de validación de entrada
        registrarEventoBitacora($con, 0, 'experiencias_laborales_estudiantes', 'LOGIN_FALLIDO', $loggedInUserId, NULL, 'Error de validación al actualizar perfil laboral: ' . implode(" | ", $errors));
    } else {
        // --- Preparación de datos para la base de datos ---
        // Convertir 'si'/'no' del formulario a 1/0 para el campo `tinyint(1)` de la BD
        $tiene_experiencia_db = ($tiene_experiencia === 'si') ? 1 : 0;
        
        // Si no hay experiencia laboral, la descripción debe ser NULL en la base de datos
        $descripcion_to_save = ($tiene_experiencia_db == 1) ? $descripcion_laboral_resumen : NULL;

        // Iniciar una transacción para asegurar que todas las operaciones de la BD sean atómicas
        mysqli_begin_transaction($con);

        try {
            $id_perfil_estudiante = null;
            $datos_antes = null; // Para la bitácora
            $id_experiencia_existente = null; // Para la bitácora y actualización

            // 1. Buscar el ID_Perfil_Estudiante asociado al ID_Usuario del usuario logueado
            $stmt_perfil = mysqli_prepare($con, "SELECT ID_Perfil_Estudiante FROM Perfil_Estudiante WHERE ID_Usuario = ?");
            if (!$stmt_perfil) {
                // Bitácora: Error del sistema
                registrarEventoBitacora($con, 0, 'perfil_estudiante', 'ERROR_SISTEMA', $loggedInUserId, NULL, "Error al preparar la consulta de perfil en actualizar_perfil_laboral: " . mysqli_error($con));
                throw new Exception("Error al preparar la consulta de perfil: " . mysqli_error($con));
            }
            mysqli_stmt_bind_param($stmt_perfil, 'i', $loggedInUserId); 
            mysqli_stmt_execute($stmt_perfil);
            $result_perfil = mysqli_stmt_get_result($stmt_perfil);
            $perfil_estudiante = mysqli_fetch_assoc($result_perfil);
            mysqli_stmt_close($stmt_perfil);

            // Verificar si se encontró un perfil de estudiante para el usuario
            if ($perfil_estudiante) {
                $id_perfil_estudiante = $perfil_estudiante['ID_Perfil_Estudiante'];

                // 2. Obtener datos antes de la actualización/inserción de la experiencia laboral
                $stmt_get_exp_antes = mysqli_prepare($con, "SELECT ID_Experiencia, tiene_experiencia_laboral, descripcion_laboral FROM experiencias_laborales_estudiantes WHERE ID_Perfil_Estudiante = ? LIMIT 1");
                if (!$stmt_get_exp_antes) {
                    registrarEventoBitacora($con, $id_perfil_estudiante, 'experiencias_laborales_estudiantes', 'ERROR_SISTEMA', $loggedInUserId, NULL, "Error al preparar consulta de datos previos de experiencia: " . mysqli_error($con));
                    throw new Exception("Error al preparar la consulta de datos previos de experiencia: " . mysqli_error($con));
                }
                mysqli_stmt_bind_param($stmt_get_exp_antes, 'i', $id_perfil_estudiante);
                mysqli_stmt_execute($stmt_get_exp_antes);
                $result_exp_antes = mysqli_stmt_get_result($stmt_get_exp_antes);
                $experiencia_antes_array = mysqli_fetch_assoc($result_exp_antes);
                mysqli_stmt_close($stmt_get_exp_antes);

                if ($experiencia_antes_array) {
                    $datos_antes = json_encode($experiencia_antes_array);
                    $id_experiencia_existente = $experiencia_antes_array['ID_Experiencia'];
                }

                if ($id_experiencia_existente) {
                    // Si ya existe un registro, se actualiza
                    $stmt_update = mysqli_prepare($con, "UPDATE experiencias_laborales_estudiantes SET tiene_experiencia_laboral = ?, descripcion_laboral = ? WHERE ID_Experiencia = ?");
                    if (!$stmt_update) {
                        // Bitácora: Error del sistema
                        registrarEventoBitacora($con, $id_perfil_estudiante, 'experiencias_laborales_estudiantes', 'ERROR_SISTEMA', $loggedInUserId, NULL, "Error al preparar la consulta de actualización de experiencia: " . mysqli_error($con));
                        throw new Exception("Error al preparar la consulta de actualización de experiencia: " . mysqli_error($con));
                    }
                    mysqli_stmt_bind_param($stmt_update, 'isi', $tiene_experiencia_db, $descripcion_to_save, $id_experiencia_existente);
                    mysqli_stmt_execute($stmt_update);
                    
                    if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                        $response = [
                            'success' => true,
                            'msg' => '¡Información laboral actualizada exitosamente!'
                        ];
                        // Bitácora: UPDATE exitoso
                        $datos_despues = extraerDatosParaBitacora($con, $id_experiencia_existente, 'experiencias_laborales_estudiantes');
                        registrarEventoBitacora($con, $id_experiencia_existente, 'experiencias_laborales_estudiantes', 'UPDATE', $loggedInUserId, $datos_antes, $datos_despues);
                    } else {
                        $response = [
                            'success' => true,
                            'msg' => 'Información laboral recibida, pero no se detectaron cambios.'
                        ];
                        // Bitácora: ADVERTENCIA (no hubo cambios)
                        registrarEventoBitacora($con, $id_experiencia_existente, 'experiencias_laborales_estudiantes', 'ADVERTENCIA', $loggedInUserId, NULL, 'Actualización de perfil laboral: No se detectaron cambios. Datos enviados: ' . json_encode(['tiene_experiencia' => $tiene_experiencia, 'descripcion_laboral' => $descripcion_laboral_resumen]));
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    // Si no existe un registro, se inserta uno nuevo
                    $stmt_insert = mysqli_prepare($con, "INSERT INTO experiencias_laborales_estudiantes (ID_Perfil_Estudiante, tiene_experiencia_laboral, descripcion_laboral) VALUES (?, ?, ?)");
                    if (!$stmt_insert) {
                        // Bitácora: Error del sistema
                        registrarEventoBitacora($con, $id_perfil_estudiante, 'experiencias_laborales_estudiantes', 'ERROR_SISTEMA', $loggedInUserId, NULL, "Error al preparar la consulta de inserción de experiencia: " . mysqli_error($con));
                        throw new Exception("Error al preparar la consulta de inserción de experiencia: " . mysqli_error($con));
                    }
                    mysqli_stmt_bind_param($stmt_insert, 'iis', $id_perfil_estudiante, $tiene_experiencia_db, $descripcion_to_save);
                    mysqli_stmt_execute($stmt_insert);
                    
                    if (mysqli_stmt_affected_rows($stmt_insert) > 0) {
                        $response = [
                            'success' => true,
                            'msg' => '¡Información laboral guardada exitosamente!'
                        ];
                        // Bitácora: INSERT exitoso
                        $new_experience_id = mysqli_insert_id($con); // Obtener el ID de la nueva experiencia
                        $datos_despues = extraerDatosParaBitacora($con, $new_experience_id, 'experiencias_laborales_estudiantes');
                        registrarEventoBitacora($con, $new_experience_id, 'experiencias_laborales_estudiantes', 'INSERT', $loggedInUserId, NULL, $datos_despues);
                    } else {
                        // Aunque se insertó (no hubo error de execute), si affected_rows es 0, algo raro pasó.
                        // Podría indicar un problema lógico o de datos.
                        $response = [
                            'success' => false,
                            'error' => 'Error al guardar la información laboral: No se insertaron filas. Intente de nuevo.'
                        ];
                        registrarEventoBitacora($con, $id_perfil_estudiante, 'experiencias_laborales_estudiantes', 'ERROR_SISTEMA', $loggedInUserId, NULL, 'Insert de experiencia laboral fallido: affected_rows 0.');
                    }
                    mysqli_stmt_close($stmt_insert);
                }
                mysqli_commit($con); // Confirmar la transacción si todo fue exitoso
            } else {
                // Si el perfil del estudiante no se encuentra, lanzar una excepción
                // Bitácora: ADVERTENCIA
                registrarEventoBitacora($con, 0, 'perfil_estudiante', 'ADVERTENCIA', $loggedInUserId, NULL, "Error: El perfil de estudiante no fue encontrado para el usuario ID: " . $loggedInUserId);
                throw new Exception("Error: El perfil de usuario no fue encontrado. Asegúrate de que el usuario esté asociado a un perfil de estudiante.");
            }
        } catch (Exception $e) {
            // Si ocurre cualquier error en el bloque try, hacer un rollback de la transacción
            mysqli_rollback($con);
            $response = [
                'success' => false,
                'error' => "Error en la base de datos: " . $e->getMessage()
            ];
            error_log("Excepción al actualizar información laboral: " . $e->getMessage());
            // Bitácora: Error del sistema (excepción no manejada)
            // Se usa $id_perfil_estudiante si está disponible, de lo contrario 0.
            registrarEventoBitacora($con, $id_perfil_estudiante ?? 0, 'experiencias_laborales_estudiantes', 'ERROR_SISTEMA', $loggedInUserId, NULL, 'Excepción inesperada al actualizar perfil laboral: ' . $e->getMessage());
        }
    }
} else {
    // Si la solicitud no es POST (ej. alguien intenta acceder al script directamente por URL)
    $response = [
        'success' => false,
        'error' => 'Método de solicitud no permitido.'
    ];
    // Bitácora: Advertencia
    registrarEventoBitacora($con, 0, 'sistema', 'ADVERTENCIA', $loggedInUserId, NULL, 'Método HTTP no permitido en actualizar_perfil_laboral.php: ' . $_SERVER['REQUEST_METHOD']);
}

// Cierra la conexión a la base de datos.
if (isset($con) && $con instanceof mysqli) {
    mysqli_close($con);
}

// Convertir el array de respuesta a formato JSON y enviarlo al cliente
echo json_encode($response);
exit(); // Terminar la ejecución del script para evitar salida adicional
?>