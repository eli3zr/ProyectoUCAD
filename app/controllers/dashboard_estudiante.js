$(function() {
    // Función para cargar los datos del dashboard
    function cargarDashboard() {

        // Seleccionar los contenedores dinámicos por sus IDs
        const ofertasContainer = $('#ofertasDestacadasContainer');
        const aplicacionesList = $('#resumenAplicacionesList');
        const postulacionesActivasCount = $('#postulacionesActivasCount'); // Asumiendo que tienes este ID para el contador total de activas

        // Mostrar estados de carga iniciales para una mejor experiencia de usuario
        ofertasContainer.html('<div class="text-center text-muted loading-message mb-3">Cargando ofertas destacadas...</div>');
        aplicacionesList.html('<li class="text-center text-muted loading-message mb-3">Cargando resumen de aplicaciones...</li>');
        postulacionesActivasCount.text('Cargando...'); // Inicializar el contador

        $.ajax({
            url: '../../app/models/obtener_dashboard_estudiante.php', // Ruta correcta al PHP
            type: 'GET',
            dataType: 'json',
            // data: { id_estudiante: idEstudiante }, // ¡¡¡ELIMINAR ESTA LÍNEA!!! El PHP obtiene el ID de la sesión.
            success: function(response) {
                // Eliminar mensajes de carga
                $('.loading-message').remove(); // Elimina todos los mensajes de carga

                if (response.success) {
                    const data = response.data;

                    // --- Renderizar Ofertas Destacadas ---
                    ofertasContainer.empty(); // Limpiar completamente el contenedor antes de añadir
                    if (data.ofertas_destacadas && data.ofertas_destacadas.length > 0) {
                        data.ofertas_destacadas.forEach(oferta => {
                            const ofertaHtml = `
                                <div class="mb-3 border-bottom pb-3">
                                    <h6 class="card-title fw-bold" style="color: #112852;">${oferta.Titulo_Puesto}</h6>
                                    <p class="card-text text-muted"><i class="fas fa-building me-2" style="color: #112852;"></i>
                                        ${oferta.Nombre_Empresa}</p>
                                    <a href="./explorar_oferta.html?oferta_id=${oferta.ID_Oferta}" class="btn btn-sm btn-outline"
                                        style="color: #112852; border-color: #112852;">Ver Detalles</a>
                                </div>
                            `;
                            ofertasContainer.append(ofertaHtml); // Añadir al final
                        });
                        // Añadir el enlace "Ver todas las ofertas" después de las ofertas
                        ofertasContainer.append('<div class="text-center mt-4"><a href="./buscar_ofertas.html" class="text-decoration-none" style="color: #112852;">Ver todas las ofertas &rightarrow;</a></div>');

                    } else {
                        ofertasContainer.append('<div class="text-center text-muted mb-3">No hay ofertas destacadas disponibles.</div>');
                        // Asegurarse de que el enlace "Ver todas" se siga mostrando si no hay ofertas
                        ofertasContainer.append('<div class="text-center mt-4"><a href="./buscar_ofertas.html" class="text-decoration-none" style="color: #112852;">Ver todas las ofertas &rightarrow;</a></div>');
                    }

                    // --- Renderizar Resumen de Aplicaciones ---
                    const resumen = data.resumen_aplicaciones;
                    aplicacionesList.empty(); // Limpiar la lista de aplicaciones
                    if (resumen) {
                        // Calcular el total de postulaciones "En revisión" si tus estados son Pendiente y Revisado
                        const enRevisionCount = (resumen.Pendiente || 0) + (resumen.Revisado || 0);
                        postulacionesActivasCount.text(enRevisionCount); // Actualizar el contador principal

                        aplicacionesList.append(`
                            <li><i class="fas fa-spinner fa-pulse text-warning me-2" style="color: #F0C11A;"></i> En
                                revisión: <span class="fw-bold">${enRevisionCount}</span></li>
                            <li><i class="fas fa-check-circle text-success me-2" style="color: #28a745;"></i> Aceptadas:
                                <span class="fw-bold">${resumen.Aceptado || 0}</span>
                            </li>
                            <li><i class="fas fa-times-circle text-danger me-2" style="color: #dc3545;"></i> Rechazadas:
                                <span class="fw-bold">${resumen.Rechazado || 0}</span>
                            </li>
                        `);
                    } else {
                        aplicacionesList.append('<li class="text-center text-muted mb-3">No hay datos de resumen de aplicaciones.</li>');
                        postulacionesActivasCount.text('N/A');
                    }

                } else {
                    console.error('Error al cargar datos del dashboard:', response.message || 'No se pudieron cargar los datos del dashboard.');
                    ofertasContainer.empty().append('<div class="text-center text-danger">Error al cargar las ofertas: ' + (response.message || 'Desconocido') + '</div>');
                    aplicacionesList.empty().append('<li class="text-center text-danger">Error al cargar el resumen de aplicaciones: ' + (response.message || 'Desconocido') + '</li>');
                    postulacionesActivasCount.text('Error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error AJAX al cargar dashboard:", textStatus, errorThrown, jqXHR);
                ofertasContainer.empty().append('<div class="text-center text-danger">No se pudo comunicar con el servidor para cargar las ofertas.</div>');
                aplicacionesList.empty().append('<li class="text-center text-danger">No se pudo comunicar con el servidor para cargar el resumen de aplicaciones.</li>');
                postulacionesActivasCount.text('Error');
            }
        });
    }

    // Cargar el dashboard cuando el documento esté listo
    cargarDashboard();
});