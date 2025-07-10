<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Torneo Simple - Panel Principal</h1>
    
    <div class="torneo-admin-grid">
        <div class="torneo-card">
            <h2>Plugin Torneo Simple</h2>
            <p>Gestiona fácilmente tus tablas de torneo de fútbol de forma simple y directa.</p>
            
            <h3>¿Cómo funciona?</h3>
            <ol>
                <li><strong>Crea tablas:</strong> Ve a cualquier sección y crea una tabla con nombre</li>
                <li><strong>Agrega filas:</strong> Completa directamente los datos de cada fila</li>
                <li><strong>Guarda todo:</strong> Se guarda la tabla completa de una vez</li>
                <li><strong>Muestra en web:</strong> Usa los shortcodes para mostrar las tablas</li>
            </ol>
            
            <h3>Tipos de tablas disponibles:</h3>
            <ul>
                <li><strong>Posiciones:</strong> Pos, Club, PJ, PG, PE, PP, GF, GC, DG, Pts</li>
                <li><strong>Partidos:</strong> Fecha, Hora, Equipo Local vs Equipo Visitante</li>
                <li><strong>Jugadores:</strong> Jugador, Goles, Asistencias, Amarillas, Rojas, Incidencia</li>
            </ul>
            
            <h3>Shortcodes para mostrar en tu web:</h3>
            <div class="shortcode-list">
                <div class="shortcode-item">
                    <code>[torneo_posiciones]</code>
                    <button class="button copy-shortcode" data-shortcode="[torneo_posiciones]">Copiar</button>
                    <p>Muestra todas las tablas de posiciones</p>
                </div>
                <div class="shortcode-item">
                    <code>[torneo_partidos]</code>
                    <button class="button copy-shortcode" data-shortcode="[torneo_partidos]">Copiar</button>
                    <p>Muestra todas las tablas de próximos partidos</p>
                </div>
                <div class="shortcode-item">
                    <code>[torneo_jugadores]</code>
                    <button class="button copy-shortcode" data-shortcode="[torneo_jugadores]">Copiar</button>
                    <p>Muestra todas las tablas de estadísticas de jugadores</p>
                </div>
            </div>
        </div>
        
        <div class="torneo-card">
            <h3>Accesos rápidos</h3>
            <div class="torneo-quick-links">
                <a href="<?php echo admin_url('admin.php?page=torneo-posiciones'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-list-view"></span>
                    Gestionar Posiciones
                </a>
                <a href="<?php echo admin_url('admin.php?page=torneo-partidos'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    Gestionar Partidos
                </a>
                <a href="<?php echo admin_url('admin.php?page=torneo-jugadores'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-groups"></span>
                    Gestionar Jugadores
                </a>
            </div>
            
            <h4>API</h4>
            <p>Endpoints disponibles:</p>
            <ul class="api-list">
                <li><code>/wp-json/torneo-simple/v1/tablas/posiciones</code></li>
                <li><code>/wp-json/torneo-simple/v1/tablas/partidos</code></li>
                <li><code>/wp-json/torneo-simple/v1/tablas/jugadores</code></li>
                <li><code>/wp-json/torneo-simple/v1/tabla/{id}</code></li>
            </ul>
        </div>
    </div>
</div>

<style>
.torneo-admin-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.torneo-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.torneo-quick-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.torneo-quick-links .button {
    width: 100%;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 8px;
}

.shortcode-list {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    margin-top: 10px;
}

.shortcode-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    padding: 8px;
    background: #fff;
    border-radius: 4px;
}

.shortcode-item code {
    background: #e1e1e1;
    padding: 4px 8px;
    border-radius: 3px;
    font-family: monospace;
    flex: 1;
}

.shortcode-item p {
    margin: 0;
    font-size: 13px;
    color: #666;
    flex: 2;
}

.copy-shortcode {
    font-size: 12px !important;
    padding: 2px 8px !important;
    height: auto !important;
}

.api-list {
    font-family: monospace;
    font-size: 12px;
}

.api-list code {
    background: #f0f0f0;
    padding: 2px 4px;
    border-radius: 2px;
}

@media (max-width: 768px) {
    .torneo-admin-grid {
        grid-template-columns: 1fr;
    }
    
    .shortcode-item {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.copy-shortcode').on('click', function(e) {
        e.preventDefault();
        var shortcode = $(this).data('shortcode');
        
        // Crear elemento temporal para copiar
        var temp = $('<input>');
        $('body').append(temp);
        temp.val(shortcode).select();
        document.execCommand('copy');
        temp.remove();
        
        // Mostrar mensaje
        var button = $(this);
        var originalText = button.text();
        button.text('¡Copiado!').css('background', '#00a32a');
        
        setTimeout(function() {
            button.text(originalText).css('background', '');
        }, 2000);
    });
});
</script>