<?php
$first_name = array(
	'name'	=> 'first_name',
	'id'	=> 'first_name',
	'size'	=> 30,
	'value' => set_value('first_name')
);

$last_name = array(
	'name'	=> 'last_name',
	'id'	=> 'last_name',
	'size'	=> 30,
	'value' => set_value('last_name')
);

$password = array(
	'name'	=> 'password',
	'id'	=> 'password',
	'size'	=> 30
);

$remember = array(
	'name'	=> 'remember',
	'id'	=> 'remember',
	'value'	=> 1,
	'checked'	=> set_value('remember'),
	'style' => 'margin:0;padding:0'
);

$confirmation_code = array(
	'name'	=> 'captcha',
	'id'	=> 'captcha',
	'maxlength'	=> 8
);

?>

<fieldset><legend>Login</legend>
<?php echo form_open($this->uri->uri_string())?>

<?php echo $this->dx_auth->get_auth_error(); ?>


<dl>
	<dt><?php echo form_label('First Name', $first_name['id']);?></dt>
	<dd>
		<?php echo form_input($first_name)?>
    <?php echo form_error($first_name['name']); ?>
	</dd>
	
	<dt><?php echo form_label('Last Name', $last_name['id']);?></dt>
	<dd>
		<?php echo form_input($last_name)?>
    <?php echo form_error($last_name['name']); ?>
	</dd>

    <dt><?php echo form_label('Password', $password['id']);?></dt>
	<dd>
		<?php echo form_password($password)?>
    <?php echo form_error($password['name']); ?>
	</dd>

<?php if ($show_captcha): ?>

	<dt>Enter the code exactly as it appears. There is no zero.</dt>
	<dd><?php echo $this->dx_auth->get_captcha_image(); ?></dd>

	<dt><?php echo form_label('Confirmation Code', $confirmation_code['id']);?></dt>
	<dd>
		<?php echo form_input($confirmation_code);?>
		<?php echo form_error($confirmation_code['name']); ?>
	</dd>
	
<?php endif; ?>

	<dt></dt>
	<dd>
		<?php echo form_checkbox($remember);?> <?php echo form_label('Remember me', $remember['id']);?> 
		<?php echo anchor($this->dx_auth->forgot_password_uri, 'Forgot password');?> 
		<?php
			if ($this->dx_auth->allow_registration) {
				echo anchor($this->dx_auth->register_uri, 'Register');
			};
		?>
	</dd>

	<dt></dt>
	<dd><?php echo form_submit('login','Login');?></dd>
</dl>

<?php echo form_close()?>
</fieldset>
