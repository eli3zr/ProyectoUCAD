<?php
// app/utils/bitacora_helper.php

/**
 * Registra un evento en la bitácora utilizando el procedimiento almacenado registrar_evento_bitacora.
 *
 * @param mysqli $con La conexión a la base de datos.
 * @param int $id_registro_afectado El ID del registro afectado (0 para eventos de sistema).
 * @param string $nombre_tabla El nombre de la tabla afectada ('no_aplica' para eventos de sistema).
 * @param string $tipo_accion_str El tipo de acción como string (ej. 'INSERT', 'UPDATE', 'LOGIN_EXITOSO').
 * @param int $id_usuario_logueado El ID del usuario que realiza la acción (0 para usuarios desconocidos o sistema).
 * @param string|null $info_antes Información antes de la acción (NULL si no aplica).
 * @param string|null $info_despues Información después de la acción (NULL si no aplica).
 */
function registrarEventoBitacora($con, $id_registro_afectado, $nombre_tabla, $tipo_accion_str, $id_usuario_logueado, $info_antes = null, $info_despues = null) {
    // Si la conexión no es válida, loguea y sal. Esto es crucial.
    if (!$con || $con->connect_error) {
        error_log("ERROR CRÍTICO: La conexión a la base de datos no es válida para registrar la bitácora. Mensaje: " . ($con ? $con->connect_error : 'Conexión NULL') . ". Evento a registrar: " . $tipo_accion_str);
        return;
    }

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
                    // Asegúrate de que $info_antes y $info_despues sean string o null.
                    // Si son arrays, json_encode() para guardarlos como JSON string.
                    $info_antes_str = is_array($info_antes) ? json_encode($info_antes) : $info_antes;
                    $info_despues_str = is_array($info_despues) ? json_encode($info_despues) : $info_despues;

                    $stmt_bitacora->bind_param("isiiis", $id_registro_afectado, $nombre_tabla, $id_accion_bitacora, $id_usuario_logueado, $info_antes_str, $info_despues_str);
                    $stmt_bitacora->execute();
                    $stmt_bitacora->close();
                    // error_log("Bitácora registrada: Tipo='" . $tipo_accion_str . "', UsuarioID='" . $id_usuario_logueado . "', Info='" . ($info_despues_str ?? $info_antes_str) . "'"); // Opcional: para depuración intensa
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

// Función adicional para extraer datos usando el SP, útil para UPDATES/DELETES
/**
 * Extrae los datos de una fila de una tabla usando el procedimiento almacenado extraer_datos_bitacora.
 *
 * @param mysqli $con La conexión a la base de datos.
 * @param int $id_afectado El ID de la fila a extraer.
 * @param string $tabla_nombre El nombre de la tabla.
 * @return string|null Los datos de la fila como string, o null si falla.
 */
function extraerDatosParaBitacora($con, $id_afectado, $tabla_nombre) {
    if (!$con || $con->connect_error) {
        error_log("ERROR CRÍTICO: La conexión a la base de datos no es válida para extraer datos para bitácora. Mensaje: " . ($con ? $con->connect_error : 'Conexión NULL') . ". Tabla: " . $tabla_nombre . ", ID: " . $id_afectado);
        return null;
    }

    $datos_extraidos = null;
    try {
        $stmt_extraer = $con->prepare("CALL extraer_datos_bitacora(?, ?, @datos_salida)");
        if ($stmt_extraer) {
            $stmt_extraer->bind_param("is", $id_afectado, $tabla_nombre);
            $stmt_extraer->execute();
            $stmt_extraer->close();

            // Recuperar el valor de la variable de salida
            $result = $con->query("SELECT @datos_salida AS datos");
            if ($result && $row = $result->fetch_assoc()) {
                $datos_extraidos = $row['datos'];
            }
            $result->close();
        } else {
            error_log("Error al preparar CALL extraer_datos_bitacora: " . $con->error);
        }
    } catch (Exception $e) {
        error_log("Excepción al extraer datos para bitácora: " . $e->getMessage());
    }
    return $datos_extraidos;
}

?>