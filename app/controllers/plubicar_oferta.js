$(function () {
    $("form").on("submit", function (e) {
        e.preventDefault(); // Evita recargar la página

        let datos = {
            nombrePuesto: $("#nombrePuesto").val().trim(),
            descripcion: $("#descripcion").val().trim(),
            requisitos: $("#requisitos").val().trim(),
            salarioMinimo: $("#salarioMinimo").val(),
            salarioMaximo: $("#salarioMaximo").val(),
            modalidad: $("#modalidad").val()
        };

        $.ajax({
            url: '../../app/models/guardar_oferta.php',
            type: 'POST',
            dataType: 'json',
            data: datos,
            beforeSend: function () {
                Swal.showLoading();
            }
        })
        .done(function (response) {
            if (response.success) {
                $("form")[0].reset();
                Swal.fire({
                    title: 'Éxito',
                    text: response.msg,
                    icon: 'success'
                });
            } else {
                Swal.fire({
                    title: 'Atención',
                    text: response.error,
                    icon: 'info'
                });
            }
        })
        .fail(function () {
            Swal.fire({
                title: 'Error',
                text: 'No se pudo conectar con el servidor.',
                icon: 'error'
            });
        });
    });
});
