<?php
// app/models/actualizar_informacion_empresa.php

session_start(); // Inicia la sesión para poder acceder al ID de usuario

// Incluye el archivo de conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php'; 
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// Configuración de errores para depuración (¡Cambia a 0 en producción!)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // Asegúrate de que la carpeta 'logs' exista y sea escribible.


header('Content-Type: application/json'); // Establece el encabezado para respuestas JSON

$response = ['success' => false, 'msg' => '', 'error' => '', 'data' => null];

// ID de usuario logueado, por defecto 0 si no está autenticado
$id_usuario_logueado = $_SESSION['ID_Usuario'] ?? 0;

// 1. Verificar si la conexión a la base de datos fue exitosa
if (!$con || $con->connect_error) { 
    $error_msg = 'Error al conectar con la base de datos. Detalles: ' . ($con ? $con->connect_error : 'Conexión no establecida');
    $response['error'] = $error_msg;
    registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario_logueado, NULL, 'Fallo conexión DB en actualizar_informacion_empresa: ' . $error_msg);
    echo json_encode($response);
    exit();
}

// 2. Verificar si el usuario ha iniciado sesión y obtener su ID
if (!isset($_SESSION['ID_Usuario']) || !is_numeric($_SESSION['ID_Usuario'])) {
    $response['error'] = 'No autorizado. Por favor, inicie sesión.';
    http_response_code(401); // Unauthorized
    registrarEventoBitacora($con, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', 0, NULL, 'Intento de actualizar perfil de empresa sin sesión activa o ID de usuario inválido.');
    echo json_encode($response);
    exit();
}

$id_usuario = $_SESSION['ID_Usuario'];

// --- Lógica para manejar solicitudes GET (Cargar Perfil) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Consulta para obtener los datos del usuario, su perfil de empresa (incluyendo ubicación)
        // y el teléfono de contacto desde contactos_empresa
        // NOMBRES DE COLUMNAS ACTUALIZADOS PARA COINCIDIR CON LA BD Y CORRECCIÓN EN EL JOIN
        $stmt_get_profile = $con->prepare("
            SELECT
                u.ID_Usuario,
                u.Nombre AS NombreEmpresa,
                u.Correo_Electronico AS EmailContacto,
                pe.Descripción AS DescripcionEmpresa,
                pe.id_pais_fk AS pais_id_pais,         
                pe.id_departamento_fk AS departamento_id_departamento, 
                pe.id_municipio_fk AS municipio_id_municipio,   
                pe.id_distrito_fk AS distrito_id_distrito,     
                ce.Teléfono AS TelefonoContacto
            FROM
                usuario u
            LEFT JOIN
                perfil_empresa pe ON u.ID_Usuario = pe.usuario_ID_Usuario
            LEFT JOIN
                contactos_empresa ce ON pe.ID_Perfil_Empresa = ce.ID_Empresa -- <<-- ¡CORRECCIÓN AQUÍ!
            WHERE
                u.ID_Usuario = ?
        ");

        if ($stmt_get_profile === false) {
            throw new Exception("Error en la preparación de la consulta GET: " . $con->error);
        }

        $stmt_get_profile->bind_param("i", $id_usuario);
        $stmt_get_profile->execute();
        $result_get_profile = $stmt_get_profile->get_result();

        if ($result_get_profile->num_rows > 0) {
            $data = $result_get_profile->fetch_assoc();
            
            $response['success'] = true;
            $response['data'] = [
                'ID_Usuario' => $data['ID_Usuario'],
                'NombreEmpresa' => $data['NombreEmpresa'],
                'DescripcionEmpresa' => $data['DescripcionEmpresa'],
                'EmailContacto' => $data['EmailContacto'],
                'TelefonoContacto' => $data['TelefonoContacto'],
                'pais_id_pais' => $data['pais_id_pais'], 
                'departamento_id_departamento' => $data['departamento_id_departamento'], 
                'municipio_id_municipio' => $data['municipio_id_municipio'], 
                'distrito_id_distrito' => $data['distrito_id_distrito'] 
            ];
            // No se registra en bitácora para GETs, ya que es solo una lectura
        } else {
            $response['success'] = true;
            $response['data'] = [ 
                'ID_Usuario' => $id_usuario,
                'NombreEmpresa' => '',
                'DescripcionEmpresa' => '',
                'EmailContacto' => '',
                'TelefonoContacto' => '',
                'pais_id_pais' => null,
                'departamento_id_departamento' => null,
                'municipio_id_municipio' => null,
                'distrito_id_distrito' => null
            ];
            // Si el perfil no existe, el GET solo devuelve un perfil vacío para que se pueda crear
            // No es un error ni una acción a bitacorar
        }
        $stmt_get_profile->close();

    } catch (Exception $e) {
        error_log("Error al cargar perfil de empresa (GET): " . $e->getMessage());
        $response['error'] = 'Error interno del servidor al cargar el perfil: ' . $e->getMessage();
        http_response_code(500);
        registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario, NULL, 'Excepción al cargar perfil empresa (GET): ' . $e->getMessage());
    } finally {
        if ($con) { $con->close(); }
    }
    echo json_encode($response);
    exit();
}

// --- Lógica para manejar solicitudes POST (Actualizar Perfil) ---
$nombreEmpresa = $_POST['nombreEmpresa'] ?? '';
$descripcionEmpresa = $_POST['descripcionEmpresa'] ?? '';
$emailContacto = $_POST['emailContacto'] ?? '';
$telefonoContacto = $_POST['telefonoContacto'] ?? '';
$paisId = $_POST['pais'] ?? null;
$departamentoId = $_POST['departamento'] ?? null;
$municipioId = $_POST['municipio'] ?? null;
$distritoId = $_POST['distrito'] ?? null;

// Asegurarse de que los IDs de ubicación sean enteros o NULL
$paisId = ($paisId !== null && $paisId !== '') ? (int)$paisId : null;
$departamentoId = ($departamentoId !== null && $departamentoId !== '') ? (int)$departamentoId : null;
$municipioId = ($municipioId !== null && $municipioId !== '') ? (int)$municipioId : null;
$distritoId = ($distritoId !== null && $distritoId !== '') ? (int)$distritoId : null;


if (empty($nombreEmpresa) || empty($emailContacto)) {
    $response['error'] = 'Los campos Nombre de la Empresa y Correo Electrónico son obligatorios.';
    // Usamos LOGIN_FALLIDO si la validación falla para indicar un intento incompleto/incorrecto
    registrarEventoBitacora($con, $id_usuario, 'usuario', 'LOGIN_FALLIDO', $id_usuario, NULL, 'Actualización de perfil empresa fallida: Campos obligatorios vacíos.');
} elseif (!filter_var($emailContacto, FILTER_VALIDATE_EMAIL)) {
    $response['error'] = 'El correo electrónico no es válido.';
    // Usamos LOGIN_FALLIDO si la validación falla para indicar un intento incompleto/incorrecto
    registrarEventoBitacora($con, $id_usuario, 'usuario', 'LOGIN_FALLIDO', $id_usuario, NULL, 'Actualización de perfil empresa fallida: Correo electrónico inválido.');
} else {
    try {
        $con->begin_transaction();

        // --- BITACOLA: Obtener datos antes de la actualización en tabla 'usuario' ---
        $datos_usuario_antes = extraerDatosParaBitacora($con, $id_usuario, 'usuario');

        // 1. Actualizar la tabla 'usuario' (solo Nombre y Correo_Electronico)
        $stmt_usuario = $con->prepare("
            UPDATE usuario 
            SET 
                Nombre = ?, 
                Correo_Electronico = ?
            WHERE 
                ID_Usuario = ?
        ");
        if ($stmt_usuario === false) {
            // Registrar ERROR_SISTEMA si la preparación falla
            registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario, NULL, 'Error al preparar UPDATE de usuario en perfil empresa: ' . $con->error);
            throw new Exception("Error en la preparación de la consulta de usuario: " . $con->error);
        }
        $stmt_usuario->bind_param("ssi", 
            $nombreEmpresa, 
            $emailContacto, 
            $id_usuario
        );
        $stmt_usuario->execute();
        $filas_afectadas_usuario = $stmt_usuario->affected_rows;
        $stmt_usuario->close();

        // --- BITACOLA: Registrar acción en tabla 'usuario' si hubo cambios ---
        if ($filas_afectadas_usuario > 0) {
            $datos_usuario_despues = extraerDatosParaBitacora($con, $id_usuario, 'usuario');
            registrarEventoBitacora(
                $con, 
                $id_usuario, 
                'usuario', 
                'UPDATE', // Usamos el tipo genérico 'UPDATE'
                $id_usuario, 
                $datos_usuario_antes, 
                $datos_usuario_despues
            );
        }

        // 2. Actualizar o insertar en la tabla 'perfil_empresa' (incluyendo descripción y ubicación)
        // Usamos ID_Perfil_Empresa para la selección y ID_Empresa en contactos_empresa
        $stmt_check_perfil = $con->prepare("SELECT ID_Perfil_Empresa FROM perfil_empresa WHERE usuario_ID_Usuario = ?"); // <<-- ¡CORRECCIÓN AQUÍ!
        if ($stmt_check_perfil === false) {
            // Registrar ERROR_SISTEMA si la preparación falla
            registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario, NULL, 'Error al preparar verificación de perfil empresa: ' . $con->error);
            throw new Exception("Error al verificar perfil existente: " . $con->error);
        }
        $stmt_check_perfil->bind_param("i", $id_usuario);
        $stmt_check_perfil->execute();
        $result_check_perfil = $stmt_check_perfil->get_result();
        $perfil_existente = $result_check_perfil->fetch_assoc();
        $stmt_check_perfil->close();

        $id_perfil_empresa = null; 
        $tipo_accion_perfil = '';
        $datos_perfil_antes = null;

        if ($perfil_existente) {
            $id_perfil_empresa = $perfil_existente['ID_Perfil_Empresa']; // <<-- ¡CORRECCIÓN AQUÍ!
            $tipo_accion_perfil = 'UPDATE'; // Usamos el tipo genérico 'UPDATE'
            // --- BITACOLA: Obtener datos antes de la actualización en tabla 'perfil_empresa' ---
            $datos_perfil_antes = extraerDatosParaBitacora($con, $id_perfil_empresa, 'perfil_empresa');

            // Si el perfil existe, actualiza la descripción y los campos de ubicación
            $stmt_perfil = $con->prepare("
                UPDATE perfil_empresa 
                SET 
                    Descripción = ?,
                    id_pais_fk = ?,         
                    id_departamento_fk = ?,     
                    id_municipio_fk = ?,       
                    id_distrito_fk = ?          
                WHERE 
                    usuario_ID_Usuario = ?
            ");
            if ($stmt_perfil === false) {
                // Registrar ERROR_SISTEMA si la preparación falla
                registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario, NULL, 'Error al preparar UPDATE de perfil empresa: ' . $con->error);
                throw new Exception("Error en la preparación de la consulta de perfil (UPDATE): " . $con->error);
            }
            $stmt_perfil->bind_param("siiiii", 
                $descripcionEmpresa, 
                $paisId, 
                $departamentoId, 
                $municipioId, 
                $distritoId,
                $id_usuario
            );
            $stmt_perfil->execute();
            $filas_afectadas_perfil = $stmt_perfil->affected_rows;
            $stmt_perfil->close();
        } else {
            $tipo_accion_perfil = 'INSERT'; // Usamos el tipo genérico 'INSERT'
            // Si el perfil NO existe, lo inserta (incluyendo descripción y ubicación)
            $id_categoria_placeholder = 1; // Asume un ID de categoría por defecto. ¡Asegúrate de que este ID sea válido!

            $stmt_perfil = $con->prepare("
                INSERT INTO perfil_empresa (usuario_ID_Usuario, Descripción, id_pais_fk, id_departamento_fk, id_municipio_fk, id_distrito_fk, ID_Categoria) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt_perfil === false) {
                // Registrar ERROR_SISTEMA si la preparación falla
                registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario, NULL, 'Error al preparar INSERT de perfil empresa: ' . $con->error);
                throw new Exception("Error en la preparación de la consulta de perfil (INSERT): " . $con->error);
            }
            $stmt_perfil->bind_param("isiiiii", 
                $id_usuario, 
                $descripcionEmpresa, 
                $paisId, 
                $departamentoId, 
                $municipioId, 
                $distritoId,
                $id_categoria_placeholder 
            );
            $stmt_perfil->execute();
            $id_perfil_empresa = $con->insert_id; // Este insert_id es el ID_Perfil_Empresa del nuevo registro
            $filas_afectadas_perfil = $stmt_perfil->affected_rows; // 1 si inserta
            $stmt_perfil->close();
        }
        
        // --- BITACOLA: Registrar acción en tabla 'perfil_empresa' si hubo cambios o inserción ---
        if ($filas_afectadas_perfil > 0 || ($tipo_accion_perfil === 'INSERT' && $id_perfil_empresa)) {
            $datos_perfil_despues = extraerDatosParaBitacora($con, $id_perfil_empresa, 'perfil_empresa');
            registrarEventoBitacora(
                $con, 
                $id_perfil_empresa, 
                'perfil_empresa', 
                $tipo_accion_perfil, // Será 'INSERT' o 'UPDATE'
                $id_usuario, 
                $datos_perfil_antes, 
                $datos_perfil_despues
            );
        }

        // 3. Actualizar o insertar en la tabla 'contactos_empresa'
        if ($id_perfil_empresa) { // $id_perfil_empresa ahora es el ID_Perfil_Empresa
            $stmt_check_contacto = $con->prepare("SELECT ID_Contacto FROM contactos_empresa WHERE ID_Empresa = ?");
            if ($stmt_check_contacto === false) {
                // Registrar ERROR_SISTEMA si la preparación falla
                registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario, NULL, 'Error al preparar verificación de contacto empresa: ' . $con->error);
                throw new Exception("Error al verificar contacto existente: " . $con->error);
            }
            $stmt_check_contacto->bind_param("i", $id_perfil_empresa); // <<-- ID_Empresa en contactos_empresa es la FK de ID_Perfil_Empresa
            $stmt_check_contacto->execute();
            $result_check_contacto = $stmt_check_contacto->get_result();
            $contacto_existente = $result_check_contacto->fetch_assoc();
            $stmt_check_contacto->close();

            $id_contacto = null;
            $tipo_accion_contacto = '';
            $datos_contacto_antes = null;

            if ($contacto_existente) {
                $id_contacto = $contacto_existente['ID_Contacto'];
                $tipo_accion_contacto = 'UPDATE'; // Usamos el tipo genérico 'UPDATE'
                // --- BITACOLA: Obtener datos antes de la actualización en tabla 'contactos_empresa' ---
                $datos_contacto_antes = extraerDatosParaBitacora($con, $id_contacto, 'contactos_empresa');

                $stmt_contacto = $con->prepare("
                    UPDATE contactos_empresa 
                    SET 
                        Teléfono = ?
                    WHERE 
                        ID_Empresa = ?
                ");
                if ($stmt_contacto === false) {
                    // Registrar ERROR_SISTEMA si la preparación falla
                    registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario, NULL, 'Error al preparar UPDATE de contacto empresa: ' . $con->error);
                    throw new Exception("Error en la preparación de la consulta de contacto (UPDATE): " . $con->error);
                }
                $stmt_contacto->bind_param("si", $telefonoContacto, $id_perfil_empresa); 
                $stmt_contacto->execute();
                $filas_afectadas_contacto = $stmt_contacto->affected_rows;
                $stmt_contacto->close();
            } else {
                $tipo_accion_contacto = 'INSERT'; // Usamos el tipo genérico 'INSERT'
                $stmt_contacto = $con->prepare("
                    INSERT INTO contactos_empresa (ID_Empresa, Teléfono) 
                    VALUES (?, ?)
                ");
                if ($stmt_contacto === false) {
                    // Registrar ERROR_SISTEMA si la preparación falla
                    registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario, NULL, 'Error al preparar INSERT de contacto empresa: ' . $con->error);
                    throw new Exception("Error en la preparación de la consulta de contacto (INSERT): " . $con->error);
                }
                $stmt_contacto->bind_param("is", $id_perfil_empresa, $telefonoContacto); 
                $stmt_contacto->execute();
                $id_contacto = $con->insert_id; // Obtener el ID del nuevo contacto
                $filas_afectadas_contacto = $stmt_contacto->affected_rows;
                $stmt_contacto->close();
            }

            // --- BITACOLA: Registrar acción en tabla 'contactos_empresa' si hubo cambios o inserción ---
            if ($filas_afectadas_contacto > 0 || ($tipo_accion_contacto === 'INSERT' && $id_contacto)) {
                $datos_contacto_despues = extraerDatosParaBitacora($con, $id_contacto, 'contactos_empresa');
                registrarEventoBitacora(
                    $con, 
                    $id_contacto, 
                    'contactos_empresa', 
                    $tipo_accion_contacto, // Será 'INSERT' o 'UPDATE'
                    $id_usuario, 
                    $datos_contacto_antes, 
                    $datos_contacto_despues
                );
            }

        } else {
            error_log("Advertencia: No se pudo obtener ID_Perfil_Empresa para actualizar/insertar contacto para usuario ID: " . $id_usuario);
            registrarEventoBitacora($con, 0, 'sistema', 'ADVERTENCIA', $id_usuario, NULL, 'No se pudo obtener ID_Perfil_Empresa para contacto de empresa ID: ' . $id_usuario);
        }

        $con->commit();
        $response['success'] = true;
        $response['msg'] = 'La información de la empresa ha sido actualizada correctamente.';

    } catch (Exception $e) {
        $con->rollback();
        error_log("Error al actualizar perfil de empresa: " . $e->getMessage()); 
        $response['error'] = 'Error interno del servidor al actualizar el perfil: ' . $e->getMessage();
        http_response_code(500);
        // Registrar ERROR_SISTEMA por excepción
        registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $id_usuario, NULL, 'Excepción en actualización de perfil de empresa: ' . $e->getMessage());
    } finally {
        if ($con) { $con->close(); }
    }
}

echo json_encode($response);
?>