// C:\xampp\htdocs\Jobtrack_Ucad\app\controllers\detalle_oferta.js
// Este script es ÚNICAMENTE para la página oferta_detalle.html

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const ofertaId = urlParams.get('id');

    // Referencias a los elementos HTML
    const cardTitleElement = document.querySelector('.card-title');
    const cardSubtitleElement = document.querySelector('.card-subtitle');
    const ubicacionElement = document.querySelector('p:nth-of-type(1)');
    const fechaPublicacionElement = document.querySelector('p:nth-of-type(2)');
    const descripcionElement = document.querySelector('h5:nth-of-type(1) + p');
    const requisitosListElement = document.querySelector('h5:nth-of-type(2) + ul');
    const salarioElement = document.querySelector('h5:nth-of-type(3) + p');
    const modalidadElement = document.querySelector('h5:nth-of-type(4) + p');
    const aplicarLinkElement = document.querySelector('a[href^="aplicar_oferta.html"]');

    if (ofertaId) {
        cargarDetalleOferta(ofertaId);
    } else {
        // Si no hay ID en la URL, mostrar un mensaje de error o redirigir
        console.error('ID de oferta no encontrado en la URL. No se puede cargar el detalle.');
        if (cardTitleElement) cardTitleElement.textContent = 'Error: Oferta no encontrada';
        if (cardSubtitleElement) cardSubtitleElement.textContent = '';
        if (ubicacionElement) ubicacionElement.textContent = 'No se proporcionó un ID de oferta válido.';
        // Opcional: window.location.href = '../views/explorar_oferta.html'; 
    }

    async function cargarDetalleOferta(id) {
        try {
            const response = await fetch(`../../app/models/get_oferta_por_id.php?id=${id}`);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Respuesta no OK del servidor:', response.status, response.statusText, errorText);
                throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success && data.oferta) {
                const oferta = data.oferta;
                
                // Actualizar el título del documento
                document.title = `JobTrack - ${oferta.titulo}`;

                // Actualizar los elementos HTML con los datos de la oferta
                if (cardTitleElement) cardTitleElement.innerHTML = `<i class="fas fa-briefcase me-2"></i> ${oferta.titulo}`;
                if (cardSubtitleElement) cardSubtitleElement.innerHTML = `<i class="fas fa-building me-2" style="color: #112852;"></i> ${oferta.empresa}`;
                if (ubicacionElement) ubicacionElement.innerHTML = `<i class="fas fa-map-marker-alt me-2" style="color: #112852;"></i> ${oferta.ubicacion || 'No especificado'}`; 
                
                const fechaPublicacion = oferta.fecha_publicacion ? new Date(oferta.fecha_publicacion).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Fecha no disponible';
                if (fechaPublicacionElement) fechaPublicacionElement.innerHTML = `<i class="fas fa-calendar-alt me-2" style="color: #112852;"></i> Publicado el: ${fechaPublicacion}`;
                
                if (descripcionElement) descripcionElement.textContent = oferta.descripcion || 'No se proporcionó una descripción para este puesto.';

                // Requisitos
                if (requisitosListElement) {
                    requisitosListElement.innerHTML = ''; // Limpiar requisitos existentes
                    const habilidadesArray = Array.isArray(oferta.habilidad) ? oferta.habilidad : (typeof oferta.habilidad === 'string' ? oferta.habilidad.split(',') : []);

                    if (habilidadesArray.length > 0 && habilidadesArray[0].trim() !== '') { 
                        habilidadesArray.forEach(req => {
                            const li = document.createElement('li');
                            li.innerHTML = `<i class="fas fa-check-circle me-2 text-success"></i> ${req.trim()}`;
                            requisitosListElement.appendChild(li);
                        });
                    } else {
                        const li = document.createElement('li');
                        li.innerHTML = `<i class="fas fa-info-circle me-2 text-muted"></i> No se especificaron requisitos.`;
                        requisitosListElement.appendChild(li);
                    }
                }

                // Rango salarial
                const salarioTexto = (oferta.salario_minimo && oferta.salario_maximo) 
                                    ? `$${parseFloat(oferta.salario_minimo).toFixed(2)} - $${parseFloat(oferta.salario_maximo).toFixed(2)}`
                                    : 'No especificado';
                if (salarioElement) salarioElement.textContent = salarioTexto;

                // Modalidad
                if (modalidadElement) modalidadElement.textContent = oferta.modalidad || 'No especificada';

                // Actualizar el enlace "Aplicar a la Oferta"
                if (aplicarLinkElement) {
                    aplicarLinkElement.href = `aplicar_oferta.html?id=${oferta.id}`;
                }

            } else {
                console.error('Error al cargar detalle de oferta desde el backend:', data.message);
                if (cardTitleElement) cardTitleElement.textContent = 'Oferta no encontrada';
                if (cardSubtitleElement) cardSubtitleElement.textContent = '';
                if (ubicacionElement) ubicacionElement.textContent = data.message;
            }
        } catch (error) {
            console.error('Error en la petición fetch para detalle de oferta:', error);
            if (cardTitleElement) cardTitleElement.textContent = 'Error al cargar la oferta';
            if (cardSubtitleElement) cardSubtitleElement.textContent = '';
            if (ubicacionElement) ubicacionElement.textContent = 'Hubo un problema al intentar obtener los detalles de la oferta. Por favor, inténtalo de nuevo más tarde.';
        }
    }
});