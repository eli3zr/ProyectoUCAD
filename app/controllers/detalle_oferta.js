// detalle_oferta.js

/**
 * Función para obtener y mostrar detalles de una sola oferta en un modal de SweetAlert2.
 * Esta función es global y se espera que sea llamada desde otros scripts (como administrar_ofertas.js).
 * @param {number} ofertaId - El ID de la oferta cuyos detalles se desean mostrar.
 */
function mostrarDetallesOfertaEnModal(ofertaId) {
    Swal.fire({
        title: 'Cargando detalles...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: '../../app/models/obtener_detalle_oferta.php', // Asegúrate que esta URL sea correcta y pueda devolver una única oferta por ID
        type: 'GET',
        dataType: 'json',
        data: { id: ofertaId }, // Envía el ID de la oferta para buscar los detalles
        success: function(response) {
            Swal.close();
            if (response.success && response.data) {
                // response.data DEBE ser un OBJETO único aquí, no un array.
                const oferta = response.data; 

                // Formatear el salario si ambos campos existen
                let salarioTexto = 'No especificado';
                if (oferta.Salario_Minimo && oferta.Salario_Maximo) {
                    salarioTexto = `$${parseFloat(oferta.Salario_Minimo).toFixed(2)} - $${parseFloat(oferta.Salario_Maximo).toFixed(2)}`;
                } else if (oferta.Salario_Minimo) {
                    salarioTexto = `$${parseFloat(oferta.Salario_Minimo).toFixed(2)}`;
                } else if (oferta.Salario_Maximo) {
                    salarioTexto = `$${parseFloat(oferta.Salario_Maximo).toFixed(2)}`;
                }

                const htmlContent = `
                    <p><strong>Puesto:</strong> ${oferta.Titulo_Puesto || 'N/A'}</p>
                    <p><strong>Descripción del Trabajo:</strong> ${oferta.Descripción_Trabajo || 'No disponible'}</p> <!-- Corregido: Descripción_Trabajo -->
                    <p><strong>Requisitos:</strong> ${oferta.Requisitos || 'No especificados'}</p>
                    <p><strong>Salario:</strong> ${salarioTexto}</p> 
                    <p><strong>Ubicación:</strong> ${oferta.Ubicación || 'No especificada'}</p> <!-- Corregido: Ubicación -->
                    <p><strong>Modalidad:</strong> ${oferta.Modalidad || 'N/A'}</p> <!-- Corregido: Modalidad -->
                    <p><strong>Fecha Publicación:</strong> ${oferta.fecha_publicacion ? oferta.fecha_publicacion.split(' ')[0] : 'N/A'}</p>
                    <p><strong>Estado:</strong> ${oferta.estado ? oferta.estado.charAt(0).toUpperCase() + oferta.estado.slice(1) : 'N/A'}</p>
                    <!-- El campo 'Empresa' no está en el JSON proporcionado por tu PHP, así que lo he eliminado. Si lo necesitas, tu PHP debe incluirlo. -->
                `;

                Swal.fire({
                    title: `Detalles de la Oferta: ${oferta.Titulo_Puesto || 'Sin Título'}`,
                    html: htmlContent,
                    icon: 'info',
                    confirmButtonText: 'Cerrar',
                    confirmButtonColor: '#112852',
                    width: '600px', 
                });
            } else {
                Swal.fire('Error', response.message || 'No se pudieron cargar los detalles de la oferta.', 'error');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            Swal.close();
            console.error("Error AJAX al obtener detalles:", textStatus, errorThrown, jqXHR);
            Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor para obtener los detalles. Revisa tu conexión.', 'error');
        }
    });
}
