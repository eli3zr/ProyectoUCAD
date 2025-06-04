// Jobtrack_Ucad-main/app/controllers/actualizar_enlaces_empresas.js

$(function() {
    const formEnlacesEmpresa = $("#formEnlacesEmpresa"); 
    const urlScript = '../models/actualizar_enlaces_empresa.php'; 

    let perfilEmpresaId = null;
    let tipoRedIds = {}; 

    const campoATipoRed = {
        'sitioWeb': 'Sitio Web',
        'linkedinPerfil': 'LinkedIn',
        'facebookPerfil': 'Facebook',
        'twitterPerfil': 'Twitter',
        'instagramPerfil': 'Instagram'
    };

    // NUEVO: Mapeo de nombres de red a dominios esperados para validación
    const expectedDomains = {
        'Sitio Web': null, // No hay un dominio específico para 'Sitio Web'
        'LinkedIn': 'linkedin.com',
        'Facebook': 'facebook.com',
        'Twitter': 'twitter.com',
        'Instagram': 'instagram.com'
    };

    /**
     * Carga los enlaces actuales de la empresa y los muestra en el formulario.
     * Ahora usa $.ajax para consistencia.
     */
    function cargarEnlacesActualesEmpresa() {
        $.ajax({
            url: urlScript + '?action=get_enlaces_data',
            type: 'GET',
            dataType: 'json'
        })
        .done(function(data) {
            // console.log('Datos de enlaces de empresa recibidos:', data); // Descomentar para depurar la carga inicial
            if (data.success) {
                perfilEmpresaId = data.data.perfil_empresa_id;
                tipoRedIds = data.data.tipo_red_ids;

                // Limpiar todos los campos antes de cargar
                for (const campoId in campoATipoRed) {
                    const inputElement = $("#" + campoId); // Selector jQuery
                    if (inputElement.length) { // Verificar si el elemento existe
                        inputElement.val(''); // Usar .val() para jQuery
                    }
                }

                // Rellenar los campos con los enlaces existentes
                if (data.data.enlaces && data.data.enlaces.length > 0) {
                    data.data.enlaces.forEach(enlace => {
                        // Buscar el nombre de la red social por su ID
                        const nombreRed = Object.keys(tipoRedIds).find(key => tipoRedIds[key] == enlace.tipo_red_id);
                        
                        // Buscar el ID del campo HTML por el nombre de la red social
                        const campoId = Object.keys(campoATipoRed).find(key => campoATipoRed[key] === nombreRed);

                        if (campoId) {
                            const inputElement = $("#" + campoId); // Selector jQuery
                            if (inputElement.length) { // Verificar si el elemento existe
                                inputElement.val(enlace.url); // Usar .val() para jQuery
                            }
                        } else {
                            console.warn(`Tipo de red o campo HTML no mapeado para ID_Red: ${enlace.tipo_red_id}`);
                        }
                    });
                }
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.error || 'No se pudo obtener la información necesaria para cargar los enlaces. Intente recargar la página.',
                    icon: 'error'
                });
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Error al cargar enlaces:', textStatus, errorThrown, jqXHR.responseText);
            Swal.fire({
                title: 'Error de Conexión',
                text: 'Error de conexión al cargar los enlaces. Recargue la página.',
                icon: 'error'
            });
        });
    }

    /**
     * Maneja el evento de envío del formulario de enlaces.
     * Previene el envío por defecto, recolecta datos y envía una solicitud POST.
     */
    if (formEnlacesEmpresa.length) { // Verificar si el formulario existe
        formEnlacesEmpresa.on('submit', function(event) { // Usar .on() para jQuery
            event.preventDefault(); // Prevenir el envío tradicional del formulario

            if (!perfilEmpresaId || Object.keys(tipoRedIds).length === 0) {
                Swal.fire({
                    title: 'Error',
                    text: 'Falta información esencial para guardar los enlaces. Recargue la página.',
                    icon: 'error'
                });
                return;
            }

            const enlacesAGuardar = [];
            const urlsEnviadas = new Set(); // Para detectar duplicados

            for (const campoId in campoATipoRed) {
                const inputElement = $("#" + campoId);
                if (inputElement.length) {
                    const url = inputElement.val().trim();
                    const nombreRed = campoATipoRed[campoId];
                    const tipoRedId = tipoRedIds[nombreRed];

                    if (url) { // Solo procesar si hay URL
                        // Validar si el tipo de red existe
                        if (!tipoRedId) {
                            Swal.fire('Error de Validación', `El tipo de red "${nombreRed}" no se encontró en la base de datos. Recargue la página.`, 'error');
                            return; // Detener el envío
                        }

                        // Validar correspondencia de dominio (si aplica)
                        const expectedDomain = expectedDomains[nombreRed];
                        if (expectedDomain && !url.toLowerCase().includes(expectedDomain)) {
                            Swal.fire('Error de Validación', `La URL para ${nombreRed} no parece ser válida. Debe contener "${expectedDomain}".`, 'warning');
                            return; // Detener el envío
                        }

                        // Validar duplicados
                        if (urlsEnviadas.has(url)) {
                            Swal.fire('Error de Validación', `La URL "${url}" está duplicada. Por favor, ingrese URLs únicas.`, 'warning');
                            return; // Detener el envío
                        }
                        urlsEnviadas.add(url); // Añadir al conjunto de URLs enviadas

                        enlacesAGuardar.push({
                            url: url,
                            tipo_red_id: tipoRedId
                        });
                    }
                }
            }

            // Si ambos campos están vacíos y no hay enlaces para guardar, se asume que se quiere "limpiar"
            if (enlacesAGuardar.length === 0) {
                Swal.fire({
                    title: 'Confirmar eliminación',
                    text: "¿Estás seguro de que quieres eliminar todos los enlaces de redes sociales?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        sendEnlacesData(enlacesAGuardar); // Envía un array vacío para eliminar
                    }
                });
                return;
            }

            // Mostrar confirmación al usuario antes de enviar
            Swal.fire({
                title: '¿Guardar enlaces?',
                text: "¿Estás seguro de que quieres guardar estos enlaces?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendEnlacesData(enlacesAGuardar);
                }
            });
        });
    }

    /**
     * Función auxiliar para enviar los datos de enlaces al servidor.
     * @param {Array} enlaces El array de objetos de enlaces a guardar.
     */
    function sendEnlacesData(enlaces) {
        $.ajax({
            url: urlScript, // URL del manejador PHP
            type: 'POST',
            dataType: 'json', // Esperamos una respuesta JSON
            contentType: 'application/json', // Indicar que enviamos JSON
            data: JSON.stringify({ // Convertir el objeto a string JSON para el body
                action: 'save_enlaces',
                perfil_empresa_id: perfilEmpresaId,
                enlaces: enlaces // El array de enlaces ya está listo
            }),
            beforeSend: function () {
                Swal.showLoading(); // Mostrar indicador de carga
            }
        })
        .done(function (response) {
            Swal.close(); // Cerrar indicador de carga
            console.log('Respuesta al guardar enlaces:', response); // Log para depuración

            if (response.success) {
                // Si la operación fue exitosa, mostrar mensaje de éxito
                Swal.fire({
                    title: 'Éxito',
                    text: response.msg || 'Enlaces guardados correctamente.',
                    icon: 'success',
                    confirmButtonText: 'Perfecto'
                });
                // Recargar los enlaces después de guardar para asegurar que los campos reflejen los datos actuales
                cargarEnlacesActualesEmpresa(); 
            } else {
                // Si hubo un error del servidor, mostrar mensaje de error
                Swal.fire({
                    title: 'Error',
                    text: response.error || 'Error al guardar los enlaces.',
                    icon: 'error',
                    confirmButtonText: 'Ok'
                });
            }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            Swal.close(); // Cerrar indicador de carga
            // Manejar errores de conexión o del lado del cliente en la solicitud AJAX
            console.error("Error AJAX (Enlaces):", textStatus, errorThrown, jqXHR.responseText);
            Swal.fire({
                title: 'Error de Conexión',
                text: 'No se pudo conectar con el servidor para guardar los enlaces. Por favor, intente de nuevo. Detalles: ' + textStatus + ' - ' + errorThrown,
                icon: 'error'
            });
        });
    }

    // Ejecutar la función para cargar los enlaces al cargar el documento (una vez que el DOM esté listo)
    cargarEnlacesActualesEmpresa();
});
