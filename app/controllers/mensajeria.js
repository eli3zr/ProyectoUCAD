$(function () {
    const conversacionesLista = $('.list-group-flush a');
    const chatTitulo = $('.card-header i.fa-comments').next();
    const chatArea = $('.card-body[style*="overflow-y: auto;"]');
    const mensajeInput = $('.card-footer textarea');
    const enviarBtn = $('.card-footer button[type="button"]');

    let contactoSeleccionado = null;


    conversacionesLista.on('click', function (e) {
        e.preventDefault();
        contactoSeleccionado = $(this).text().trim().split('\n')[0];
        chatTitulo.text(`Chat con ${contactoSeleccionado}`);
        chatArea.empty();
        chatArea.append('<div class="d-flex justify-content-start mb-2"><div class="bg-light p-2 rounded-pill text-muted small">Mensaje de ejemplo al cambiar conversación.</div></div>');
        chatArea.append('<div class="d-flex justify-content-end mb-2"><div class="bg-primary text-white p-2 rounded-pill small">Respuesta de ejemplo.</div></div>');
    });


    $("form#enviarMensajeForm").on("submit", function (e) {
        e.preventDefault();

        const mensaje = mensajeInput.val().trim();

        if (mensaje !== '' && contactoSeleccionado) {
            let datos = {
                contacto: contactoSeleccionado,
                mensaje: mensaje
            };

            $.ajax({
                url: '../../app/models/enviar_mensaje.php',
                type: 'POST',
                dataType: 'json',
                data: datos,
                beforeSend: function () {
                    Swal.showLoading();
                }
            })
                .done(function (response) {
                    console.log("Respuesta del servidor:", response);
                    Swal.close();

                    if (response.success) {

                        const mensajeUsuarioDiv = $('<div class="d-flex justify-content-end mb-2"></div>');
                        mensajeUsuarioDiv.append(`<div class="bg-primary text-white p-2 rounded-pill small">${mensaje}</div>`);
                        chatArea.append(mensajeUsuarioDiv);
                        mensajeInput.val('');


                        setTimeout(function () {
                            const respuestaSimulada = generarRespuestaSimulada();
                            const mensajeOtroDiv = $('<div class="d-flex justify-content-start mb-2"></div>');
                            mensajeOtroDiv.append(`<div class="bg-light p-2 rounded-pill text-muted small">${respuestaSimulada}</div>`);
                            chatArea.append(mensajeOtroDiv);
                            chatArea.scrollTop(chatArea[0].scrollHeight);
                        }, 500);

                        chatArea.scrollTop(chatArea[0].scrollHeight);

                        Swal.fire({
                            icon: 'success',
                            title: 'Mensaje Enviado',
                            showConfirmButton: false,
                            timer: 1000
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error al Enviar',
                            text: response.error || 'No se pudo enviar el mensaje.'
                        });
                    }
                })
                .fail(function () {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Conexión',
                        text: 'No se pudo conectar con el servidor.'
                    });
                });
        } else if (!contactoSeleccionado) {
            Swal.fire({
                icon: 'warning',
                title: 'Selecciona un Contacto',
                text: 'Por favor, selecciona una conversación de la lista.'
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Mensaje Vacío',
                text: 'Por favor, escribe un mensaje antes de enviarlo.'
            });
        }
    });

    $('.card-footer').wrap('<form id="enviarMensajeForm"></form>');


    mensajeInput.on('keypress', function (e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            $("form#enviarMensajeForm").submit();
        }
    });

    function generarRespuestaSimulada() {
        const respuestas = [
            "Entiendo.",
            "Ok.",
            "¿Algo más en lo que pueda ayudarte?",
            "Recibido.",
            "Gracias."
        ];
        const indice = Math.floor(Math.random() * respuestas.length);
        return respuestas[indice];
    }
});