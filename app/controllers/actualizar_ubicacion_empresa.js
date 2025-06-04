// Jobtrack_Ucad-main/app/controllers/actualizar_ubicacion_empresa.js

$(function () {
    const selectPais = $("#pais");
    const selectDepartamento = $("#departamento");
    const selectMunicipio = $("#municipio");
    const selectDistrito = $("#distrito");
    const formUbicacionEmpresa = $("#formUbicacionEmpresa");
    const direccionDetallada = $("#direccionDetallada");

    const UBICACION_HANDLER_URL = '../models/actualizar_ubicacion_empresa.php';

    // Función para cargar países
    function cargarPaises() {
        selectPais.html('<option value="">Cargando Países...</option>');
        selectPais.prop('disabled', true);

        $.ajax({
            url: UBICACION_HANDLER_URL,
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_paises' }
        })
        .done(function (response) {
            selectPais.html('<option value="">Seleccione un País</option>');
            if (response.success && response.data && response.data.length > 0) {
                $.each(response.data, function (i, pais) {
                    selectPais.append($('<option>', {
                        value: pais.id_pais,
                        text: pais.nombre_pais
                    }));
                });
            } else if (response.error) {
                console.error('Error al cargar países desde el PHP:', response.error);
                Swal.fire('Error', 'No se pudieron cargar los países: ' + response.error, 'error');
            }
            selectPais.prop('disabled', false);

            // Una vez que los países están cargados, intenta cargar la ubicación existente (si hay)
            // Esto es crucial para que el .val() funcione si ya hay un país seleccionado
            cargarUbicacionActualEmpresa(); 

        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Error AJAX al cargar países:', textStatus, errorThrown);
            Swal.fire('Error', 'No se pudieron cargar los países.', 'error');
            selectPais.html('<option value="">Error al cargar Países</option>');
        });
    }

    // Función para cargar departamentos
    function cargarDepartamentos(paisId, selectedDepartamentoId = null) {
        selectDepartamento.html('<option value="">Cargando Departamentos...</option>');
        selectDepartamento.prop('disabled', true);
        selectMunicipio.html('<option value="">Seleccione un Municipio</option>').prop('disabled', true);
        selectDistrito.html('<option value="">Seleccione un Distrito</option>').prop('disabled', true);


        if (!paisId) {
            selectDepartamento.html('<option value="">Seleccione un País primero</option>');
            return;
        }

        $.ajax({
            url: UBICACION_HANDLER_URL,
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_departamentos', pais_id: paisId }
        })
        .done(function (response) {
            selectDepartamento.html('<option value="">Seleccione un Departamento</option>');
            if (response.success && response.data && response.data.length > 0) {
                $.each(response.data, function (i, departamento) {
                    selectDepartamento.append($('<option>', {
                        value: departamento.id_departamento,
                        text: departamento.nombre_departamento
                    }));
                });
                if (selectedDepartamentoId) {
                    selectDepartamento.val(selectedDepartamentoId);
                    selectDepartamento.trigger('change'); // Para cargar municipios si hay uno seleccionado
                }
            } else if (response.error) {
                console.error('Error al cargar departamentos:', response.error);
                Swal.fire('Error', 'No se pudieron cargar los departamentos: ' + response.error, 'error');
            } else {
                console.log('No se encontraron departamentos para el país seleccionado.');
            }
            selectDepartamento.prop('disabled', false);
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Error AJAX al cargar departamentos:', textStatus, errorThrown);
            Swal.fire('Error', 'No se pudieron cargar los departamentos.', 'error');
            selectDepartamento.html('<option value="">Error al cargar Departamentos</option>');
        });
    }

    // Función para cargar municipios
    function cargarMunicipios(departamentoId, selectedMunicipioId = null) {
        selectMunicipio.html('<option value="">Cargando Municipios...</option>');
        selectMunicipio.prop('disabled', true);
        selectDistrito.html('<option value="">Seleccione un Distrito</option>').prop('disabled', true);


        if (!departamentoId) {
            selectMunicipio.html('<option value="">Seleccione un Departamento primero</option>');
            return;
        }

        $.ajax({
            url: UBICACION_HANDLER_URL,
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_municipios', departamento_id: departamentoId }
        })
        .done(function (response) {
            selectMunicipio.html('<option value="">Seleccione un Municipio</option>');
            if (response.success && response.data && response.data.length > 0) {
                $.each(response.data, function (i, municipio) {
                    selectMunicipio.append($('<option>', {
                        value: municipio.id_municipio,
                        text: municipio.municipio
                    }));
                });
                if (selectedMunicipioId) {
                    selectMunicipio.val(selectedMunicipioId);
                    selectMunicipio.trigger('change'); // Para cargar distritos si hay uno seleccionado
                }
            } else if (response.error) {
                console.error('Error al cargar municipios:', response.error);
                Swal.fire('Error', 'No se pudieron cargar los municipios: ' + response.error, 'error');
            } else {
                console.log('No se encontraron municipios para el departamento seleccionado.');
            }
            selectMunicipio.prop('disabled', false);
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Error AJAX al cargar municipios:', textStatus, errorThrown);
            Swal.fire('Error', 'No se pudieron cargar los municipios.', 'error');
            selectMunicipio.html('<option value="">Error al cargar Municipios</option>');
        });
    }

    // Función para cargar distritos
    function cargarDistritos(municipioId, selectedDistritoId = null) {
        selectDistrito.html('<option value="">Cargando Distritos...</option>');
        selectDistrito.prop('disabled', true);

        if (!municipioId) {
            selectDistrito.html('<option value="">Seleccione un Municipio primero</option>');
            return;
        }

        $.ajax({
            url: UBICACION_HANDLER_URL,
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_distritos', municipio_id: municipioId }
        })
        .done(function (response) {
            selectDistrito.html('<option value="">Seleccione un Distrito</option>');
            if (response.success && response.data && response.data.length > 0) {
                $.each(response.data, function (i, distrito) {
                    selectDistrito.append($('<option>', {
                        value: distrito.id_distrito,
                        text: distrito.nombre_distrito
                    }));
                });
                if (selectedDistritoId) {
                    selectDistrito.val(selectedDistritoId);
                }
            } else if (response.error) {
                console.error('Error al cargar distritos:', response.error);
                Swal.fire('Error', 'No se pudieron cargar los distritos: ' + response.error, 'error');
            } else {
                console.log('No se encontraron distritos para el municipio seleccionado.');
            }
            selectDistrito.prop('disabled', false);
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Error AJAX al cargar distritos:', textStatus, errorThrown);
            Swal.fire('Error', 'No se pudieron cargar los distritos.', 'error');
            selectDistrito.html('<option value="">Error al cargar Distritos</option>');
        });
    }


    // Función para cargar la ubicación existente de la empresa al cargar la página
    async function cargarUbicacionActualEmpresa() {
        try {
            const response = await $.ajax({
                url: UBICACION_HANDLER_URL,
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_perfil_empresa' }
            });

            if (response.success && response.data) {
                const data = response.data;
                // console.log('Datos de perfil de empresa recibidos:', data); // Esta línea ha sido eliminada/comentada

                if (data.id_pais_fk) {
                    // Selecciona el país si ya existe uno guardado
                    selectPais.val(data.id_pais_fk);

                    // Luego, llama a cargarDepartamentos directamente con el ID del país y el ID del departamento pre-seleccionado
                    // No necesitas trigger('change') aquí si estás llamando directamente
                    cargarDepartamentos(data.id_pais_fk, data.id_departamento_fk);

                    // La lógica encadenada para municipio y distrito se manejará dentro de
                    // cargarDepartamentos y cargarMunicipios gracias a los parámetros selectedId.
                    // Esto asume que selectedDepartamentoId y selectedMunicipioId serán pasados y usados.
                }

                direccionDetallada.val(data.direccion_detallada || '');
            } else if (response.error) {
                console.error('Error al cargar la ubicación actual de la empresa:', response.error);
            } else if (response.msg && !response.data) {
                //console.log('Mensaje del servidor sobre perfil de empresa:', response.msg);
            }
        } catch (error) {
            console.error('Error al cargar la ubicación actual de la empresa (catch):', error);
        }
    }

    // Eventos de cambio para los selectores
    selectPais.on('change', function () {
        const paisId = $(this).val();
        if (paisId) {
            cargarDepartamentos(paisId);
        } else {
            selectDepartamento.html('<option value="">Seleccione un Departamento</option>').prop('disabled', true);
            selectMunicipio.html('<option value="">Seleccione un Municipio</option>').prop('disabled', true);
            selectDistrito.html('<option value="">Seleccione un Distrito</option>').prop('disabled', true);
        }
    });

    selectDepartamento.on('change', function () {
        const departamentoId = $(this).val();
        if (departamentoId) {
            cargarMunicipios(departamentoId);
        } else {
            selectMunicipio.html('<option value="">Seleccione un Municipio</option>').prop('disabled', true);
            selectDistrito.html('<option value="">Seleccione un Distrito</option>').prop('disabled', true);
        }
    });

    selectMunicipio.on('change', function () {
        const municipioId = $(this).val();
        if (municipioId) {
            cargarDistritos(municipioId);
        } else {
            selectDistrito.html('<option value="">Seleccione un Distrito</option>').prop('disabled', true);
        }
    });

    // Manejo del envío del formulario
    formUbicacionEmpresa.on('submit', function (e) {
        e.preventDefault(); // Evita el envío tradicional del formulario

        const paisSeleccionado = selectPais.val();
        const departamentoSeleccionado = selectDepartamento.val();
        const municipioSeleccionado = selectMunicipio.val();
        const distritoSeleccionado = selectDistrito.val();
        const direccionDetalladaVal = direccionDetallada.val().trim();

        if (!paisSeleccionado || !departamentoSeleccionado || !municipioSeleccionado || !distritoSeleccionado || !direccionDetalladaVal) {
            Swal.fire('Error', 'Por favor, complete todos los campos de ubicación, incluyendo la dirección detallada.', 'warning');
            return;
        }

        Swal.fire({
            title: '¿Guardar ubicación?',
            text: "¿Estás seguro de que quieres guardar esta ubicación?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData(this); // Captura todos los datos del formulario
                formData.append('action', 'update_ubicacion'); // Añade la acción para el PHP

                $.ajax({
                    url: UBICACION_HANDLER_URL,
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    processData: false, // Necesario para FormData
                    contentType: false  // Necesario para FormData
                })
                .done(function (response) {
                    if (response.success) {
                        Swal.fire('¡Éxito!', response.msg, 'success');
                        // Puedes recargar la ubicación actual para reflejar los cambios
                        cargarUbicacionActualEmpresa();
                    } else {
                        Swal.fire('Error', 'No se pudo guardar la ubicación: ' + (response.error || 'Error desconocido'), 'error');
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    console.error('Error AJAX al guardar la ubicación:', textStatus, errorThrown, jqXHR.responseText);
                    Swal.fire('Error', 'Ocurrió un error al intentar guardar la ubicación.', 'error');
                });
            }
        });
    });

    // Cargar los países al cargar la página (inicio de la cadena)
    cargarPaises();
    // La función cargarUbicacionActualEmpresa() se llama DESPUÉS de que cargarPaises() termina.
});
