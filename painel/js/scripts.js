document.addEventListener('DOMContentLoaded', function () {

 $(document).ready(function () {
    // Funções para Submissão de Formulários
    $('#formlogin').submit(function (e) {
        e.preventDefault();

        var alertaId = '';
        var submitButton = '';

        switch ($(this).attr('id')) {
            case 'formlogin':
                alertaId = '#alerta-login';
                submitButton = '#subLogin';
                break;
        }

        // Desativa o botão de envio para evitar múltiplos cliques
        $(submitButton).prop('disabled', true);

        var formData = new FormData(this);

        $.ajax({
            url: $(this).attr('action'),
            data: formData,
            processData: false,
            type: 'POST',
            contentType: false,
            dataType: 'json', // Garante que o retorno será tratado como JSON
            success: function (retorno) {
                if (retorno.status === 'alertasim') {
                    $(alertaId).html(retorno.message).css('display', 'block');

                    // Reseta o formulário após sucesso
                    $('#' + e.target.id).trigger('reset');

                    setTimeout(function () {
                        window.location.href = "/painel/";
                    }, 1500);
                } else if (retorno.status === 'alertanao') {
                    $(alertaId).html(retorno.message).css('display', 'block');
                }
            },
            error: function () {
                $(alertaId).html("<p class='alertanao'>Ocorreu um erro. Tente novamente! <span><i class='fas fa-times'></i></span></p>")
                    .css('display', 'block');
            },
            complete: function () {
                // Reativa o botão após a requisição ser concluída
                $(submitButton).prop('disabled', false);
            }
        });
    });
});

$(function() {
  $('#alerta-login').click(function() {
    closeAlert();
  });
});
 
function closeAlert() {
  $('#alerta-login').fadeOut();
} 

});