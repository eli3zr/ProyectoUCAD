<?php
// app/models/actualizar_perfil_academico.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../config/conexion.php';

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar y sanear los datos enviados por AJAX
    $carrera = $con->real_escape_string($_POST['carrera'] ?? '');
    // Convertir a INT si el campo es numérico, si está vacío o no es un número, se guarda como NULL o 0 si la DB no permite NULL
    $anioGraduacion = empty($_POST['anioGraduacion']) ? NULL : (int)$_POST['anioGraduacion']; 

    // Validaciones (opcional, pero buena práctica)
    // Eliminamos la validación de que al menos uno debe ser proporcionado
    // Ahora solo validamos el formato si se proporciona el año de graduación.
    if (!is_null($anioGraduacion) && (!is_numeric($anioGraduacion) || strlen((string)$anioGraduacion) != 4)) {
        $response = [
            'success' => false,
            'error' => 'El Año de Graduación debe ser un número de 4 dígitos válido.'
        ];
    } else {
        // Verificar si el usuario está autenticado (ID en sesión)
        if (!isset($_SESSION['id_usuario'])) {
            $response = [
                'success' => false,
                'error' => 'Usuario no autenticado. Por favor, inicie sesión.'
            ];
        } else {
            $idUsuario = $_SESSION['id_usuario'];

            $con->autocommit(false); // Iniciar transacción
            $transaction_successful = true;

            try {
                // Obtener el ID_Perfil_Estudiante asociado al ID_Usuario
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
                            'error' => 'No se encontró un perfil de estudiante asociado a este usuario.'
                        ];
                    }
                } else {
                    $transaction_successful = false;
                    $response = [
                        'success' => false,
                        'error' => 'Error al preparar la consulta para obtener ID de perfil de estudiante: ' . $con->error
                    ];
                    error_log("Error al preparar SELECT ID_Perfil_Estudiante (academico): " . $con->error);
                }

                // Si se encontró el ID_Perfil_Estudiante, proceder a actualizar
                if ($transaction_successful) {
                    // Actualizar la tabla 'perfil_estudiante' con Carrera_Profesional y Anio_Graduacion
                    // Asegúrate de que los nombres de columna coincidan EXACTAMENTE con tu DB
                    $sql_update_academico = "UPDATE perfil_estudiante SET Carrera_Profesional = ?, Anio_Graduacion = ? WHERE ID_Perfil_Estudiante = ?";
                    $stmt_update_academico = $con->prepare($sql_update_academico);

                    if ($stmt_update_academico) {
                        // 'si' si Anio_Graduacion es INT/YEAR (y si puede ser NULL, lo manejamos más abajo)
                        // Si Anio_Graduacion puede ser NULL, MySQLi bind_param requiere que pases NULL como una variable
                        if (is_null($anioGraduacion)) {
                            $stmt_update_academico->bind_param('sii', $carrera, $null_param, $idPerfilEstudiante);
                            $null_param = NULL; // Asignar NULL a la variable para el bind
                        } else {
                            $stmt_update_academico->bind_param('sii', $carrera, $anioGraduacion, $idPerfilEstudiante);
                        }

                        if (!$stmt_update_academico->execute()) {
                            $transaction_successful = false;
                            $response = [
                                'success' => false,
                                'error' => 'Error al actualizar información académica: ' . $stmt_update_academico->error
                            ];
                            error_log("Error al ejecutar UPDATE perfil_estudiante (academico): " . $stmt_update_academico->error);
                        }
                        $stmt_update_academico->close();
                    } else {
                        $transaction_successful = false;
                        $response = [
                            'success' => false,
                            'error' => 'Error al preparar la sentencia de actualización académica: ' . $con->error
                        ];
                        error_log("Error al preparar UPDATE perfil_estudiante (academico): " . $con->error);
                    }
                }

                // Finalizar transacción
                if ($transaction_successful) {
                    $con->commit();
                    $response = [
                        'success' => true,
                        'msg' => 'Información académica actualizada correctamente.'
                    ];
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