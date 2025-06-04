<?php

require_once __DIR__ . '/../config/conexion.php'; // Asegúrate de que esta ruta sea correcta

// Iniciar sesión para acceder a las variables de sesión del usuario logueado
session_start();

// Inicializar el array de respuesta que se enviará como JSON
$response = [];

// Verificar si la solicitud es un POST, que es el método esperado del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ** Importante: Obtén el ID del usuario y el ID del perfil de empresa de la sesión. **
    // Estos deben ser establecidos correctamente en tu script de inicio de sesión.
    if (!isset($_SESSION['ID_Usuario']) || !isset($_SESSION['ID_Perfil_Empresa'])) {
        $response = [
            'success' => false,
            'error' => 'Sesión inválida. Por favor, inicie sesión con su cuenta de empresa (ID de Usuario y Perfil de Empresa requeridos).'
        ];
        // Enviar respuesta y salir si la sesión no es válida
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $idUsuario = $_SESSION['ID_Usuario'];         
    $idPerfilEmpresa = $_SESSION['ID_Perfil_Empresa']; 

    // Capturar la contraseña de confirmación enviada desde el formulario
    $confirmPassword = isset($_POST['deletePassword']) ? $_POST['deletePassword'] : '';

    // --- Validación de los datos recibidos ---
    $errors = [];
    if (empty($confirmPassword)) {
        $errors[] = "Debe introducir su contraseña para confirmar la eliminación.";
    }

    // Si se encontraron errores de validación, se prepara la respuesta de error
    if (!empty($errors)) {
        $response = [
            'success' => false,
            'error' => implode("<br>", $errors) // Unir los errores en una sola cadena para el mensaje
        ];
    } else {
        // Iniciar una transacción para asegurar que todas las operaciones de la BD sean atómicas
        mysqli_begin_transaction($con);

        try {
            // 1. Obtener el hash actual de la contraseña del usuario para verificación
            // Usando la tabla 'contraseñas' (plural) y la columna 'Contrasena_Hash'
            $stmt_password = mysqli_prepare($con, "SELECT Contrasena_Hash FROM contrasenas WHERE ID_Usuario = ?");
            if (!$stmt_password) {
                throw new Exception("Error al preparar la consulta de obtención de contraseña: " . mysqli_error($con));
            }
            mysqli_stmt_bind_param($stmt_password, 'i', $idUsuario); // 'i' indica que $idUsuario es un entero
            mysqli_stmt_execute($stmt_password);
            $result_password = mysqli_stmt_get_result($stmt_password);
            $user_password_data = mysqli_fetch_assoc($result_password);
            mysqli_stmt_close($stmt_password); // Cerrar el statement

            if (!$user_password_data || !password_verify($confirmPassword, $user_password_data['Contrasena_Hash'])) {
                throw new Exception("Contraseña de confirmación incorrecta.");
            }

            // 2. Eliminar ofertas de trabajo asociadas a este perfil de empresa
            // Asumiendo que 'ofertas_trabajo' tiene una FK 'ID_Empresa' que referencia a 'perfil_empresa'
            $stmt_ofertas = mysqli_prepare($con, "DELETE FROM oferta_laboral WHERE ID_Empresa = ?");
            if (!$stmt_ofertas) {
                throw new Exception("Error al preparar la eliminación de ofertas de trabajo: " . mysqli_error($con));
            }
            mysqli_stmt_bind_param($stmt_ofertas, 'i', $idPerfilEmpresa);
            mysqli_stmt_execute($stmt_ofertas);
            mysqli_stmt_close($stmt_ofertas);

            // 3. Eliminar el perfil de la empresa
            // Usando la tabla 'perfil_empresa'
            $stmt_perfil_empresa = mysqli_prepare($con, "DELETE FROM perfil_empresa WHERE ID_Perfil_Empresa = ?");
            if (!$stmt_perfil_empresa) {
                throw new Exception("Error al preparar la eliminación del perfil de empresa: " . mysqli_error($con));
            }
            mysqli_stmt_bind_param($stmt_perfil_empresa, 'i', $idPerfilEmpresa);
            mysqli_stmt_execute($stmt_perfil_empresa);
            mysqli_stmt_close($stmt_perfil_empresa);

            // 4. Eliminar la contraseña del usuario
            // Usando la tabla 'contraseñas' (plural)
            $stmt_contrasena = mysqli_prepare($con, "DELETE FROM contrasenas WHERE ID_Usuario = ?");
            if (!$stmt_contrasena) {
                throw new Exception("Error al preparar la eliminación de la contraseña del usuario: " . mysqli_error($con));
            }
            mysqli_stmt_bind_param($stmt_contrasena, 'i', $idUsuario);
            mysqli_stmt_execute($stmt_contrasena);
            mysqli_stmt_close($stmt_contrasena);

            // 5. Eliminar el registro del usuario
            // Usando la tabla 'usuario'
            $stmt_usuario = mysqli_prepare($con, "DELETE FROM usuario WHERE ID_Usuario = ?");
            if (!$stmt_usuario) {
                throw new Exception("Error al preparar la eliminación del usuario: " . mysqli_error($con));
            }
            mysqli_stmt_bind_param($stmt_usuario, 'i', $idUsuario);
            mysqli_stmt_execute($stmt_usuario);
            mysqli_stmt_close($stmt_usuario);

            // Si todas las operaciones fueron exitosas, confirmar la transacción
            mysqli_commit($con);

            // Destruir la sesión del usuario (para desloguearlo)
            session_unset();
            session_destroy();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }

            $response = [
                'success' => true,
                'msg' => 'Perfil de empresa eliminado exitosamente. Su sesión ha sido cerrada.'
            ];

        } catch (Exception $e) {
            // Si ocurre algún error, revertir la transacción
            mysqli_rollback($con);
            $response = [
                'success' => false,
                'error' => "Error en la base de datos: " . $e->getMessage()
            ];
        }
    }
} else {
    // Si la solicitud no es POST
    $response = [
        'success' => false,
        'error' => 'Método de solicitud no permitido.'
    ];
}

// Cierra la conexión a la base de datos
mysqli_close($con);

// Establecer el tipo de contenido de la respuesta como JSON
header('Content-Type: application/json');
// Convertir el array de respuesta a formato JSON y enviarlo al cliente
echo json_encode($response);
exit(); // Terminar la ejecución del script para evitar salida adicional