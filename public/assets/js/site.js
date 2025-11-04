$( document ).ready(function(){
    /* Autocompletar */
    $(".usua-nombre").select2({
        minimumInputLength: 3,
        ajax: {
            url: $("input#baseUrl").val() + '/backend/usuarios/xa/1',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
              return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/usuarios/xa/2', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    $(".empr-nombre").select2({
        minimumInputLength: 3,
        ajax: {
            url: $("input#baseUrl").val() + '/backend/empresas/xa/1',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
              return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/empresas/xa/2', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    $(".curs-nombre").select2({
        minimumInputLength: 3,
        ajax: {
            url: $("input#baseUrl").val() + '/backend/formacion/xa/1',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
              return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/formacion/xa/2', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    $(".insc-nombre").select2({
        minimumInputLength: 3,
        ajax: {
            url: $("input#baseUrl").val() + '/backend/formacion/xa/3',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
              return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/formacion/xa/4', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    $(".ofer-nombre").select2({
        minimumInputLength: 3,
        ajax: {
            url: $("input#baseUrl").val() + '/backend/empleo/xa/1',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
              return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/empleo/xa/2', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    $(".cand-nombre").select2({
        minimumInputLength: 3,
        ajax: {
            url: $("input#baseUrl").val() + '/backend/empleo/xa/3',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
              return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/empleo/xa/4', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    $("#cate-nombre").select2({
        ajax: {
            url: $("input#baseUrl").val() + '/backend/formacion/xa/5',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
                return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/formacion/xa/6', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    $("#cate-nombre-formacion").select2({
        ajax: {
            url: $("input#baseUrl").val() + '/backend/formacion/xa/5/1',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
                return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/formacion/xa/6', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    $("#cate-nombre-empleo").select2({
        ajax: {
            url: $("input#baseUrl").val() + '/backend/formacion/xa/5/2',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
                return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/formacion/xa/6', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    $("#sec-nombre").select2({
        ajax: {
            url: $("input#baseUrl").val() + '/backend/empleo/xa/5',
            type:'GET',
            dataType: 'json',
            data: function (term, page) {
                return {
                    q: term
                };
            },
            results: function (data, page) {
              return { results: data };
            }
        },
        initSelection: function (element, callback) {
            var id = $(element).val();
            if (id !== "") {
                $.ajax($("input#baseUrl").val() + '/backend/empleo/xa/6', {
                    data: {q: id},
                    dataType: "json"
                }).done(function (data) {
                    $.each(data, function (i, value) {
                        callback({"text": value.text, "id": value.id});
                    });
                    ;
                });
            }
        }
    });
    /* Adjuntar un numero ilimitado de archivos*/
    $(".custom-file-input").change(function(){
        var nombre_archivo = this.value.split("\\").pop();
        var code = $(this).data('code') + 1;
        var name = $(this).prop('name');
        newfile(this,nombre_archivo,code,name);
    });
    /* JS de sistema: fechas, select2, tooltip, focus para ventanas modales, etc. */
    $.fn.datepicker.dates["es"] = {
        days: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
        daysShort: ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "S´b"],
        daysMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sá"],
        months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
        monthsShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
        today: "Hoy",
        clear: "Limpiar",
        format: "dd-mm-yyyy",
        titleFormat: "MM yyyy",
        weekStart: 1
    };
    $('.datepicker').datepicker( { language:"es",autoclose:true } );
    $( ".select2" ).select2( { } );
    $('#tabList').tabCollapse(); 
    $('[data-toggle="tooltip"],[data-toggle="modal"]').tooltip();
    $('.modal').on('shown.bs.modal', function() {
        $('input:first',this).trigger('focus');
    });

    $('#add-child-form').submit(function(event){
        event.preventDefault();

        var html = '<tr><td class="nombre"></td><td class="apellidos"></td><td class="nacimiento"></td><td class="observaciones"></td><td class="text-center minors-actions"></td></tr>';
        $( "#participants-container tbody" ).append(html);
        var name = $(this).find('> div > div > div:first-child').find('input#nombre').clone();
        name.appendTo("#participants-container tbody tr:last-child td.nombre");
        var apellidos = $(this).find('> div > div > div:first-child').find('input#apellidos').clone();
        apellidos.appendTo("#participants-container tbody tr:last-child td.apellidos");
        var nacimiento = $(this).find('> div > div > div:first-child').find('input#nacimiento').clone();
        nacimiento.datepicker( { language:"es" } );
        nacimiento.appendTo("#participants-container tbody tr:last-child td.nacimiento");
        var observaciones = $(this).find('> div > div > div:first-child').find('input#observaciones').clone();
        observaciones.appendTo("#participants-container tbody tr:last-child td.observaciones");
        var remove_child = $(this).find('> div > div > div:first-child').find('a.remove-child').clone();
        remove_child.appendTo("#participants-container tbody tr:last-child td.minors-actions");

        $('.remove-child').on('click', function(e){
             e.preventDefault();
             $(this).closest('tr').remove();
             if($( "#participants-container tbody tr" ).length == 0){
                 $( "#participants-container" ).addClass('d-none');
             }
        });

        if($( "#participants-container" ).hasClass('d-none')){
            $( "#participants-container" ).removeClass('d-none');
        }
        $(this).find('input, textarea').each(function(){
            $(this).val('')
        });
        $('#add-child .close').trigger('click');
    });
    
    $('#add-employee-form').submit(function(event){
        event.preventDefault();

        var email = $(this).find('input#email').val();
        var nif = $(this).find('input#nif').val();
        element = $(this);
        $.ajax({
            url: $("input#baseUrl").val() + '/backend/inscripciones/xa/1',
            type: 'GET',
            data: { email : email, nif : nif },
            dataType: 'json',
            beforeSend: function (jqXHR, settings) {
                element.find('button#add-employee').attr("disabled", true);
                element.find('#message-nif').addClass('d-none');
                element.find('#message-email').addClass('d-none');
            },
            complete: function (jqXHR, textStatus) {
                element.find('button#add-employee').attr("disabled", false);
            },
            success: function (data, textStatus, jqXHR) {
                console.log(data);
                if(data.status == 0){
                   rellenaTrabajador(element);
                }else if(data.status == 1){
                    element.find('#message-email').removeClass('d-none');
                }else if(data.status == 2){
                    element.find('#message-nif').removeClass('d-none');
                }else if(data.status == 3){
                    element.find('#message-nif').removeClass('d-none');
                    element.find('#message-email').removeClass('d-none');
                }
            }
        });


    });
    function rellenaTrabajador(element){
        $('#participants-container').append('<div class="form-group" ><input class="chkToggle2" type="checkbox" data-toggle="toggle" data-on="<i class=\'fa fa-check\'></i>" data-off="<i class=\'fa fa-times\'></i>" data-onstyle="success" data-offstyle="light" data-size="sm" name="id_usu_new[]" id="id_usu_new[]" checked /><label for="id_usu[]"> ' + element.find('input#nombre').val() + ' ' + element.find('input#apellidos').val() + '</label></div>')
        $('.chkToggle2').bootstrapToggle();
        var clone = element.find('> div > div > div:first-child').clone().addClass('d-none');
        clone.find('#sitcol').val(element.find('#sitcol').val());
        clone.find('#sitlab').val(element.find('#sitcol').val());
        clone.find('#sexo').val(element.find('#sitcol').val());
        clone.appendTo( "#participants-container" );

        element.find('input, textarea').each(function(){
            $(this).val('')
        });

        $('#add-employee .close').trigger('click');
    }
    
    $('#cambia_clave1').modal('show');

    $('.modal').on('shown.bs.modal', function() {
        $('input:first',this).trigger('focus');
    });
    
    $('.presentar-candidatura').click(function() {
        var code = $(this).data('code');
        $('#ver-oferta-'+code).modal('toggle');
        $('#presentar-candidatura-'+code).modal('show');
    });
    
    if($('#oferta_seleccionada').val() > 0){
        var code = $('#oferta_seleccionada').val();
        $('#ver-oferta-'+code).modal('show');
    }

    $('#check-assistance').click(function(event){
        $('#assistance').submit();
    })


    $('button.generar-certificados').click(function(event){
        event.preventDefault();
        if($('input.id_ui_generar:checkbox:checked').length == 0 && $('input.id_ui_enviar:checkbox:checked').length == 0){
            alert('Debe marcar alguna inscripción para poder generar o enviar el/los certificado/s.');
        }else{
            $('#generar-certificados .d-none').html($('input.id_ui_generar:checkbox:checked').clone());
            $('#generar-certificados .d-none').append($('input.id_ui_enviar:checkbox:checked').clone());
            $('#generar-certificados').submit();
        }
    })
});

function newfile(node,nombre_archivo,code,name){
    $(node).prev().text(nombre_archivo).addClass('btn-success').removeClass('btn-secondary');
    var nuevo_boton = '<label class="custom-file col-12 col-md-2 mb-2"><span class="custom-file-control form-control-file btn btn-secondary">Adjuntar</span><input type="file" class="custom-file-input d-none" id="q'+code+'" name="'+name+'" aria-describedby="fileHelp" data-code="'+code+'"></label>';
    var row = $(node).parent().parent();
    row.append(nuevo_boton);
    $("#q"+code).change(function(){
        var nombre_archivo = this.value.split("\\").pop();
        var code = $(this).data('code') + 1;
        var name = $(this).prop('name');
        newfile(this,nombre_archivo,code,name);
    });
}
