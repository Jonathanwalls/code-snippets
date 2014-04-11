add_action('wp_print_styles', 'stylesheets');

	function stylesheets() {
			$date = date('m d y h i s ');
			wp_register_style( 'dc.stylesheet',   get_stylesheet_directory_uri() . '/style.css', false, $date ); 