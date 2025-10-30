<?php
/**
 * Plugin Name: Ocenenie nehnuteľnosti – formulár
 * Description: Slovenský viac-krokový formulár na ocenenie nehnuteľnosti s analytikou a notifikáciami.
 * Version: 1.7.0
 * Author: Generated
 * Text Domain: gv-valuation
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GVValuationForm {

    const VERSION = '1.7.0';

    protected static $instance = null;

    protected $plugin_path;

    protected $plugin_url;

    protected $table_submissions;

    protected $table_hits;

    protected $option_key = 'gvval_settings';

    protected $nonce_action = 'gvval_nonce_action';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;

        $this->plugin_path       = plugin_dir_path( __FILE__ );
        $this->plugin_url        = plugin_dir_url( __FILE__ );
        $this->table_submissions = $wpdb->prefix . 'gv_val_submissions';
        $this->table_hits        = $wpdb->prefix . 'gv_val_step_hits';

        register_activation_hook( __FILE__, array( $this, 'activate' ) );

        add_action( 'init', array( $this, 'maybe_create_tables' ) );
        add_action( 'init', array( $this, 'register_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_head', array( $this, 'inject_critical_css' ) );

        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        add_action( 'wp_ajax_gvval_save_step', array( $this, 'ajax_save_step' ) );
        add_action( 'wp_ajax_nopriv_gvval_save_step', array( $this, 'ajax_save_step' ) );

        add_action( 'wp_ajax_gvval_track_step', array( $this, 'ajax_track_step' ) );
        add_action( 'wp_ajax_nopriv_gvval_track_step', array( $this, 'ajax_track_step' ) );

        add_action( 'wp_ajax_gvval_save_phone', array( $this, 'ajax_save_phone' ) );
        add_action( 'wp_ajax_nopriv_gvval_save_phone', array( $this, 'ajax_save_phone' ) );

        add_action( 'wp_ajax_gvval_upload_photo', array( $this, 'ajax_upload_photo' ) );
        add_action( 'wp_ajax_nopriv_gvval_upload_photo', array( $this, 'ajax_upload_photo' ) );

        add_action( 'wp_ajax_gvval_complete_submission', array( $this, 'ajax_complete_submission' ) );
        add_action( 'wp_ajax_nopriv_gvval_complete_submission', array( $this, 'ajax_complete_submission' ) );

        add_action( 'wp_ajax_gvval_set_status', array( $this, 'ajax_set_status' ) );
        add_action( 'wp_ajax_gvval_delete_submission', array( $this, 'ajax_delete_submission' ) );
        add_action( 'wp_ajax_gvval_reset_analytics', array( $this, 'ajax_reset_analytics' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function activate() {
        $this->create_tables();
    }

    public function maybe_create_tables() {
        if ( ! $this->table_exists( $this->table_submissions ) || ! $this->table_exists( $this->table_hits ) ) {
            $this->create_tables();
        }
    }

    protected function table_exists( $table ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    protected function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE {$this->table_submissions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            ip VARCHAR(45) NULL,
            user_agent TEXT NULL,
            started_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            current_step INT NULL,
            completed TINYINT(1) DEFAULT 0,
            status VARCHAR(32) DEFAULT 'new',
            data LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id)
        ) $charset_collate;";

        $sql2 = "CREATE TABLE {$this->table_hits} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            step INT NOT NULL,
            first_seen DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_step (session_id, step)
        ) $charset_collate;";

        dbDelta( $sql1 );
        dbDelta( $sql2 );
    }

    public function register_shortcode() {
        add_shortcode( 'gv_valuation_form', array( $this, 'render_shortcode' ) );
    }

    public function render_shortcode( $atts, $content = '' ) {
        wp_enqueue_style( 'gvval-frontend' );
        wp_enqueue_script( 'gvval-frontend' );

        ob_start();
        $template = $this->plugin_path . 'templates/form.php';
        if ( file_exists( $template ) ) {
            $settings = $this->get_settings();
            include $template;
        }
        return ob_get_clean();
    }

    public function inject_critical_css() {
        echo '<style id="gvval-critical">body .gvval-wrapper{display:block;position:relative;}body .gvval-wrapper .gvval-step{display:none;}body .gvval-wrapper .gvval-step.gvval-active{display:block;}</style>';
    }

    public function enqueue_assets() {
        $settings = $this->get_settings();

        wp_register_style( 'gvval-frontend', $this->plugin_url . 'assets/css/gvval.css', array(), self::VERSION );
        wp_register_script( 'gvval-frontend', $this->plugin_url . 'assets/js/gvval.js', array( 'jquery' ), self::VERSION, true );

        $localize = array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( $this->nonce_action ),
            'gmaps_key'     => ! empty( $settings['gmaps_api_key'] ) ? $settings['gmaps_api_key'] : '',
            'redirect_url'  => ! empty( $settings['thankyou_url'] ) ? esc_url_raw( $settings['thankyou_url'] ) : '',
            'i18n'          => array(
                'next'       => __( 'Ďalej', 'gv-valuation' ),
                'back'       => __( 'Späť', 'gv-valuation' ),
                'submit'     => __( 'Odoslať', 'gv-valuation' ),
                'uploading'  => __( 'Nahrávam...', 'gv-valuation' ),
                'error'      => __( 'Ups, nastala chyba. Skúste to znova.', 'gv-valuation' ),
                'success'    => __( 'Úspešne odoslané', 'gv-valuation' ),
            ),
        );

        wp_localize_script( 'gvval-frontend', 'GVValuation', $localize );

        if ( ! empty( $settings['gmaps_api_key'] ) ) {
            wp_enqueue_script( 'google-maps-places', 'https://maps.googleapis.com/maps/api/js?libraries=places&key=' . esc_attr( $settings['gmaps_api_key'] ), array(), null, true );
        }
    }

    public function register_admin_menu() {
        add_menu_page(
            __( 'Ocenenie nehnuteľnosti', 'gv-valuation' ),
            __( 'Ocenenie nehnuteľnosti', 'gv-valuation' ),
            'manage_options',
            'gvval-admin',
            array( $this, 'render_admin_page' ),
            'dashicons-admin-home',
            58
        );
    }

    public function register_settings() {
        register_setting( 'gvval_settings_group', $this->option_key, array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( $input ) {
        $output = array();
        $fields = array(
            'gmaps_api_key',
            'notifications_email',
            'webhook_url',
            'thankyou_url',
            'api_secret',
            'twilio_sid',
            'twilio_token',
            'twilio_from',
            'twilio_notify_to',
        );
        foreach ( $fields as $field ) {
            if ( isset( $input[ $field ] ) ) {
                switch ( $field ) {
                    case 'notifications_email':
                        $output[ $field ] = sanitize_email( $input[ $field ] );
                        break;
                    case 'webhook_url':
                    case 'thankyou_url':
                        $output[ $field ] = esc_url_raw( $input[ $field ] );
                        break;
                    default:
                        $output[ $field ] = sanitize_text_field( $input[ $field ] );
                        break;
                }
            }
        }
        return $output;
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'submissions';

        echo '<div class="wrap gvval-admin">';
        echo '<h1>' . esc_html__( 'Ocenenie nehnuteľnosti', 'gv-valuation' ) . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        $tabs = array(
            'submissions' => __( 'Odoslania', 'gv-valuation' ),
            'analytics'   => __( 'Analytika', 'gv-valuation' ),
            'settings'    => __( 'Nastavenia', 'gv-valuation' ),
        );
        foreach ( $tabs as $key => $label ) {
            $class = ( $active_tab === $key ) ? ' nav-tab-active' : '';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=gvval-admin&tab=' . $key ) ) . '" class="nav-tab' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</h2>';

        if ( 'analytics' === $active_tab ) {
            $this->render_analytics_tab();
        } elseif ( 'settings' === $active_tab ) {
            $this->render_settings_tab();
        } else {
            $this->render_submissions_tab();
        }

        echo '</div>';
    }

    protected function render_submissions_tab() {
        global $wpdb;

        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        $date_from     = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
        $date_to       = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

        $where   = array();
        $params  = array();
        $where[] = '1=1';
        if ( $status_filter ) {
            $where[] = 'status = %s';
            $params[] = $status_filter;
        }
        if ( $date_from ) {
            $where[] = 'DATE(started_at) >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where[] = 'DATE(started_at) <= %s';
            $params[] = $date_to;
        }

        $sql = 'SELECT * FROM ' . $this->table_submissions . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY started_at DESC LIMIT 200';
        $query = $params ? $wpdb->prepare( $sql, $params ) : $sql;
        $rows = $wpdb->get_results( $query );

        $statuses = array(
            'new'             => __( 'Nové', 'gv-valuation' ),
            'contacted'       => __( 'Kontaktované', 'gv-valuation' ),
            'interested'      => __( 'Má záujem', 'gv-valuation' ),
            'not_interested'  => __( 'Nemá záujem', 'gv-valuation' ),
            'call_again'      => __( 'Zavolať neskôr', 'gv-valuation' ),
            'closed'          => __( 'Uzavreté', 'gv-valuation' ),
        );

        echo '<form method="get" class="gvval-filters">';
        echo '<input type="hidden" name="page" value="gvval-admin" />';
        echo '<input type="hidden" name="tab" value="submissions" />';
        echo '<label>' . esc_html__( 'Status', 'gv-valuation' ) . ' '; 
        echo '<select name="status">';
        echo '<option value="">' . esc_html__( 'Všetky', 'gv-valuation' ) . '</option>';
        foreach ( $statuses as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $status_filter, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>' . esc_html__( 'Od', 'gv-valuation' ) . ' <input type="date" name="from" value="' . esc_attr( $date_from ) . '" /></label> ';
        echo '<label>' . esc_html__( 'Do', 'gv-valuation' ) . ' <input type="date" name="to" value="' . esc_attr( $date_to ) . '" /></label> ';
        submit_button( __( 'Filtrovať', 'gv-valuation' ), 'secondary', '', false );
        echo '</form>';

        if ( isset( $_GET['submission'] ) ) {
            $this->render_submission_detail( intval( $_GET['submission'] ) );
            return;
        }

        echo '<table class="widefat fixed striped gvval-table">';
        echo '<thead><tr>';
        $columns = array(
            __( 'Meno', 'gv-valuation' ),
            __( 'Telefón', 'gv-valuation' ),
            __( 'Status', 'gv-valuation' ),
            __( 'Typ', 'gv-valuation' ),
            __( 'Adresa', 'gv-valuation' ),
            __( 'Aktuálny krok', 'gv-valuation' ),
            __( 'Dokončené', 'gv-valuation' ),
            __( 'Začiatok', 'gv-valuation' ),
            __( 'Upravené', 'gv-valuation' ),
            __( 'Akcie', 'gv-valuation' ),
        );
        foreach ( $columns as $column ) {
            echo '<th>' . esc_html( $column ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $data = $row->data ? json_decode( $row->data, true ) : array();
                $name = isset( $data['contact_name'] ) ? $data['contact_name'] : '';
                $phone = isset( $data['phone'] ) ? $data['phone'] : '';
                $type = isset( $data['property_type'] ) ? $this->get_property_type_label( $data['property_type'] ) : '';
                $address = isset( $data['address_line'] ) ? $data['address_line'] : '';

                echo '<tr>';
                echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=gvval-admin&tab=submissions&submission=' . intval( $row->id ) ) ) . '">' . esc_html( $name ) . '</a></td>';
                echo '<td>' . esc_html( $phone ) . '</td>';
                echo '<td>';
                echo '<select class="gvval-status" data-id="' . esc_attr( $row->id ) . '">';
                foreach ( $statuses as $key => $label ) {
                    echo '<option value="' . esc_attr( $key ) . '"' . selected( $row->status, $key, false ) . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
                echo '</td>';
                echo '<td>' . esc_html( $type ) . '</td>';
                echo '<td>' . esc_html( $address ) . '</td>';
                echo '<td>' . esc_html( $row->current_step ) . '</td>';
                echo '<td>' . ( $row->completed ? esc_html__( 'Áno', 'gv-valuation' ) : esc_html__( 'Nie', 'gv-valuation' ) ) . '</td>';
                echo '<td>' . esc_html( $row->started_at ) . '</td>';
                echo '<td>' . esc_html( $row->updated_at ) . '</td>';
                echo '<td><a href="#" class="button button-small button-link-delete gvval-delete" data-id="' . esc_attr( $row->id ) . '">' . esc_html__( 'Zmazať', 'gv-valuation' ) . '</a></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="10">' . esc_html__( 'Žiadne odoslania', 'gv-valuation' ) . '</td></tr>';
        }

        echo '</tbody></table>';

        $this->print_admin_inline_script();
    }

    protected function print_admin_inline_script() {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;
        $nonce   = wp_create_nonce( $this->nonce_action );
        $confirm_delete = esc_js( __( 'Naozaj chcete zmazať toto odoslanie?', 'gv-valuation' ) );
        $confirm_reset  = esc_js( __( 'Naozaj chcete resetovať analytiku?', 'gv-valuation' ) );
        $ajax_url       = esc_js( esc_url_raw( admin_url( 'admin-ajax.php' ) ) );
        echo '<script>document.addEventListener("DOMContentLoaded",function(){if(typeof jQuery==="undefined"){return;}var n="' . esc_js( $nonce ) . '";var ajaxUrl="' . $ajax_url . '";var tables=document.querySelectorAll(".gvval-table");tables.forEach(function(table){table.addEventListener("change",function(e){if(e.target.classList.contains("gvval-status")){var id=e.target.getAttribute("data-id");var status=e.target.value;jQuery.post(ajaxUrl,{action:"gvval_set_status",nonce:n,id:id,status:status});}});table.addEventListener("click",function(e){if(e.target.classList.contains("gvval-delete")){e.preventDefault();if(confirm("' . $confirm_delete . '")){var id=e.target.getAttribute("data-id");jQuery.post(ajaxUrl,{action:"gvval_delete_submission",nonce:n,id:id},function(){window.location.reload();});}}});});var resetBtn=document.querySelector(".gvval-reset-analytics");if(resetBtn){resetBtn.addEventListener("click",function(e){e.preventDefault();if(confirm("' . $confirm_reset . '")){jQuery.post(ajaxUrl,{action:"gvval_reset_analytics",nonce:n},function(){window.location.reload();});}});}});</script>';
    }

    protected function render_submission_detail( $id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table_submissions . ' WHERE id = %d', $id ) );
        if ( ! $row ) {
            echo '<p>' . esc_html__( 'Odoslanie neexistuje.', 'gv-valuation' ) . '</p>';
            return;
        }
        $data = $row->data ? json_decode( $row->data, true ) : array();

        echo '<h2>' . esc_html__( 'Detail odoslania', 'gv-valuation' ) . '</h2>';
        echo '<table class="widefat striped gvval-detail">';
        echo '<tbody>';

        $fields = $this->format_submission_for_display( $data );
        foreach ( $fields as $label => $value ) {
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . wp_kses_post( $value ) . '</td></tr>';
        }

        if ( ! empty( $data['photos'] ) && is_array( $data['photos'] ) ) {
            echo '<tr><th>' . esc_html__( 'Fotky', 'gv-valuation' ) . '</th><td class="gvval-photos">';
            foreach ( $data['photos'] as $photo ) {
                $url = isset( $photo['url'] ) ? esc_url( $photo['url'] ) : '';
                if ( $url ) {
                    echo '<a href="' . $url . '" target="_blank"><img src="' . $url . '" alt="" style="max-width:120px;margin-right:8px;margin-bottom:8px;" /></a>';
                }
            }
            echo '</td></tr>';
        }

        echo '</tbody></table>';
        echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=gvval-admin&tab=submissions' ) ) . '">' . esc_html__( 'Späť na zoznam', 'gv-valuation' ) . '</a></p>';
    }

    protected function format_submission_for_display( $data ) {
        $output = array();
        $map_heating = $this->get_heating_labels();

        $output[ __( 'Meno a priezvisko', 'gv-valuation' ) ] = isset( $data['contact_name'] ) ? esc_html( $data['contact_name'] ) : '';
        $output[ __( 'Telefón', 'gv-valuation' ) ] = isset( $data['phone'] ) ? esc_html( $data['phone'] ) : '';
        if ( ! empty( $data['phone_e164'] ) ) {
            $output[ __( 'Telefón (E.164)', 'gv-valuation' ) ] = esc_html( $data['phone_e164'] );
        }
        $output[ __( 'Typ nehnuteľnosti', 'gv-valuation' ) ] = isset( $data['property_type'] ) ? esc_html( $this->get_property_type_label( $data['property_type'] ) ) : '';
        $output[ __( 'Adresa', 'gv-valuation' ) ] = isset( $data['address_line'] ) ? esc_html( $data['address_line'] ) : '';
        $output[ __( 'Výmera', 'gv-valuation' ) ] = isset( $data['area_sqm'] ) ? esc_html( $data['area_sqm'] . ' m²' ) : '';
        $output[ __( 'Počet izieb', 'gv-valuation' ) ] = isset( $data['rooms'] ) ? esc_html( $this->format_rooms_label( $data['rooms'] ) ) : '';

        if ( isset( $data['property_type'] ) && 'flat' === $data['property_type'] ) {
            $output[ __( 'Poschodie', 'gv-valuation' ) ] = isset( $data['floor'] ) ? esc_html( $this->format_floor_label( $data['floor'] ) ) : '';
            $output[ __( 'Výťah', 'gv-valuation' ) ] = ! empty( $data['has_elevator'] ) ? esc_html__( 'Áno', 'gv-valuation' ) : esc_html__( 'Nie', 'gv-valuation' );
        }

        $output[ __( 'Stav nehnuteľnosti', 'gv-valuation' ) ] = isset( $data['condition'] ) ? esc_html( $this->get_condition_label( $data['condition'] ) ) : '';
        if ( ! empty( $data['accessories'] ) && is_array( $data['accessories'] ) ) {
            $output[ __( 'Príslušenstvo', 'gv-valuation' ) ] = $this->format_accessories( $data['accessories'] );
        }
        if ( ! empty( $data['year_built'] ) ) {
            $output[ __( 'Rok výstavby', 'gv-valuation' ) ] = esc_html( $data['year_built'] );
        }
        if ( ! empty( $data['has_renovation'] ) && ! empty( $data['year_renovated'] ) ) {
            $output[ __( 'Rok rekonštrukcie', 'gv-valuation' ) ] = esc_html( $data['year_renovated'] );
        }
        if ( ! empty( $data['heating'] ) ) {
            $heating_key = $data['heating'];
            $output[ __( 'Vykurovanie', 'gv-valuation' ) ] = isset( $map_heating[ $heating_key ] ) ? esc_html( $map_heating[ $heating_key ] ) : esc_html( $heating_key );
            if ( 'other' === $heating_key && ! empty( $data['heating_other_note'] ) ) {
                $output[ __( 'Poznámka k vykurovaniu', 'gv-valuation' ) ] = esc_html( $data['heating_other_note'] );
            }
        }
        if ( ! empty( $data['extras_text'] ) ) {
            $output[ __( 'Nadštandard', 'gv-valuation' ) ] = nl2br( esc_html( $data['extras_text'] ) );
        }

        return $output;
    }

    protected function format_accessories( $data ) {
        $parts = array();
        if ( ! empty( $data['has_balcony'] ) && ! empty( $data['balcony_area'] ) ) {
            $parts[] = sprintf( __( 'Balkón %s m²', 'gv-valuation' ), intval( $data['balcony_area'] ) );
        }
        if ( ! empty( $data['has_terrace'] ) && ! empty( $data['terrace_area'] ) ) {
            $parts[] = sprintf( __( 'Terasa %s m²', 'gv-valuation' ), intval( $data['terrace_area'] ) );
        }
        if ( ! empty( $data['has_cellar'] ) && ! empty( $data['cellar_area'] ) ) {
            $parts[] = sprintf( __( 'Pivnica %s m²', 'gv-valuation' ), intval( $data['cellar_area'] ) );
        }
        if ( isset( $data['parking'] ) ) {
            $parts[] = sprintf( __( 'Parkovanie: %s', 'gv-valuation' ), $this->get_parking_label( $data['parking'] ) );
            if ( in_array( $data['parking'], array( 'reserved_outdoor', 'garage_private', 'garage_inhouse' ), true ) && ! empty( $data['parking_slots'] ) ) {
                $parts[] = sprintf( __( 'Počet státí: %s', 'gv-valuation' ), intval( $data['parking_slots'] ) );
            }
        }
        return implode( ', ', $parts );
    }

    protected function render_analytics_tab() {
        global $wpdb;

        $sql = 'SELECT step, COUNT(*) as hits FROM ' . $this->table_hits . ' GROUP BY step ORDER BY step ASC';
        $rows = $wpdb->get_results( $sql );
        $counts = array();
        if ( $rows ) {
            foreach ( $rows as $row ) {
                $counts[ intval( $row->step ) ] = intval( $row->hits );
            }
        }

        $max_step = 10;

        echo '<h2>' . esc_html__( 'Analytika', 'gv-valuation' ) . '</h2>';
        echo '<div class="gvval-analytics-header">';
        echo '<span class="gvval-analytics-note">' . esc_html__( 'Zobrazenie postupov krokov a konverzií.', 'gv-valuation' ) . '</span>';
        echo '<button class="button button-secondary gvval-reset-analytics">' . esc_html__( 'Resetovať analytiku', 'gv-valuation' ) . '</button>';
        echo '</div>';
        echo '<div class="gvval-analytics-table-wrap">';
        echo '<table class="widefat gvval-analytics-table">';
        echo '<colgroup><col class="gvval-analytics-step" /><col class="gvval-analytics-metric" /><col class="gvval-analytics-metric" /><col class="gvval-analytics-metric" /></colgroup>';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Krok', 'gv-valuation' ) . '</th>';
        echo '<th>' . esc_html__( 'Unikátni návštevníci', 'gv-valuation' ) . '</th>';
        echo '<th>' . esc_html__( 'Ďalší krok', 'gv-valuation' ) . '</th>';
        echo '<th>' . esc_html__( 'Konverzia (%)', 'gv-valuation' ) . '</th>';
        echo '</tr></thead><tbody>';

        for ( $i = 0; $i <= $max_step; $i++ ) {
            $current_count = isset( $counts[ $i ] ) ? $counts[ $i ] : 0;
            $next_count    = isset( $counts[ $i + 1 ] ) ? $counts[ $i + 1 ] : 0;
            $label = ( $i === $max_step ) ? 'REDIRECT' : sprintf( __( 'Krok %d', 'gv-valuation' ), $i );
            $conversion = $current_count > 0 ? round( ( $next_count / $current_count ) * 100, 2 ) : 0;
            echo '<tr>';
            echo '<td class="gvval-analytics-col-step">' . esc_html( $label ) . '</td>';
            echo '<td class="gvval-analytics-col">' . esc_html( $current_count ) . '</td>';
            echo '<td class="gvval-analytics-col">' . esc_html( $next_count ) . '</td>';
            echo '<td class="gvval-analytics-col gvval-analytics-col-conv">' . esc_html( $conversion ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '<style>
        .gvval-admin .gvval-analytics-header{display:flex;align-items:center;gap:12px;margin:12px 0 16px;}
        .gvval-admin .gvval-analytics-note{font-size:14px;color:#4b5563;}
        .gvval-admin .gvval-analytics-table-wrap{max-width:680px;margin:0;padding:0 12px 0 0;}
        .gvval-admin .gvval-analytics-table{margin-top:0;border-radius:12px;overflow:hidden;box-shadow:0 12px 30px rgba(15,23,42,0.08);width:100%;table-layout:fixed;}
        .gvval-admin .gvval-analytics-table col.gvval-analytics-step{width:40%;}
        .gvval-admin .gvval-analytics-table col.gvval-analytics-metric{width:20%;}
        .gvval-admin .gvval-analytics-table th,
        .gvval-admin .gvval-analytics-table td{padding:8px 12px;text-align:center;font-size:13px;}
        .gvval-admin .gvval-analytics-table thead th{background:#f3f4f6;font-weight:600;color:#1f2937;}
        .gvval-admin .gvval-analytics-table td.gvval-analytics-col-step{text-align:left;font-weight:600;color:#1f2937;}
        .gvval-admin .gvval-analytics-table tbody tr:nth-child(even){background:#f9fafb;}
        .gvval-admin .gvval-analytics-col-conv{font-weight:600;color:#0f766e;}
        .gvval-admin .gvval-analytics-table tbody tr:hover{background:#eef2ff;}
        @media (min-width: 1024px){.gvval-admin .gvval-analytics-table-wrap{padding-right:0;}}
        </style>';

        $this->print_admin_inline_script();
    }

    protected function render_settings_tab() {
        $settings = $this->get_settings();
        echo '<form method="post" action="options.php" class="gvval-settings">';
        settings_fields( 'gvval_settings_group' );

        echo '<table class="form-table">';
        $fields = array(
            'gmaps_api_key'       => __( 'Google Maps API kľúč (Places Autocomplete)', 'gv-valuation' ),
            'notifications_email' => __( 'E-mail pre odoslania', 'gv-valuation' ),
            'webhook_url'         => __( 'Webhook URL (n8n)', 'gv-valuation' ),
            'thankyou_url'        => __( 'URL po odoslaní', 'gv-valuation' ),
            'api_secret'          => __( 'API tajomstvo (REST & podpisy)', 'gv-valuation' ),
            'twilio_sid'          => __( 'Twilio SID', 'gv-valuation' ),
            'twilio_token'        => __( 'Twilio Token', 'gv-valuation' ),
            'twilio_from'         => __( 'Twilio odosielateľ', 'gv-valuation' ),
            'twilio_notify_to'    => __( 'Twilio príjemca', 'gv-valuation' ),
        );

        foreach ( $fields as $key => $label ) {
            $value = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
            echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
            echo '<td><input type="text" class="regular-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $this->option_key . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" /></td></tr>';
        }

        echo '</table>';

        echo '<h3>' . esc_html__( 'Webhook & REST dokumentácia', 'gv-valuation' ) . '</h3>';
        echo '<p>' . esc_html__( 'Webhook odošle JSON s udalosťou "submission.completed". Hlavička X-GVVAL-Signature obsahuje HMAC SHA256 tela pomocou API tajomstva.', 'gv-valuation' ) . '</p>';
        echo '<p>' . esc_html__( 'REST: /wp-json/gvval/v1/submissions?secret=TAJOMSTVO a /wp-json/gvval/v1/submission/{id}?secret=TAJOMSTVO', 'gv-valuation' ) . '</p>';

        submit_button();
        echo '</form>';
    }

    protected function get_settings() {
        $defaults = array(
            'gmaps_api_key'       => '',
            'notifications_email' => get_option( 'admin_email' ),
            'webhook_url'         => '',
            'thankyou_url'        => '',
            'api_secret'          => '',
            'twilio_sid'          => '',
            'twilio_token'        => '',
            'twilio_from'         => '',
            'twilio_notify_to'    => '',
        );
        $settings = get_option( $this->option_key, array() );
        return wp_parse_args( $settings, $defaults );
    }

    protected function get_submission_by_session( $session_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table_submissions . ' WHERE session_id = %s', $session_id ) );
    }

    protected function save_submission_data( $session_id, $data, $current_step = null, $completed = null ) {
        global $wpdb;
        $existing = $this->get_submission_by_session( $session_id );

        $now = current_time( 'mysql', 1 );
        $ip = $this->get_user_ip();
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        $encoded = wp_json_encode( $data );
        if ( false === $encoded ) {
            $encoded = '{}';
        }

        $current_step_value = ( null === $current_step ) ? null : intval( $current_step );
        $completed_value    = ( null === $completed ) ? null : (int) (bool) $completed;

        if ( $existing ) {
            $fields = array(
                'data'       => $encoded,
                'updated_at' => $now,
            );
            $format = array( '%s', '%s' );
            if ( null !== $current_step_value ) {
                $fields['current_step'] = $current_step_value;
                $format[] = '%d';
            }
            if ( null !== $completed_value ) {
                $fields['completed'] = $completed_value;
                $format[] = '%d';
            }
            $wpdb->update( $this->table_submissions, $fields, array( 'session_id' => $session_id ), $format, array( '%s' ) );
            return $existing->id;
        } else {
            if ( null === $current_step_value ) {
                $current_step_value = 0;
            }
            if ( null === $completed_value ) {
                $completed_value = 0;
            }
            $insert = array(
                'session_id'  => $session_id,
                'ip'          => $ip,
                'user_agent'  => $ua,
                'started_at'  => $now,
                'updated_at'  => $now,
                'current_step'=> $current_step_value,
                'completed'   => $completed_value,
                'data'        => $encoded,
            );
            $format = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );
            $wpdb->insert( $this->table_submissions, $insert, $format );
            return $wpdb->insert_id;
        }
    }

    protected function insert_step_hit( $session_id, $step ) {
        global $wpdb;
        $now = current_time( 'mysql', 1 );
        $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . $this->table_hits . ' (session_id, step, first_seen) VALUES (%s, %d, %s) ON DUPLICATE KEY UPDATE first_seen = first_seen', $session_id, intval( $step ), $now ) );
    }

    public function ajax_save_step() {
        $this->verify_nonce();
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
        $step       = isset( $_POST['step'] ) ? intval( $_POST['step'] ) : 0;
        $payload    = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

        if ( empty( $session_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Chýba session ID.', 'gv-valuation' ) ) );
        }

        $data = $this->merge_submission_data( $session_id, $payload );
        $this->save_submission_data( $session_id, $data, $step, null );
        $this->insert_step_hit( $session_id, $step );

        wp_send_json_success( array( 'data' => $data ) );
    }

    protected function merge_submission_data( $session_id, $payload ) {
        $payload = is_array( $payload ) ? $payload : json_decode( wp_unslash( $payload ), true );
        $payload = is_array( $payload ) ? $payload : array();

        $existing = $this->get_submission_by_session( $session_id );
        $data = array();
        if ( $existing && $existing->data ) {
            $decoded = json_decode( $existing->data, true );
            if ( is_array( $decoded ) ) {
                $data = $decoded;
            }
        }

        $sanitized = $this->sanitize_submission_payload( $payload );
        $data = array_merge( $data, $sanitized );
        return $data;
    }

    protected function sanitize_submission_payload( $payload ) {
        $clean = array();

        $string_fields = array(
            'contact_name',
            'phone',
            'phone_e164',
            'property_type',
            'address_city',
            'address_street_number',
            'address_zip',
            'address_line',
            'rooms',
            'floor',
            'condition',
            'parking',
            'heating',
            'heating_other_note',
        );
        foreach ( $string_fields as $field ) {
            if ( isset( $payload[ $field ] ) ) {
                $clean[ $field ] = sanitize_text_field( $payload[ $field ] );
            }
        }

        if ( isset( $payload['extras_text'] ) ) {
            $clean['extras_text'] = sanitize_textarea_field( $payload['extras_text'] );
        }

        $int_fields = array(
            'area_sqm',
            'balcony_area',
            'terrace_area',
            'cellar_area',
            'parking_slots',
            'year_built',
            'year_renovated',
        );
        foreach ( $int_fields as $field ) {
            if ( isset( $payload[ $field ] ) ) {
                $clean[ $field ] = intval( $payload[ $field ] );
            }
        }

        $bool_fields = array(
            'has_elevator',
            'has_balcony',
            'has_terrace',
            'has_cellar',
            'has_renovation',
        );
        foreach ( $bool_fields as $field ) {
            if ( isset( $payload[ $field ] ) ) {
                $clean[ $field ] = (int) (bool) $payload[ $field ];
            }
        }

        if ( isset( $payload['accessories'] ) && is_array( $payload['accessories'] ) ) {
            $acc_clean = array();
            foreach ( $payload['accessories'] as $key => $value ) {
                switch ( $key ) {
                    case 'has_balcony':
                    case 'has_terrace':
                    case 'has_cellar':
                        $acc_clean[ $key ] = (int) (bool) $value;
                        break;
                    case 'balcony_area':
                    case 'terrace_area':
                    case 'cellar_area':
                    case 'parking_slots':
                        $acc_clean[ $key ] = intval( $value );
                        break;
                    case 'parking':
                        $acc_clean[ $key ] = sanitize_text_field( $value );
                        break;
                }
            }
            $clean['accessories'] = $acc_clean;
        }

        if ( isset( $payload['photos'] ) && is_array( $payload['photos'] ) ) {
            $photos = array();
            foreach ( $payload['photos'] as $photo ) {
                if ( isset( $photo['attachment_id'], $photo['url'] ) ) {
                    $photos[] = array(
                        'attachment_id' => intval( $photo['attachment_id'] ),
                        'url'           => esc_url_raw( $photo['url'] ),
                    );
                }
            }
            $clean['photos'] = $photos;
        }

        if ( isset( $payload['sms_phone_only_sent'] ) ) {
            $clean['sms_phone_only_sent'] = (int) $payload['sms_phone_only_sent'];
        }
        if ( isset( $payload['sms_full_sent'] ) ) {
            $clean['sms_full_sent'] = (int) $payload['sms_full_sent'];
        }

        return $clean;
    }

    protected function verify_nonce() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            wp_send_json_error( array( 'message' => __( 'Neplatný nonce.', 'gv-valuation' ) ) );
        }
    }

    public function ajax_track_step() {
        $this->verify_nonce();
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
        $step       = isset( $_POST['step'] ) ? intval( $_POST['step'] ) : 0;

        if ( empty( $session_id ) ) {
            wp_send_json_error();
        }

        $this->insert_step_hit( $session_id, $step );
        wp_send_json_success();
    }

    public function ajax_save_phone() {
        $this->verify_nonce();
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
        $phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

        if ( empty( $session_id ) || empty( $phone ) ) {
            wp_send_json_error();
        }

        $normalized = $this->normalize_phone( $phone );
        if ( ! $normalized ) {
            wp_send_json_error( array( 'message' => __( 'Neplatné telefónne číslo.', 'gv-valuation' ) ) );
        }

        $data = $this->merge_submission_data( $session_id, array(
            'phone'      => $phone,
            'phone_e164' => $normalized,
            'contact_name' => $name,
        ) );

        $submission_id = $this->save_submission_data( $session_id, $data, null, null );

        if ( empty( $data['sms_phone_only_sent'] ) ) {
            $settings = $this->get_settings();
            if ( ! empty( $settings['twilio_sid'] ) && ! empty( $settings['twilio_token'] ) && ! empty( $settings['twilio_from'] ) && ! empty( $settings['twilio_notify_to'] ) ) {
                $this->send_twilio_sms( $settings, sprintf( 'LEAD (phone only): %s %s', $name, $normalized ) );
                $data['sms_phone_only_sent'] = 1;
                $this->save_submission_data( $session_id, $data, null, null );
            }
        }

        wp_send_json_success( array( 'phone_e164' => $normalized, 'submission_id' => $submission_id ) );
    }

    protected function normalize_phone( $phone ) {
        $phone = preg_replace( '/[^0-9+]/', '', $phone );
        if ( strpos( $phone, '+421' ) === 0 ) {
            $normalized = '+421' . preg_replace( '/\D/', '', substr( $phone, 4 ) );
        } elseif ( strpos( $phone, '421' ) === 0 ) {
            $normalized = '+421' . preg_replace( '/\D/', '', substr( $phone, 3 ) );
        } elseif ( strpos( $phone, '0' ) === 0 ) {
            $normalized = '+421' . preg_replace( '/\D/', '', substr( $phone, 1 ) );
        } elseif ( strpos( $phone, '+' ) === 0 ) {
            $normalized = '+' . preg_replace( '/\D/', '', substr( $phone, 1 ) );
        } else {
            $normalized = '+421' . preg_replace( '/\D/', '', $phone );
        }
        $digits = preg_replace( '/\D/', '', $normalized );
        if ( strlen( $digits ) < 10 || strlen( $digits ) > 15 ) {
            return false;
        }
        return $normalized;
    }

    public function ajax_upload_photo() {
        $this->verify_nonce();
        if ( ! isset( $_FILES['file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Súbor chýba.', 'gv-valuation' ) ) );
        }

        $file = $_FILES['file'];

        $allowed = array( 'image/jpeg', 'image/png', 'image/webp' );
        $check   = wp_check_filetype( $file['name'] );
        if ( empty( $check['type'] ) || ! in_array( $check['type'], $allowed, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Nepodporovaný typ súboru.', 'gv-valuation' ) ) );
        }
        if ( $file['size'] > 10 * 1024 * 1024 ) {
            wp_send_json_error( array( 'message' => __( 'Súbor je príliš veľký.', 'gv-valuation' ) ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $file, $overrides );
        if ( isset( $movefile['error'] ) ) {
            wp_send_json_error( array( 'message' => $movefile['error'] ) );
        }

        $attachment = array(
            'guid'           => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title'     => sanitize_file_name( $file['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        $attachment_id = wp_insert_attachment( $attachment, $movefile['file'] );
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $movefile['file'] );
        wp_update_attachment_metadata( $attachment_id, $attach_data );

        wp_send_json_success( array( 'attachment_id' => $attachment_id, 'url' => $movefile['url'] ) );
    }

    public function ajax_complete_submission() {
        $this->verify_nonce();
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
        $payload    = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

        if ( empty( $session_id ) ) {
            wp_send_json_error();
        }

        $data = $this->merge_submission_data( $session_id, $payload );
        $data['completed_at'] = current_time( 'mysql' );

        $submission_id = $this->save_submission_data( $session_id, $data, 10, 1 );
        $this->insert_step_hit( $session_id, 10 );

        $this->handle_completion_notifications( $submission_id, $data, $session_id );

        wp_send_json_success( array( 'submission_id' => $submission_id ) );
    }

    protected function handle_completion_notifications( $submission_id, $data, $session_id ) {
        $settings = $this->get_settings();

        $this->send_completion_email( $settings, $submission_id, $data );
        $this->send_webhook_notification( $settings, $submission_id, $session_id, $data );

        if ( empty( $data['sms_full_sent'] ) ) {
            if ( ! empty( $settings['twilio_sid'] ) && ! empty( $settings['twilio_token'] ) && ! empty( $settings['twilio_from'] ) && ! empty( $settings['twilio_notify_to'] ) ) {
                $heating_labels = $this->get_heating_labels();
                $heating_label  = isset( $data['heating'] ) ? ( $heating_labels[ $data['heating'] ] ?? $data['heating'] ) : '-';
                $message        = sprintf(
                    'LEAD (full): %s, %s, %s, %s m2, %s izby, %s, %s/%s, %s',
                    isset( $data['contact_name'] ) ? $data['contact_name'] : '',
                    isset( $data['address_line'] ) ? $data['address_line'] : '',
                    isset( $data['property_type'] ) ? $this->get_property_type_label( $data['property_type'] ) : '',
                    isset( $data['area_sqm'] ) ? $data['area_sqm'] : '',
                    isset( $data['rooms'] ) ? $this->format_rooms_label( $data['rooms'] ) : '',
                    isset( $data['condition'] ) ? $this->get_condition_label( $data['condition'] ) : '',
                    isset( $data['year_built'] ) ? $data['year_built'] : '-',
                    isset( $data['year_renovated'] ) ? $data['year_renovated'] : '-',
                    $heating_label
                );
                $this->send_twilio_sms( $settings, $message );
                $data['sms_full_sent'] = 1;
                $this->save_submission_data( $session_id, $data, 10, 1 );
            }
        }
    }

    protected function send_completion_email( $settings, $submission_id, $data ) {
        $email = ! empty( $settings['notifications_email'] ) ? $settings['notifications_email'] : get_option( 'admin_email' );
        if ( ! $email ) {
            return;
        }

        $subject = sprintf( __( 'Nové odoslanie #%d', 'gv-valuation' ), $submission_id );

        $fields = $this->format_submission_for_display( $data );
        $body  = '<h2>' . esc_html__( 'Nové odoslanie ocenenia', 'gv-valuation' ) . '</h2>';
        $body .= '<table style="width:100%;border-collapse:collapse;">';
        foreach ( $fields as $label => $value ) {
            $body .= '<tr><th style="text-align:left;border:1px solid #ddd;padding:6px;">' . esc_html( $label ) . '</th><td style="border:1px solid #ddd;padding:6px;">' . wp_kses_post( $value ) . '</td></tr>';
        }
        if ( ! empty( $data['photos'] ) && is_array( $data['photos'] ) ) {
            $body .= '<tr><th style="text-align:left;border:1px solid #ddd;padding:6px;">' . esc_html__( 'Fotky', 'gv-valuation' ) . '</th><td style="border:1px solid #ddd;padding:6px;">';
            foreach ( $data['photos'] as $photo ) {
                $url = isset( $photo['url'] ) ? esc_url( $photo['url'] ) : '';
                if ( $url ) {
                    $body .= '<a href="' . $url . '">' . $url . '</a><br />';
                }
            }
            $body .= '</td></tr>';
        }
        $body .= '</table>';
        $body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=gvval-admin&tab=submissions&submission=' . $submission_id ) ) . '">' . esc_html__( 'Zobraziť v administrácii', 'gv-valuation' ) . '</a></p>';

        add_filter( 'wp_mail_content_type', array( $this, 'set_mail_content_type' ) );
        wp_mail( $email, $subject, $body );
        remove_filter( 'wp_mail_content_type', array( $this, 'set_mail_content_type' ) );
    }

    public function set_mail_content_type() {
        return 'text/html';
    }

    protected function send_webhook_notification( $settings, $submission_id, $session_id, $data ) {
        if ( empty( $settings['webhook_url'] ) ) {
            return;
        }

        $body = array(
            'event'         => 'submission.completed',
            'submission_id' => $submission_id,
            'session_id'    => $session_id,
            'timestamp'     => gmdate( 'c', current_time( 'timestamp', true ) ),
            'data'          => $data,
        );
        $json = wp_json_encode( $body );

        $headers = array( 'Content-Type' => 'application/json' );
        if ( ! empty( $settings['api_secret'] ) ) {
            $signature = hash_hmac( 'sha256', $json, $settings['api_secret'] );
            $headers['X-GVVAL-Signature'] = 'sha256=' . $signature;
        }

        wp_remote_post( $settings['webhook_url'], array(
            'body'    => $json,
            'headers' => $headers,
            'timeout' => 10,
        ) );
    }

    protected function send_twilio_sms( $settings, $message ) {
        $sid   = $settings['twilio_sid'];
        $token = $settings['twilio_token'];
        $from  = $settings['twilio_from'];
        $to    = $settings['twilio_notify_to'];

        if ( ! $sid || ! $token || ! $from || ! $to ) {
            return;
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json';
        $args = array(
            'body'      => array(
                'From' => $from,
                'To'   => $to,
                'Body' => $message,
            ),
            'headers'   => array(
                'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
            ),
            'timeout'   => 10,
        );
        wp_remote_post( $url, $args );
    }

    public function ajax_set_status() {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        $id     = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        global $wpdb;
        $wpdb->update( $this->table_submissions, array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
        wp_send_json_success();
    }

    public function ajax_delete_submission() {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        global $wpdb;
        $submission = $wpdb->get_row( $wpdb->prepare( 'SELECT session_id FROM ' . $this->table_submissions . ' WHERE id = %d', $id ) );
        if ( $submission ) {
            $wpdb->delete( $this->table_submissions, array( 'id' => $id ), array( '%d' ) );
            $wpdb->delete( $this->table_hits, array( 'session_id' => $submission->session_id ), array( '%s' ) );
        }
        wp_send_json_success();
    }

    public function ajax_reset_analytics() {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . $this->table_hits );
        wp_send_json_success();
    }

    public function register_rest_routes() {
        register_rest_route( 'gvval/v1', '/submissions', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_get_submissions' ),
            'permission_callback' => array( $this, 'rest_check_secret' ),
        ) );

        register_rest_route( 'gvval/v1', '/submission/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_get_submission' ),
            'permission_callback' => array( $this, 'rest_check_secret' ),
        ) );
    }

    public function rest_check_secret( $request ) {
        $secret   = $request->get_param( 'secret' );
        $settings = $this->get_settings();
        return ! empty( $settings['api_secret'] ) && hash_equals( $settings['api_secret'], $secret );
    }

    public function rest_get_submissions( $request ) {
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT * FROM ' . $this->table_submissions . ' ORDER BY started_at DESC LIMIT 100' );
        $data = array();
        foreach ( $rows as $row ) {
            $item = $this->prepare_submission_for_api( $row );
            $data[] = $item;
        }
        return rest_ensure_response( $data );
    }

    public function rest_get_submission( $request ) {
        global $wpdb;
        $id  = intval( $request['id'] );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table_submissions . ' WHERE id = %d', $id ) );
        if ( ! $row ) {
            return new WP_Error( 'not_found', __( 'Nenájdené', 'gv-valuation' ), array( 'status' => 404 ) );
        }
        return rest_ensure_response( $this->prepare_submission_for_api( $row ) );
    }

    protected function prepare_submission_for_api( $row ) {
        $data = $row->data ? json_decode( $row->data, true ) : array();
        return array(
            'id'           => intval( $row->id ),
            'session_id'   => $row->session_id,
            'status'       => $row->status,
            'current_step' => intval( $row->current_step ),
            'completed'    => intval( $row->completed ),
            'started_at'   => $row->started_at,
            'updated_at'   => $row->updated_at,
            'data'         => $data,
        );
    }

    protected function get_user_ip() {
        foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
            }
        }
        return '';
    }

    protected function get_property_type_label( $value ) {
        $map = array(
            'flat'  => __( 'Byt', 'gv-valuation' ),
            'house' => __( 'Dom', 'gv-valuation' ),
        );
        return isset( $map[ $value ] ) ? $map[ $value ] : $value;
    }

    protected function get_condition_label( $value ) {
        $map = array(
            'original'   => __( 'Pôvodný stav', 'gv-valuation' ),
            'renovated'  => __( 'Po rekonštrukcii', 'gv-valuation' ),
            'new_build'  => __( 'Novostavba do 5 rokov', 'gv-valuation' ),
        );
        return isset( $map[ $value ] ) ? $map[ $value ] : $value;
    }

    protected function get_parking_label( $value ) {
        $map = array(
            'none'             => __( 'Bez parkovania', 'gv-valuation' ),
            'street'           => __( 'Ulica', 'gv-valuation' ),
            'reserved_outdoor' => __( 'Rezervované vonkajšie státie', 'gv-valuation' ),
            'garage_private'   => __( 'Garáž (samostatná)', 'gv-valuation' ),
            'garage_inhouse'   => __( 'Garáž v dome', 'gv-valuation' ),
        );
        return isset( $map[ $value ] ) ? $map[ $value ] : $value;
    }

    protected function get_heating_labels() {
        return array(
            'gas'            => __( 'Plyn', 'gv-valuation' ),
            'district'       => __( 'Centrálne (mestská tepláreň)', 'gv-valuation' ),
            'central_boiler' => __( 'Ústredné (kotolňa v dome)', 'gv-valuation' ),
            'electric'       => __( 'Elektrina', 'gv-valuation' ),
            'heat_pump'      => __( 'Tepelné čerpadlo', 'gv-valuation' ),
            'solid_fuel'     => __( 'Tuhé palivo', 'gv-valuation' ),
            'other'          => __( 'Iné', 'gv-valuation' ),
        );
    }

    protected function format_rooms_label( $value ) {
        $map = array(
            '1'      => '1',
            '1_5'    => '1.5',
            '2'      => '2',
            '2_5'    => '2.5',
            '3'      => '3',
            '3_5'    => '3.5',
            '4'      => '4',
            '5'      => '5',
            '5_plus' => '5+',
        );
        return isset( $map[ $value ] ) ? $map[ $value ] : $value;
    }

    protected function format_floor_label( $value ) {
        $map = array(
            'basement' => __( 'Suterén', 'gv-valuation' ),
            'ground'   => __( 'Prízemie', 'gv-valuation' ),
            '15_plus'  => __( '15 a viac', 'gv-valuation' ),
        );
        if ( isset( $map[ $value ] ) ) {
            return $map[ $value ];
        }
        if ( is_numeric( $value ) ) {
            return sprintf( __( '%s. poschodie', 'gv-valuation' ), intval( $value ) );
        }
        return $value;
    }
}

GVValuationForm::instance();

