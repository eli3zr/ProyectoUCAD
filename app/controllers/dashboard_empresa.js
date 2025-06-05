$(function() {
    /**
     * Carga y actualiza los datos del dashboard de la empresa.
     * Muestra el conteo de ofertas activas y una lista de los postulantes más recientes.
     * La ID de la empresa se gestiona en el lado del servidor (PHP) a través de la sesión.
     */
    function cargarDashboardEmpresa() {
        const ofertasActivasCount = $('#ofertasActivasCount');
        const postulantesRecientesContainer = $('#postulantesRecientesContainer');

        // Mostrar estados de carga iniciales para una mejor experiencia de usuario
        ofertasActivasCount.text('Cargando...');
        // Eliminar cualquier mensaje de carga anterior y añadir el nuevo
        postulantesRecientesContainer.find('.loading-message, .text-muted').remove();
        postulantesRecientesContainer.prepend('<div class="text-center text-muted loading-message mb-3">Cargando postulantes...</div>');

        $.ajax({
            url: '../../app/models/obtener_dashboard_empresa.php', // Ruta al script PHP que obtiene los datos
            type: 'GET', // Método GET para solicitar datos
            dataType: 'json', // Esperamos una respuesta en formato JSON
            success: function(response) {
                // Eliminar el mensaje de carga una vez que se recibe la respuesta
                postulantesRecientesContainer.find('.loading-message').remove();

                if (response.success) {
                    const data = response.data;

                    // Actualizar el contador de ofertas activas
                    ofertasActivasCount.text(data.ofertas_activas);

                    // **GUARDAR EL ENLACE "VER TODOS LOS POSTULANTES" ANTES DE LIMPIAR**
                    const verTodosLink = postulantesRecientesContainer.find('.text-center.mt-4').clone(true); // Clonar con eventos

                    // Limpiar solo los postulantes existentes, manteniendo el enlace "Ver todos" si ya existe
                    postulantesRecientesContainer.empty(); // Limpia completamente el contenedor

                    // **AÑADIR UN CONTENEDOR ESPECÍFICO PARA LOS POSTULANTES SI ES NECESARIO**
                    // Esto ayuda a mantener la estructura limpia. Si no tienes un div específico para los
                    // postulantes dentro de #postulantesRecientesContainer, podrías agregarlo:
                    // postulantesRecientesContainer.append('<div id="listaPostulantes"></div>');
                    // const listaPostulantes = $('#listaPostulantes'); // Luego usas esto para append

                    // Renderizar los postulantes recientes
                    if (data.postulantes_recientes && data.postulantes_recientes.length > 0) {
                        data.postulantes_recientes.forEach(postulante => {
                            const fechaPostulacion = new Date(postulante.Fecha_Aplicacion).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
                            const postulanteHtml = `
                                <div class="mb-3 border-bottom pb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title fw-bold" style="color: #112852;">${postulante.Nombre_Estudiante} ${postulante.Apellido_Estudiante}</h6>
                                            <small class="text-muted">para ${postulante.Titulo_Puesto}</small><br>
                                            <small class="text-muted">Fecha de Postulación: ${fechaPostulacion}</small>
                                        </div>
                                    </div>
                                </div>
                            `;
                            postulantesRecientesContainer.append(postulanteHtml); // Añadir directamente al contenedor
                        });
                    } else {
                        // Si no hay postulantes recientes, mostrar un mensaje
                        postulantesRecientesContainer.append('<div class="text-center text-muted mb-3">No hay postulantes recientes.</div>');
                    }

                    // RE-AÑADIR EL ENLACE "VER TODOS LOS POSTULANTES" AL FINAL
                    if (verTodosLink.length) {
                        postulantesRecientesContainer.append(verTodosLink);
                    } else {
                        // Si el enlace no existía (primera carga), lo puedes agregar aquí
                        postulantesRecientesContainer.append('<div class="text-center mt-4"><a href="./postulantes.html" class="text-decoration-none" style="color: #112852;">Ver todos los postulantes &rightarrow;</a></div>');
                    }

                } else {
                    // Si la respuesta no es exitosa (pero no es un error de conexión)
                    console.error('Error al cargar datos del dashboard de empresa:', response.message);
                    ofertasActivasCount.text('Error');
                    postulantesRecientesContainer.empty().append('<div class="text-center text-danger">Error al cargar postulantes recientes: ' + (response.message || 'Desconocido') + '</div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Manejo de errores de la solicitud AJAX (ej. problemas de red, script PHP no encontrado)
                console.error("Error AJAX al cargar dashboard de empresa:", textStatus, errorThrown, jqXHR);
                ofertasActivasCount.text('Error');
                postulantesRecientesContainer.empty().append('<div class="text-center text-danger">No se pudo conectar con el servidor o hubo un error en la solicitud.</div>');
            }
        });
    }

    // Cargar el dashboard cuando el documento HTML esté completamente cargado y listo
    cargarDashboardEmpresa();
});