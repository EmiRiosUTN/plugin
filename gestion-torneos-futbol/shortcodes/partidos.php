<?php
if (!defined('ABSPATH')) {
    exit;
}

function torneo_mostrar_partidos($atts) {
    global $wpdb;
    
    // Atributos del shortcode
    $atts = shortcode_atts(array(
        'liga' => '', // ID específico de liga o vacío para todas
        'limite' => 10, // Número máximo de partidos por liga
    ), $atts);
    
    $output = '';
    $limite = intval($atts['limite']);
    
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
        // Obtener próximos partidos para esta liga
        $partidos = $wpdb->get_results($wpdb->prepare("
            SELECT p.*, 
                   el.nombre as equipo_local_nombre, el.logo as equipo_local_logo,
                   ev.nombre as equipo_visitante_nombre, ev.logo as equipo_visitante_logo
            FROM {$wpdb->prefix}torneo_partidos p
            LEFT JOIN {$wpdb->prefix}torneo_equipos el ON p.equipo_local_id = el.id
            LEFT JOIN {$wpdb->prefix}torneo_equipos ev ON p.equipo_visitante_id = ev.id
            WHERE p.liga_id = %d AND p.fecha_hora >= NOW()
            ORDER BY p.fecha_hora ASC
            LIMIT %d
        ", $liga->id, $limite));
        
        if (!empty($partidos)) {
            $output .= '<div class="torneo-tabla-container">';
            $output .= '<h3 class="torneo-liga-titulo">' . esc_html($liga->nombre) . '</h3>';
            $output .= '<div class="torneo-partidos-lista">';
            
            foreach ($partidos as $partido) {
                $fecha = new DateTime($partido->fecha_hora);
                $fecha_formateada = $fecha->format('d/m/Y');
                $hora_formateada = $fecha->format('H:i');
                
                $output .= '<div class="torneo-partido">';
                $output .= '<div class="partido-fecha">';
                $output .= '<span class="fecha">' . $fecha_formateada . '</span>';
                $output .= '<span class="hora">' . $hora_formateada . '</span>';
                $output .= '</div>';
                
                $output .= '<div class="partido-enfrentamiento">';
                
                // Equipo local
                $output .= '<div class="equipo equipo-local">';
                if (!empty($partido->equipo_local_logo)) {
                    $output .= '<img src="' . esc_url($partido->equipo_local_logo) . '" alt="' . esc_attr($partido->equipo_local_nombre) . '" class="equipo-logo">';
                } else {
                    $output .= '<div class="equipo-logo-placeholder"></div>';
                }
                $output .= '<span class="equipo-nombre">' . esc_html($partido->equipo_local_nombre) . '</span>';
                $output .= '</div>';
                
                // VS
                $output .= '<div class="vs">vs</div>';
                
                // Equipo visitante
                $output .= '<div class="equipo equipo-visitante">';
                if (!empty($partido->equipo_visitante_logo)) {
                    $output .= '<img src="' . esc_url($partido->equipo_visitante_logo) . '" alt="' . esc_attr($partido->equipo_visitante_nombre) . '" class="equipo-logo">';
                } else {
                    $output .= '<div class="equipo-logo-placeholder"></div>';
                }
                $output .= '<span class="equipo-nombre">' . esc_html($partido->equipo_visitante_nombre) . '</span>';
                $output .= '</div>';
                
                $output .= '</div>';
                $output .= '</div>';
            }
            
            $output .= '</div>';
            $output .= '</div>';
        }
    }
    
    if (empty($output)) {
        $output = '<div class="torneo-no-data">No hay próximos partidos programados.</div>';
    }
    
    return $output;
}
?>