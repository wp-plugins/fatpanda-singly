<style>
#singly .services .service { height: 16px; margin-bottom: 8px; }
#singly .services .service img { display: inline-block; margin: 0 2px; width: 16px; height: 16px; vertical-align: middle; }
</style>
<table class="form-table" id="singly">
  <tbody>
    <tr>
      <th scope="row">Client ID</th>
      <td>
        <input name="fps_singly_client_id" type="text" id="fps_singly_client_id" value="<?php echo esc_attr(get_option('fps_singly_client_id')) ?>" class="regular-text">
      </td>
    </tr>
    <tr>
      <th scope="row">Client Secret</th>
      <td>
        <input name="fps_singly_client_secret" type="text" id="fps_singly_client_secret" value="<?php echo esc_attr(get_option('fps_singly_client_secret')) ?>" class="regular-text">
      </td>
    </tr>
    <tr>
      <th scope="row">Supported services</th>
      <td class="services">
        <?php $i = 0; foreach($fps_services as $service => $label) { $option = "fps_service_{$service}_enabled"; ?>
          <div class="service">
            <label>
              <input type="checkbox" name="<?php echo $option ?>" value="1" <?php if (get_option($option)) echo 'checked="checked"' ?>>
              <img src="<?php echo plugins_url("/services/{$service}.png", FPSINGLY) ?>">
              <?php echo $label ?>
            </label>
          </div>
        <?php $i++; } ?>
      </td>
    </tr>
  </tbody>
</table>
