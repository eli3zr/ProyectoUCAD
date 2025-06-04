// App/controllers/registro_empresa.js

$(function () {
    const urlBase = '../models/registrar_empresa.php'; // Agregamos la 's' final para que coincida con tu archivo PHP

    // --- Funciones para la carga de datos (Categorías y Ubicaciones) ---

    /**
     * Carga las categorías de la base de datos y las añade al select de categorías.
     */
    function loadCategorias() {
        $.ajax({
            url: urlBase,
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_categorias' },
            success: function (data) {
                $('#categoria').empty();
                $('#categoria').append('<option value="">Seleccionar</option>');
                if (data && data.length > 0) {
                    $.each(data, function (key, categoria) {
                        $('#categoria').append('<option value="' + categoria.id_categoria + '">' + categoria.Nombre_Categoria + '</option>');
                    });
                } else {
                    $('#categoria').append('<option value="">No hay categorías disponibles</option>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Error AJAX al cargar categorías:", textStatus, errorThrown, jqXHR.responseText);
                $('#categoria').empty().append('<option value="">Error al cargar</option>');
            }
        });
    }

    /**
     * Carga los departamentos basados en un ID de país.
     * Limpia y deshabilita los selects de municipio y distrito.
     * @param {string|number} paisId - El ID del país seleccionado.
     */
    function loadDepartamentos(paisId) {
        $('#departamento').empty().prop('disabled', true).append('<option value="">Seleccionar</option>');
        $('#municipio').empty().prop('disabled', true).append('<option value="">Seleccionar</option>');
        $('#distrito').empty().prop('disabled', true).append('<option value="">Seleccionar</option>');

        if (paisId) {
            $.ajax({
                url: urlBase,
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_departamentos', paisId: paisId },
                success: function (data) {
                    if (data && data.length > 0) {
                        $.each(data, function (key, departamento) {
                            $('#departamento').append('<option value="' + departamento.id_departamento + '">' + departamento.nombre_departamento + '</option>');
                        });
                        $('#departamento').prop('disabled', false);
                    } else {
                        $('#departamento').append('<option value="">No hay departamentos disponibles</option>');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("Error AJAX al cargar departamentos:", textStatus, errorThrown, jqXHR.responseText);
                    $('#departamento').append('<option value="">Error al cargar</option>');
                }
            });
        }
    }

    /**
     * Carga los municipios basados en un ID de departamento.
     * Limpia y deshabilita el select de distrito.
     * @param {string|number} departamentoId - El ID del departamento seleccionado.
     */
    function loadMunicipios(departamentoId) {
        $('#municipio').empty().prop('disabled', true).append('<option value="">Seleccionar</option>');
        $('#distrito').empty().prop('disabled', true).append('<option value="">Seleccionar</option>');

        if (departamentoId) {
            $.ajax({
                url: urlBase,
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_municipios', departamentoId: departamentoId },
                success: function (data) {
                    if (data && data.length > 0) {
                        $.each(data, function (key, municipio) {
                            $('#municipio').append('<option value="' + municipio.id_municipio + '">' + municipio.municipio + '</option>');
                        });
                        $('#municipio').prop('disabled', false);
                    } else {
                        $('#municipio').append('<option value="">No hay municipios disponibles</option>');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("Error AJAX al cargar municipios:", textStatus, errorThrown, jqXHR.responseText);
                    $('#municipio').append('<option value="">Error al cargar</option>');
                }
            });
        }
    }

    /**
     * Carga los distritos basados en un ID de municipio.
     * @param {string|number} municipioId - El ID del municipio seleccionado.
     */
    function loadDistritos(municipioId) {
        $('#distrito').empty().prop('disabled', true).append('<option value="">Seleccionar</option>');

        if (municipioId) {
            $.ajax({
                url: urlBase,
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_distritos', municipioId: municipioId },
                success: function (data) {
                    if (data && data.length > 0) {
                        $.each(data, function (key, distrito) {
                            $('#distrito').append('<option value="' + distrito.id_distrito + '">' + distrito.nombre_distrito + '</option>');
                        });
                        $('#distrito').prop('disabled', false);
                    } else {
                        $('#distrito').append('<option value="">No hay distritos disponibles</option>');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("Error AJAX al cargar distritos:", textStatus, errorThrown, jqXHR.responseText);
                    $('#distrito').append('<option value="">Error al cargar</option>');
                }
            });
        }
    }

    // --- Eventos y Carga Inicial ---

    loadCategorias(); // Carga las categorías al inicio
    const initialPaisId = $('#pais').val();
    if (initialPaisId) {
        loadDepartamentos(initialPaisId);
    }

    $('#pais').on('change', function () {
        const paisId = $(this).val();
        loadDepartamentos(paisId);
    });

    $('#departamento').on('change', function () {
        const departamentoId = $(this).val();
        loadMunicipios(departamentoId);
    });

    $('#municipio').on('change', function () {
        const municipioId = $(this).val();
        loadDistritos(municipioId);
    });

    // --- Lógica de Envío del Formulario de Registro ---

    $("#registroEmpresaForm").on("submit", function (e) {
        e.preventDefault();

        // Validaciones de Bootstrap 5
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        } else {
            $(this).removeClass('was-validated');
        }

        // Construcción de datos del formulario
        let datos = {
            action: 'registro_empresa',
            nombre: $("#nombre").val().trim(),
            telefono: $("#telefono").val().trim(),
            email: $("#email").val().trim(),
            // Aseguramos que los valores de los selects sean numéricos o vacíos
            categoria: parseInt($("#categoria").val()) || '',
            pais: parseInt($("#pais").val()) || '',
            departamento: parseInt($("#departamento").val()) || '',
            municipio: parseInt($("#municipio").val()) || '',
            distrito: parseInt($("#distrito").val()) || '',
            clave: $("#clave").val(),
            repetirClave: $("#repetir-clave").val(),
            terminos: $("#terminos").prop("checked") ? 'true' : 'false',
            notificaciones: $("#notificaciones").prop("checked") ? 'true' : 'false'
        };

        if (datos.clave !== datos.repetirClave) {
            Swal.fire({
                icon: 'error',
                title: 'Error de validación',
                text: 'Las claves no coinciden. Por favor, verifica.',
            });
            $('#clave').addClass('is-invalid');
            $('#repetir-clave').addClass('is-invalid');
            return;
        } else {
            $('#clave').removeClass('is-invalid');
            $('#repetir-clave').removeClass('is-invalid');
        }

        $.ajax({
            url: urlBase,
            type: 'POST',
            dataType: 'json',
            data: datos, // Aquí se envía el objeto 'datos'
            beforeSend: function () {
                Swal.showLoading();
            }
        })
            .done(function (response) {
                Swal.close();

                if (response.success) {
                    $("#registroEmpresaForm")[0].reset();
                    loadCategorias(); // Recargar categorías al limpiar el formulario
                    // Nota: el país está fijo en el HTML, pero si se deshabilitara/habilitara dinámicamente, esto sería útil
                    loadDepartamentos($('#pais').val()); // Recargar ubicaciones al limpiar el formulario

                    Swal.fire({
                        title: 'Éxito',
                        text: response.msg,
                        icon: 'success',
                        confirmButtonText: '¡Genial!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'login.html';
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.error,
                        icon: 'error',
                        confirmButtonText: 'Entendido'
                    });
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                Swal.close();
                console.error("Error en la petición AJAX:", textStatus, errorThrown, jqXHR.responseText);
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudo conectar con el servidor o hubo un error en la respuesta. Por favor, revisa la consola para más detalles.',
                    icon: 'error'
                });
            });
    });
});