<?php
if (!defined('ABSPATH')) {
    exit;
}

function torneo_mostrar_jugadores($atts) {
    global $wpdb;
    
    // Atributos del shortcode
    $atts = shortcode_atts(array(
        'liga' => '', // ID específico de liga o vacío para todas
        'orden' => 'goles', // goles, asistencias, amarillas, rojas
        'limite' => 20, // Número máximo de jugadores por liga
    ), $atts);
    
    $output = '';
    $limite = intval($atts['limite']);
    $orden = sanitize_text_field($atts['orden']);
    
    // Validar orden
    $ordenes_validos = array('goles', 'asistencias', 'amarillas', 'rojas');
    if (!in_array($orden, $ordenes_validos)) {
        $orden = 'goles';
    }
    
    // Si se especifica una liga específica
    if (!empty($atts['liga'])) {
        $liga_id = intval($atts['liga']);
        $ligas = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}torneo_ligas WHERE id = %d",
            $liga_id
        ));
    } else {
        // Obtener todas las ligas
        $ligas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}torneo_ligas ORDER BY nombre ASC");
    }
    
    foreach ($ligas as $liga) {
        // Obtener estadísticas de jugadores para esta liga
        $estadisticas = $wpdb->get_results($wpdb->prepare("
            SELECT e.*, j.nombre as jugador_nombre, eq.nombre as equipo_nombre, eq.logo as equipo_logo
            FROM {$wpdb->prefix}torneo_estadisticas_jugadores e
            LEFT JOIN {$wpdb->prefix}torneo_jugadores j ON e.jugador_id = j.id
            LEFT JOIN {$wpdb->prefix}torneo_equipos eq ON j.equipo_id = eq.id
            WHERE e.liga_id = %d
            ORDER BY e.{$orden} DESC, j.nombre ASC
            LIMIT %d
        ", $liga->id, $limite));
        
        if (!empty($estadisticas)) {
            $output .= '<div class="torneo-tabla-container">';
            $output .= '<h3 class="torneo-liga-titulo">' . esc_html($liga->nombre) . '</h3>';
            $output .= '<div class="torneo-tabla-jugadores">';
            $output .= '<table class="torneo-tabla">';
            $output .= '<thead>';
            $output .= '<tr>';
            $output .= '<th class="jugador-col">Jugador</th>';
            $output .= '<th class="equipo-col">Equipo</th>';
            $output .= '<th class="stat-col goles-col">Goles</th>';
            $output .= '<th class="stat-col">Asistencias</th>';
            $output .= '<th class="stat-col amarillas-col">Amarillas</th>';
            $output .= '<th class="stat-col rojas-col">Rojas</th>';
            $output .= '<th class="incidencia-col">Incidencia</th>';
            $output .= '</tr>';
            $output .= '</thead>';
            $output .= '<tbody>';
            
            foreach ($estadisticas as $est) {
                $output .= '<tr>';
                
                // Jugador
                $output .= '<td class="jugador-cell">';
                $output .= '<strong>' . esc_html($est->jugador_nombre) . '</strong>';
                $output .= '</td>';
                
                // Equipo
                $output .= '<td class="equipo-cell">';
                if (!empty($est->equipo_logo)) {
                    $output .= '<img src="' . esc_url($est->equipo_logo) . '" alt="' . esc_attr($est->equipo_nombre) . '" class="equipo-logo-small">';
                }
                $output .= '<span class="equipo-nombre">' . esc_html($est->equipo_nombre) . '</span>';
                $output .= '</td>';
                
                // Estadísticas
                $output .= '<td class="goles-cell"><strong>' . $est->goles . '</strong></td>';
                $output .= '<td class="asistencias-cell">' . $est->asistencias . '</td>';
                $output .= '<td class="amarillas-cell">' . $est->amarillas . '</td>';
                $output .= '<td class="rojas-cell">' . $est->rojas . '</td>';
                
                // Incidencia
                $output .= '<td class="incidencia-cell">';
                if (!empty($est->incidencia)) {
                    $output .= '<span class="incidencia-texto" title="' . esc_attr($est->incidencia) . '">';
                    $output .= esc_html(wp_trim_words($est->incidencia, 8));
                    $output .= '</span>';
                } else {
                    $output .= '<span class="sin-incidencia">-</span>';
                }
                $output .= '</td>';
                
                $output .= '</tr>';
            }
            
            $output .= '</tbody>';
            $output .= '</table>';
            $output .= '</div>';
            $output .= '</div>';
        }
    }
    
    if (empty($output)) {
        $output = '<div class="torneo-no-data">No hay estadísticas de jugadores disponibles.</div>';
    }
    
    return $output;
}
?>