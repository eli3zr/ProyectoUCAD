// administrar_ofertas.js

$(function() {
    // Seleccionar los elementos del DOM
    const filtroPuestoInput = $('#filtroPuesto');
    const filtroEstadoSelect = $('#filtroEstado');
    const filtroFechaInput = $('#filtroFecha');
    const btnFiltrar = $('.card-body button.btn-primary');
    const btnRecargar = $('.card-body button.btn-outline-secondary');
    const tablaOfertasBody = $('#tablaOfertas tbody'); // Asegúrate de que tu <table> tenga id="tablaOfertas"

    // --- Funciones para manejar la lógica de la página ---

    /**
     * Función para renderizar las filas de la tabla con datos de ofertas.
     * @param {Array<Object>} ofertas - Un array de objetos, donde cada objeto es una oferta.
     */
    function renderizarOfertas(ofertas) {
        tablaOfertasBody.empty(); // Limpia cualquier fila existente

        if (ofertas.length === 0) {
            tablaOfertasBody.append('<tr><td colspan="5" class="text-center">No se encontraron ofertas que coincidan con los criterios.</td></tr>');
            return;
        }

        let contador = 1;
        ofertas.forEach(function (oferta) {
            // Determinar la clase del badge según el estado de la oferta
            let badgeClass = '';
            switch (oferta.estado) {
                case 'activa':
                    badgeClass = 'bg-success';
                    break;
                case 'cerrada':
                    badgeClass = 'bg-secondary';
                    break;
                case 'borrador':
                    badgeClass = 'bg-warning text-dark';
                    break;
                case 'eliminada': // Si tu DB tiene 'eliminada'
                    badgeClass = 'bg-danger';
                    break;
                default:
                    badgeClass = 'bg-info'; // Estado por defecto
            }
            const estadoTexto = oferta.estado.charAt(0).toUpperCase() + oferta.estado.slice(1);

            // Formatear la fecha de publicación (solo la parte de la fecha)
            const fechaPublicacion = oferta.fecha_publicacion ? oferta.fecha_publicacion.split(' ')[0] : 'N/A';

            // Crear la fila de la tabla con los botones de acción
            const row = `
                <tr>
                    <td>${contador++}</td>
                    <td>${oferta.Titulo_Puesto}</td>
                    <td>${fechaPublicacion}</td>
                    <td><span class="badge ${badgeClass}">${estadoTexto}</span></td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline btn-ver-detalles" data-id="${oferta.ID_Oferta}" style="color: #112852; border-color: #112852;" title="Ver Detalles"><i class="fas fa-eye"></i></a>
                        <a href="#" class="btn btn-sm btn-outline-primary ms-1 btn-editar-oferta" data-id="${oferta.ID_Oferta}" title="Editar"><i class="fas fa-edit"></i></a>
                        <button class="btn btn-sm btn-outline-danger ms-1 btn-eliminar-oferta" data-id="${oferta.ID_Oferta}" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        <a href="#" class="btn btn-sm btn-outline-info ms-1 btn-ver-postulantes ${oferta.estado === 'borrador' || oferta.estado === 'eliminada' ? 'disabled' : ''}" data-id="${oferta.ID_Oferta}" title="Ver Postulantes"><i class="fas fa-users"></i></a>
                    </td>
                </tr>
            `;
            tablaOfertasBody.append(row); // Añade la fila al cuerpo de la tabla
        });

        // --- Manejo de eventos para las acciones de la tabla (delegación de eventos) ---
        // Se usa delegación de eventos porque los botones se añaden dinámicamente al renderizar.

        // Evento para el botón "Ver Detalles"
        tablaOfertasBody.off('click', '.btn-ver-detalles').on('click', '.btn-ver-detalles', function(e) {
            e.preventDefault(); // Previene la acción por defecto del enlace (navegar a #)
            const ofertaId = $(this).data('id');
            // Llamada a la función global para mostrar detalles, definida en detalle_oferta.js
            if (typeof mostrarDetallesOfertaEnModal === 'function') {
                mostrarDetallesOfertaEnModal(ofertaId);
            } else {
                Swal.fire('Error JS', 'La función para ver detalles no está disponible. Asegúrate de que detalle_oferta.js esté cargado correctamente.', 'error');
            }
        });

        // Evento para el botón "Editar Oferta" (simulación por ahora)
        tablaOfertasBody.off('click', '.btn-editar-oferta').on('click', '.btn-editar-oferta', function(e) {
            e.preventDefault();
            const ofertaId = $(this).data('id');
            // Aquí se podría redirigir a una página de edición o abrir un modal de edición
            Swal.fire('Editar Oferta', `Simulando la edición de la oferta ID ${ofertaId}.`, 'warning');
        });

        // Evento para el botón "Eliminar Oferta" (confirmación y llamada AJAX)
        tablaOfertasBody.off('click', '.btn-eliminar-oferta').on('click', '.btn-eliminar-oferta', function() {
            const ofertaId = $(this).data('id');
            Swal.fire({
                title: '¿Estás seguro?',
                text: `¿Deseas inactivar la oferta con ID ${ofertaId}? Su estado cambiará a "Eliminada".`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, inactivar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Llamada AJAX para inactivar la oferta en el backend
                    $.ajax({
                        url: '../../app/models/eliminar_oferta.php', // ASEGÚRATE QUE ESTA RUTA ES CORRECTA
                        type: 'POST',
                        dataType: 'json',
                        data: { id: ofertaId },
                        beforeSend: function() {
                            Swal.showLoading();
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                Swal.fire('Inactivada!', response.message, 'success');
                                cargarOfertas(); // Recarga la tabla después de la inactivación exitosa
                            } else {
                                Swal.fire('Error', response.message || 'No se pudo inactivar la oferta.', 'error');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            Swal.close();
                            console.error("Error al inactivar oferta:", textStatus, errorThrown, jqXHR);
                            Swal.fire('Error', 'No se pudo comunicar con el servidor para inactivar la oferta. Revisa tu conexión.', 'error');
                        }
                    });
                }
            });
        });

        // Evento para el botón "Ver Postulantes" (simulación, con deshabilitación si el estado no lo permite)
        tablaOfertasBody.off('click', '.btn-ver-postulantes').on('click', '.btn-ver-postulantes', function(e) {
            e.preventDefault();
            const ofertaId = $(this).data('id');
            if (!$(this).hasClass('disabled')) {
                // Aquí se podría redirigir a una página para ver los postulantes de esta oferta
                Swal.fire('Ver Postulantes', `Simulando la visualización de postulantes para la oferta ID ${ofertaId}.`, 'info');
            } else {
                Swal.fire('Advertencia', 'No puedes ver postulantes para ofertas en estado borrador o eliminada.', 'warning');
            }
        });
    }

    /**
     * Función para cargar las ofertas desde el backend, aplicando filtros si se proporcionan.
     * Muestra un SweetAlert de carga y maneja la respuesta del servidor.
     * @param {Object} [filtros={}] - Objeto opcional con los criterios de filtro (puesto, estado, fecha).
     */
    function cargarOfertas(filtros = {}) {
        Swal.fire({
            title: 'Cargando ofertas...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '../../app/models/obtener_oferta.php', // ASEGÚRATE QUE ESTA RUTA ES CORRECTA
            type: 'GET',
            dataType: 'json',
            data: filtros, // Envía los filtros como parámetros GET
        })
        .done(function (response) {
            Swal.close();

            if (response.success) {
                renderizarOfertas(response.data);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al cargar',
                    text: response.message || 'No se pudieron cargar las ofertas. Inténtalo de nuevo.',
                });
            }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            Swal.close();
            console.error("Error AJAX al cargar ofertas:", textStatus, errorThrown, jqXHR);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo comunicar con el servidor para cargar las ofertas. Revisa tu conexión.',
            });
        });
    }

    // --- Inicialización y Event Listeners principales de filtros/recarga ---

    // Cargar las ofertas iniciales al cargar la página
    cargarOfertas();

    // Manejar el evento de clic del botón "Filtrar"
    btnFiltrar.on('click', function() {
        const filtros = {
            puesto: filtroPuestoInput.val().trim(),
            estado: filtroEstadoSelect.val(),
            fecha: filtroFechaInput.val()
        };
        cargarOfertas(filtros);
    });

    // Manejar el evento de clic del botón "Recargar"
    btnRecargar.on('click', function() {
        // Limpiar los campos de filtro
        filtroPuestoInput.val('');
        filtroEstadoSelect.val('');
        filtroFechaInput.val('');
        cargarOfertas(); // Recargar todas las ofertas sin filtros
    });

    // Opcional: Filtrar automáticamente al cambiar los campos de filtro
    // Descomenta las líneas siguientes si deseas que el filtrado se aplique
    // inmediatamente al cambiar el valor de los inputs/selects, sin necesidad de hacer clic en "Filtrar".
    // filtroPuestoInput.on('keyup', function() { btnFiltrar.click(); });
    // filtroEstadoSelect.on('change', function() { btnFiltrar.click(); });
    // filtroFechaInput.on('change', function() { btnFiltrar.click(); });
});