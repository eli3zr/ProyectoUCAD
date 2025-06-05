<?php
// app/models/actualizar_perfil_academico.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/actualizar_perfil_academico_errors.log'); // Log específico

session_start();

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// Inicializar la respuesta por defecto
$response = ['success' => false, 'error' => '', 'msg' => ''];

// Obtener el ID de usuario de la sesión para la bitácora. Usar 0 si no está logueado (para bitácora de acceso no autorizado).
$usuario_id = $_SESSION['ID_Usuario'] ?? 0;

// Verificar que la conexión a la base de datos sea válida
if (!isset($con) || !is_object($con) || mysqli_connect_errno()) {
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida en actualizar_perfil_academico.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg);
    $response['error'] = 'Error interno del servidor: La conexión a la base de datos no está disponible.';
    // No podemos bitacorar aquí si la conexión falló
    header('Content-Type: application/json');
    echo json_encode($response);
    exit(); // Terminar la ejecución si la conexión no es válida
}

// Establecer el encabezado de contenido aquí para que siempre se envíe
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar y sanear los datos enviados por AJAX
    $carrera = $con->real_escape_string($_POST['carrera'] ?? '');
    // Convertir a INT si el campo es numérico, si está vacío o no es un número, se guarda como NULL
    $anioGraduacion = empty($_POST['anioGraduacion']) ? NULL : filter_var($_POST['anioGraduacion'], FILTER_VALIDATE_INT); 

    // Validaciones
    if (!is_null($anioGraduacion) && (!is_numeric($anioGraduacion) || strlen((string)$anioGraduacion) != 4)) {
        $response = [
            'success' => false,
            'error' => 'El Año de Graduación debe ser un número de 4 dígitos válido.'
        ];
        // Bitácora: Error de validación de entrada
        registrarEventoBitacora($con, 0, 'perfil_estudiante', 'LOGIN_FALLIDO', $usuario_id, NULL, 'Error de validación: Año de Graduación inválido. Valor recibido: ' . ($_POST['anioGraduacion'] ?? 'vacío'));
    } else {
        // Verificar si el usuario está autenticado (ID en sesión)
        if (!isset($_SESSION['ID_Usuario'])) {
            $response = [
                'success' => false,
                'error' => 'Usuario no autenticado. Por favor, inicie sesión.'
            ];
            // Bitácora: Acceso no autorizado
            registrarEventoBitacora($con, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', 0, NULL, 'Intento de actualizar perfil académico sin sesión activa.');
        } else {
            $idUsuario = $_SESSION['ID_Usuario']; // Ya tenemos $usuario_id, pero lo renombramos por claridad
            $con->autocommit(false); // Iniciar transacción
            $transaction_successful = true;
            $idPerfilEstudiante = null; // Inicializar antes del try para que sea accesible en el finally

            try {
                // --- BITACOLA: Obtener datos antes de la actualización en 'perfil_estudiante' ---
                $datos_antes = null;
                $stmt_get_antes = $con->prepare("SELECT Carrera_Profesional, Anio_Graduacion, ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = ? LIMIT 1");
                if ($stmt_get_antes) {
                    $stmt_get_antes->bind_param("i", $idUsuario);
                    $stmt_get_antes->execute();
                    $result_get_antes = $stmt_get_antes->get_result();
                    $datos_antes_array = $result_get_antes->fetch_assoc();
                    $stmt_get_antes->close();
                    if ($datos_antes_array) {
                        $datos_antes = json_encode($datos_antes_array);
                        $idPerfilEstudiante = $datos_antes_array['ID_Perfil_Estudiante']; // Obtener ID de perfil estudiante aquí
                    }
                } else {
                    $transaction_successful = false;
                    $response = [
                        'success' => false,
                        'error' => 'Error al preparar la consulta para obtener datos previos del perfil académico: ' . $con->error
                    ];
                    error_log("Error al preparar SELECT para datos previos (academico): " . $con->error);
                    // Bitácora: Error del sistema
                    registrarEventoBitacora($con, 0, 'perfil_estudiante', 'ERROR_SISTEMA', $idUsuario, NULL, 'Error al obtener datos previos de perfil académico para bitácora: ' . $con->error);
                }

                // Si no se encontró el ID_Perfil_Estudiante en el paso anterior, o hubo error
                if (is_null($idPerfilEstudiante) && $transaction_successful) { // Solo si no hubo error de preparación
                    $transaction_successful = false;
                    $response = [
                        'success' => false,
                        'error' => 'No se encontró un perfil de estudiante asociado a este usuario.'
                    ];
                    // Bitácora: Advertencia
                    registrarEventoBitacora($con, 0, 'perfil_estudiante', 'ADVERTENCIA', $idUsuario, NULL, 'No se encontró perfil de estudiante asociado al usuario ID: ' . $idUsuario);
                }
                
                // Si se encontró el ID_Perfil_Estudiante y no hubo errores, proceder a actualizar
                if ($transaction_successful) {
                    // Actualizar la tabla 'perfil_estudiante' con Carrera_Profesional y Anio_Graduacion
                    $sql_update_academico = "UPDATE perfil_estudiante SET Carrera_Profesional = ?, Anio_Graduacion = ? WHERE ID_Perfil_Estudiante = ?";
                    $stmt_update_academico = $con->prepare($sql_update_academico);

                    if ($stmt_update_academico) {
                        // MySQLi bind_param requiere que pases NULL como una variable
                        $param_anio_graduacion = $anioGraduacion; // Copia para el bind_param
                        $stmt_update_academico->bind_param('sii', $carrera, $param_anio_graduacion, $idPerfilEstudiante);
                        
                        if (!$stmt_update_academico->execute()) {
                            $transaction_successful = false;
                            $response = [
                                'success' => false,
                                'error' => 'Error al actualizar información académica: ' . $stmt_update_academico->error
                            ];
                            error_log("Error al ejecutar UPDATE perfil_estudiante (academico): " . $stmt_update_academico->error);
                            // Bitácora: Error del sistema
                            registrarEventoBitacora($con, $idPerfilEstudiante, 'perfil_estudiante', 'ERROR_SISTEMA', $idUsuario, NULL, 'Error al ejecutar UPDATE de perfil académico: ' . $stmt_update_academico->error);
                        } else {
                            if ($stmt_update_academico->affected_rows > 0) {
                                $response = [
                                    'success' => true,
                                    'msg' => 'Información académica actualizada correctamente.'
                                ];
                                // --- BITACOLA: Registrar UPDATE exitoso ---
                                $datos_despues = extraerDatosParaBitacora($con, $idPerfilEstudiante, 'perfil_estudiante');
                                registrarEventoBitacora($con, $idPerfilEstudiante, 'perfil_estudiante', 'UPDATE', $idUsuario, $datos_antes, $datos_despues);
                            } else {
                                $response = [
                                    'success' => true, // Todavía es un éxito que no haya errores, solo no hubo cambios
                                    'msg' => 'Información académica recibida, pero no se detectaron cambios.'
                                ];
                                // Bitácora: Advertencia
                                registrarEventoBitacora($con, $idPerfilEstudiante, 'perfil_estudiante', 'ADVERTENCIA', $idUsuario, NULL, 'Actualización de perfil académico: No se detectaron cambios. Datos enviados: ' . json_encode(['carrera' => $carrera, 'anioGraduacion' => $anioGraduacion]));
                            }
                        }
                        $stmt_update_academico->close();
                    } else {
                        $transaction_successful = false;
                        $response = [
                            'success' => false,
                            'error' => 'Error al preparar la sentencia de actualización académica: ' . $con->error
                        ];
                        error_log("Error al preparar UPDATE perfil_estudiante (academico): " . $con->error);
                        // Bitácora: Error del sistema
                        registrarEventoBitacora($con, 0, 'perfil_estudiante', 'ERROR_SISTEMA', $idUsuario, NULL, 'Error al preparar UPDATE de perfil académico: ' . $con->error);
                    }
                }

                // Finalizar transacción
                if ($transaction_successful) {
                    $con->commit();
                } else {
                    $con->rollback();
                }

            } catch (Exception $e) {
                $con->rollback();
                $response = [
                    'success' => false,
                    'error' => 'Excepción inesperada al actualizar información académica: ' . $e->getMessage()
                ];
                error_log("Excepción al actualizar información académica: " . $e->getMessage());
                // Bitácora: Error del sistema (excepción no manejada)
                registrarEventoBitacora($con, $idPerfilEstudiante ?? 0, 'perfil_estudiante', 'ERROR_SISTEMA', $idUsuario, NULL, 'Excepción inesperada al actualizar perfil académico: ' . $e->getMessage());
            } finally {
                // Asegurarse de restaurar el autocommit a true
                $con->autocommit(true); 
            }
        }
    }
} else {
    $response = [
        'success' => false,
        'error' => 'Método de solicitud no permitido.'
    ];
    // Bitácora: Advertencia
    registrarEventoBitacora($con, 0, 'sistema', 'ADVERTENCIA', $usuario_id, NULL, 'Método HTTP no permitido en actualizar_perfil_academico.php: ' . $_SERVER['REQUEST_METHOD']);
}

echo json_encode($response);

// Asegurarse de cerrar la conexión
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}

?>