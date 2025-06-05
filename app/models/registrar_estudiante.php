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
    // Sanear y obtener los datos
    // mysqli_real_escape_string es bueno, pero las sentencias preparadas son la principal defensa SQLi
    $nombre = mysqli_real_escape_string($con, $datos['nombre']);
    $apellido = mysqli_real_escape_string($con, $datos['apellido']);
    $correo_electronico = mysqli_real_escape_string($con, $datos['email']);
    $fecha_nacimiento = mysqli_real_escape_string($con, $datos['fechaNacimiento']); // Formato YYYY-MM-DD
    $genero = mysqli_real_escape_string($con, $datos['genero']); // Masculino/Femenino/Otro

    $carrera_profesional = mysqli_real_escape_string($con, $datos['carrera']);

    $contrasena_plana = $datos['clave']; // Contraseña en texto plano

    // Campos para perfil_estudiante que no se piden en el formulario pero son necesarios
    // Dejar vacíos si tu esquema los permite como '' o NULL, o definir valores por defecto
    $foto_perfil = ''; // Tu esquema dice NO NULL, por lo que '' es lo más seguro si no pides una foto
    // El campo Anio_Graduacion es NULL en tu esquema, no es necesario insertarlo si no lo tienes.
    // Si lo insertaras, tendrías que pasar NULL o un valor int válido.

    // Generar el hash de la contraseña
    $opciones_hashing = ['cost' => 12];
    $contrasena_hash = password_hash($contrasena_plana, PASSWORD_BCRYPT, $opciones_hashing);

    // Iniciar una transacción para asegurar la integridad de los datos
    mysqli_begin_transaction($con);

    try {
        // 1. Verificar si el correo electrónico ya existe en la tabla 'usuario'
        $check_email_query = "SELECT ID_Usuario FROM usuario WHERE Correo_Electronico = ?";
        $stmt_check = mysqli_prepare($con, $check_email_query);
        if (!$stmt_check) {
            throw new Exception("Error al preparar la consulta de verificación de email: " . mysqli_error($con));
        }
        mysqli_stmt_bind_param($stmt_check, "s", $correo_electronico);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            throw new Exception("El correo electrónico ya está registrado.");
        }
        mysqli_stmt_close($stmt_check);

        // 2. Insertar el nuevo usuario en la tabla 'usuario'
        // Tu tabla 'usuario' tiene Nombre, Apellido, Correo_Electronico, ID_Rol_FK, estado_us
        $ID_Rol_Estudiante = 2; // Asegúrate de que este ID_Rol_FK sea correcto para 'estudiante' en tu tabla 'rol'
        $estado_usuario = 'Activo'; // Coincide con ENUM('Activo','Inactivo')

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
        mysqli_stmt_close($stmt_usuario);

        // 3. Insertar el hash de la contraseña en la tabla 'contrasenas'
        // Asumiendo que esta tabla tiene ID_Usuario y Contrasena_Hash
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
        mysqli_stmt_close($stmt_contrasena);

        // 4. Insertar los datos específicos del estudiante en 'perfil_estudiante'
        // Los campos en tu formulario y tu tabla 'perfil_estudiante' son:
        // ID_Usuario, Carrera_Profesional, Foto_Perfil, Fecha_Nacimiento, Genero
        // 'Anio_Graduacion' es NULLable y no se pide en el formulario, así que no lo incluimos en el INSERT.
        $query_perfil = "INSERT INTO perfil_estudiante (ID_Usuario, Carrera_Profesional, Foto_Perfil, Fecha_Nacimiento, Genero) VALUES (?, ?, ?, ?, ?)";
        $stmt_perfil = mysqli_prepare($con, $query_perfil);
        if (!$stmt_perfil) {
            throw new Exception("Error al preparar la consulta de perfil de estudiante: " . mysqli_error($con));
        }
        // 'issss' -> ID_Usuario (int), Carrera_Profesional (string), Foto_Perfil (string), Fecha_Nacimiento (string), Genero (string)
        // Corregido: Son 5 parámetros, por lo tanto 5 especificadores de tipo.
        mysqli_stmt_bind_param($stmt_perfil, "issss", $id_nuevo_usuario, $carrera_profesional, $foto_perfil, $fecha_nacimiento, $genero);
        mysqli_stmt_execute($stmt_perfil);

        if (mysqli_stmt_affected_rows($stmt_perfil) === 0) {
            throw new Exception("No se pudo insertar el perfil del estudiante.");
        }
        mysqli_stmt_close($stmt_perfil);

        // Opcional: Si tienes una tabla para preferencias de notificación y quieres guardarla
        // y el checkbox de notificaciones está en el formulario.
        // Asumiendo que 'preferencias_correo_usuario' tiene ID_Usuario y Acepta_Notificaciones (boolean/tinyint)
        if (isset($datos['notificaciones'])) {
            $acepta_notificaciones = ($datos['notificaciones'] === 'true') ? 1 : 0;
            $query_notificaciones = "INSERT INTO preferencias_correo_usuario (ID_Usuario, Enviar_Notificaciones) VALUES (?, ?)";
            $stmt_notificaciones = mysqli_prepare($con, $query_notificaciones);
            if (!$stmt_notificaciones) {
                error_log("Error al preparar la consulta de notificaciones: " . mysqli_error($con));
                // No lanzamos una excepción fatal aquí si la tabla es opcional
            } else {
                mysqli_stmt_bind_param($stmt_notificaciones, "ii", $id_nuevo_usuario, $acepta_notificaciones);
                mysqli_stmt_execute($stmt_notificaciones);
                if (mysqli_stmt_affected_rows($stmt_notificaciones) === 0) {
                    error_log("No se pudo insertar la preferencia de notificación para el usuario " . $id_nuevo_usuario);
                }
                mysqli_stmt_close($stmt_notificaciones);
            }
        }


        // Confirmar la transacción si todo fue exitoso
        mysqli_commit($con);

        $response = [
            'success' => true,
            'msg' => '¡Te has registrado correctamente!'
        ];

    } catch (Exception $e) {
        // En caso de error, deshacer la transacción
        mysqli_rollback($con);
        error_log("Error en registro de estudiante: " . $e->getMessage() . " | SQL Error: " . mysqli_error($con));
        $response = [
            'success' => false,
            'error' => 'Error al registrar el estudiante: ' . $e->getMessage() // Muestra el mensaje de la excepción al frontend
        ];
    } finally {
        // mysqli_close($con); // Se puede cerrar la conexión aquí si este script es el final de la ejecución para esta petición.
    }
}

// Devolver respuesta en JSON
echo json_encode($response);

?>