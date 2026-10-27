(function($){ 
    
  $(document).ready(function() {    
    $('#dp-admin-tabs a:first').addClass('nav-tab-active');
    $('#dp-admin-tabs a:not(:first)').addClass('nav-tab-inactive');
    $('.dp-admin-nav-container').hide();
    $('.dp-admin-nav-container:first').show();
      
    $('#dp-admin-tabs a').click(function(){
      var t = $(this).attr('id');
      if($(this).hasClass('nav-tab-inactive')){ 
        $('#dp-admin-tabs a').addClass('nav-tab-inactive');           
        $(this).removeClass('nav-tab-inactive');
        $(this).addClass('nav-tab-active');
        
        $('.dp-admin-nav-container').hide();
        $('#'+ t + 'C').fadeIn('slow');
      }
    });

    setTimeout(function(){
      $(".dpwap-notice-pre .notice-dismiss").on('click', function(){
        jQuery.ajax({
          type: "POST",
          url: admin_vars.ajax_url,
          data: {action: 'dpwap_dismiss_notice_action'},
          success: function (response) { }
        });
      });  
    }, 1000);


    $(document).on('click', '.dpwap-dismissible .notice-dismiss', function() {
        dpwapNotice()
    });

  // Handle install button click
  $(document).on('click', '.install-eventprime', function() {
    dpwapNotice()
  });

  function dpwapNotice() {
      var data = {
          action: 'dpwap_dismiss_eventprime_promotion',
          nonce: admin_vars.nonce
      };
      $.post(admin_vars.ajax_url, data, function(response) {
          if (response.success) {
            
          }
      });
    }
  });
})(jQuery);
