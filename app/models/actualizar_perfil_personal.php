<?php

// Muestra todos los errores y advertencias para depuración (SOLO EN DESARROLLO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../config/conexion.php';

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = $_POST;

    // --- Validación inicial de campos obligatorios y formato de email ---
    if (
        empty($datos['nombre']) ||
        empty($datos['apellido']) || 
        empty($datos['email'])
    ) {
        $response = [
            'success' => false,
            'error' => 'Los campos Nombre, Apellido y Correo Electrónico son obligatorios.'
        ];
    } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $response = [
            'success' => false,
            'error' => 'El correo electrónico no es válido.'
        ];
    } else {
        // --- Lógica de actualización de la base de datos ---

        // Verificar si el usuario está autenticado (ID en sesión)
        if (!isset($_SESSION['ID_Usuario'])) {
            $response = [
                'success' => false,
                'error' => 'Usuario no autenticado. Por favor, inicie sesión.'
            ];
        } else {
            $idUsuario = $_SESSION['ID_Usuario']; // Obtener el ID del usuario logueado

            // Sanear los datos
            $nombre = $con->real_escape_string($datos['nombre']);
            $apellido = $con->real_escape_string($datos['apellido']);
            $email = $con->real_escape_string($datos['email']);
            // El teléfono puede ser opcional, se trata como string vacío si no se envía
            $telefono = $con->real_escape_string($datos['telefono'] ?? ''); 

            $con->autocommit(false); // Desactivar el auto-commit
            $transaction_successful = true; // Bandera para controlar el éxito de la transacción

            try {
                // Paso 1: Obtener el ID_Perfil_Estudiante asociado al ID_Usuario
                $idPerfilEstudiante = null;
                $sql_get_perfil_id = "SELECT ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = ?";
                $stmt_get_perfil_id = $con->prepare($sql_get_perfil_id);

                if ($stmt_get_perfil_id) {
                    $stmt_get_perfil_id->bind_param('i', $idUsuario);
                    $stmt_get_perfil_id->execute();
                    $stmt_get_perfil_id->bind_result($idPerfilEstudiante);
                    $stmt_get_perfil_id->fetch();
                    $stmt_get_perfil_id->close();

                    if (is_null($idPerfilEstudiante)) {
                        $transaction_successful = false;
                        $response = [
                            'success' => false,
                            'error' => 'No se encontró un perfil de estudiante asociado a este usuario. Asegúrese de que el perfil de estudiante exista.'
                        ];
                    }
                } else {
                    $transaction_successful = false;
                    $response = [
                        'success' => false,
                        'error' => 'Error al preparar la consulta para obtener ID de perfil: ' . $con->error
                    ];
                    error_log("Error al preparar SELECT ID_Perfil_Estudiante: " . $con->error);
                }

                // Si el paso anterior fue exitoso, continuar con las actualizaciones
                if ($transaction_successful) {
                    // Paso 2: Actualizar la tabla 'usuario' (Nombre, Apellido, Correo)
                    // NOMBRES DE COLUMNA CORREGIDOS SEGÚN TU DB 'usuario'
                    $sql_usuario = "UPDATE usuario SET Nombre = ?, Apellido = ?, Correo_Electronico = ? WHERE ID_Usuario = ?";
                    $stmt_usuario = $con->prepare($sql_usuario);

                    if ($stmt_usuario) {
                        $stmt_usuario->bind_param('sssi', $nombre, $apellido, $email, $idUsuario);
                        if (!$stmt_usuario->execute()) {
                            $transaction_successful = false;
                            $response = [
                                'success' => false,
                                'error' => 'Error al actualizar datos en tabla usuario: ' . $stmt_usuario->error
                            ];
                            error_log("Error al ejecutar UPDATE usuario: " . $stmt_usuario->error);
                        }
                        $stmt_usuario->close();
                    } else {
                        $transaction_successful = false;
                        $response = [
                            'success' => false,
                            'error' => 'Error al preparar la sentencia para tabla usuario: ' . $con->error
                        ];
                        error_log("Error al preparar UPDATE usuario: " . $con->error);
                    }
                }

                // Si los pasos anteriores fueron exitosos, continuar con el teléfono
                if ($transaction_successful) {
                    // Paso 3: Actualizar/Insertar el teléfono en la tabla 'contactos_estudiantes'
                    // ESTO ES SOLO PARA ESTUDIANTES Y LA TABLA 'contactos_estudiantes'
                    // La columna en la DB es 'Teléfono' (con tilde)
                    
                    // Primero, verifica si ya existe un registro de teléfono para este perfil de estudiante.
                    $sql_check_phone = "SELECT ID_Contacto FROM contactos_estudiantes WHERE ID_Perfil_Estudiante = ?";
                    $stmt_check_phone = $con->prepare($sql_check_phone);

                    if ($stmt_check_phone) {
                        $stmt_check_phone->bind_param('i', $idPerfilEstudiante);
                        $stmt_check_phone->execute();
                        $stmt_check_phone->store_result();

                        if ($stmt_check_phone->num_rows > 0) {
                            // Si ya existe, actualiza el teléfono
                            $sql_contactos_update = "UPDATE contactos_estudiantes SET Teléfono = ? WHERE ID_Perfil_Estudiante = ?";
                            $stmt_contactos_update = $con->prepare($sql_contactos_update);

                            if ($stmt_contactos_update) {
                                $stmt_contactos_update->bind_param('si', $telefono, $idPerfilEstudiante);
                                if (!$stmt_contactos_update->execute()) {
                                    $transaction_successful = false;
                                    $response = [
                                        'success' => false,
                                        'error' => 'Error al actualizar teléfono en contactos_estudiantes: ' . $stmt_contactos_update->error
                                    ];
                                    error_log("Error al ejecutar UPDATE contactos_estudiantes: " . $stmt_contactos_update->error);
                                }
                                $stmt_contactos_update->close();
                            } else {
                                $transaction_successful = false;
                                $response = [
                                    'success' => false,
                                    'error' => 'Error al preparar la sentencia UPDATE para contactos_estudiantes: ' . $con->error
                                ];
                                error_log("Error al preparar UPDATE contactos_estudiantes: " . $con->error);
                            }
                        } else {
                            // Si no existe, inserta un nuevo registro de teléfono
                            $sql_contactos_insert = "INSERT INTO contactos_estudiantes (ID_Perfil_Estudiante, Teléfono) VALUES (?, ?)";
                            $stmt_contactos_insert = $con->prepare($sql_contactos_insert);

                            if ($stmt_contactos_insert) {
                                $stmt_contactos_insert->bind_param('is', $idPerfilEstudiante, $telefono);
                                if (!$stmt_contactos_insert->execute()) {
                                    $transaction_successful = false;
                                    $response = [
                                        'success' => false,
                                        'error' => 'Error al insertar teléfono en contactos_estudiantes: ' . $stmt_contactos_insert->error
                                    ];
                                    error_log("Error al ejecutar INSERT contactos_estudiantes: " . $stmt_contactos_insert->error);
                                }
                                $stmt_contactos_insert->close();
                            } else {
                                $transaction_successful = false;
                                $response = [
                                    'success' => false,
                                    'error' => 'Error al preparar la sentencia INSERT para contactos_estudiantes: ' . $con->error
                                ];
                                error_log("Error al preparar INSERT contactos_estudiantes: " . $con->error);
                            }
                        }
                        $stmt_check_phone->close();
                    } else {
                         $transaction_successful = false;
                         $response = [
                            'success' => false,
                            'error' => 'Error al preparar la sentencia de verificación para contactos_estudiantes: ' . $con->error
                        ];
                        error_log("Error al preparar SELECT ID_Contacto en contactos_estudiantes: " . $con->error);
                    }
                }
                
                // --- Confirmar o revertir la transacción ---
                if ($transaction_successful) {
                    $con->commit();
                    $response = [
                        'success' => true,
                        'msg' => 'Tu información personal ha sido actualizada correctamente.'
                    ];
                } else {
                    $con->rollback();
                }

            } catch (Exception $e) {
                $con->rollback();
                $response = [
                    'success' => false,
                    'error' => 'Excepción inesperada al actualizar: ' . $e->getMessage()
                ];
                error_log("Excepción al actualizar perfil: " . $e->getMessage());
            } finally {
                $con->autocommit(true);
            }
        }
    }
} else {
    $response = [
        'success' => false,
        'error' => 'Método de solicitud no permitido.'
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
$con->close();

?>