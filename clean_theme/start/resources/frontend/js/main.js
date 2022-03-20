var $ = jQuery.noConflict();
$(document).ready(function () {
/*
    var ajaxdata = $(_t).serialize() + "&action=" + action + ( action == 'transfers' ? SearchForm : '&who=' + $('.padding-tour .who').attr('data-json') ) + (action === 'transfers' ? '&selectedCar=' + selectedCar : '') + post_idData;
    $.post(ajax_obj.ajaxurl, ajaxdata, function (res) {
      var obj = $.parseJSON(res);
      if (obj.status == 'failed') {
        var p = $('<p />', {
          'class': 'error-msg',
          'text': obj.msg
        }).appendTo('.' + obj.where);
        $('.' + obj.where).addClass('error-wrapper');
        scroll_to_error('.' + obj.where);
      }

      if (obj.status == 'success') {
          if ( action == 'transfers' ) {
              $('.inner-search-wrapper form').remove();
              transfers_success( obj );
          }
          if ( action == 'tours' ) {
              tours_success( obj );
          }
          
          if ( action == 'comment_trip' ) {
              comments_success( obj.data );
          }
          _t[0].reset();
      }
      $('.loader-wrapper').hide();


    });
    return false;*/
});