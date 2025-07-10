<?php
/**
 * Plugin Name: Tablas Futbol
 * Description: Plugin simple para gestionar tablas de torneos de fútbol
 * Version: 1.0
 * Author: Push And Pull Now
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('TORNEO_SIMPLE_VERSION', '1.0');
define('TORNEO_SIMPLE_PATH', plugin_dir_path(__FILE__));
define('TORNEO_SIMPLE_URL', plugin_dir_url(__FILE__));

// Clase principal del plugin
class TorneoSimple {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Crear tabla en la base de datos
        $this->create_table();
        
        // Registrar menús de administración
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Registrar shortcodes
        add_shortcode('torneo_posiciones', array($this, 'shortcode_posiciones'));
        add_shortcode('torneo_partidos', array($this, 'shortcode_partidos'));
        add_shortcode('torneo_jugadores', array($this, 'shortcode_jugadores'));
        
        // Registrar estilos CSS
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // API Endpoints para n8n
        add_action('rest_api_init', array($this, 'register_api_endpoints'));
        
        // Procesar formularios
        add_action('admin_post_torneo_save_table', array($this, 'save_table'));
        add_action('admin_post_torneo_delete_table', array($this, 'delete_table'));
        add_action('admin_post_torneo_import_csv', array($this, 'import_csv'));
        add_action('admin_post_torneo_export_csv', array($this, 'export_csv'));
        add_action('admin_post_torneo_export_all_csv', array($this, 'export_all_csv'));
    }
    
    public function activate() {
        $this->create_table();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}torneo_tablas (
            id int(11) NOT NULL AUTO_INCREMENT,
            nombre_tabla varchar(255) NOT NULL,
            tipo enum('posiciones','partidos','jugadores') NOT NULL,
            contenido_json longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function admin_menu() {
        add_menu_page(
            'Torneo Simple',
            'Torneo Simple',
            'manage_options',
            'torneo-simple',
            array($this, 'admin_page_main'),
            'dashicons-awards',
            30
        );
        
        add_submenu_page(
            'torneo-simple',
            'Tablas de Posiciones',
            'Posiciones',
            'manage_options',
            'torneo-posiciones',
            array($this, 'admin_page_posiciones')
        );
        
        add_submenu_page(
            'torneo-simple',
            'Próximos Partidos',
            'Partidos',
            'manage_options',
            'torneo-partidos',
            array($this, 'admin_page_partidos')
        );
        
        add_submenu_page(
            'torneo-simple',
            'Estadísticas Jugadores',
            'Jugadores',
            'manage_options',
            'torneo-jugadores',
            array($this, 'admin_page_jugadores')
        );
    }
    
    public function enqueue_styles() {
        wp_enqueue_style('torneo-simple-style', TORNEO_SIMPLE_URL . 'assets/style.css', array(), TORNEO_SIMPLE_VERSION . '-' . time());
        
        // CSS inline de emergencia
        $custom_css = '
        .torneo-liga-titulo {
            background: linear-gradient(135deg, #1a1a1a 0%, #333 100%) !important;
            color: #fff !important;
            padding: 15px 20px !important;
            font-weight: bold !important;
            border-left: 4px solid #e74c3c !important;
        }
        .torneo-tabla thead {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%) !important;
        }
        .torneo-tabla th {
            color: #fff !important;
            padding: 15px 12px !important;
        }';
        wp_add_inline_style('torneo-simple-style', $custom_css);
    }
    
    public function admin_enqueue_scripts() {
        wp_enqueue_script('torneo-simple-admin', TORNEO_SIMPLE_URL . 'assets/admin.js', array('jquery'), TORNEO_SIMPLE_VERSION, true);
    }
    
    // Páginas de administración
    public function admin_page_main() {
        include TORNEO_SIMPLE_PATH . 'admin/main.php';
    }
    
    public function admin_page_posiciones() {
        include TORNEO_SIMPLE_PATH . 'admin/posiciones.php';
    }
    
    public function admin_page_partidos() {
        include TORNEO_SIMPLE_PATH . 'admin/partidos.php';
    }
    
    public function admin_page_jugadores() {
        include TORNEO_SIMPLE_PATH . 'admin/jugadores.php';
    }
    
    // Shortcodes
    public function shortcode_posiciones($atts) {
        return $this->mostrar_tablas('posiciones');
    }
    
    public function shortcode_partidos($atts) {
        return $this->mostrar_tablas('partidos');
    }
    
    public function shortcode_jugadores($atts) {
        return $this->mostrar_tablas('jugadores');
    }
    
    // Mostrar tablas en frontend
    private function mostrar_tablas($tipo) {
        global $wpdb;
        
        $tablas = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE tipo = %s ORDER BY created_at DESC",
            $tipo
        ));
        
        if (empty($tablas)) {
            return '<div class="torneo-no-data">No hay tablas de ' . $tipo . ' disponibles.</div>';
        }
        
        $output = '';
        
        foreach ($tablas as $tabla) {
            $contenido = json_decode($tabla->contenido_json, true);
            
            // IMPORTANTE: Ordenar contenido según el tipo
            if ($tipo === 'posiciones' && !empty($contenido)) {
                // Ordenar por posición de manera ascendente
                usort($contenido, function($a, $b) {
                    return intval($a['posicion']) - intval($b['posicion']);
                });
            } elseif ($tipo === 'partidos' && !empty($contenido)) {
                // Ordenar partidos por fecha y hora
                usort($contenido, function($a, $b) {
                    $fecha_a = $a['fecha'] . ' ' . $a['hora'];
                    $fecha_b = $b['fecha'] . ' ' . $b['hora'];
                    return strcmp($fecha_a, $fecha_b);
                });
            } elseif ($tipo === 'jugadores' && !empty($contenido)) {
                // Ordenar jugadores por goles (descendente)
                usort($contenido, function($a, $b) {
                    return intval($b['goles']) - intval($a['goles']);
                });
            }
            
            $output .= '<div class="torneo-tabla-container">';
            $output .= '<h3 class="torneo-liga-titulo">' . esc_html($tabla->nombre_tabla) . '</h3>';
            
            if ($tipo === 'posiciones') {
                $output .= $this->render_tabla_posiciones($contenido);
            } elseif ($tipo === 'partidos') {
                $output .= $this->render_tabla_partidos($contenido);
            } elseif ($tipo === 'jugadores') {
                $output .= $this->render_tabla_jugadores($contenido);
            }
            
            $output .= '</div>';
        }
        
        return $output;
    }
    
    private function render_tabla_posiciones($contenido) {
        $output = '<div class="torneo-tabla-posiciones">';
        $output .= '<table class="torneo-tabla">';
        $output .= '<thead>';
        $output .= '<tr>';
        $output .= '<th class="pos-col">Pos</th>';
        $output .= '<th class="club-col">Club</th>';
        $output .= '<th class="stat-col">PJ</th>';
        $output .= '<th class="stat-col">PG</th>';
        $output .= '<th class="stat-col">PE</th>';
        $output .= '<th class="stat-col">PP</th>';
        $output .= '<th class="stat-col">GF</th>';
        $output .= '<th class="stat-col">GC</th>';
        $output .= '<th class="dg-col">DG</th>';
        $output .= '<th class="pts-col">Pts</th>';
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';
        
        foreach ($contenido as $fila) {
            $dg = intval($fila['gf']) - intval($fila['gc']);
            $dg_class = $dg >= 0 ? 'positive' : 'negative';
            
            $output .= '<tr>';
            $output .= '<td class="pos-cell">' . esc_html($fila['posicion']) . '</td>';
            $output .= '<td class="club-cell"><strong>' . esc_html($fila['club']) . '</strong></td>';
            $output .= '<td>' . esc_html($fila['pj']) . '</td>';
            $output .= '<td>' . esc_html($fila['pg']) . '</td>';
            $output .= '<td>' . esc_html($fila['pe']) . '</td>';
            $output .= '<td>' . esc_html($fila['pp']) . '</td>';
            $output .= '<td>' . esc_html($fila['gf']) . '</td>';
            $output .= '<td>' . esc_html($fila['gc']) . '</td>';
            $output .= '<td class="' . $dg_class . '">' . ($dg >= 0 ? '+' : '') . $dg . '</td>';
            $output .= '<td class="pts-cell"><strong>' . esc_html($fila['pts']) . '</strong></td>';
            $output .= '</tr>';
        }
        
        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';
        
        return $output;
    }
    
    private function render_tabla_partidos($contenido) {
        $output = '<div class="torneo-partidos-lista">';
        
        foreach ($contenido as $partido) {
            $output .= '<div class="torneo-partido">';
            $output .= '<div class="partido-fecha">';
            $output .= '<span class="fecha">' . esc_html($partido['fecha']) . '</span>';
            $output .= '<span class="hora">' . esc_html($partido['hora']) . '</span>';
            $output .= '</div>';
            $output .= '<div class="partido-enfrentamiento">';
            $output .= '<div class="equipo equipo-local">';
            $output .= '<span class="equipo-nombre">' . esc_html($partido['local']) . '</span>';
            $output .= '</div>';
            $output .= '<div class="vs">vs</div>';
            $output .= '<div class="equipo equipo-visitante">';
            $output .= '<span class="equipo-nombre">' . esc_html($partido['visitante']) . '</span>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    private function render_tabla_jugadores($contenido) {
        $output = '<div class="torneo-tabla-jugadores">';
        $output .= '<table class="torneo-tabla">';
        $output .= '<thead>';
        $output .= '<tr>';
        $output .= '<th class="jugador-col">Jugador</th>';
        $output .= '<th class="stat-col goles-col">Goles</th>';
        $output .= '<th class="stat-col">Asistencias</th>';
        $output .= '<th class="stat-col amarillas-col">Amarillas</th>';
        $output .= '<th class="stat-col rojas-col">Rojas</th>';
        $output .= '<th class="incidencia-col">Incidencia</th>';
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';
        
        foreach ($contenido as $jugador) {
            $output .= '<tr>';
            $output .= '<td class="jugador-cell"><strong>' . esc_html($jugador['jugador']) . '</strong></td>';
            $output .= '<td class="goles-cell"><strong>' . esc_html($jugador['goles']) . '</strong></td>';
            $output .= '<td class="asistencias-cell">' . esc_html($jugador['asistencias']) . '</td>';
            $output .= '<td class="amarillas-cell">' . esc_html($jugador['amarillas']) . '</td>';
            $output .= '<td class="rojas-cell">' . esc_html($jugador['rojas']) . '</td>';
            $output .= '<td class="incidencia-cell">' . esc_html($jugador['incidencia']) . '</td>';
            $output .= '</tr>';
        }
        
        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';
        
        return $output;
    }
    
    // Guardar tabla
    public function save_table() {
        if (!wp_verify_nonce($_POST['torneo_nonce'], 'torneo_save_table')) {
            wp_die('Error de seguridad');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permisos insuficientes');
        }
        
        global $wpdb;
        
        $tabla_id = isset($_POST['tabla_id']) ? intval($_POST['tabla_id']) : 0;
        $nombre = sanitize_text_field($_POST['nombre_tabla']);
        $tipo = sanitize_text_field($_POST['tipo_tabla']);
        $contenido = $_POST['contenido'];
        
        // Validar y sanitizar contenido según tipo
        $contenido_sanitizado = $this->sanitize_contenido($contenido, $tipo);
        $contenido_json = json_encode($contenido_sanitizado);
        
        if ($tabla_id > 0) {
            // Actualizar
            $result = $wpdb->update(
                $wpdb->prefix . 'torneo_tablas',
                array(
                    'nombre_tabla' => $nombre,
                    'contenido_json' => $contenido_json
                ),
                array('id' => $tabla_id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            // Crear nuevo
            $result = $wpdb->insert(
                $wpdb->prefix . 'torneo_tablas',
                array(
                    'nombre_tabla' => $nombre,
                    'tipo' => $tipo,
                    'contenido_json' => $contenido_json
                ),
                array('%s', '%s', '%s')
            );
        }
        
        $redirect_url = admin_url('admin.php?page=torneo-' . $tipo);
        if ($result !== false) {
            $redirect_url = add_query_arg('success', '1', $redirect_url);
        } else {
            $redirect_url = add_query_arg('error', '1', $redirect_url);
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    // Eliminar tabla
    public function delete_table() {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'torneo_delete_table')) {
            wp_die('Error de seguridad');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permisos insuficientes');
        }
        
        global $wpdb;
        
        $tabla_id = intval($_GET['tabla_id']);
        $tipo = sanitize_text_field($_GET['tipo']);
        
        $wpdb->delete(
            $wpdb->prefix . 'torneo_tablas',
            array('id' => $tabla_id),
            array('%d')
        );
        
        wp_redirect(admin_url('admin.php?page=torneo-' . $tipo . '&deleted=1'));
        exit;
    }
    
    private function sanitize_contenido($contenido, $tipo) {
        $sanitizado = array();
        
        foreach ($contenido as $fila) {
            if ($tipo === 'posiciones') {
                $sanitizado[] = array(
                    'posicion' => intval($fila['posicion']),
                    'club' => sanitize_text_field($fila['club']),
                    'pj' => intval($fila['pj']),
                    'pg' => intval($fila['pg']),
                    'pe' => intval($fila['pe']),
                    'pp' => intval($fila['pp']),
                    'gf' => intval($fila['gf']),
                    'gc' => intval($fila['gc']),
                    'pts' => intval($fila['pts'])
                );
            } elseif ($tipo === 'partidos') {
                $sanitizado[] = array(
                    'fecha' => sanitize_text_field($fila['fecha']),
                    'hora' => sanitize_text_field($fila['hora']),
                    'local' => sanitize_text_field($fila['local']),
                    'visitante' => sanitize_text_field($fila['visitante'])
                );
            } elseif ($tipo === 'jugadores') {
                $sanitizado[] = array(
                    'jugador' => sanitize_text_field($fila['jugador']),
                    'goles' => intval($fila['goles']),
                    'asistencias' => intval($fila['asistencias']),
                    'amarillas' => intval($fila['amarillas']),
                    'rojas' => intval($fila['rojas']),
                    'incidencia' => sanitize_textarea_field($fila['incidencia'])
                );
            }
        }
        
        return $sanitizado;
    }
    
    // API Endpoints
    public function register_api_endpoints() {
        // Incluir funciones de API
        include_once TORNEO_SIMPLE_PATH . 'api/api-endpoints.php';
        
        // Endpoints principales
        register_rest_route('torneo-simple/v1', '/tablas/(?P<tipo>posiciones|partidos|jugadores)', array(
            'methods' => array('GET', 'POST'),
            'callback' => 'torneo_simple_api_tablas',
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('torneo-simple/v1', '/tabla/(?P<id>\d+)', array(
            'methods' => array('GET', 'PUT', 'DELETE'),
            'callback' => 'torneo_simple_api_tabla',
            'permission_callback' => '__return_true'
        ));
        
        // Endpoint para estadísticas generales
        register_rest_route('torneo-simple/v1', '/estadisticas', array(
            'methods' => 'GET',
            'callback' => 'torneo_simple_api_estadisticas',
            'permission_callback' => '__return_true'
        ));
        
        // Endpoint para búsqueda
        register_rest_route('torneo-simple/v1', '/buscar', array(
            'methods' => 'GET',
            'callback' => 'torneo_simple_api_buscar',
            'permission_callback' => '__return_true'
        ));
    }
    
    // Import/Export CSV
    public function import_csv() {
        include TORNEO_SIMPLE_PATH . 'includes/csv-handler.php';
        torneo_simple_import_csv();
    }
    
    public function export_csv() {
        include TORNEO_SIMPLE_PATH . 'includes/csv-handler.php';
        torneo_simple_export_csv();
    }
    
    public function export_all_csv() {
        include TORNEO_SIMPLE_PATH . 'includes/csv-handler.php';
        torneo_simple_export_all_csv();
    }
}

// Inicializar el plugin
new TorneoSimple();
?>