// C:\xampp\htdocs\Jobtrack_Ucad\app\controllers\mis_aplicaciones.js

// Almacenar las aplicaciones originales para filtros y recarga
let aplicacionesOriginales = [];
const tablaAplicacionesBody = $('#tablaAplicaciones tbody');
const filtroPuestoInput = $('#filtroPuesto');
const filtroEmpresaInput = $('#filtroEmpresa');
const filtroFechaInput = $('#filtroFecha');

$(function () {
    // Función para obtener y mostrar las aplicaciones
    async function cargarAplicaciones() {
        // Mostrar un loader mientras se cargan los datos
        tablaAplicacionesBody.html('<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div> Cargando aplicaciones...</td></tr>');

        try {
            const response = await fetch('http://localhost/Jobtrack_Ucad/app/models/obtener_aplicaciones_estudiante.php');

            if (!response.ok) {
                // Si hay un error HTTP, lanzar una excepción
                const errorText = await response.text();
                throw new Error(`Error del servidor: ${response.status} ${response.statusText} - ${errorText}`);
            }

            const result = await response.json();

            if (result.success) {
                aplicacionesOriginales = result.data.map(app => ({
                    // Normalizar los nombres de las propiedades para facilitar el manejo
                    id: app.ID_Aplicacion,
                    puesto: app.Puesto,
                    empresa: app.Empresa,
                    fecha: app.Fecha_Aplicacion,
                    estado: app.Estado_Aplicacion,
                    carta_presentacion: app.Carta_Presentacion,
                    ruta_cv: app.Ruta_CV // Añadir la ruta del CV
                }));
                mostrarAplicaciones(aplicacionesOriginales); // Mostrar todas al cargar
            } else {
                Swal.fire('Error', result.message || 'No se pudieron cargar las aplicaciones.', 'error');
                tablaAplicacionesBody.html('<tr><td colspan="6" class="text-center text-danger">Error al cargar aplicaciones: ' + (result.message || 'Desconocido') + '</td></tr>');
            }
        } catch (error) {
            console.error('Error al obtener aplicaciones:', error);
            Swal.fire('Error de Conexión', 'No se pudo conectar con el servidor para obtener las aplicaciones.', 'error');
            tablaAplicacionesBody.html('<tr><td colspan="6" class="text-center text-danger">Error de conexión al servidor.</td></tr>');
        }
    }

    // Función para mostrar las aplicaciones en la tabla (ya existente, pero adaptada)
    function mostrarAplicaciones(aplicaciones) {
        tablaAplicacionesBody.empty(); // Limpiar la tabla

        if (aplicaciones.length === 0) {
            const noResultadosRow = $('<tr><td colspan="6" class="text-center">No se encontraron aplicaciones con los criterios de búsqueda.</td></tr>');
            tablaAplicacionesBody.append(noResultadosRow);
            return;
        }

        $.each(aplicaciones, function (index, aplicacion) {
            // Determinar la clase del badge según el estado
            let badgeClass = '';
            let btnRetirarDisabled = '';
            if (aplicacion.estado === 'Pendiente' || aplicacion.estado === 'Enviada') {
                badgeClass = 'bg-info text-white';
            } else if (aplicacion.estado === 'En Revisión') {
                badgeClass = 'bg-warning text-dark';
            } else if (aplicacion.estado === 'Contactado') {
                badgeClass = 'bg-success text-white';
                // Si ya está contactado, no permitir retirar
                btnRetirarDisabled = 'disabled';
            } else if (aplicacion.estado === 'Retirada') {
                 badgeClass = 'bg-secondary text-white';
                 btnRetirarDisabled = 'disabled';
            } else if (aplicacion.estado === 'Rechazado') {
                 badgeClass = 'bg-danger text-white';
                 btnRetirarDisabled = 'disabled';
            }

            const row = $('<tr></tr>');
            row.html(`
                <td>${index + 1}</td>
                <td>${aplicacion.puesto}</td>
                <td>${aplicacion.empresa}</td>
                <td>${aplicacion.fecha}</td>
                <td><span class="badge ${badgeClass}">${aplicacion.estado}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary ver-detalles"
                        style="color: #112852; border-color: #112852;" title="Ver Detalles"
                        data-id="${aplicacion.id}"
                        data-puesto="${aplicacion.puesto}"
                        data-empresa="${aplicacion.empresa}"
                        data-fecha="${aplicacion.fecha}"
                        data-estado="${aplicacion.estado}"
                        data-carta="${aplicacion.carta_presentacion || ''}"
                        data-rutacv="${aplicacion.ruta_cv || ''}">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger ms-1 retirar-aplicacion ${btnRetirarDisabled}"
                        title="Retirar Aplicación"
                        data-id="${aplicacion.id}"
                        data-puesto="${aplicacion.puesto}"
                        ${btnRetirarDisabled}>
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `);
            tablaAplicacionesBody.append(row);
        });
    }

    // --- Event Listeners ---

    // Event listener para el botón "Filtrar"
    // Ahora el filtro se aplica sobre `aplicacionesOriginales` y se muestra el resultado
    $('.card-body .row .col-md-2 .btn').on('click', function () {
        const filtroPuestoVal = filtroPuestoInput.val().toLowerCase();
        const filtroEmpresaVal = filtroEmpresaInput.val().toLowerCase();
        const filtroFechaVal = filtroFechaInput.val();

        const aplicacionesFiltradas = aplicacionesOriginales.filter(aplicacion => {
            const coincidePuesto = aplicacion.puesto.toLowerCase().includes(filtroPuestoVal);
            const coincideEmpresa = aplicacion.empresa.toLowerCase().includes(filtroEmpresaVal);
            const coincideFecha = !filtroFechaVal || aplicacion.fecha.startsWith(filtroFechaVal); // `startsWith` para fechas si filtro es 'YYYY-MM-DD'

            return coincidePuesto && coincideEmpresa && coincideFecha;
        });

        mostrarAplicaciones(aplicacionesFiltradas);
    });

    // Event listener para el botón "Recargar"
    $('.btn-outline-secondary.btn-sm').on('click', function () {
        filtroPuestoInput.val('');
        filtroEmpresaInput.val('');
        filtroFechaInput.val('');
        cargarAplicaciones(); // Vuelve a cargar del servidor para asegurar datos frescos
    });

    // Event listener para "Ver Detalles" (Event Delegation)
    $('#tablaAplicaciones').on('click', '.ver-detalles', function () {
        const btn = $(this);
        const id_aplicacion = btn.data('id');
        const puesto = btn.data('puesto');
        const empresa = btn.data('empresa');
        const fecha = btn.data('fecha');
        const estado = btn.data('estado');
        const carta = btn.data('carta');
        const ruta_cv = btn.data('rutacv');

        let cvHtml = '';
        if (ruta_cv) {
            // Construir la URL completa del CV. Asume que tu carpeta public está accesible.
            const fullCvUrl = `http://localhost/Jobtrack_Ucad${ruta_cv}`;
            cvHtml = `<br><b>CV Adjunto:</b> <a href="${fullCvUrl}" target="_blank" class="btn btn-link p-0">Ver CV <i class="fas fa-external-link-alt"></i></a>`;
        }

        Swal.fire({
            title: `Detalles de Aplicación #${id_aplicacion}`,
            html: `<b>Puesto:</b> ${puesto}<br>` +
                  `<b>Empresa:</b> ${empresa}<br>` +
                  `<b>Fecha de Aplicación:</b> ${fecha}<br>` +
                  `<b>Estado:</b> ${estado}<br>` +
                  `<b>Carta de Presentación:</b> ${carta || 'No proporcionada.'}${cvHtml}`,
            icon: 'info',
            confirmButtonText: 'Cerrar',
            customClass: {
                content: 'text-start' // Alineación del contenido del pop-up
            }
        });
    });

    // Event listener para "Retirar Aplicación" (Event Delegation)
    $('#tablaAplicaciones').on('click', '.retirar-aplicacion:not(.disabled)', function () {
        const btn = $(this);
        const id_aplicacion = btn.data('id');
        const puesto = btn.data('puesto');

        Swal.fire({
            title: '¿Estás seguro?',
            text: `¿Deseas retirar tu aplicación para "${puesto}"? Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, retirar',
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await fetch('http://localhost/Jobtrack_Ucad/app/models/retirar_aplicacion.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json' // Indicamos que enviamos JSON
                        },
                        body: JSON.stringify({ id_aplicacion: id_aplicacion }) // Enviamos el ID de la aplicación
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Error del servidor: ${response.status} ${response.statusText} - ${errorText}`);
                    }

                    const jsonResponse = await response.json();

                    if (jsonResponse.success) {
                        Swal.fire(
                            '¡Retirada!',
                            jsonResponse.message,
                            'success'
                        );
                        cargarAplicaciones(); // Recargar la tabla para reflejar el cambio de estado
                    } else {
                        Swal.fire(
                            'Error al Retirar',
                            jsonResponse.message || 'No se pudo retirar la aplicación.',
                            'error'
                        );
                    }
                } catch (error) {
                    console.error('Error al retirar aplicación:', error);
                    Swal.fire(
                        'Error de Conexión',
                        'No se pudo conectar con el servidor para retirar la aplicación.',
                        'error'
                    );
                }
            }
        });
    });

    // Event listeners para filtrar en tiempo real (opcional, llama al click del botón Filtrar)
    // Se dispara el click del botón filtrar cada vez que se escribe
    filtroPuestoInput.on('input', function () { $('.card-body .row .col-md-2 .btn').click(); });
    filtroEmpresaInput.on('input', function () { $('.card-body .row .col-md-2 .btn').click(); });
    filtroFechaInput.on('change', function () { $('.card-body .row .col-md-2 .btn').click(); }); // Para el input date es mejor 'change'

    // Cargar las aplicaciones al cargar la página por primera vez
    cargarAplicaciones();
});