// administrar_ofertas.js

$(function() {
    // --- Seleccionar elementos del DOM ---
    const filtroPuestoInput = $('#filtroPuesto');
    const filtroEstadoSelect = $('#filtroEstado');
    const filtroFechaInput = $('#filtroFecha');
    const btnFiltrar = $('.card-body button.btn-primary');
    const btnRecargar = $('.card-body button.btn-outline-secondary');
    const tablaOfertasBody = $('#tablaOfertas tbody');

    // --- Elementos del modal de edición ---
    // Referencia al modal de Bootstrap
    const modalEditarOferta = $('#modalEditarOferta');
    // Referencia al formulario dentro del modal
    const formEditarOferta = $('#formEditarOferta');
    // Referencia a los botónes dentro del modal
    const btnGuardarCambios = $('#btnGuardarCambios');
    const btnCancelar = $('#btnCancelar');

    // Referencias a los campos de entrada del formulario del modal (asegúrate que los IDs y 'name' en tu HTML coinciden)
    const editOfertaId = $('#editOfertaId');
    const editTituloPuesto = $('#editTituloPuesto');
    const editDescripcionTrabajo = $('#editDescripcionTrabajo');
    const editRequisitos = $('#editRequisitos');
    const editSalarioMinimo = $('#editSalarioMinimo');
    const editSalarioMaximo = $('#editSalarioMaximo');
    const editModalidad = $('#editModalidad');
    const editUbicacion = $('#editUbicacion');
    const editEstado = $('#editEstado');

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
                case 'eliminada':
                    badgeClass = 'bg-danger';
                    break;
                default:
                    badgeClass = 'bg-info';
            }
            const estadoTexto = oferta.estado.charAt(0).toUpperCase() + oferta.estado.slice(1);

            const fechaPublicacion = oferta.fecha_publicacion ? oferta.fecha_publicacion.split(' ')[0] : 'N/A';

            const row = `
                <tr>
                    <td>${contador++}</td>
                    <td>${oferta.Titulo_Puesto}</td>
                    <td>${fechaPublicacion}</td>
                    <td><span class="badge ${badgeClass}">${estadoTexto}</span></td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline btn-ver-detalles" data-id="${oferta.ID_Oferta}" style="color: #112852; border-color: #112852;" title="Ver Detalles"><i class="fas fa-eye"></i></a>
                        <a href="#" class="btn btn-sm btn-outline-primary ms-1 btn-editar-oferta" data-id="${oferta.ID_Oferta}" title="Editar"><i class="fas fa-edit"></i></a>
                        <a href="#" class="btn btn-sm btn-outline-info ms-1 btn-ver-postulantes ${oferta.estado === 'borrador' || oferta.estado === 'eliminada' ? 'disabled' : ''}" data-id="${oferta.ID_Oferta}" title="Ver Postulantes"><i class="fas fa-users"></i></a>
                    </td>
                </tr>
            `;
            tablaOfertasBody.append(row);
        });

        // --- Manejo de eventos para las acciones de la tabla (delegación de eventos) ---

        // Evento para el botón "Ver Detalles"
        tablaOfertasBody.off('click', '.btn-ver-detalles').on('click', '.btn-ver-detalles', function(e) {
            e.preventDefault();
            const ofertaId = $(this).data('id');
            if (typeof mostrarDetallesOfertaEnModal === 'function') {
                mostrarDetallesOfertaEnModal(ofertaId);
            } else {
                Swal.fire('Error JS', 'La función para ver detalles no está disponible. Asegúrate de que detalle_oferta.js esté cargado correctamente.', 'error');
            }
        });

        // Evento para el botón "Editar Oferta"
        tablaOfertasBody.off('click', '.btn-editar-oferta').on('click', '.btn-editar-oferta', function(e) {
            e.preventDefault();
            const ofertaId = $(this).data('id');
            cargarOfertaParaEdicion(ofertaId); // Llama a la función que carga la oferta y abre el modal
        });

        // Evento para el botón "Ver Postulantes"
        tablaOfertasBody.off('click', '.btn-ver-postulantes').on('click', '.btn-ver-postulantes', function(e) {
            e.preventDefault();
            const ofertaId = $(this).data('id');
            if (!$(this).hasClass('disabled')) {
                // Redirige a la página de postulantes con el ID de la oferta
                window.location.href = `../views/postulantes.html?oferta_id=${ofertaId}`;
            } else {
                Swal.fire('Advertencia', 'No puedes ver postulantes para ofertas en estado borrador o eliminada.', 'warning');
            }
        });
    }

    /**
     * Función para cargar una oferta específica en el modal de edición.
     * Realiza una petición AJAX para obtener los datos de la oferta por su ID
     * y luego rellena el formulario del modal con esos datos.
     * @param {number} ofertaId - El ID de la oferta a cargar.
     */
    function cargarOfertaParaEdicion(ofertaId) {
        Swal.fire({
            title: 'Cargando datos de la oferta...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '../../app/models/obtener_oferta.php', // El mismo script que carga todas, pero ahora con 'id'
            type: 'GET',
            dataType: 'json',
            data: { id: ofertaId }, // Envía el ID de la oferta como parámetro GET
            success: function(response) {
                Swal.close();
                if (response.success && response.data) {
                    const oferta = response.data;
                    // Rellenar los campos del formulario con los datos de la oferta
                    editOfertaId.val(oferta.ID_Oferta);
                    editTituloPuesto.val(oferta.Titulo_Puesto);
                    editDescripcionTrabajo.val(oferta.Descripción_Trabajo);
                    editRequisitos.val(oferta.Requisitos);
                    editSalarioMinimo.val(oferta.Salario_Minimo);
                    editSalarioMaximo.val(oferta.Salario_Maximo);
                    editModalidad.val(oferta.Modalidad);
                    editUbicacion.val(oferta.Ubicación);
                    editEstado.val(oferta.estado);

                    // Mostrar el modal de edición
                    modalEditarOferta.modal('show');
                } else {
                    Swal.fire('Error', response.message || 'No se pudo cargar la oferta para edición.', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                Swal.close();
                console.error("Error AJAX al cargar oferta para edición:", textStatus, errorThrown, jqXHR);
                Swal.fire('Error', 'No se pudo comunicar con el servidor para cargar la oferta. Revisa tu conexión.', 'error');
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
            url: '../../app/models/obtener_oferta.php',
            type: 'GET',
            dataType: 'json',
            data: filtros,
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

    // --- Manejador para el envío del formulario de edición ---
    formEditarOferta.on('submit', function(e) {
        e.preventDefault(); // Previene el envío tradicional del formulario

        const formData = $(this).serialize(); // Serializa todos los datos del formulario

        Swal.fire({
            title: 'Guardando cambios...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '../../app/models/actualizar_oferta.php', // Ruta al script PHP que procesará la actualización
            type: 'POST', // Método POST para enviar los datos del formulario
            dataType: 'json', // Espera una respuesta JSON del servidor
            data: formData, // Los datos serializados del formulario
            success: function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire('¡Éxito!', response.message, 'success');
                    modalEditarOferta.modal('hide'); // Oculta el modal
                    cargarOfertas(); // Recarga la tabla para mostrar los cambios
                } else {
                    Swal.fire('Error', response.message || 'No se pudieron guardar los cambios.', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                Swal.close();
                console.error("Error AJAX al guardar cambios:", textStatus, errorThrown, jqXHR);
                Swal.fire('Error', 'No se pudo comunicar con el servidor para guardar los cambios. Revisa tu conexión.', 'error');
            }
        });
    });


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
        filtroPuestoInput.val('');
        filtroEstadoSelect.val('');
        filtroFechaInput.val('');
        cargarOfertas();
    });

});
