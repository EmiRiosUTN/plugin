<?php
if (!defined('ABSPATH')) {
    exit;
}

function torneo_simple_import_csv() {
    // Verificar nonce
    if (!isset($_POST['torneo_csv_nonce']) || !wp_verify_nonce($_POST['torneo_csv_nonce'], 'torneo_csv_import')) {
        wp_die('Error de seguridad al verificar el formulario. Por favor, intenta nuevamente.');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para realizar esta acción.');
    }
    
    global $wpdb;
    
    // Validar campos requeridos
    if (empty($_POST['tipo']) || empty($_POST['nombre_tabla'])) {
        wp_die('Faltan campos requeridos: tipo y nombre de tabla.');
    }
    
    $tipo = sanitize_text_field($_POST['tipo']);
    $nombre_tabla = sanitize_text_field($_POST['nombre_tabla']);
    $valid_types = array('posiciones', 'partidos', 'jugadores');
    
    if (!in_array($tipo, $valid_types)) {
        wp_die('Tipo de tabla no válido. Tipos permitidos: ' . implode(', ', $valid_types));
    }
    
    // Validar archivo subido
    if (!isset($_FILES['csv_file'])) {
        wp_die('No se ha seleccionado ningún archivo.');
    }
    
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_messages = array(
            UPLOAD_ERR_INI_SIZE => 'El archivo es demasiado grande (límite del servidor).',
            UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente.',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal.',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo.',
            UPLOAD_ERR_EXTENSION => 'Extensión de archivo no permitida.'
        );
        
        $error_code = $_FILES['csv_file']['error'];
        $error_message = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Error desconocido al subir el archivo.';
        wp_die('Error al subir el archivo: ' . $error_message);
    }
    
    // Validar tipo de archivo
    $file_info = pathinfo($_FILES['csv_file']['name']);
    if (!isset($file_info['extension']) || strtolower($file_info['extension']) !== 'csv') {
        wp_die('El archivo debe ser de tipo CSV (.csv).');
    }
    
    // Validar tamaño del archivo (máximo 2MB)
    if ($_FILES['csv_file']['size'] > 2 * 1024 * 1024) {
        wp_die('El archivo es demasiado grande. Tamaño máximo permitido: 2MB.');
    }
    
    $file_path = $_FILES['csv_file']['tmp_name'];
    
    // Verificar que el archivo temporal existe
    if (!file_exists($file_path)) {
        wp_die('El archivo temporal no se encuentra disponible.');
    }
    
    $file_handle = fopen($file_path, 'r');
    
    if (!$file_handle) {
        wp_die('No se pudo leer el archivo. Verifica que el archivo no esté corrupto.');
    }
    
    $contenido = array();
    $line_number = 0;
    $errors = array();
    
    try {
        // Leer header (primera línea)
        $header = fgetcsv($file_handle);
        
        if ($header === false || empty($header)) {
            fclose($file_handle);
            wp_die('El archivo CSV está vacío o no tiene el formato correcto.');
        }
        
        // Limpiar headers (quitar espacios y caracteres especiales)
        $header = array_map(function($h) {
            return trim(strtolower($h));
        }, $header);
        
        // Validar header según tipo
        $expected_headers = torneo_get_expected_headers($tipo);
        if (!torneo_validate_headers($header, $expected_headers)) {
            fclose($file_handle);
            wp_die('El formato del CSV no es correcto.<br><strong>Formato esperado para ' . $tipo . ':</strong><br>' . implode(', ', $expected_headers) . '<br><br><strong>Formato encontrado:</strong><br>' . implode(', ', $header));
        }
        
        // Leer datos
        while (($data = fgetcsv($file_handle)) !== FALSE) {
            $line_number++;
            
            // Saltar líneas vacías
            if (empty(array_filter($data))) {
                continue;
            }
            
            if (count($data) !== count($header)) {
                $errors[] = "Línea $line_number: número incorrecto de columnas (esperado: " . count($header) . ", encontrado: " . count($data) . ")";
                continue;
            }
            
            $row_data = array_combine($header, $data);
            $sanitized_row = torneo_sanitize_csv_row($row_data, $tipo);
            
            if ($sanitized_row === false) {
                $errors[] = "Línea $line_number: datos inválidos";
                continue;
            }
            
            $contenido[] = $sanitized_row;
        }
        
        fclose($file_handle);
        
    } catch (Exception $e) {
        if ($file_handle) {
            fclose($file_handle);
        }
        wp_die('Error al procesar el archivo: ' . $e->getMessage());
    }
    
    // Verificar si se importaron datos
    if (empty($contenido)) {
        $error_message = 'No se pudieron importar datos válidos del CSV.';
        if (!empty($errors)) {
            $error_message .= '<br><br><strong>Errores encontrados:</strong><ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
        }
        wp_die($error_message);
    }
    
    // Guardar en base de datos
    $contenido_json = json_encode($contenido, JSON_UNESCAPED_UNICODE);
    
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
        $mensaje = 'Importación exitosa: ' . count($contenido) . ' filas importadas';
        if (!empty($errors)) {
            $mensaje .= ' (' . count($errors) . ' errores omitidos)';
        }
        $redirect_url = add_query_arg(array(
            'success' => '1', 
            'imported' => count($contenido),
            'message' => urlencode($mensaje)
        ), $redirect_url);
    } else {
        $redirect_url = add_query_arg(array(
            'error' => '1',
            'message' => urlencode('Error al guardar en la base de datos: ' . $wpdb->last_error)
        ), $redirect_url);
    }
    
    wp_redirect($redirect_url);
    exit;
}

function torneo_simple_export_csv() {
    // Verificar nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'torneo_csv_export')) {
        wp_die('Error de seguridad al exportar CSV. Por favor, intenta nuevamente.');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para exportar datos.');
    }
    
    global $wpdb;
    
    if (!isset($_GET['tabla_id']) || empty($_GET['tabla_id'])) {
        wp_die('ID de tabla no especificado.');
    }
    
    $tabla_id = intval($_GET['tabla_id']);
    
    $tabla = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE id = %d",
        $tabla_id
    ));
    
    if (!$tabla) {
        wp_die('Tabla no encontrada con ID: ' . $tabla_id);
    }
    
    // Configurar headers para descarga
    $filename = sanitize_file_name($tabla->nombre_tabla) . '_' . $tabla->tipo . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    // Crear output
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 (para que Excel abra correctamente los acentos)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    $expected_headers = torneo_get_expected_headers($tabla->tipo);
    fputcsv($output, $expected_headers);
    
    // Datos
    $contenido = json_decode($tabla->contenido_json, true);
    if (!empty($contenido)) {
        foreach ($contenido as $fila) {
            $row_data = array();
            foreach ($expected_headers as $header) {
                $row_data[] = isset($fila[$header]) ? $fila[$header] : '';
            }
            fputcsv($output, $row_data);
        }
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
    
    // Normalizar headers esperados
    $expected_normalized = array_map('strtolower', $expected_headers);
    
    foreach ($expected_normalized as $expected) {
        if (!in_array($expected, $actual_headers)) {
            return false;
        }
    }
    
    return true;
}

function torneo_sanitize_csv_row($row_data, $tipo) {
    try {
        switch ($tipo) {
            case 'posiciones':
                // Validar campos requeridos
                if (empty($row_data['club'])) {
                    return false;
                }
                
                return array(
                    'posicion' => intval($row_data['posicion']),
                    'club' => sanitize_text_field(trim($row_data['club'])),
                    'pj' => intval($row_data['pj']),
                    'pg' => intval($row_data['pg']),
                    'pe' => intval($row_data['pe']),
                    'pp' => intval($row_data['pp']),
                    'gf' => intval($row_data['gf']),
                    'gc' => intval($row_data['gc']),
                    'pts' => intval($row_data['pts'])
                );
                
            case 'partidos':
                // Validar campos requeridos
                if (empty($row_data['fecha']) || empty($row_data['hora']) || 
                    empty($row_data['local']) || empty($row_data['visitante'])) {
                    return false;
                }
                
                // Validar y normalizar formato de fecha
                $fecha = trim($row_data['fecha']);
                
                // Intentar diferentes formatos de fecha
                $fecha_formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];
                $fecha_valida = false;
                
                foreach ($fecha_formatos as $formato) {
                    $fecha_obj = DateTime::createFromFormat($formato, $fecha);
                    if ($fecha_obj !== false) {
                        $fecha = $fecha_obj->format('Y-m-d');
                        $fecha_valida = true;
                        break;
                    }
                }
                
                if (!$fecha_valida) {
                    return false;
                }
                
                // Validar y normalizar formato de hora
                $hora = trim($row_data['hora']);
                if (!preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
                    return false;
                }
                
                // Asegurar formato HH:MM
                $hora_parts = explode(':', $hora);
                $hora = sprintf('%02d:%02d', intval($hora_parts[0]), intval($hora_parts[1]));
                
                return array(
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'local' => sanitize_text_field(trim($row_data['local'])),
                    'visitante' => sanitize_text_field(trim($row_data['visitante']))
                );
                
            case 'jugadores':
                // Validar campo requerido
                if (empty($row_data['jugador'])) {
                    return false;
                }
                
                return array(
                    'jugador' => sanitize_text_field(trim($row_data['jugador'])),
                    'goles' => intval($row_data['goles']),
                    'asistencias' => intval($row_data['asistencias']),
                    'amarillas' => intval($row_data['amarillas']),
                    'rojas' => intval($row_data['rojas']),
                    'incidencia' => sanitize_textarea_field(trim($row_data['incidencia']))
                );
                
            default:
                return false;
        }
    } catch (Exception $e) {
        return false;
    }
}

// Función para exportar todas las tablas de un tipo
function torneo_simple_export_all_csv() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'torneo_csv_export_all')) {
        wp_die('Error de seguridad al exportar CSV. Por favor, intenta nuevamente.');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para exportar datos.');
    }
    
    global $wpdb;
    
    if (!isset($_GET['tipo']) || empty($_GET['tipo'])) {
        wp_die('Tipo de tabla no especificado.');
    }
    
    $tipo = sanitize_text_field($_GET['tipo']);
    $valid_types = array('posiciones', 'partidos', 'jugadores');
    
    if (!in_array($tipo, $valid_types)) {
        wp_die('Tipo de tabla no válido: ' . $tipo);
    }
    
    $tablas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE tipo = %s ORDER BY created_at DESC",
        $tipo
    ));
    
    if (empty($tablas)) {
        wp_die('No hay tablas de tipo "' . $tipo . '" para exportar.');
    }
    
    // Configurar headers para descarga
    $filename = 'todas_las_tablas_' . $tipo . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
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
        if (!empty($contenido)) {
            foreach ($contenido as $fila) {
                $row_data = array($tabla->nombre_tabla);
                foreach (array_slice($expected_headers, 1) as $header) {
                    $row_data[] = isset($fila[$header]) ? $fila[$header] : '';
                }
                fputcsv($output, $row_data);
            }
        }
    }
    
    fclose($output);
    exit;
}
?>