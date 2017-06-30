<h1>WPFBBotKit, yo!</h1>
<p><strong>Verification String: </strong> <?php echo $this->get_verification_string(); ?></p>

<form action="" method="POST">
	<?php wp_nonce_field( $this->string_ns . 'save_page_access_token' ); ?>
	<p>
		<label for="<?php echo $this->string_ns;?>access_token"><strong>Page Access Token</strong></label><br>
		<textarea name="<?php echo $this->string_ns;?>access_token" cols=50 rows=4 placeholder='paste token here'><?php echo $this->get_page_access_token();?></textarea>
	</p>
	<p>
		<button type="submit">Submit</button>
	</p>
</form>
