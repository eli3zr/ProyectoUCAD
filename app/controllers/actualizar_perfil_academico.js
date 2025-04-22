$(function () {
    $("#formInformacionAcademica").on("submit", function (e) {
        e.preventDefault();

        let datos = {
            carrera: $("#carrera").val().trim(),
            anioGraduacion: $("#anioGraduacion").val().trim()
        };

        $.ajax({
            url: '../../app/models/actualizar_perfil_academico.php', 
            type: 'POST',
            dataType: 'json',
            data: datos,
            beforeSend: function () {
                Swal.showLoading();
            }
        })
        .done(function (response) {
            Swal.close(); 

            if (response.success) {
                Swal.fire({
                    title: 'Éxito',
                    text: response.msg,
                    icon: 'success',
                    confirmButtonText: '¡Genial!'
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
        .fail(function () {
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: 'No se pudo conectar con el servidor.',
                icon: 'error'
            });
        });
    });
});