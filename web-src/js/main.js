var $ = require('jquery');
// JS is equivalent to the normal "bootstrap" package
// no need to set this to a variable, just require it
require('bootstrap-sass');
// require('tabcordion');
// require('jquery-querybuilder')


// or you can include specific pieces
// require('bootstrap-sass/javascripts/bootstrap/tooltip');
// require('bootstrap-sass/javascripts/bootstrap/popover');

$(document).ready(function() {
    $('[data-toggle="popover"]').popover();

    // from https://codepen.io/georgeroubie/pen/dpryjp
    $.expr[':'].containsCaseInsensitive = function (n, i, m) {
        return jQuery(n).text().toUpperCase().indexOf(m[3].toUpperCase()) >= 0;
    };

    //
    // Dictonary block
    //


    // Bind to modal opening to set necessary data properties to be used to make request
    $('#dict-confirm-delete').on('show.bs.modal', function(e) {
        var data = $(e.relatedTarget).data();
        $('.title', this).text(data.recordId);
        $('.btn-ok', this).data('recordId', data.recordId);
    });

    $('#dict-confirm-delete').on('click', '.btn-ok', function(e) {
        var $modalDiv = $(e.delegateTarget);
        var id = $(this).data('recordId');

        // $.ajax({url: '/api/record/' + id, type: 'DELETE'})
        // $.post('/api/record/' + id).then()
        $modalDiv.addClass('loading');
        setTimeout(function() {
            $modalDiv.modal('hide').removeClass('loading');
        }, 1000)
    });

    $('#dictonary_search_bar').on('change keyup paste click', function () {
        var searchTerm, panelContainerId;
        searchTerm = $(this).val().trim();
        console.log(searchTerm);
        $('#accordion_dictonary > .panel').each(function () {
            panelContainerId = '#' + $(this).attr('id');
            $(panelContainerId + ':not(:containsCaseInsensitive(' + searchTerm + '))').hide();
            $(panelContainerId + ':containsCaseInsensitive(' + searchTerm + ')').show();
        });
    });

    // Dynamic content manipulation

    $.fn.dict_element_add = function(name_param, value_param)
    {
        var name = name_param;
        if(name.length == 0)
        {
            return; // do nothing empty name
        }
        else {
            if($('#wrd-'+name).length)
            {
                return; // already exists
            }
        }

        var $template = $("#dictonary_element_template");
        var $newElem = $template.clone();
        $newElem.attr('id','acd_0');
        $($newElem).find('.title-collapse-href').attr('href','#dict-collapse_0');
        $($newElem).find('.title-collapse-href').text(name);
        $($newElem).find('.panel-collapse').attr('id','dict-collapse_0');
        $($newElem).find('.title-delete-href').data('record-id', name);
        $($newElem).find('.title-delete-href').data('record-title',name);
        $($newElem).find('.dict-value').attr('id','wrd-'+name);

        if(value_param)
        {
            $($newElem).find('.dict-value').text(value_param);
        }

        var id = 1;
        // recalculate collapse-id
        $('#accordion_dictonary > .panel').each(function () {

            $(this).attr('id','acd_'+id);
            $(this).find('.title-collapse-href').attr('href','#dict-collapse_'+id);
            $(this).find('.panel-collapse').attr('id','dict-collapse_'+id);
            id++;
        });
        $newElem.removeClass('hidden');
        $("#accordion_dictonary").prepend($newElem.fadeIn());
    }

    $('#dict-add-button').on('click', function(e)
    {
        var name = $('#dictonary_search_bar').val().trim();
        $.fn.dict_element_add(name);
    });

    // Initialization Dictonary
    // page_dictonary_data generated in module.dictonary.twig

    $.fn.generate_dict_by_json = function(dictonary_data){
        $.each(dictonary_data, function (key, value) {
            $.fn.dict_element_add(key, value);
        });
    }
    $.fn.generate_dict_by_json(page_dictonary_data);

});