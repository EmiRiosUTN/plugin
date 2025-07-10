<?php
if (!defined('ABSPATH')) {
    exit;
}

function torneo_mostrar_posiciones($atts) {
    global $wpdb;
    
    // Atributos del shortcode
    $atts = shortcode_atts(array(
        'liga' => '', // ID específico de liga o vacío para todas
    ), $atts);
    
    $output = '';
    
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
        // Obtener posiciones para esta liga
        $posiciones = $wpdb->get_results($wpdb->prepare("
            SELECT p.*, e.nombre as equipo_nombre, e.logo as equipo_logo
            FROM {$wpdb->prefix}torneo_posiciones p
            LEFT JOIN {$wpdb->prefix}torneo_equipos e ON p.equipo_id = e.id
            WHERE p.liga_id = %d
            ORDER BY p.posicion ASC
        ", $liga->id));
        
        if (!empty($posiciones)) {
            $output .= '<div class="torneo-tabla-container">';
            $output .= '<h3 class="torneo-liga-titulo">' . esc_html($liga->nombre) . '</h3>';
            $output .= '<div class="torneo-tabla-posiciones">';
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
            
            foreach ($posiciones as $pos) {
                $diferencia_gol = $pos->goles_favor - $pos->goles_contra;
                $dg_class = $diferencia_gol >= 0 ? 'positive' : 'negative';
                
                $output .= '<tr>';
                $output .= '<td class="pos-cell">' . $pos->posicion . '</td>';
                $output .= '<td class="club-cell">';
                
                if (!empty($pos->equipo_logo)) {
                    $output .= '<img src="' . esc_url($pos->equipo_logo) . '" alt="' . esc_attr($pos->equipo_nombre) . '" class="equipo-logo">';
                } else {
                    $output .= '<div class="equipo-logo-placeholder"></div>';
                }
                
                $output .= '<span class="equipo-nombre">' . esc_html($pos->equipo_nombre) . '</span>';
                $output .= '</td>';
                $output .= '<td>' . $pos->partidos_jugados . '</td>';
                $output .= '<td>' . $pos->partidos_ganados . '</td>';
                $output .= '<td>' . $pos->partidos_empatados . '</td>';
                $output .= '<td>' . $pos->partidos_perdidos . '</td>';
                $output .= '<td>' . $pos->goles_favor . '</td>';
                $output .= '<td>' . $pos->goles_contra . '</td>';
                $output .= '<td class="' . $dg_class . '">' . ($diferencia_gol >= 0 ? '+' : '') . $diferencia_gol . '</td>';
                $output .= '<td class="pts-cell"><strong>' . $pos->puntos . '</strong></td>';
                $output .= '</tr>';
            }
            
            $output .= '</tbody>';
            $output .= '</table>';
            $output .= '</div>';
            $output .= '</div>';
        }
    }
    
    if (empty($output)) {
        $output = '<div class="torneo-no-data">No hay datos de posiciones disponibles.</div>';
    }
    
    return $output;
}
?>