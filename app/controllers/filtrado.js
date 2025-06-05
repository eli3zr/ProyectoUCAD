document.addEventListener('DOMContentLoaded', function() {
    // Selectores para los elementos del HTML
    const filtrarBtn = document.querySelector('.btn-filter-modern');
    const vigenciaFilter = document.getElementById('vigencia-filter');
    const oferenteFilter = document.getElementById('oferente-filter');
    const interesFilter = document.getElementById('interes-filter');
    const habilidadFilter = document.getElementById('habilidad-filter');
    const ofertasContainer = document.querySelector('section:nth-child(2)'); // La sección donde se muestran las ofertas
    const limpiarFiltrosBtn = document.querySelector('.btn-clear-modern');
    const compartirBtn = document.querySelector('.btn-share-modern');

    // Aquí guardaremos todas las ofertas cargadas de la base de datos.
    let ofertasDeEmpleoGlobal = []; 

    // Función para mostrar las ofertas en el HTML
    function mostrarOfertas(ofertasAMostrar) {
        // Limpia el contenedor actual y añade el título.
        ofertasContainer.innerHTML = '<h2 class="mb-3" style="color: #112852;"><i class="fas fa-list me-2"></i> Ofertas de Empleo Encontradas</h2>';

        if (ofertasAMostrar.length === 0) {
            ofertasContainer.innerHTML += '<p>No se encontraron ofertas con los criterios de búsqueda.</p>';
            return;
        }

        // Crea y añade una tarjeta por cada oferta.
        ofertasAMostrar.forEach(oferta => {
            const ofertaCard = document.createElement('div');
            ofertaCard.classList.add('card', 'shadow-sm', 'mb-3'); // Clases de Bootstrap para estilo.
            ofertaCard.innerHTML = `
                <div class="card-body">
                    <h5 class="card-title" style="color: #112852;">${oferta.titulo}</h5>
                    <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-building me-2" style="color: #112852;"></i> ${oferta.empresa}</h6>
                    <p class="card-text">${oferta.descripcion}</p>
                    <p class="card-text"><small class="text-muted">Modalidad: ${oferta.modalidad || 'N/A'}</small></p>
                    <p class="card-text"><small class="text-muted">Ubicación: ${oferta.ubicacion || 'N/A'}</small></p>
                    <p class="card-text"><small class="text-muted">Salario: $${oferta.salario_minimo || 'N/A'} - $${oferta.salario_maximo || 'N/A'}</small></p>
                    <p class="card-text"><small class="text-muted">Habilidades: ${Array.isArray(oferta.habilidad) ? oferta.habilidad.join(', ') : 'N/A'}</small></p>
                    <a href="${oferta.linkDetalle}" class="btn btn-outline" style="color: #112852; border-color: #112852;">Ver más <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            `;
            ofertasContainer.appendChild(ofertaCard);
        });
    }

    // Función para cargar ofertas desde tu script PHP (backend)
    async function cargarOfertasDesdeBackend() {
        try {
            // **IMPORTANTE: Esta es la URL relativa correcta para tu estructura de carpetas**
            // Desde 'explorar_oferta.html' (en 'views/'), sube un nivel (a 'app/') 
            // y luego baja a 'models/get_ofertas_publicas.php'.
            const url = '../models/get_ofertas_publicas.php'; 
            
            const response = await fetch(url);
            
            // Verifica si la petición fue exitosa (código 200 OK)
            if (!response.ok) {
                // Intenta leer el cuerpo del error si es posible para depuración
                const errorText = await response.text(); 
                throw new Error(`Error HTTP! Estado: ${response.status}. Detalle: ${errorText}`);
            }

            // Convierte la respuesta a JSON
            const data = await response.json();

            // Si el backend reporta éxito y tiene ofertas...
            if (data.success && data.ofertas) {
                ofertasDeEmpleoGlobal = data.ofertas; // Guarda todas las ofertas cargadas
                mostrarOfertas(ofertasDeEmpleoGlobal); // Muestra todas las ofertas inicialmente
                popularFiltros(data.ofertas); // Rellena los filtros con datos de las ofertas.
            } else {
                // Si no hay éxito o no hay ofertas (pero la petición fue 200 OK)
                ofertasContainer.innerHTML += `<p>${data.message || 'No se pudieron obtener ofertas.'}</p>`;
                console.error('Error al cargar ofertas desde el backend:', data.message);
            }
        } catch (error) {
            // Maneja errores de red o de parseo de JSON
            ofertasContainer.innerHTML += '<p class="text-danger">Error al conectar con el servidor para obtener ofertas. Inténtalo de nuevo más tarde.</p>';
            console.error('Error en la petición fetch:', error);
        }
    }

    // Función para rellenar los selects de los filtros (oferente, interés, habilidad)
    function popularFiltros(ofertas) {
        // Oferentes
        // Usamos Set para obtener valores únicos y luego sort para ordenarlos
        const oferentesUnicos = [...new Set(ofertas.map(oferta => oferta.oferente))].sort();
        oferenteFilter.innerHTML = '<option value="">Todos</option>';
        oferentesUnicos.forEach(oferente => {
            oferenteFilter.innerHTML += `<option value="${oferente}">${oferente}</option>`;
        });

        // Intereses
        const interesesUnicos = [...new Set(ofertas.map(oferta => oferta.interes))].sort();
        interesFilter.innerHTML = '<option value="">Todos</option>';
        interesesUnicos.forEach(interes => {
            interesFilter.innerHTML += `<option value="${interes}">${interes}</option>`;
        });

        // Habilidades (aplanar el array de arrays de habilidades a un solo array, luego únicos y ordenar)
        const habilidadesUnicas = [...new Set(ofertas.flatMap(oferta => Array.isArray(oferta.habilidad) ? oferta.habilidad : []))].sort();
        habilidadFilter.innerHTML = '<option value="">Todas</option>';
        habilidadesUnicas.forEach(habilidad => {
            if (habilidad && habilidad.trim() !== '') { // Evita agregar elementos vacíos
                habilidadFilter.innerHTML += `<option value="${habilidad}">${habilidad}</option>`;
            }
        });
    }

    // Lógica para filtrar las ofertas basadas en la selección del usuario
    function aplicarFiltros() {
        const vigenciaSeleccionada = vigenciaFilter.value;
        const oferenteSeleccionado = oferenteFilter.value;
        const interesSeleccionado = interesFilter.value;
        const habilidadSeleccionada = habilidadFilter.value.toLowerCase();

        const ofertasFiltradas = ofertasDeEmpleoGlobal.filter(oferta => {
            const cumpleVigencia = !vigenciaSeleccionada || oferta.vigencia === vigenciaSeleccionada;
            const cumpleOferente = !oferenteSeleccionado || oferta.oferente === oferenteSeleccionado;
            const cumpleInteres = !interesSeleccionado || oferta.interes === interesSeleccionado;
            // Verifica que oferta.habilidad sea un array antes de intentar usar map/includes
            const cumpleHabilidad = !habilidadSeleccionada || (Array.isArray(oferta.habilidad) && oferta.habilidad.map(h => h.toLowerCase()).includes(habilidadSeleccionada));

            return cumpleVigencia && cumpleOferente && cumpleInteres && cumpleHabilidad;
        });

        mostrarOfertas(ofertasFiltradas);
    }

    // Event listeners para los botones
    filtrarBtn.addEventListener('click', aplicarFiltros);

    limpiarFiltrosBtn.addEventListener('click', function() {
        // Restablece los filtros a "Todos"
        vigenciaFilter.value = '';
        oferenteFilter.value = '';
        interesFilter.value = '';
        habilidadFilter.value = '';
        mostrarOfertas(ofertasDeEmpleoGlobal); // Muestra todas las ofertas cargadas inicialmente
    });

    compartirBtn.addEventListener('click', function() {
        if (navigator.share) {
            navigator.share({
                title: 'JobTrack - Ofertas de Empleo',
                text: '¡Echa un vistazo a las últimas ofertas de empleo en JobTrack!',
                url: window.location.href 
            }).then(() => {
                console.log('Contenido compartido exitosamente');
            }).catch((error) => {
                console.error('Error al compartir', error);
                alert('No se pudo compartir en este momento.');
            });
        } else {
            alert('La función de compartir no está disponible en este navegador.');
            console.log('Web Share API no soportada.');
        }
    });

    // Llama a esta función cuando la página se carga para mostrar las ofertas.
    // Esta función reemplazará tu lista 'ofertasDeEmpleo' estática.
    cargarOfertasDesdeBackend();
});