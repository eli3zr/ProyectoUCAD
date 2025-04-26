$(function () {
    // Event listener para el botón "Filtrar"
    $('.card-body .row .col-md-2 .btn').on('click', function () {
        const filtroPuesto = $('#filtroPuesto').val().toLowerCase();
        const filtroEmpresa = $('#filtroEmpresa').val().toLowerCase();
        const filtroFecha = $('#filtroFecha').val();

        const aplicacionesFiltradas = aplicacionesOriginales.filter(aplicacion => {
            const coincidePuesto = aplicacion.puesto.includes(filtroPuesto);
            const coincideEmpresa = aplicacion.empresa.includes(filtroEmpresa);
            const coincideFecha = !filtroFecha || aplicacion.fecha === filtroFecha;

            return coincidePuesto && coincideEmpresa && coincideFecha;
        });

        mostrarAplicaciones(aplicacionesFiltradas);
    });

    // Event listener para el botón "Recargar"
    $('.card-body .d-flex.justify-content-between.align-items-center.mb-3 div .btn-outline-secondary.btn-sm').on('click', function () {
        $('#filtroPuesto').val('');
        $('#filtroEmpresa').val('');
        $('#filtroFecha').val('');
        mostrarAplicaciones(aplicacionesOriginales);
    });

    // Event listener para "Ver Detalles" y "Retirar Aplicación" (Event Delegation)
    $('#tablaAplicaciones').on('click', 'a .fa-eye', function () {
        const verDetallesBtn = $(this).closest('a');
        const row = verDetallesBtn.closest('tr');
        const puesto = row.find('td:nth-child(2)').text();
        const empresa = row.find('td:nth-child(3)').text();
        const fecha = row.find('td:nth-child(4)').text();
        const estado = row.find('td:nth-child(5)').text();

        Swal.fire({
            title: 'Detalles de la Aplicación',
            html: `<b>Puesto:</b> ${puesto}<br>` +
                `<b>Empresa:</b> ${empresa}<br>` +
                `<b>Fecha de Aplicación:</b> ${fecha}<br>` +
                `<b>Estado:</b> ${estado}`,
            icon: 'info',
            confirmButtonText: 'Cerrar'
        });
    });

    $('#tablaAplicaciones').on('click', 'button .fa-times', function () {
        const retirarBtn = $(this).closest('button');
        const row = retirarBtn.closest('tr');
        const puesto = row.find('td:nth-child(2)').text();

        Swal.fire({
            title: '¿Estás seguro?',
            text: `¿Deseas retirar tu aplicación para "${puesto}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, retirar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                row.remove();
                Swal.fire(
                    '¡Retirada!',
                    `Tu aplicación para "${puesto}" ha sido retirada.`,
                    'success'
                );
                console.log(`Aplicación para "${puesto}" retirada (simulado).`);
            }
        });
    });

    const tablaAplicacionesBody = $('.table-responsive .table tbody');
    const aplicacionesOriginales = [];

    // Función para extraer los datos de las aplicaciones de la tabla
    function extraerDatosAplicaciones() {
        tablaAplicacionesBody.find('tr').each(function () {
            const columnas = $(this).find('td');
            if (columnas.length > 0) {
                aplicacionesOriginales.push({
                    id: columnas.eq(0).text(),
                    puesto: columnas.eq(1).text().toLowerCase(),
                    empresa: columnas.eq(2).text().toLowerCase(),
                    fecha: columnas.eq(3).text(),
                    estado: columnas.eq(4).find('span').text(),
                    acciones: columnas.eq(5).html()
                });
            }
        });
        mostrarAplicaciones(aplicacionesOriginales); // Mostrar todas las aplicaciones al cargar
    }

    // Función para mostrar las aplicaciones en la tabla
    function mostrarAplicaciones(aplicaciones) {
        tablaAplicacionesBody.empty(); // Limpiar la tabla

        if (aplicaciones.length === 0) {
            const noResultadosRow = $('<tr><td colspan="6" class="text-center">No se encontraron aplicaciones con los criterios de búsqueda.</td></tr>');
            tablaAplicacionesBody.append(noResultadosRow);
            return;
        }

        $.each(aplicaciones, function (index, aplicacion) {
            const row = $('<tr></tr>');
            row.html(`
                <td>${aplicacion.id}</td>
                <td>${aplicacion.puesto}</td>
                <td>${aplicacion.empresa}</td>
                <td>${aplicacion.fecha}</td>
                <td>${aplicacion.estado}</td>
                <td>${aplicacion.acciones}</td>
            `);
            tablaAplicacionesBody.append(row);
        });
    }

    // Event listeners para filtrar en tiempo real mientras se escribe (opcional)
    $('#filtroPuesto').on('input', function () {
        $('.card-body .row .col-md-2 .btn').click();
    });

    $('#filtroEmpresa').on('input', function () {
        $('.card-body .row .col-md-2 .btn').click();
    });

    $('#filtroFecha').on('input', function () {
        $('.card-body .row .col-md-2 .btn').click();
    });

    // Extraer los datos de las aplicaciones al cargar la página
    extraerDatosAplicaciones();
}); 