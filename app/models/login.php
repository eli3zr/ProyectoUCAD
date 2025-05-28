<?php
// app/models/login.php

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/conexion.php';

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $con->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    error_log("--- Intento de Login ---");
    error_log("Email recibido: " . $email);

    if (empty($email) || empty($password)) {
        $response = [
            'success' => false,
            'error' => 'Por favor, ingresa tu correo electrónico y contraseña.'
        ];
        error_log("Error: Campos vacíos.");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = [
            'success' => false,
            'error' => 'El formato del correo electrónico no es válido.'
        ];
        error_log("Error: Formato de email inválido.");
    } else {
        try {
            // Seleccionamos ID_Rol_FK y estado_us de la tabla usuario
            $sql = "SELECT u.ID_Usuario, u.ID_Rol_FK, c.Contrasena_Hash, u.estado_us 
                    FROM usuario u
                    JOIN contrasenas c ON u.ID_Usuario = c.ID_Usuario
                    WHERE u.Correo_Electronico = ?"; 

            error_log("DEBUG: SQL Query being prepared: " . $sql);

            $stmt = $con->prepare($sql);

            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $stmt->store_result();

                error_log("SQL ejecutado. Filas encontradas: " . $stmt->num_rows);

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id_usuario, $id_rol_fk, $hashed_password, $estado_usuario); // Añadido estado_us
                    $stmt->fetch();

                    error_log("ID_Usuario de BD: " . $id_usuario);
                    error_log("ID_Rol_FK de BD: " . $id_rol_fk);
                    error_log("Contrasena_Hash de BD: " . $hashed_password); 
                    error_log("Estado de usuario de BD: " . $estado_usuario);

                    // Verificar si el usuario está inactivo
                    if ($estado_usuario === 'Inactivo') { // Asegúrate de que el valor 'Inactivo' coincide con tu ENUM
                        $response = [
                            'success' => false,
                            'error' => 'Tu cuenta está inactiva. Por favor, contacta al soporte.'
                        ];
                        error_log("Error: Cuenta inactiva para el usuario: " . $email);
                    } else if (password_verify($password, $hashed_password)) {
                        error_log("password_verify(): ¡TRUE! Contraseña verificada correctamente.");
                        
                        // --- NUEVO: Obtener el nombre del rol a partir del ID_Rol_FK ---
                        $nombre_rol = 'desconocido'; // Valor por defecto en caso de no encontrar el rol
                        $query_rol_nombre = "SELECT Nombre_Rol FROM rol WHERE ID_Rol = ?";
                        $stmt_rol_nombre = $con->prepare($query_rol_nombre);
                        if ($stmt_rol_nombre) {
                            $stmt_rol_nombre->bind_param('i', $id_rol_fk); // 'i' para entero
                            $stmt_rol_nombre->execute();
                            $stmt_rol_nombre->bind_result($rol_encontrado);
                            if ($stmt_rol_nombre->fetch()) {
                                $nombre_rol = $rol_encontrado;
                            }
                            $stmt_rol_nombre->close();
                        } else {
                            error_log("Error al preparar la consulta de nombre de rol: " . $con->error);
                        }
                        // --- FIN NUEVO ---

                        $_SESSION['id_usuario'] = $id_usuario;
                        $_SESSION['id_rol_fk'] = $id_rol_fk; // Guardar el ID_Rol_FK en sesión
                        $_SESSION['nombre_rol'] = $nombre_rol; // Guardar también el nombre del rol en sesión

                        $response = [
                            'success' => true,
                            'msg' => 'Inicio de sesión exitoso.',
                            'id_rol_fk' => $id_rol_fk,
                            'nombre_rol' => $nombre_rol // Devolver el nombre del rol al frontend
                        ];
                    } else {
                        error_log("password_verify(): ¡FALSE! Contraseña ingresada NO coincide con el hash.");
                        $response = [
                            'success' => false,
                            'error' => 'Correo electrónico o contraseña incorrectos.'
                        ];
                    }
                } else {
                    error_log("Error: Usuario no encontrado con el email proporcionado o JOIN falló.");
                    $response = [
                        'success' => false,
                        'error' => 'Correo electrónico o contraseña incorrectos.'
                    ];
                }
                $stmt->close();
            } else {
                error_log("Error al preparar la sentencia SQL: " . $con->error);
                $response = [
                    'success' => false,
                    'error' => 'Error interno del servidor al preparar la consulta: ' . $con->error
                ];
            }
        } catch (Exception $e) {
            error_log("Excepción inesperada en el login: " . $e->getMessage());
            $response = [
                'success' => false,
                'error' => 'Error inesperado en el servidor: ' . $e->getMessage()
            ];
        }
    }
} else {
    error_log("Error: Solicitud no POST.");
    $response = [
        'success' => false,
        'error' => 'Método de solicitud no permitido.'
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
$con->close();
?>