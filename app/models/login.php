<?php
// app/models/login.php

session_start(); // Inicia la sesión de PHP

// Configuración de errores para depuración (¡Cambia a 0 en producción!)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // Asegúrate de que la carpeta 'logs' exista y sea escribible.

require_once __DIR__ . '/../config/conexion.php';

$response = [];

// --- Funciones para la Bitácora (puedes moverlas a un archivo include si las usas en más lugares) ---
/**
 * Registra un evento en la bitácora utilizando el procedimiento almacenado registrar_evento_bitacora.
 *
 * @param mysqli $con La conexión a la base de datos.
 * @param int $id_registro_afectado El ID del registro afectado (0 para eventos de sistema como login).
 * @param string $nombre_tabla El nombre de la tabla afectada ('no_aplica' para eventos de sistema).
 * @param string $tipo_accion_str El tipo de acción como string (ej. 'LOGIN_EXITOSO', 'LOGIN_FALLIDO').
 * @param int $id_usuario_logueado El ID del usuario que realiza la acción (0 para usuarios desconocidos o sistema).
 * @param string|null $info_antes Información antes de la acción (NULL si no aplica).
 * @param string|null $info_despues Información después de la acción (NULL si no aplica).
 */
function registrarEventoBitacora($con, $id_registro_afectado, $nombre_tabla, $tipo_accion_str, $id_usuario_logueado, $info_antes = null, $info_despues = null) {
    try {
        // Primero, obtener el id_accion_bitacora de la tabla accion_bitacora
        $stmt_accion = $con->prepare("SELECT id_accion_bitacora FROM accion_bitacora WHERE tipo_accion = ? AND estado = 'activo' LIMIT 1");
        if ($stmt_accion) {
            $stmt_accion->bind_param("s", $tipo_accion_str);
            $stmt_accion->execute();
            $stmt_accion->bind_result($id_accion_bitacora);
            $stmt_accion->fetch();
            $stmt_accion->close();

            if ($id_accion_bitacora) {
                // Llamar al procedimiento almacenado registrar_evento_bitacora
                $stmt_bitacora = $con->prepare("CALL registrar_evento_bitacora(?, ?, ?, ?, ?, ?)");
                if ($stmt_bitacora) {
                    // Si info_antes o info_despues son NULL, pasar "s" como su tipo. MySQLi los manejará correctamente.
                    $stmt_bitacora->bind_param("isiiis", $id_registro_afectado, $nombre_tabla, $id_accion_bitacora, $id_usuario_logueado, $info_antes, $info_despues);
                    $stmt_bitacora->execute();
                    $stmt_bitacora->close();
                    error_log("Bitácora registrada: Tipo='" . $tipo_accion_str . "', UsuarioID='" . $id_usuario_logueado . "', Info='" . ($info_despues ?? $info_antes) . "'");
                } else {
                    error_log("Error al preparar CALL registrar_evento_bitacora: " . $con->error);
                }
            } else {
                error_log("Advertencia: Tipo de acción '" . $tipo_accion_str . "' no encontrado o inactivo en accion_bitacora.");
            }
        } else {
            error_log("Error al preparar SELECT id_accion_bitacora: " . $con->error);
        }
    } catch (Exception $e) {
        error_log("Excepción al registrar bitácora: " . $e->getMessage());
    }
}
// --- Fin Funciones para la Bitácora ---


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
        // Registrar login fallido por campos vacíos
        registrarEventoBitacora($con, 0, 'no_aplica', 'LOGIN_FALLIDO', 0, NULL, 'Intento de login fallido: Campos vacíos');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = [
            'success' => false,
            'error' => 'El formato del correo electrónico no es válido.'
        ];
        error_log("Error: Formato de email inválido.");
        // Registrar login fallido por formato de email inválido
        registrarEventoBitacora($con, 0, 'no_aplica', 'LOGIN_FALLIDO', 0, NULL, 'Intento de login fallido: Formato de email inválido - ' . $email);
    } else {
        $id_usuario_log = 0; // Por defecto para eventos de usuario no identificado
        $log_message = ''; // Mensaje para la bitácora

        try {
            // Seleccionamos ID_Usuario, ID_Rol_FK, Contrasena_Hash, estado_us de la tabla usuario
            $sql = "SELECT u.ID_Usuario, u.ID_Rol_FK, c.Contrasena_Hash, u.estado_us
                            FROM usuario u
                            JOIN contrasenas c ON u.ID_Usuario = c.ID_Usuario
                            WHERE u.Correo_Electronico = ?";

            error_log("DEBUG: SQL Query being prepared for user: " . $sql);

            $stmt = $con->prepare($sql);

            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $stmt->store_result();

                error_log("SQL ejecutado. Filas encontradas: " . $stmt->num_rows);

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id_usuario, $id_rol_fk, $hashed_password, $estado_usuario);
                    $stmt->fetch();
                    $id_usuario_log = $id_usuario; // Asignamos el ID del usuario para futuras bitácoras

                    error_log("ID_Usuario de BD: " . $id_usuario);
                    error_log("ID_Rol_FK de BD: " . $id_rol_fk);
                    error_log("Estado de usuario de BD: " . $estado_usuario);

                    // Verificar si el usuario está inactivo
                    if ($estado_usuario === 'Inactivo') { // Asegúrate de que el valor 'Inactivo' coincide con tu ENUM
                        $response = [
                            'success' => false,
                            'error' => 'Tu cuenta está inactiva. Por favor, contacta al soporte.'
                        ];
                        $log_message = 'Cuenta inactiva para el usuario: ' . $email;
                        error_log("Error: " . $log_message);
                    } else if (password_verify($password, $hashed_password)) {
                        error_log("password_verify(): ¡TRUE! Contraseña verificada correctamente.");

                        // --- OBTENER EL NOMBRE DEL ROL ---
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
                            error_log("Nombre del rol obtenido: " . $nombre_rol);
                        } else {
                            error_log("Error al preparar la consulta de nombre de rol: " . $con->error);
                            // También registrar en bitácora un error de sistema si es crítico
                            registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario_log, NULL, 'Error al obtener nombre de rol para ID: ' . $id_rol_fk);
                        }
                        // --- FIN OBTENER EL NOMBRE DEL ROL ---

                        // --- ALMACENAR DATOS BÁSICOS EN SESIÓN ---
                        $_SESSION['ID_Usuario'] = $id_usuario;
                        $_SESSION['ID_Rol'] = $id_rol_fk;
                        $_SESSION['Nombre_Rol'] = $nombre_rol;

                        // --- Obtener ID_perfil_empresa si el rol es 'empresa' ---
                        if ($nombre_rol === 'Empresa') { // Asegúrate de que coincida con el valor exacto de tu ENUM
                            $id_perfil_empresa = null;
                            $query_perfil_empresa = "SELECT ID_Perfil_Empresa FROM perfil_empresa WHERE usuario_ID_Usuario = ?";
                            $stmt_perfil_empresa = $con->prepare($query_perfil_empresa);
                            if ($stmt_perfil_empresa) {
                                $stmt_perfil_empresa->bind_param('i', $id_usuario);
                                $stmt_perfil_empresa->execute();
                                $stmt_perfil_empresa->bind_result($perfil_empresa_id_encontrado);
                                if ($stmt_perfil_empresa->fetch()) {
                                    $id_perfil_empresa = $perfil_empresa_id_encontrado;
                                    $_SESSION['ID_Perfil_Empresa'] = $id_perfil_empresa;
                                    error_log("ID_Perfil_Empresa obtenido y guardado en sesión: " . $id_perfil_empresa);
                                } else {
                                    error_log("Advertencia: Usuario con rol 'Empresa' (ID_Usuario: " . $id_usuario . ") no tiene un perfil de empresa asociado en la tabla perfil_empresa. Verifica la relación usuario_ID_Usuario.");
                                    // Registrar en bitácora esta advertencia
                                    registrarEventoBitacora($con, $id_usuario, 'usuario', 'ADVERTENCIA', $id_usuario_log, NULL, 'Usuario Empresa sin perfil asociado: ' . $email);
                                }
                                $stmt_perfil_empresa->close();
                            } else {
                                error_log("Error al preparar la consulta de perfil de empresa: " . $con->error);
                                // Registrar en bitácora un error de sistema
                                registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario_log, NULL, 'Error al obtener perfil empresa para ID: ' . $id_usuario);
                            }
                        }
                        // --- FIN OBTENER ID_perfil_empresa ---

                        $response = [
                            'success' => true,
                            'msg' => 'Inicio de sesión exitoso.',
                            'id_rol_fk' => $id_rol_fk,
                            'nombre_rol' => $nombre_rol
                        ];
                        if (isset($id_perfil_empresa)) {
                            $response['id_perfil_empresa'] = $id_perfil_empresa;
                        }
                        $log_message = 'Login exitoso para usuario: ' . $email;
                        // Registrar Login Exitoso
                        registrarEventoBitacora($con, $id_usuario_log, 'usuario', 'LOGIN_EXITOSO', $id_usuario_log, NULL, $log_message);

                    } else {
                        // Contraseña incorrecta
                        error_log("password_verify(): ¡FALSE! Contraseña ingresada NO coincide con el hash.");
                        $response = [
                            'success' => false,
                            'error' => 'Correo electrónico o contraseña incorrectos.'
                        ];
                        $log_message = 'Intento de login fallido: Contraseña incorrecta para usuario: ' . $email;
                        // Registrar Login Fallido
                        registrarEventoBitacora($con, $id_usuario_log, 'usuario', 'LOGIN_FALLIDO', $id_usuario_log, NULL, $log_message);
                    }
                } else {
                    // Usuario no encontrado o JOIN falló
                    error_log("Error: Usuario no encontrado con el email proporcionado o JOIN falló.");
                    $response = [
                        'success' => false,
                        'error' => 'Correo electrónico o contraseña incorrectos.'
                    ];
                    $log_message = 'Intento de login fallido: Usuario no encontrado - ' . $email;
                    // Registrar Login Fallido (ID de usuario 0 ya que no se encontró)
                    registrarEventoBitacora($con, 0, 'usuario', 'LOGIN_FALLIDO', 0, NULL, $log_message);
                }
                $stmt->close();
            } else {
                // Error al preparar la sentencia SQL para el login
                error_log("Error al preparar la sentencia SQL: " . $con->error);
                $response = [
                    'success' => false,
                    'error' => 'Error interno del servidor al preparar la consulta de login: ' . $con->error
                ];
                $log_message = 'Error interno: Preparación de SQL fallida - ' . $con->error;
                // Registrar Error de Sistema
                registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', 0, NULL, $log_message);
            }
        } catch (Exception $e) {
            // Excepción PHP inesperada
            error_log("Excepción inesperada en el login: " . $e->getMessage());
            $response = [
                'success' => false,
                'error' => 'Error inesperado en el servidor: ' . $e->getMessage()
            ];
            $log_message = 'Excepción PHP inesperada en login: ' . $e->getMessage();
            // Registrar Error de Sistema (si tenemos ID de usuario, lo usamos, sino 0)
            registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario_log, NULL, $log_message);
        }
    }
} else {
    // Solicitud no POST
    error_log("Error: Solicitud no POST.");
    $response = [
        'success' => false,
        'error' => 'Método de solicitud no permitido.'
    ];
    // Registrar intento de acceso no autorizado
    registrarEventoBitacora($con, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', 0, NULL, 'Intento de acceso a login.php por método no permitido (' . $_SERVER['REQUEST_METHOD'] . ')');
}

header('Content-Type: application/json');
echo json_encode($response);
$con->close();
?>