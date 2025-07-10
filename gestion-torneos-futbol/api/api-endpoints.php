<?php
if (!defined('ABSPATH')) {
    exit;
}

// Funciones de la API para el plugin simple

function torneo_simple_api_tablas($request) {
    global $wpdb;
    
    $tipo = $request['tipo'];
    $method = $request->get_method();
    
    // Validar tipo
    $tipos_validos = array('posiciones', 'partidos', 'jugadores');
    if (!in_array($tipo, $tipos_validos)) {
        return new WP_Error('tipo_invalido', 'Tipo de tabla no válido', array('status' => 400));
    }
    
    if ($method === 'GET') {
        return torneo_simple_api_get_tablas($tipo);
    } elseif ($method === 'POST') {
        return torneo_simple_api_create_tabla($tipo, $request);
    }
    
    return new WP_Error('method_not_allowed', 'Método no permitido', array('status' => 405));
}

function torneo_simple_api_tabla($request) {
    global $wpdb;
    
    $tabla_id = intval($request['id']);
    $method = $request->get_method();
    
    // Verificar que la tabla existe
    $tabla = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE id = %d",
        $tabla_id
    ));
    
    if (!$tabla) {
        return new WP_Error('tabla_not_found', 'Tabla no encontrada', array('status' => 404));
    }
    
    switch ($method) {
        case 'GET':
            return torneo_simple_api_get_tabla($tabla);
        case 'PUT':
            return torneo_simple_api_update_tabla($tabla, $request);
        case 'DELETE':
            return torneo_simple_api_delete_tabla($tabla_id);
        default:
            return new WP_Error('method_not_allowed', 'Método no permitido', array('status' => 405));
    }
}

function torneo_simple_api_get_tablas($tipo) {
    global $wpdb;
    
    $tablas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE tipo = %s ORDER BY created_at DESC",
        $tipo
    ));
    
    $response = array();
    foreach ($tablas as $tabla) {
        $contenido = json_decode($tabla->contenido_json, true);
        
        $response[] = array(
            'id' => intval($tabla->id),
            'nombre' => $tabla->nombre_tabla,
            'tipo' => $tabla->tipo,
            'contenido' => $contenido ?: array(),
            'total_filas' => count($contenido ?: array()),
            'created_at' => $tabla->created_at,
            'updated_at' => $tabla->updated_at
        );
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'data' => $response,
        'total' => count($response),
        'tipo' => $tipo
    ));
}

function torneo_simple_api_create_tabla($tipo, $request) {
    global $wpdb;
    
    $body = $request->get_json_params();
    
    // Validar datos requeridos
    if (empty($body['nombre'])) {
        return new WP_Error('missing_data', 'El campo nombre es requerido', array('status' => 400));
    }
    
    if (empty($body['contenido']) || !is_array($body['contenido'])) {
        return new WP_Error('missing_data', 'El campo contenido es requerido y debe ser un array', array('status' => 400));
    }
    
    $nombre = sanitize_text_field($body['nombre']);
    $contenido = torneo_simple_sanitize_contenido($body['contenido'], $tipo);
    
    if ($contenido === false) {
        return new WP_Error('invalid_data', 'Los datos del contenido no son válidos para el tipo ' . $tipo, array('status' => 400));
    }
    
    $contenido_json = json_encode($contenido);
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'torneo_tablas',
        array(
            'nombre_tabla' => $nombre,
            'tipo' => $tipo,
            'contenido_json' => $contenido_json
        ),
        array('%s', '%s', '%s')
    );
    
    if ($result === false) {
        return new WP_Error('insert_failed', 'Error al crear la tabla', array('status' => 500));
    }
    
    $nueva_tabla_id = $wpdb->insert_id;
    
    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Tabla creada exitosamente',
        'data' => array(
            'id' => $nueva_tabla_id,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'contenido' => $contenido,
            'total_filas' => count($contenido)
        )
    ));
}

function torneo_simple_api_get_tabla($tabla) {
    $contenido = json_decode($tabla->contenido_json, true);
    
    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'id' => intval($tabla->id),
            'nombre' => $tabla->nombre_tabla,
            'tipo' => $tabla->tipo,
            'contenido' => $contenido ?: array(),
            'total_filas' => count($contenido ?: array()),
            'created_at' => $tabla->created_at,
            'updated_at' => $tabla->updated_at
        )
    ));
}

function torneo_simple_api_update_tabla($tabla, $request) {
    global $wpdb;
    
    $body = $request->get_json_params();
    
    $nombre = isset($body['nombre']) ? sanitize_text_field($body['nombre']) : $tabla->nombre_tabla;
    $contenido = isset($body['contenido']) ? $body['contenido'] : json_decode($tabla->contenido_json, true);
    
    if (!is_array($contenido)) {
        return new WP_Error('invalid_data', 'El contenido debe ser un array', array('status' => 400));
    }
    
    $contenido_sanitizado = torneo_simple_sanitize_contenido($contenido, $tabla->tipo);
    
    if ($contenido_sanitizado === false) {
        return new WP_Error('invalid_data', 'Los datos del contenido no son válidos', array('status' => 400));
    }
    
    $contenido_json = json_encode($contenido_sanitizado);
    
    $result = $wpdb->update(
        $wpdb->prefix . 'torneo_tablas',
        array(
            'nombre_tabla' => $nombre,
            'contenido_json' => $contenido_json
        ),
        array('id' => $tabla->id),
        array('%s', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        return new WP_Error('update_failed', 'Error al actualizar la tabla', array('status' => 500));
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Tabla actualizada exitosamente',
        'data' => array(
            'id' => intval($tabla->id),
            'nombre' => $nombre,
            'tipo' => $tabla->tipo,
            'contenido' => $contenido_sanitizado,
            'total_filas' => count($contenido_sanitizado)
        )
    ));
}

function torneo_simple_api_delete_tabla($tabla_id) {
    global $wpdb;
    
    $result = $wpdb->delete(
        $wpdb->prefix . 'torneo_tablas',
        array('id' => $tabla_id),
        array('%d')
    );
    
    if ($result === false) {
        return new WP_Error('delete_failed', 'Error al eliminar la tabla', array('status' => 500));
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Tabla eliminada exitosamente',
        'deleted_id' => $tabla_id
    ));
}

function torneo_simple_sanitize_contenido($contenido, $tipo) {
    if (!is_array($contenido)) {
        return false;
    }
    
    $sanitizado = array();
    
    foreach ($contenido as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        
        switch ($tipo) {
            case 'posiciones':
                $fila_sanitizada = torneo_simple_sanitize_posicion($fila);
                break;
            case 'partidos':
                $fila_sanitizada = torneo_simple_sanitize_partido($fila);
                break;
            case 'jugadores':
                $fila_sanitizada = torneo_simple_sanitize_jugador($fila);
                break;
            default:
                return false;
        }
        
        if ($fila_sanitizada !== false) {
            $sanitizado[] = $fila_sanitizada;
        }
    }
    
    return $sanitizado;
}

function torneo_simple_sanitize_posicion($fila) {
    $required_fields = array('posicion', 'club');
    
    foreach ($required_fields as $field) {
        if (!isset($fila[$field]) || empty($fila[$field])) {
            return false;
        }
    }
    
    return array(
        'posicion' => intval($fila['posicion']),
        'club' => sanitize_text_field($fila['club']),
        'pj' => isset($fila['pj']) ? intval($fila['pj']) : 0,
        'pg' => isset($fila['pg']) ? intval($fila['pg']) : 0,
        'pe' => isset($fila['pe']) ? intval($fila['pe']) : 0,
        'pp' => isset($fila['pp']) ? intval($fila['pp']) : 0,
        'gf' => isset($fila['gf']) ? intval($fila['gf']) : 0,
        'gc' => isset($fila['gc']) ? intval($fila['gc']) : 0,
        'pts' => isset($fila['pts']) ? intval($fila['pts']) : 0
    );
}

function torneo_simple_sanitize_partido($fila) {
    $required_fields = array('fecha', 'hora', 'local', 'visitante');
    
    foreach ($required_fields as $field) {
        if (!isset($fila[$field]) || empty($fila[$field])) {
            return false;
        }
    }
    
    // Validar formato de fecha
    $fecha = sanitize_text_field($fila['fecha']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return false;
    }
    
    // Validar formato de hora
    $hora = sanitize_text_field($fila['hora']);
    if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
        return false;
    }
    
    return array(
        'fecha' => $fecha,
        'hora' => $hora,
        'local' => sanitize_text_field($fila['local']),
        'visitante' => sanitize_text_field($fila['visitante'])
    );
}

function torneo_simple_sanitize_jugador($fila) {
    if (!isset($fila['jugador']) || empty($fila['jugador'])) {
        return false;
    }
    
    return array(
        'jugador' => sanitize_text_field($fila['jugador']),
        'goles' => isset($fila['goles']) ? intval($fila['goles']) : 0,
        'asistencias' => isset($fila['asistencias']) ? intval($fila['asistencias']) : 0,
        'amarillas' => isset($fila['amarillas']) ? intval($fila['amarillas']) : 0,
        'rojas' => isset($fila['rojas']) ? intval($fila['rojas']) : 0,
        'incidencia' => isset($fila['incidencia']) ? sanitize_textarea_field($fila['incidencia']) : ''
    );
}

// Endpoint para obtener estadísticas generales
function torneo_simple_api_estadisticas($request) {
    global $wpdb;
    
    $stats = $wpdb->get_results("
        SELECT 
            tipo,
            COUNT(*) as total_tablas,
            SUM(JSON_LENGTH(contenido_json)) as total_filas
        FROM {$wpdb->prefix}torneo_tablas 
        GROUP BY tipo
    ");
    
    $response = array(
        'posiciones' => array('tablas' => 0, 'filas' => 0),
        'partidos' => array('tablas' => 0, 'filas' => 0),
        'jugadores' => array('tablas' => 0, 'filas' => 0)
    );
    
    foreach ($stats as $stat) {
        $response[$stat->tipo] = array(
            'tablas' => intval($stat->total_tablas),
            'filas' => intval($stat->total_filas)
        );
    }
    
    $total_tablas = array_sum(array_column($response, 'tablas'));
    $total_filas = array_sum(array_column($response, 'filas'));
    
    return rest_ensure_response(array(
        'success' => true,
        'data' => $response,
        'totales' => array(
            'total_tablas' => $total_tablas,
            'total_filas' => $total_filas
        )
    ));
}

// Endpoint para búsqueda
function torneo_simple_api_buscar($request) {
    global $wpdb;
    
    $termino = sanitize_text_field($request->get_param('q'));
    $tipo = $request->get_param('tipo');
    
    if (empty($termino)) {
        return new WP_Error('missing_param', 'Parámetro de búsqueda requerido', array('status' => 400));
    }
    
    $where_tipo = '';
    if (!empty($tipo) && in_array($tipo, array('posiciones', 'partidos', 'jugadores'))) {
        $where_tipo = $wpdb->prepare(" AND tipo = %s", $tipo);
    }
    
    $tablas = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}torneo_tablas 
        WHERE (nombre_tabla LIKE %s OR contenido_json LIKE %s) $where_tipo
        ORDER BY updated_at DESC
        LIMIT 20
    ", '%' . $termino . '%', '%' . $termino . '%'));
    
    $results = array();
    foreach ($tablas as $tabla) {
        $contenido = json_decode($tabla->contenido_json, true);
        
        $results[] = array(
            'id' => intval($tabla->id),
            'nombre' => $tabla->nombre_tabla,
            'tipo' => $tabla->tipo,
            'total_filas' => count($contenido ?: array()),
            'updated_at' => $tabla->updated_at
        );
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'data' => $results,
        'total' => count($results),
        'termino_busqueda' => $termino
    ));
}
?>