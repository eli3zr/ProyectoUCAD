$(function() {
    const filtroPuestoInput = $('#filtroPuesto');
    const filtroEstadoSelect = $('#filtroEstado');
    const filtroFechaInput = $('#filtroFecha');
    const btnFiltrar = $('.card-body button.btn-primary');
    const btnRecargar = $('.card-body button.btn-outline-secondary');
    const tablaOfertas = $('table tbody');

    // Datos de las ofertas simuladas
    let ofertasSimuladas = [
        { id: 1, puesto: 'Desarrollador Web Junior', fechaPublicacion: '2025-04-01', estado: 'activa' },
        { id: 2, puesto: 'Diseñador Gráfico (Prácticas)', fechaPublicacion: '2025-03-25', estado: 'borrador' },
        { id: 3, puesto: 'Community Manager', fechaPublicacion: '2025-03-15', estado: 'cerrada' }
    ];

    // Función para renderizar las filas de la tabla
    function renderizarOfertas(ofertas) {
        tablaOfertas.empty();
        if (ofertas.length === 0) {
            tablaOfertas.append('<tr><td colspan="5" class="text-center">No se encontraron ofertas.</td></tr>');
            return;
        }
        ofertas.forEach(oferta => {
            const estadoBadgeClass =
                oferta.estado === 'activa' ? 'bg-success' :
                oferta.estado === 'cerrada' ? 'bg-secondary' :
                'bg-warning text-dark';
            const estadoTexto = oferta.estado.charAt(0).toUpperCase() + oferta.estado.slice(1);
            const row = `
                <tr>
                    <td>${oferta.id}</td>
                    <td>${oferta.puesto}</td>
                    <td>${oferta.fechaPublicacion}</td>
                    <td><span class="badge ${estadoBadgeClass}">${estadoTexto}</span></td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline btn-ver-detalles" data-id="${oferta.id}" style="color: #112852; border-color: #112852;" title="Ver Detalles"><i class="fas fa-eye"></i></a>
                        <a href="#" class="btn btn-sm btn-outline-primary ms-1 btn-editar-oferta" data-id="${oferta.id}" title="Editar"><i class="fas fa-edit"></i></a>
                        <button class="btn btn-sm btn-outline-danger ms-1 btn-eliminar-oferta" data-id="${oferta.id}" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        <a href="#" class="btn btn-sm btn-outline-info ms-1 btn-ver-postulantes ${oferta.estado === 'borrador' ? 'disabled' : ''}" data-id="${oferta.id}" title="Ver Postulantes"><i class="fas fa-users"></i></a>
                    </td>
                </tr>
            `;
            tablaOfertas.append(row);
        });

        // Event listeners para las acciones
        tablaOfertas.find('.btn-ver-detalles').off('click').on('click', function(e) {
            e.preventDefault();
            const ofertaId = $(this).data('id');
            Swal.fire('Ver Detalles', `Simulando la visualización de detalles para la oferta ID ${ofertaId}`, 'info');
            // En el futuro, aquí podrías redirigir a una página de detalles o mostrar un modal con la información.
        });

        tablaOfertas.find('.btn-editar-oferta').off('click').on('click', function(e) {
            e.preventDefault();
            const ofertaId = $(this).data('id');
            Swal.fire('Editar Oferta', `Simulando la edición de la oferta ID ${ofertaId}`, 'warning');
            // En el futuro, aquí podrías redirigir a un formulario de edición o mostrar un modal con el formulario.
        });

        tablaOfertas.find('.btn-eliminar-oferta').off('click').on('click', function() {
            const ofertaId = $(this).data('id');
            Swal.fire({
                title: '¿Estás seguro?',
                text: `¿Deseas eliminar la oferta con ID ${ofertaId}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Simular la eliminación a través de una llamada AJAX
                    $.ajax({
                        url: '../../app/models/eliminar_oferta.php',
                        type: 'POST',
                        dataType: 'json',
                        data: { id: ofertaId },
                        success: function(response) {
                            if (response.success) {
                                ofertasSimuladas = ofertasSimuladas.filter(oferta => oferta.id !== ofertaId);
                                cargarOfertas();
                                Swal.fire('Eliminada!', response.msg, 'success');
                            } else {
                                Swal.fire('Error', response.error, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                        }
                    });
                }
            });
        });

        tablaOfertas.find('.btn-ver-postulantes').off('click').on('click', function(e) {
            e.preventDefault();
            const ofertaId = $(this).data('id');
            Swal.fire('Ver Postulantes', `Simulando la visualización de postulantes para la oferta ID ${ofertaId}`, 'info');
            // En el futuro, aquí podrías redirigir a una página de postulantes o mostrar un modal con la lista.
        });
    }

    // Función para cargar las ofertas (simulando una llamada al backend)
    function cargarOfertas() {
        // Simulación sin backend real
        renderizarOfertas(ofertasSimuladas);
    }

    // Cargar las ofertas iniciales
    cargarOfertas();

    // Simular el filtrado
    btnFiltrar.on('click', function() {
        const puestoTexto = filtroPuestoInput.val().trim().toLowerCase();
        const estadoSeleccionado = filtroEstadoSelect.val();
        const fechaSeleccionada = filtroFechaInput.val();

        const ofertasFiltradas = ofertasSimuladas.filter(oferta => {
            const coincidePuesto = oferta.puesto.toLowerCase().includes(puestoTexto);
            const coincideEstado = !estadoSeleccionado || oferta.estado === estadoSeleccionado;
            const coincideFecha = !fechaSeleccionada || oferta.fechaPublicacion === fechaSeleccionada;
            return coincidePuesto && coincideEstado && coincideFecha;
        });

        renderizarOfertas(ofertasFiltradas);
    });

    // Simular la recarga
    btnRecargar.on('click', function() {
        cargarOfertas();
        filtroPuestoInput.val('');
        filtroEstadoSelect.val('');
        filtroFechaInput.val('');
    });
});