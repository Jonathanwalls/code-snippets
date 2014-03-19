add_action('wp_print_styles', 'load_fonts');

function load_fonts() {
            wp_register_style('googleFonts', 'http://fonts.googleapis.com/css?family=Amatic+SC|Josefin+Sans');
            wp_enqueue_style( 'googleFonts');
}
