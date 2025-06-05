$(function () {
    // --- Seleccionar elementos del DOM ---
    const listaPostulantes = $('.list-group'); // El contenedor donde se renderizarán los postulantes
    const tituloPrincipal = $('h2'); // El título 'Todos los Postulantes'
    const parrafoDescripcion = $('p.mb-3'); // El párrafo descriptivo debajo del título

    // --- Funciones de Utilidad ---

    /**
     * Obtiene el valor de un parámetro de la URL por su nombre.
     * Esto permite leer el 'oferta_id' cuando se redirige desde 'administrar_ofertas.html'.
     * @param {string} name - El nombre del parámetro.
     * @returns {string|null} El valor del parámetro o null si no se encuentra.
     */
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        const results = regex.exec(location.search);
        return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    /**
     * Renderiza los elementos de la lista con los datos de los postulantes.
     * Se crea un `div` por cada postulante y se añade al `list-group`.
     * @param {Array<Object>} postulantes - Un array de objetos, donde cada objeto es un postulante.
     * @param {string|null} ofertaTitulo - Título de la oferta si se está filtrando (ej. "Desarrollador Web"), o `null` si es una lista general de todos los postulantes.
     */
    function renderizarPostulantes(postulantes, ofertaTitulo = null) {
        listaPostulantes.empty(); // Limpia cualquier elemento existente antes de renderizar

        // Actualiza el título y la descripción de la página dinámicamente
        if (ofertaTitulo) {
            tituloPrincipal.html(`<i class="fas fa-users me-2" style="color: #112852;"></i> Postulantes para: <span class="text-primary">${ofertaTitulo}</span>`);
            parrafoDescripcion.text('Aquí puedes ver los estudiantes que han aplicado a esta oferta de empleo específica.');
        } else {
            tituloPrincipal.html(`<i class="fas fa-users me-2" style="color: #112852;"></i> Todos los Postulantes`);
            parrafoDescripcion.text('Aquí puedes ver la lista completa de los estudiantes que han aplicado a tus ofertas de empleo.');
        }

        // Si no hay postulantes, muestra un mensaje
        if (postulantes.length === 0) {
            listaPostulantes.append('<div class="list-group-item text-center">No se encontraron postulantes que coincidan con los criterios.</div>');
            return;
        }

        // Itera sobre cada postulante y crea un elemento de lista
        postulantes.forEach(function (postulante) {
            let badgeClass = '';
            // Asigna clases de estilo Bootstrap según el estado de la postulación
            switch (postulante.Estado_Postulacion) {
                case 'pendiente':
                    badgeClass = 'bg-warning text-dark';
                    break;
                case 'revisado':
                    badgeClass = 'bg-info';
                    break;
                case 'aceptado':
                    badgeClass = 'bg-success';
                    break;
                case 'rechazado':
                    badgeClass = 'bg-danger';
                    break;
                default:
                    badgeClass = 'bg-secondary';
            }
            const estadoTexto = postulante.Estado_Postulacion.charAt(0).toUpperCase() + postulante.Estado_Postulacion.slice(1);
            const fechaPostulacion = postulante.Fecha_Postulacion ? postulante.Fecha_Postulacion.split(' ')[0] : 'N/A';

            // Construye el HTML para cada elemento de la lista de postulantes
            const item = `
                <div class="list-group-item d-flex justify-content-between align-items-center shadow-sm mb-2">
                    <div>
                        <h6 class="mb-0" style="color: #112852;"><strong>${postulante.NombrePostulante} ${postulante.Apellido || ''}</strong></h6>
                        <small class="text-muted">Aplica a: ${postulante.TituloOferta || 'N/A'}</small><br>
                        <small class="text-muted">Email: ${postulante.Correo_Electronico || 'N/A'}</small><br>
                        <small class="text-muted">Postulado el: ${fechaPostulacion}</small><br>
                        <span class="badge ${badgeClass}">${estadoTexto}</span>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline" style="color: #112852; border-color: #112852;"
                            data-cv-path="${postulante.Ruta_CV || 'N/A'}"
                            data-nombre-postulante="${postulante.NombrePostulante || ''} ${postulante.Apellido || ''}"
                            data-email-postulante="${postulante.Correo_Electronico || 'N/A'}"
                            data-titulo-oferta="${postulante.TituloOferta || 'N/A'}"
                            data-carrera="${postulante.Carrera || 'N/A'}"           data-fecha-nacimiento="${postulante.Fecha_Nacimiento || 'N/A'}" data-genero="${postulante.Genero || 'N/A'}"             data-experiencia="${postulante.Experiencia_Laboral || 'No disponible'}" data-foto-perfil="${postulante.Foto_Perfil || 'N/A'}"  title="Ver Detalles del Postulante y CV">
                            <i class="fas fa-file-alt"></i> Ver Detalles
                        </button>
                        <button class="btn btn-sm btn-outline-success ms-1 btn-cambiar-estado" data-id="${postulante.ID_Postulacion}" data-actual-estado="${postulante.Estado_Postulacion}" data-oferta-id="${postulante.ID_Oferta_Relacionada}" title="Cambiar Estado"><i class="fas fa-sync-alt"></i> Cambiar Estado</button>
                    </div>
                </div>
            `;
            listaPostulantes.append(item); // Añade el elemento a la lista
        });

        // --- Manejo de eventos para las acciones (delegación de eventos) ---
        // Se usa `off` y `on` para evitar múltiples asignaciones de eventos si la función se llama varias veces

        // Evento para el botón "Ver CV" (Ahora "Ver Detalles")
        listaPostulantes.off('click', '[data-cv-path]').on('click', '[data-cv-path]', function () {
            const cvPath = $(this).data('cv-path');
            const nombrePostulante = $(this).data('nombre-postulante');
            const emailPostulante = $(this).data('email-postulante');
            const tituloOferta = $(this).data('titulo-oferta');
            const carrera = $(this).data('carrera');
            // ELIMINADA: const fechaNacimiento = $(this).data('fecha-nacimiento'); // Ya no se recupera
            const genero = $(this).data('genero');
            const experiencia = $(this).data('experiencia');
            // ELIMINADA: const fotoPerfil = $(this).data('foto-perfil'); // Ya no se recupera

            let fotoHtml = '';
            // ELIMINADA: Lógica para mostrar la fotoHtml
            // if (fotoPerfil && fotoPerfil !== 'N/A') {
            //     const fullFotoUrl = `http://localhost/Jobtrack_Ucad${fotoPerfil}`;
            //     fotoHtml = `<div class="text-center mb-3"><img src="${fullFotoUrl}" alt="Foto de Perfil" class="img-thumbnail rounded-circle" style="width: 100px; height: 100px; object-fit: cover;"></div>`;
            // }

            let cvLinkHtml = '';
            if (cvPath && cvPath !== 'N/A') {
                const fullCvUrl = `http://localhost/Jobtrack_Ucad${cvPath}`; // Ajusta si la base URL es diferente
                cvLinkHtml = `<a href="${fullCvUrl}" target="_blank" class="btn btn-primary btn-sm mt-2">Abrir CV en nueva pestaña</a>`;
            } else {
                cvLinkHtml = `<p class="text-danger mt-2">CV no disponible para este postulante.</p>`;
            }

            Swal.fire({
                title: `Detalles de ${nombrePostulante}`,
                html: `
                    ${fotoHtml} <p><strong>Email:</strong> ${emailPostulante}</p>
                    <p><strong>Oferta Aplicada:</strong> ${tituloOferta}</p>
                    <p><strong>Carrera:</strong> ${carrera}</p>
                    <p><strong>Género:</strong> ${genero}</p>
                    <p><strong>Experiencia Laboral:</strong> <br>${experiencia.replace(/\n/g, '<br>')}</p>
                    ${cvLinkHtml}
                `,
                icon: 'info',
                confirmButtonText: 'Cerrar',
                customClass: {
                    content: 'text-start'
                }
            });
        });

        // Evento para el botón "Cambiar Estado"
        // Evento para el botón "Cambiar Estado"
        listaPostulantes.off('click', '.btn-cambiar-estado').on('click', '.btn-cambiar-estado', function () {
            const postulacionId = $(this).data('id'); // ID de la postulación
            const actualEstado = $(this).data('actual-estado'); // Estado actual de la postulación
            const ofertaId = $(this).data('oferta-id'); // ID de la oferta para recargar la lista si es necesario

            // Muestra un SweetAlert con un selector para cambiar el estado
            Swal.fire({
                title: 'Cambiar Estado de Postulación',
                input: 'select',
                inputOptions: { // Opciones de estado disponibles
                    'Pendiente': 'Pendiente',
                    'Revisado': 'Revisado',
                    'Aceptado': 'Aceptado',
                    'Rechazado': 'Rechazado'
                },
                inputValue: actualEstado, // El valor predeterminado del selector
                inputPlaceholder: 'Selecciona un nuevo estado',
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => { // Valida que se haya seleccionado un estado
                    if (!value) {
                        return 'Debes seleccionar un estado';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) { // Si el usuario confirma el cambio
                    const nuevoEstado = result.value;

                    // Muestra un SweetAlert de carga mientras se guarda el cambio
                    Swal.fire({
                        title: 'Guardando cambios...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    // Inicia el bloque try-catch para la llamada AJAX de actualización
                    try {
                        // Realiza una llamada AJAX para actualizar el estado en el backend
                        $.ajax({
                            url: '../../app/models/actualizar_estado_postulacion.php', // Ruta al script PHP para actualizar el estado
                            type: 'POST', // Es POST, como lo espera tu PHP
                            dataType: 'json',
                            data: {
                                ID_Postulacion: postulacionId, // Coincide con $_POST['ID_Postulacion']
                                Estado_Postulacion: nuevoEstado // Coincide con $_POST['Estado_Postulacion']
                            },
                            success: function (response) {
                                Swal.close(); // Cierra el SweetAlert de carga
                                if (response.success) {
                                    Swal.fire('¡Éxito!', response.message, 'success');
                                    // Recarga la lista de postulantes para reflejar el cambio
                                    cargarPostulantes(ofertaId || null); // Se recarga con el ID de oferta si existía, si no, todos los postulantes
                                } else {
                                    Swal.fire('Error', response.message || 'No se pudieron guardar los cambios de estado.', 'error');
                                }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                Swal.close(); // Cierra el SweetAlert de carga en caso de error
                                console.error("Error AJAX al actualizar estado:", textStatus, errorThrown, jqXHR);
                                Swal.fire('Error', 'No se pudo comunicar con el servidor para guardar el estado. Revisa tu conexión.', 'error');
                            }
                        });
                    } catch (e) {
                        // Captura errores que puedan ocurrir antes de que la llamada AJAX se envíe
                        Swal.close();
                        console.error("Error al configurar la solicitud AJAX de actualización:", e);
                        Swal.fire('Error JS', 'Ocurrió un problema inesperado al intentar actualizar el estado.', 'error');
                    }
                }
            });
        });
    }

    /**
     * Carga los postulantes desde el backend.
     * Puede cargar todos los postulantes o solo los de una oferta específica,
     * basándose en el parámetro 'oferta_id' en la URL.
     * @param {number|null} ofertaId - El ID de la oferta para filtrar, o `null` para cargar todos.
     */
    function cargarPostulantes(ofertaId = null) {
        // Muestra un SweetAlert de carga
        Swal.fire({
            title: 'Cargando postulantes...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const ajaxData = {};
        // Si hay un ID de oferta válido, lo añade a los datos de la petición AJAX
        if (ofertaId && ofertaId > 0) {
            ajaxData.oferta_id = ofertaId;
        }

        // Inicia el bloque try-catch para la llamada AJAX de carga
        try {
            $.ajax({
                url: '../../app/models/obtener_estudiante.php', // URL del script PHP para obtener postulantes
                type: 'GET',
                dataType: 'json',
                data: ajaxData, // Envía el ID de oferta solo si es válido
            })
                .done(function (response) {
                    Swal.close(); // Cierra el SweetAlert de carga

                    // Determina el título de la oferta si los datos se filtraron por una oferta específica
                    const tituloOferta = (response.data && response.data.length > 0 && ofertaId && ofertaId > 0) ? response.data[0].TituloOferta : null;
                    renderizarPostulantes(response.data, tituloOferta); // Renderiza los postulantes

                    if (!response.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error al cargar',
                            text: response.message || 'No se pudieron cargar los postulantes. Inténtalo de nuevo.',
                        });
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    Swal.close(); // Cierra el SweetAlert de carga en caso de error de conexión
                    console.error("Error AJAX al cargar postulantes:", textStatus, errorThrown, jqXHR);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No se pudo comunicar con el servidor para cargar los postulantes. Revisa tu conexión.',
                    });
                    renderizarPostulantes([]); // Renderiza una lista vacía en caso de error
                });
        } catch (e) {
            Swal.close();
            console.error("Error al configurar la solicitud AJAX de carga:", e);
            Swal.fire('Error JS', 'Ocurrió un problema inesperado al intentar cargar los postulantes.', 'error');
        }
    }

    // --- Inicialización al cargar la página ---

    // Obtiene el ID de la oferta de los parámetros de la URL
    const ofertaIdDeURL = getUrlParameter('oferta_id');

    // Carga los postulantes. Si hay un ID de oferta en la URL, los filtrará por él.
    // De lo contrario, cargará todos los postulantes.
    cargarPostulantes(ofertaIdDeURL);
});