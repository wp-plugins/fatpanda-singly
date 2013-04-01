<?php
$enabled = array();
foreach($fps_services as $service => $label) {
  if (get_option("fps_service_{$service}_enabled")) {
    $enabled[$service] = $label;
  }
}
if ($enabled) {
  ?>
    <style>
      #loginform .services { margin-bottom: 8px; }
      #loginform .service { display: inline-block; margin-right: 11px; margin-bottom: 8px; }
      #loginform .service img { display: block; }
    </style>
    <script>
      !function($) {
        var url = '<?php echo plugins_url('/services', FPSINGLY) ?>', $form = $('#loginform'), $services;
        $form.prepend( $services = $('<p class="services"></p>') );
        <?php foreach($enabled as $service => $label) { ?>
          $services.append('<a href="<?php echo fps_get_login_url($service, $_REQUEST['redirect_to']) ?>" title="<?php echo $label ?>" class="service service-<?php echo $service ?>"><img src="' + url + '/<?php echo $service ?>.png' + '"></a>');
        <?php } ?>
        $form.find('.service').click(function() {
          $('#wp-submit').attr('disabled', true).val('Logging in...').text('Logging in...');
        });
      }(jQuery);
    </script>
  <?php
}