<?php 
namespace BU\WordPress\Plugins;

/**
 * Configures the settings page 
 */

class SSL_Settings_Page extends SSL {
    
    function __construct() {
        add_action( 'admin_init',   array( $this, 'setup_settings' ) );
        add_action( 'admin_menu',   array( $this, 'add_settings_page' ) );
    }

    public function setup_settings(){
        register_setting('buSSLOptionsPage', 'bu_ssl_settings');

        add_settings_section(
            'bu_ssl_settings_section',
            'SSL Options',
            array( $this, 'settings_general_intro' ),
            'buSSLOptionsPage'
        );
        
        add_settings_field(
            'always_redirect',
            'Always Redirect to HTTPS',
            array( $this, 'settings_field_always_redirect' ),
            'buSSLOptionsPage',
            'bu_ssl_settings_section'
        );  

        add_settings_field(
            'enable_csp',
            'Enable Content Security Policy',
            array( $this, 'settings_field_enable_csp' ),
            'buSSLOptionsPage',
            'bu_ssl_settings_section'
        ); 

        add_settings_field(
            'content_security_policy',
            'Content Security Policy',
            array( $this, 'settings_field_csp' ),
            'buSSLOptionsPage',
            'bu_ssl_settings_section'
        );  
        
        add_settings_field(
            'enforce_csp',
            'Enforce Content Security Policy',
            array( $this, 'settings_field_enforce_csp' ),
            'buSSLOptionsPage',
            'bu_ssl_settings_section'
        );  

        add_settings_field(
            'csp_report_url',
            'CSP Report URL',
            array( $this, 'settings_field_csp_report_url' ),
            'buSSLOptionsPage',
            'bu_ssl_settings_section'
        );  

        add_settings_field(
            'override_url_scheme',
            'Avoid sending logged-in users to insecure pages',
            array( $this, 'settings_field_url_scheme' ),
            'buSSLOptionsPage',
            'bu_ssl_settings_section'
        );
    }

    public function add_settings_page(){
        add_options_page( 
            'SSL Options',
            'SSL',
            'manage_options',
            'bu-ssl',
            array( $this, 'settings_page' )
        );
    }

    public function settings_general_intro(){
        echo '';
    }

    public function settings_field_always_redirect(){
        printf( 
            "<input type='checkbox' name='bu_ssl_settings[always_redirect]' %s value='1' />", 
            checked( $this->options['always_redirect'], 1, false )
        );
    }
    public function settings_field_enable_csp(){
        printf( 
            "<input type='checkbox' name='bu_ssl_settings[enable_csp]' %s value='1' />", 
            checked( $this->options['enable_csp'], 1, false )
        );
    }
    public function settings_field_enforce_csp(){
        printf( 
            "<input type='checkbox' name='bu_ssl_settings[enforce_csp]' %s value='1' />", 
            checked( $this->options['enforce_csp'], 1, false )
        );

        echo "<p class='description'>Unchecked uses <a href='https://developer.mozilla.org/en-US/docs/Web/Security/CSP/Using_CSP_violation_reports' target='_blank'>CSP Report-Only</a> mode, if a Report URL is set.</p>";
    }
    public function settings_field_csp(){
        printf( 
            "<textarea type='textarea' name='bu_ssl_settings[content_security_policy]' cols='50' rows='2'>%s</textarea>", 
            esc_textarea( $this->options['content_security_policy'] )
        );
    }
    public function settings_field_url_scheme(){
        printf( 
            "<input type='checkbox' name='bu_ssl_settings[override_url_scheme]' %s value='1' />", 
            checked( $this->options['override_url_scheme'], 1, false )    
        );
    }
    public function settings_field_csp_report_url(){
        printf( 
            "<input type='text' name='bu_ssl_settings[csp_report_url]' value='%s' />", 
            esc_attr( $this->options['csp_report_url'] )    
        );
    }

    public function settings_page(){
        echo '<form method="post" action="options.php">';
        settings_fields( 'buSSLOptionsPage' );
        do_settings_sections( 'buSSLOptionsPage' );
        submit_button();
    }
}
$bu_ssl = new SSL_Settings_Page();
