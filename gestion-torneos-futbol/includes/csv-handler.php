<?php
if (!defined('ABSPATH')) {
    exit;
}

function torneo_simple_import_csv() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['torneo_csv_nonce'], 'torneo_csv_import')) {
        wp_die('Error de seguridad');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('Permisos insuficientes');
    }
    
    global $wpdb;
    
    $tipo = sanitize_text_field($_POST['tipo']);
    $nombre_tabla = sanitize_text_field($_POST['nombre_tabla']);
    $valid_types = array('posiciones', 'partidos', 'jugadores');
    
    if (!in_array($tipo, $valid_types)) {
        wp_die('Tipo de tabla no válido');
    }
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_die('Error al subir el archivo');
    }
    
    $file_path = $_FILES['csv_file']['tmp_name'];
    $file_handle = fopen($file_path, 'r');
    
    if (!$file_handle) {
        wp_die('No se pudo leer el archivo');
    }
    
    $contenido = array();
    $line_number = 0;
    
    // Leer header (primera línea)
    $header = fgetcsv($file_handle);
    
    // Validar header según tipo
    $expected_headers = torneo_get_expected_headers($tipo);
    if (!torneo_validate_headers($header, $expected_headers)) {
        fclose($file_handle);
        wp_die('El formato del CSV no es correcto. Formato esperado: ' . implode(',', $expected_headers));
    }
    
    // Leer datos
    while (($data = fgetcsv($file_handle)) !== FALSE) {
        $line_number++;
        
        if (count($data) !== count($header)) {
            continue; // Saltar líneas con número incorrecto de columnas
        }
        
        $row_data = array_combine($header, $data);
        $sanitized_row = torneo_sanitize_csv_row($row_data, $tipo);
        
        if ($sanitized_row) {
            $contenido[] = $sanitized_row;
        }
    }
    
    fclose($file_handle);
    
    if (empty($contenido)) {
        wp_die('No se pudieron importar datos válidos del CSV');
    }
    
    // Guardar en base de datos
    $contenido_json = json_encode($contenido);
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'torneo_tablas',
        array(
            'nombre_tabla' => $nombre_tabla,
            'tipo' => $tipo,
            'contenido_json' => $contenido_json
        ),
        array('%s', '%s', '%s')
    );
    
    // Redirigir con mensaje
    $redirect_url = admin_url('admin.php?page=torneo-' . $tipo);
    if ($result !== false) {
        $redirect_url = add_query_arg(array('success' => '1', 'imported' => count($contenido)), $redirect_url);
    } else {
        $redirect_url = add_query_arg('error', '1', $redirect_url);
    }
    
    wp_redirect($redirect_url);
    exit;
}

function torneo_simple_export_csv() {
    // Verificar nonce
    if (!wp_verify_nonce($_GET['_wpnonce'], 'torneo_csv_export')) {
        wp_die('Error de seguridad');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('Permisos insuficientes');
    }
    
    global $wpdb;
    
    $tabla_id = intval($_GET['tabla_id']);
    
    $tabla = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE id = %d",
        $tabla_id
    ));
    
    if (!$tabla) {
        wp_die('Tabla no encontrada');
    }
    
    // Configurar headers para descarga
    $filename = sanitize_file_name($tabla->nombre_tabla) . '_' . $tabla->tipo . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Crear output
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    $expected_headers = torneo_get_expected_headers($tabla->tipo);
    fputcsv($output, $expected_headers);
    
    // Datos
    $contenido = json_decode($tabla->contenido_json, true);
    foreach ($contenido as $fila) {
        $row_data = array();
        foreach ($expected_headers as $header) {
            $row_data[] = isset($fila[$header]) ? $fila[$header] : '';
        }
        fputcsv($output, $row_data);
    }
    
    fclose($output);
    exit;
}

function torneo_get_expected_headers($tipo) {
    switch ($tipo) {
        case 'posiciones':
            return array('posicion', 'club', 'pj', 'pg', 'pe', 'pp', 'gf', 'gc', 'pts');
        case 'partidos':
            return array('fecha', 'hora', 'local', 'visitante');
        case 'jugadores':
            return array('jugador', 'goles', 'asistencias', 'amarillas', 'rojas', 'incidencia');
        default:
            return array();
    }
}

function torneo_validate_headers($actual_headers, $expected_headers) {
    if (count($actual_headers) !== count($expected_headers)) {
        return false;
    }
    
    foreach ($expected_headers as $expected) {
        if (!in_array($expected, $actual_headers)) {
            return false;
        }
    }
    
    return true;
}

function torneo_sanitize_csv_row($row_data, $tipo) {
    switch ($tipo) {
        case 'posiciones':
            return array(
                'posicion' => intval($row_data['posicion']),
                'club' => sanitize_text_field($row_data['club']),
                'pj' => intval($row_data['pj']),
                'pg' => intval($row_data['pg']),
                'pe' => intval($row_data['pe']),
                'pp' => intval($row_data['pp']),
                'gf' => intval($row_data['gf']),
                'gc' => intval($row_data['gc']),
                'pts' => intval($row_data['pts'])
            );
            
        case 'partidos':
            // Validar formato de fecha
            $fecha = sanitize_text_field($row_data['fecha']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                return false; // Fecha inválida
            }
            
            // Validar formato de hora
            $hora = sanitize_text_field($row_data['hora']);
            if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
                return false; // Hora inválida
            }
            
            return array(
                'fecha' => $fecha,
                'hora' => $hora,
                'local' => sanitize_text_field($row_data['local']),
                'visitante' => sanitize_text_field($row_data['visitante'])
            );
            
        case 'jugadores':
            return array(
                'jugador' => sanitize_text_field($row_data['jugador']),
                'goles' => intval($row_data['goles']),
                'asistencias' => intval($row_data['asistencias']),
                'amarillas' => intval($row_data['amarillas']),
                'rojas' => intval($row_data['rojas']),
                'incidencia' => sanitize_textarea_field($row_data['incidencia'])
            );
            
        default:
            return false;
    }
}

// Función para exportar todas las tablas de un tipo
function torneo_simple_export_all_csv() {
    if (!wp_verify_nonce($_GET['_wpnonce'], 'torneo_csv_export_all')) {
        wp_die('Error de seguridad');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Permisos insuficientes');
    }
    
    global $wpdb;
    
    $tipo = sanitize_text_field($_GET['tipo']);
    $valid_types = array('posiciones', 'partidos', 'jugadores');
    
    if (!in_array($tipo, $valid_types)) {
        wp_die('Tipo no válido');
    }
    
    $tablas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE tipo = %s ORDER BY created_at DESC",
        $tipo
    ));
    
    if (empty($tablas)) {
        wp_die('No hay tablas para exportar');
    }
    
    // Configurar headers para descarga
    $filename = 'todas_las_tablas_' . $tipo . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Crear output
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header con campo adicional para nombre de tabla
    $expected_headers = torneo_get_expected_headers($tipo);
    array_unshift($expected_headers, 'nombre_tabla');
    fputcsv($output, $expected_headers);
    
    // Datos de todas las tablas
    foreach ($tablas as $tabla) {
        $contenido = json_decode($tabla->contenido_json, true);
        foreach ($contenido as $fila) {
            $row_data = array($tabla->nombre_tabla);
            foreach (array_slice($expected_headers, 1) as $header) {
                $row_data[] = isset($fila[$header]) ? $fila[$header] : '';
            }
            fputcsv($output, $row_data);
        }
    }
    
    fclose($output);
    exit;
}
?>