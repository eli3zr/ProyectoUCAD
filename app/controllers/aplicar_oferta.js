// C:\xampp\htdocs\Jobtrack_Ucad\app\controllers\aplicar_oferta.js
// Script para manejar la página de aplicación de oferta.

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const ofertaId = urlParams.get('id');

    // Elementos del resumen de la oferta
    const ofertaTituloElem = document.getElementById('ofertaTitulo');
    const ofertaEmpresaElem = document.getElementById('ofertaEmpresa');
    const ofertaUbicacionElem = document.getElementById('ofertaUbicacion');
    const ofertaDescripcionCortaElem = document.getElementById('ofertaDescripcionCorta');
    const volverOfertasBtn = document.getElementById('volverOfertasBtn');

    // Formulario y sus elementos
    const aplicarOfertaForm = document.getElementById('aplicarOfertaForm');
    const cvFileElem = document.getElementById('cvFile');
    const mensajeElem = document.getElementById('mensaje');

    // Elemento para mostrar mensajes de alerta (Bootstrap)
    const mensajeAlertaDiv = document.getElementById('mensajeAlerta');

    /**
     * Muestra un mensaje de alerta usando los estilos de Bootstrap.
     * @param {string} mensaje - El texto del mensaje a mostrar.
     * @param {'success'|'danger'|'warning'|'info'} tipo - El tipo de alerta para estilos (ej. 'success', 'danger').
     */
    function mostrarAlerta(mensaje, tipo) {
        mensajeAlertaDiv.textContent = ''; // Limpiar mensaje anterior
        // Limpiar todas las clases de tipo de alerta antes de añadir la nueva
        mensajeAlertaDiv.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        
        mensajeAlertaDiv.classList.add('alert', `alert-${tipo}`);
        mensajeAlertaDiv.textContent = mensaje;
        mensajeAlertaDiv.style.display = 'block'; // Mostrar el div

        // Opcional: Ocultar la alerta automáticamente después de 5 segundos
        setTimeout(() => {
            mensajeAlertaDiv.style.display = 'none';
            mensajeAlertaDiv.textContent = ''; // Limpiar contenido al ocultar
        }, 5000); 
    }

    /**
     * Carga y muestra los detalles de la oferta en la sección de resumen.
     * @param {string} id - El ID de la oferta a cargar.
     */
    async function cargarResumenOferta(id) {
        if (!id) {
            console.error('ID de oferta no proporcionado en la URL para cargar resumen.');
            mostrarAlerta('ID de oferta no encontrado. No se puede cargar el resumen.', 'danger');
            return;
        }

        try {
            const response = await fetch(`../../app/models/get_oferta_por_id.php?id=${id}`);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Error del servidor al cargar oferta: ${response.status} ${response.statusText} - ${errorText}`);
            }
            const data = await response.json();

            if (data.success && data.oferta) {
                const oferta = data.oferta;
                // Asegurarse de que los elementos existan antes de asignarles texto
                if (ofertaTituloElem) ofertaTituloElem.textContent = oferta.titulo || 'N/A';
                if (ofertaEmpresaElem) ofertaEmpresaElem.textContent = oferta.empresa || 'N/A';
                if (ofertaUbicacionElem) ofertaUbicacionElem.textContent = oferta.ubicacion || 'N/A';
                if (ofertaDescripcionCortaElem) {
                    const descCorta = oferta.descripcion ? oferta.descripcion.substring(0, 150) + '...' : 'No hay descripción.';
                    ofertaDescripcionCortaElem.textContent = descCorta;
                }
                if (volverOfertasBtn) {
                    // Enlace para volver a la página de exploración de ofertas
                    volverOfertasBtn.href = `./explorar_oferta.html`; 
                }

            } else {
                mostrarAlerta(data.message || 'No se pudieron cargar los detalles del resumen de la oferta.', 'warning');
            }
        } catch (error) {
            console.error('Error al cargar resumen de oferta:', error);
            mostrarAlerta('Hubo un problema al cargar el resumen de la oferta.', 'danger');
        }
    }

    // Cargar resumen de la oferta al cargar la página si el ID está presente en la URL
    if (ofertaId) {
        cargarResumenOferta(ofertaId);
    } else {
        mostrarAlerta('No se encontró un ID de oferta en la URL para aplicar.', 'danger');
        // Opcional: redirigir si no hay ID válido para evitar errores en la aplicación
        // window.location.href = './explorar_oferta.html'; 
    }

    // Manejar el envío del formulario de aplicación
    if (aplicarOfertaForm) {
        aplicarOfertaForm.addEventListener('submit', async (event) => {
            event.preventDefault(); // Prevenir el envío por defecto del formulario

            // Limpiar alertas anteriores antes de un nuevo intento de envío
            mensajeAlertaDiv.style.display = 'none';
            mensajeAlertaDiv.textContent = '';

            const formData = new FormData();
            formData.append('id_oferta', ofertaId);
            formData.append('mensaje', mensajeElem.value);

            // Validar y añadir el archivo CV al FormData
            if (cvFileElem.files.length > 0) {
                const cvFile = cvFileElem.files[0];
                const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                const maxFileSize = 2 * 1024 * 1024; // 2 MB

                if (!allowedTypes.includes(cvFile.type)) {
                    mostrarAlerta('Solo se permiten archivos PDF, DOC y DOCX.', 'danger');
                    return; // Detener la ejecución si la validación falla
                }
                if (cvFile.size > maxFileSize) {
                    mostrarAlerta('El CV no debe exceder los 2MB.', 'danger');
                    return; // Detener la ejecución si la validación falla
                }
                formData.append('cvFile', cvFile); // Asegúrate de que el nombre aquí ('cvFile') coincide con el esperado en PHP
            } else {
                mostrarAlerta('Por favor, selecciona un archivo de CV.', 'danger');
                return; // Detener la ejecución si no hay archivo
            }

            // Mostrar un mensaje de "enviando" mientras se procesa la solicitud
            mostrarAlerta('Enviando aplicación...', 'info');

            try {
                // Envía la solicitud al script PHP que maneja tanto el CV como la aplicación
                const response = await fetch('../../app/models/guardar_aplicacion.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Error en la respuesta del servidor:', response.status, response.statusText, errorText);
                    // Lanza un error para ser capturado por el bloque catch
                    throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
                }

                const data = await response.json(); // Parsea la respuesta JSON

                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    // Redirigir a la página de mis aplicaciones después de un breve retraso
                    setTimeout(() => {
                        window.location.href = './administrar_aplicaciones.html'; 
                    }, 1500); 
                } else {
                    mostrarAlerta(data.message || 'Hubo un problema al enviar tu aplicación.', 'danger');
                }
            } catch (error) {
                console.error('Error al enviar la aplicación:', error);
                mostrarAlerta('No se pudo conectar con el servidor o hubo un error inesperado. Inténtalo de nuevo más tarde.', 'danger');
            }
        });
    }
});