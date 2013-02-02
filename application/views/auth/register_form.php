<?php

if ($use_username) {
	$username = array(
		'name'	=> 'username',
		'id'	=> 'username',
		'value' => set_value('username'),
		'maxlength'	=> $this->config->item('username_max_length', 'tank_auth'),
		'size'	=> 30,
		'autocomplete' => 'off'
	);
}
$email = array(
	'name'	=> 'email',
	'id'	=> 'email',
	'value'	=> set_value('email'),
	'maxlength'	=> 80,
	'size'	=> 30,
	'autocomplete' => 'off'
);
$password = array(
	'name'	=> 'password',
	'id'	=> 'password',
	'value' => set_value('password'),
	'maxlength'	=> $this->config->item('password_max_length', 'tank_auth'),
	'size'	=> 30,
	'autocomplete' => 'off'
);
$confirm_password = array(
	'name'	=> 'confirm_password',
	'id'	=> 'confirm_password',
	'value' => set_value('confirm_password'),
	'maxlength'	=> $this->config->item('password_max_length', 'tank_auth'),
	'size'	=> 30,
	'autocomplete' => 'off'
);
$captcha = array(
	'name'	=> 'captcha',
	'id'	=> 'captcha',
	'maxlength'	=> 8,
	'autocomplete' => 'off'
);

?>

<?php echo form_open($this->uri->uri_string()); ?>
<table>
	<?php if ($use_username) : ?>
	<tr>
		<td><?php echo form_label('Username', $username['id']); ?></td>
		<td><?php echo form_input($username); ?></td>
		<td style="color: red;"><?php echo form_error($username['name']); ?><?php echo isset($errors[$username['name']]) ? $errors[$username['name']] : ''; ?></td>
	</tr>
	<?php endif; ?>
	<tr>
		<td><?php echo form_label('Email Address', $email['id']); ?></td>
		<td><?php echo form_input($email); ?></td>
		<td style="color: red;"><?php echo form_error($email['name']); ?><?php echo isset($errors[$email['name']]) ? $errors[$email['name']] : ''; ?></td>
	</tr>
	<tr>
		<td><?php echo form_label('Password', $password['id']); ?></td>
		<td><?php echo form_password($password); ?></td>
		<td style="color: red;"><?php echo form_error($password['name']); ?></td>
	</tr>
	<tr>
		<td><?php echo form_label('Confirm Password', $confirm_password['id']); ?></td>
		<td><?php echo form_password($confirm_password); ?></td>
		<td style="color: red;"><?php echo form_error($confirm_password['name']); ?></td>
	</tr>
	
  <?php if(isset($registration_fields)) : foreach($registration_fields as $val) : ?>
		<?php
			list($name, $label,, $type) = $val;
			$field = array('name'	=> $name, 'id'	=> $name, 'value' => set_value($name));
		?>
  	<?php if($type == 'text') : ?>
			<?php
				$field += array('size'=>30);
				$attr = isset($val[4]) ? $val[4] : FALSE;
				if($attr){
					foreach($attr as $k=>$v){
						$field[$k] = $v;
					}
				}
			?>
      <tr>
        <td><?php echo form_label($label, $field['name']); ?></td>
        <td><?php echo form_input($field); ?></td>
        <td style="color: red;"><?php echo form_error($field['name']); ?><?php echo isset($errors[$field['name']]) ? $errors[$field['name']] : ''; ?></td>
      </tr>
  	<?php elseif($type == 'dropdown') : ?>
      <tr>
        <td><?php echo form_label($label, $name); ?></td>
				<?php if(isset($db_dropdowns) && in_array($name, $db_dropdowns)) : ?>
					<td><?php echo form_dropdown($name, $dropdown_items[$name], $dropdown_items_default[$name]); ?></td>
				<?php else : ?>
					<td><?php echo form_dropdown($name, $dropdown_simple[$name], $dropdown_simple_default[$name]); ?></td>
				<?php endif; ?>
        <td style="color: red;"><?php echo form_error($name); ?><?php echo isset($errors[$name]) ? $errors[$name] : ''; ?></td>
      </tr>
  	<?php elseif($type == 'checkbox') : ?>
      <tr valign="top">
        <td><?php echo $label; ?></td>
				<!--
        <td><?php echo form_checkbox(array('name'=>$name, 'id'=>$name), 1, set_checkbox($name, 1, isset($name) ? $name : FALSE)) . ' ' . form_label($val[4], $name); ?></td>
				-->
				<td><?php echo form_checkbox(array('name'=>$name, 'id'=>$name), 1, set_checkbox($name, 1, isset($val[5]) ? $val[5] : FALSE)) . ' ' . form_label($val[4], $name); ?></td>
        <td style="color: red;"><?php echo form_error($name); ?><?php echo isset($errors[$name]) ? $errors[$name] : ''; ?></td>
      </tr>
  	<?php elseif($type == 'radio') : ?>
      <tr valign="top">
        <td><?php echo $label; ?></td>
        <td>
					<?php
						$open_tag = isset($val[5]) ? $val[5] : '<span>';
						$close_tag = isset($val[6]) ? $val[6] : '</span>';
						foreach($val[4] as $key=>$radio_label){
							echo $open_tag.'<label>'.form_radio($name, $key, set_radio($name, $key)).' '.$radio_label.'</label>'.$close_tag;
						}
					?>
				</td>
        <td style="color: red;"><?php echo form_error($name); ?><?php echo isset($errors[$name]) ? $errors[$name] : ''; ?></td>
      </tr>
    <?php endif; ?>
  
  <?php endforeach; endif; ?>	
	
	

	<?php if ($captcha_registration) : if ($use_recaptcha) : ?>
	<tr>
		<td colspan="2">
			<div id="recaptcha_image"></div>
		</td>
		<td>
			<a href="javascript:Recaptcha.reload()">Get another CAPTCHA</a>
			<div class="recaptcha_only_if_image"><a href="javascript:Recaptcha.switch_type('audio')">Get an audio CAPTCHA</a></div>
			<div class="recaptcha_only_if_audio"><a href="javascript:Recaptcha.switch_type('image')">Get an image CAPTCHA</a></div>
		</td>
	</tr>
	<tr>
		<td>
			<div class="recaptcha_only_if_image">Enter the words above</div>
			<div class="recaptcha_only_if_audio">Enter the numbers you hear</div>
		</td>
		<td><input type="text" id="recaptcha_response_field" name="recaptcha_response_field" /></td>
		<td style="color: red;"><?php echo form_error('recaptcha_response_field'); ?></td>
		<?php echo $recaptcha_html; ?>
	</tr>
	<?php else : ?>
	<tr>
		<td>&nbsp;</td>
		<td>
			<p>
				Write the following word:<br>
				<img src="<?php echo $captcha_html; ?>" id="captcha" /><br />
			<a class="small" href="javascript:void(0)" onclick="document.getElementById('captcha').src='<?php echo site_url(); ?>captcha/captcha.php?'+Math.random(); document.getElementById('captcha-form').focus();" id="change-image">Not readable? Change text.</a><br/><br/><input type="text" name="captcha" id="captcha-form" autocomplete="off" /></p>
		</td>
		<td style="color: red;"><?php echo form_error('captcha'); ?><?php echo isset($errors['captcha']) ? $errors['captcha'] : ''; ?></td>
	</tr>
	<?php endif; endif; ?>
</table>
<?php echo form_submit('register', 'Register'); ?>
<?php echo form_close(); ?>