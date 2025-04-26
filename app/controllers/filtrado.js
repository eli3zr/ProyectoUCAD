document.addEventListener('DOMContentLoaded', function() {
    const filtrarBtn = document.querySelector('.btn-filter-modern');
    const vigenciaFilter = document.getElementById('vigencia-filter');
    const oferenteFilter = document.getElementById('oferente-filter');
    const interesFilter = document.getElementById('interes-filter');
    const habilidadFilter = document.getElementById('habilidad-filter');
    const ofertasContainer = document.querySelector('section:nth-child(2)'); // Selecciona la sección donde se muestran las ofertas
    const limpiarFiltrosBtn = document.querySelector('.btn-clear-modern');
    const compartirBtn = document.querySelector('.btn-share-modern');

    const ofertasDeEmpleo = [
        {
            titulo: "Desarrollador Web Junior",
            empresa: "Empresa Ejemplo S.A.",
            vigencia: "activo",
            oferente: "Empresa Ejemplo S.A.",
            interes: "tecnologia",
            habilidad: ["JavaScript", "HTML", "CSS"],
            descripcion: "Breve descripción del puesto...",
            linkDetalle: "../views/oferta_detalle.html"
        },
        {
            titulo: "Diseñador Gráfico (Prácticas)",
            empresa: "Otra Empresa Inc.",
            vigencia: "permanente",
            oferente: "Otra Empresa Inc.",
            interes: "diseño",
            habilidad: ["Diseño UI/UX", "Adobe Photoshop"],
            descripcion: "Pequeño resumen del puesto...",
            linkDetalle: "../views/oferta_detalle.html"
        },
        {
            titulo: "Analista de Datos Junior",
            empresa: "Datos Inteligentes Corp.",
            vigencia: "activo",
            oferente: "Datos Inteligentes Corp.",
            interes: "analisis",
            habilidad: ["Excel", "SQL", "Python"],
            descripcion: "Descripción de las tareas del analista...",
            linkDetalle: "../views/oferta_detalle.html"
        },
        // ... más ofertas ...
    ];

    function mostrarOfertas(ofertas) {
        // Limpiar el contenedor de ofertas actual
        ofertasContainer.innerHTML = '<h2 class="mb-3" style="color: #112852;"><i class="fas fa-list me-2"></i> Ofertas de Empleo Encontradas</h2>';

        if (ofertas.length === 0) {
            ofertasContainer.innerHTML += '<p>No se encontraron ofertas con los criterios de búsqueda.</p>';
            return;
        }

        // Renderizar las ofertas filtradas
        ofertas.forEach(oferta => {
            const ofertaCard = document.createElement('div');
            ofertaCard.classList.add('card', 'shadow-sm', 'mb-3');
            ofertaCard.innerHTML = `
                <div class="card-body">
                    <h5 class="card-title" style="color: #112852;">${oferta.titulo}</h5>
                    <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-building me-2" style="color: #112852;"></i> ${oferta.empresa}</h6>
                    <p class="card-text">${oferta.descripcion}</p>
                    <a href="${oferta.linkDetalle}" class="btn btn-outline" style="color: #112852; border-color: #112852;">Ver más <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            `;
            ofertasContainer.appendChild(ofertaCard);
        });
    }

    filtrarBtn.addEventListener('click', function() {
        const vigenciaSeleccionada = vigenciaFilter.value;
        const oferenteSeleccionado = oferenteFilter.value;
        const interesSeleccionado = interesFilter.value;
        const habilidadSeleccionada = habilidadFilter.value.toLowerCase(); // Convertir a minúsculas para búsqueda sin distinción de mayúsculas

        const ofertasFiltradas = ofertasDeEmpleo.filter(oferta => {
            const cumpleVigencia = !vigenciaSeleccionada || oferta.vigencia === vigenciaSeleccionada;
            const cumpleOferente = !oferenteSeleccionado || oferta.oferente === oferenteSeleccionado;
            const cumpleInteres = !interesSeleccionado || oferta.interes === interesSeleccionado;
            const cumpleHabilidad = !habilidadSeleccionada || oferta.habilidad.map(h => h.toLowerCase()).includes(habilidadSeleccionada);

            return cumpleVigencia && cumpleOferente && cumpleInteres && cumpleHabilidad;
        });

        mostrarOfertas(ofertasFiltradas);
    });

    limpiarFiltrosBtn.addEventListener('click', function() {
        vigenciaFilter.value = '';
        oferenteFilter.value = '';
        interesFilter.value = '';
        habilidadFilter.value = '';
        mostrarOfertas(ofertasDeEmpleo); // Mostrar todas las ofertas nuevamente
    });

    compartirBtn.addEventListener('click', function() {
        if (navigator.share) {
            navigator.share({
                title: 'JobTrack - Ofertas de Empleo',
                text: '¡Echa un vistazo a las últimas ofertas de empleo en JobTrack!',
                url: window.location.href // Compartir la URL actual de la página de ofertas
            }).then(() => {
                console.log('Contenido compartido exitosamente');
            }).catch((error) => {
                console.error('Error al compartir', error);
                alert('No se pudo compartir en este momento.');
            });
        } else {
            alert('La función de compartir no está disponible en este navegador.');
            console.log('Web Share API no soportada.');
            // Aquí podrías ofrecer otras opciones de compartir si lo deseas (copiar enlace, etc.)
        }
    });

    // Mostrar todas las ofertas al cargar la página
    mostrarOfertas(ofertasDeEmpleo);
});