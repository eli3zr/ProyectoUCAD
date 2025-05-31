<?php

require_once __DIR__ . '/../config/conexion.php';

// Establecer el tipo de contenido para la respuesta (JSON)
header('Content-Type: application/json');

$response = [];

// Captura los datos enviados
$datos = $_POST;

// Validación básica (esta sección se mantiene igual a tu código)
if (
    empty($datos['nombre']) ||
    empty($datos['apellido']) ||
    empty($datos['email']) ||
    empty($datos['fechaNacimiento']) ||
    empty($datos['genero']) ||
    empty($datos['carrera']) ||
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
} elseif (strlen($datos['clave']) < 8) { // Añadida validación de longitud de clave
    $response = [
        'success' => false,
        'error' => 'La contraseña debe tener al menos 8 caracteres.'
    ];
} else {
    // A partir de aquí, es donde se añade la lógica de base de datos.
    // Esto reemplaza el comentario "Simulación de guardado en la base de datos (TODO: implementar)".

    // Sanear y obtener los datos
    // Es CRUCIAL usar mysqli_real_escape_string con la conexión $con de conexion.php
    // Las sentencias preparadas ofrecen protección adicional contra inyección SQL.
    $nombre = mysqli_real_escape_string($con, $datos['nombre']);
    $apellido = mysqli_real_escape_string($con, $datos['apellido']);
    $correo_electronico = mysqli_real_escape_string($con, $datos['email']);
    $fecha_nacimiento = mysqli_real_escape_string($con, $datos['fechaNacimiento']); // Formato YYYY-MM-DD
    $genero = mysqli_real_escape_string($con, $datos['genero']); // Masculino/Femenino/Otro

    // La 'carrera' del formulario se usará para 'Carrera_Profesional' en la tabla 'perfil_estudiante'
    $carrera_profesional = mysqli_real_escape_string($con, $datos['carrera']);

    $contrasena_plana = $datos['clave']; // Contraseña en texto plano

    // Campos adicionales de perfil_estudiante que no están en este formulario inicial
    $experiencia_laboral = ''; // Dejar vacío o NULL si la columna de DB lo permite
    $foto_perfil = '';          // Dejar vacío o NULL si la columna de DB lo permite

    // Generar el hash de la contraseña
    // PASSWORD_BCRYPT es el algoritmo recomendado.
    $opciones_hashing = ['cost' => 12]; // Puedes ajustar el 'cost' para más seguridad/rendimiento
    $contrasena_hash = password_hash($contrasena_plana, PASSWORD_BCRYPT, $opciones_hashing);

    // Iniciar una transacción para asegurar la integridad de los datos
    // Si alguna inserción falla, todas las demás se deshacen (rollback).
    mysqli_begin_transaction($con);

    try {
        // 1. Verificar si el correo electrónico ya existe para evitar duplicados
        $check_email_query = "SELECT ID_Usuario FROM usuario WHERE Correo_Electronico = ?";
        $stmt_check = mysqli_prepare($con, $check_email_query);
        if (!$stmt_check) {
            throw new Exception("Error al preparar la consulta de verificación de email: " . mysqli_error($con));
        }
        mysqli_stmt_bind_param($stmt_check, "s", $correo_electronico);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check); // Necesario para mysqli_stmt_num_rows
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            throw new Exception("El correo electrónico ya está registrado.");
        }
        mysqli_stmt_close($stmt_check); // Cerrar statement después de usarlo

        // 2. Insertar el nuevo usuario en la tabla 'usuario'
        // ATENCIÓN: Tu tabla 'usuario' tiene 'Nombre' y 'Apellido' separados,
        // no una columna 'Tipo'. Usaremos ID_Rol_FK.
        // Asumo que tienes una tabla 'rol' y conoces el ID de rol para 'estudiante'.
        // Por ejemplo, si en tu tabla 'rol', el ID para 'estudiante' es 1.
        $ID_Rol_Estudiante = 2; // <--- ¡Asegúrate de que este ID_Rol_FK sea correcto en tu tabla 'rol'!
        $estado_usuario = 'Activo'; // Asegúrate de que coincida con el ENUM('Activo','Inactivo')

        $query_usuario = "INSERT INTO usuario (Nombre, Apellido, Correo_Electronico, ID_Rol_FK, estado_us) VALUES (?, ?, ?, ?, ?)";
        $stmt_usuario = mysqli_prepare($con, $query_usuario);
        if (!$stmt_usuario) {
            throw new Exception("Error al preparar la consulta de usuario: " . mysqli_error($con));
        }
        // 'sssis' -> Nombre (string), Apellido (string), Correo_Electronico (string), ID_Rol_FK (int), estado_us (string)
        mysqli_stmt_bind_param($stmt_usuario, "sssis", $nombre, $apellido, $correo_electronico, $ID_Rol_Estudiante, $estado_usuario);
        mysqli_stmt_execute($stmt_usuario);

        if (mysqli_stmt_affected_rows($stmt_usuario) === 0) {
            throw new Exception("No se pudo insertar el usuario principal.");
        }

        $id_nuevo_usuario = mysqli_insert_id($con); // Obtener el ID del usuario recién insertado
        mysqli_stmt_close($stmt_usuario); // Cerrar statement

        // 3. Insertar el hash de la contraseña en la tabla 'contrasenas'
        $query_contrasena = "INSERT INTO contrasenas (ID_Usuario, Contrasena_Hash) VALUES (?, ?)";
        $stmt_contrasena = mysqli_prepare($con, $query_contrasena);
        if (!$stmt_contrasena) {
            throw new Exception("Error al preparar la consulta de contraseña: " . mysqli_error($con));
        }
        mysqli_stmt_bind_param($stmt_contrasena, "is", $id_nuevo_usuario, $contrasena_hash); // 'i' para int, 's' para string
        mysqli_stmt_execute($stmt_contrasena);

        if (mysqli_stmt_affected_rows($stmt_contrasena) === 0) {
            throw new Exception("No se pudo insertar la contraseña.");
        }
        mysqli_stmt_close($stmt_contrasena); // Cerrar statement

        // 4. Insertar los datos específicos del estudiante en 'perfil_estudiante'
        // Incluye Carrera_Profesional, Fecha_Nacimiento y Genero
        $query_perfil = "INSERT INTO perfil_estudiante (ID_Usuario, Carrera_Profesional, Fecha_Nacimiento, Genero, Experiencia_Laboral, Foto_Perfil) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_perfil = mysqli_prepare($con, $query_perfil);
        if (!$stmt_perfil) {
            throw new Exception("Error al preparar la consulta de perfil de estudiante: " . mysqli_error($con));
        }
        // 'isssss' -> ID_Usuario (int), Carrera_Profesional (string), Fecha_Nacimiento (string), Genero (string), Experiencia_Laboral (string), Foto_Perfil (string)
        mysqli_stmt_bind_param($stmt_perfil, "isssss", $id_nuevo_usuario, $carrera_profesional, $fecha_nacimiento, $genero, $experiencia_laboral, $foto_perfil);
        mysqli_stmt_execute($stmt_perfil);

        if (mysqli_stmt_affected_rows($stmt_perfil) === 0) {
            throw new Exception("No se pudo insertar el perfil del estudiante.");
        }
        mysqli_stmt_close($stmt_perfil); // Cerrar statement

        // Opcional: Si tienes una tabla para preferencias de notificación y quieres guardarla
        /*
        $acepta_notificaciones = ($datos['notificaciones'] === 'true') ? 1 : 0;
        $query_notificaciones = "INSERT INTO preferencias_correo_usuario (ID_Usuario, Acepta_Notificaciones) VALUES (?, ?)";
        $stmt_notificaciones = mysqli_prepare($con, $query_notificaciones);
        if (!$stmt_notificaciones) {
            throw new Exception("Error al preparar la consulta de notificaciones: " . mysqli_error($con));
        }
        mysqli_stmt_bind_param($stmt_notificaciones, "ii", $id_nuevo_usuario, $acepta_notificaciones);
        mysqli_stmt_execute($stmt_notificaciones);
        if (mysqli_stmt_affected_rows($stmt_notificaciones) === 0) {
            error_log("No se pudo insertar la preferencia de notificación para el usuario " . $id_nuevo_usuario);
        }
        mysqli_stmt_close($stmt_notificaciones);
        */

        // Confirmar la transacción si todo fue exitoso
        mysqli_commit($con);

        $response = [
            'success' => true,
            'msg' => '¡Te has registrado correctamente!'
        ];

    } catch (Exception $e) {
        // En caso de error, deshacer la transacción
        mysqli_rollback($con);
        // Loguea el error real para depuración (revisar logs del servidor web)
        error_log("Error en registro de estudiante: " . $e->getMessage() . " | SQL Error: " . mysqli_error($con));
        $response = [
            'success' => false,
            'error' => 'Error al registrar el estudiante: ' . $e->getMessage() // Muestra el mensaje de la excepción al frontend
        ];
        // En un entorno de producción, podrías poner un mensaje más genérico:
        // $response = ['success' => false, 'error' => 'Error al registrar el estudiante. Por favor, inténtalo de nuevo más tarde.'];
    } finally {
        // mysqli_close($con); // Se puede cerrar la conexión aquí si este script es el final de la ejecución para esta petición.
                                // Si otros scripts posteriores usarán $con, NO la cierres.
    }
}

// Devolver respuesta en JSON
echo json_encode($response);

?>