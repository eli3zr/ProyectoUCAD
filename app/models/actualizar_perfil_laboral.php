<?php

require_once __DIR__ . '/../config/conexion.php';
// Configuración de reporte de errores para desarrollo.
// ¡Desactiva esto en producción! (cambia a E_ERROR o 0 y comenta display_errors)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicializar el array de respuesta que se enviará como JSON
$response = [];

// ** Importante: Obtén el ID del usuario logueado de tu sistema de autenticación. **
// Este es un marcador de posición. En una aplicación real, no debe ser un valor fijo.
// Por ejemplo: $loggedInUserId = $_SESSION['ID_Usuario'];
$loggedInUserId = 1; // Reemplaza '1' con el ID dinámico del usuario autenticado.

// Verificar si la solicitud es un POST, que es el método esperado del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Capturar los datos enviados desde el formulario
    $tiene_experiencia = isset($_POST['tiene_experiencia']) ? $_POST['tiene_experiencia'] : '';
    $descripcion_laboral_resumen = isset($_POST['descripcion_laboral_resumen']) ? trim($_POST['descripcion_laboral_resumen']) : '';

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
    } else {
        // --- Preparación de datos para la base de datos ---
        // Convertir 'si'/'no' del formulario a 1/0 para el campo `tinyint(1)` de la BD
        $tiene_experiencia_db = ($tiene_experiencia === 'si') ? 1 : 0;
        
        // Si no hay experiencia laboral, la descripción debe ser NULL en la base de datos
        $descripcion_to_save = ($tiene_experiencia_db == 1) ? $descripcion_laboral_resumen : NULL;

        // Iniciar una transacción para asegurar que todas las operaciones de la BD sean atómicas
        // Esto significa que si algo falla, todos los cambios se revierten.
        mysqli_begin_transaction($con);

        try {
            $id_perfil_estudiante = null;

            // 1. Buscar el ID_Perfil_Estudiante asociado al ID_Usuario del usuario logueado
            $stmt_perfil = mysqli_prepare($con, "SELECT ID_Perfil_Estudiante FROM Perfil_Estudiante WHERE ID_Usuario = ?");
            if (!$stmt_perfil) {
                // Si la preparación de la consulta falla, lanzar una excepción
                throw new Exception("Error al preparar la consulta de perfil: " . mysqli_error($con));
            }
            mysqli_stmt_bind_param($stmt_perfil, 'i', $loggedInUserId); // 'i' indica que $loggedInUserId es un entero
            mysqli_stmt_execute($stmt_perfil);
            $result_perfil = mysqli_stmt_get_result($stmt_perfil);
            $perfil_estudiante = mysqli_fetch_assoc($result_perfil);
            mysqli_stmt_close($stmt_perfil); // Cerrar el statement después de usarlo

            // Verificar si se encontró un perfil de estudiante para el usuario
            if ($perfil_estudiante) {
                $id_perfil_estudiante = $perfil_estudiante['ID_Perfil_Estudiante'];

                // 2. Verificar si ya existe un registro de experiencia para este ID_Perfil_Estudiante
                // ** CORRECCIÓN CLAVE: Nombre de la tabla cambiado a 'experiencias_laborales_estudiantes' **
                $stmt_check_exp = mysqli_prepare($con, "SELECT ID_Experiencia FROM experiencias_laborales_estudiantes WHERE ID_Perfil_Estudiante = ?");
                if (!$stmt_check_exp) {
                    throw new Exception("Error al preparar la consulta de existencia de experiencia: " . mysqli_error($con));
                }
                mysqli_stmt_bind_param($stmt_check_exp, 'i', $id_perfil_estudiante); // 'i' indica que $id_perfil_estudiante es un entero
                mysqli_stmt_execute($stmt_check_exp);
                $result_check_exp = mysqli_stmt_get_result($stmt_check_exp);
                $existing_experience = mysqli_fetch_assoc($result_check_exp);
                mysqli_stmt_close($stmt_check_exp); // Cerrar el statement

                if ($existing_experience) {
                    // Si ya existe un registro, se actualiza
                    // ** CORRECCIÓN CLAVE: Nombre de la tabla cambiado a 'experiencias_laborales_estudiantes' **
                    $stmt_update = mysqli_prepare($con, "UPDATE experiencias_laborales_estudiantes SET tiene_experiencia_laboral = ?, descripcion_laboral = ? WHERE ID_Experiencia = ?");
                    if (!$stmt_update) {
                        throw new Exception("Error al preparar la consulta de actualización: " . mysqli_error($con));
                    }
                    // 'i' para tinyint (entero), 's' para text (cadena), 'i' para ID_Experiencia (entero)
                    mysqli_stmt_bind_param($stmt_update, 'isi', $tiene_experiencia_db, $descripcion_to_save, $existing_experience['ID_Experiencia']);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update); // Cerrar el statement
                    
                    $response = [
                        'success' => true,
                        'msg' => '¡Información laboral actualizada exitosamente!'
                    ];
                } else {
                    // Si no existe un registro, se inserta uno nuevo
                    // ** CORRECCIÓN CLAVE: Nombre de la tabla cambiado a 'experiencias_laborales_estudiantes' **
                    $stmt_insert = mysqli_prepare($con, "INSERT INTO experiencias_laborales_estudiantes (ID_Perfil_Estudiante, tiene_experiencia_laboral, descripcion_laboral) VALUES (?, ?, ?)");
                    if (!$stmt_insert) {
                        throw new Exception("Error al preparar la consulta de inserción: " . mysqli_error($con));
                    }
                    // 'i' para ID_Perfil_Estudiante (entero), 'i' para tinyint (entero), 's' para text (cadena)
                    mysqli_stmt_bind_param($stmt_insert, 'iis', $id_perfil_estudiante, $tiene_experiencia_db, $descripcion_to_save);
                    mysqli_stmt_execute($stmt_insert);
                    mysqli_stmt_close($stmt_insert); // Cerrar el statement
                    
                    $response = [
                        'success' => true,
                        'msg' => '¡Información laboral guardada exitosamente!'
                    ];
                }
                mysqli_commit($con); // Confirmar la transacción si todo fue exitoso
            } else {
                // Si el perfil del estudiante no se encuentra, lanzar una excepción
                throw new Exception("Error: El perfil de usuario no fue encontrado. Asegúrate de que el usuario esté asociado a un perfil de estudiante.");
            }

        } catch (Exception $e) {
            // Si ocurre cualquier error en el bloque try, hacer un rollback de la transacción
            mysqli_rollback($con);
            $response = [
                'success' => false,
                'error' => "Error en la base de datos: " . $e->getMessage()
            ];
        }
    }
} else {
    // Si la solicitud no es POST (ej. alguien intenta acceder al script directamente por URL)
    $response = [
        'success' => false,
        'error' => 'Método de solicitud no permitido.'
    ];
}

// Cierra la conexión a la base de datos.
// Dependiendo de tu arquitectura, podrías querer cerrar la conexión en un punto más global.
mysqli_close($con);

// Establecer el tipo de contenido de la respuesta como JSON
header('Content-Type: application/json');
// Convertir el array de respuesta a formato JSON y enviarlo al cliente
echo json_encode($response);
exit(); // Terminar la ejecución del script para evitar salida adicional