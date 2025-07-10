<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Procesar formulario
if (isset($_GET['success'])) {
    echo '<div class="notice notice-success"><p>Tabla guardada exitosamente.</p></div>';
}
if (isset($_GET['error'])) {
    echo '<div class="notice notice-error"><p>Error al guardar la tabla.</p></div>';
}
if (isset($_GET['deleted'])) {
    echo '<div class="notice notice-success"><p>Tabla eliminada exitosamente.</p></div>';
}

// Obtener tabla para editar
$tabla_editar = null;
if (isset($_GET['edit'])) {
    $tabla_id = intval($_GET['edit']);
    $tabla_editar = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE id = %d AND tipo = 'jugadores'",
        $tabla_id
    ));
}

// Obtener todas las tablas de jugadores
$tablas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE tipo = 'jugadores' ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1>Estadísticas de Jugadores</h1>
    
    <div class="torneo-admin-section">
        <h2><?php echo $tabla_editar ? 'Editar Tabla' : 'Crear Nueva Tabla'; ?></h2>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="form-jugadores">
            <input type="hidden" name="action" value="torneo_save_table">
            <input type="hidden" name="tipo_tabla" value="jugadores">
            <?php if ($tabla_editar): ?>
                <input type="hidden" name="tabla_id" value="<?php echo $tabla_editar->id; ?>">
            <?php endif; ?>
            <?php wp_nonce_field('torneo_save_table', 'torneo_nonce'); ?>
            
            <div class="form-section">
                <label for="nombre_tabla"><strong>Nombre de la tabla:</strong></label>
                <input type="text" id="nombre_tabla" name="nombre_tabla" 
                       value="<?php echo $tabla_editar ? esc_attr($tabla_editar->nombre_tabla) : ''; ?>" 
                       placeholder="Ej: Liga Sábado - Goleadores, Liga Domingo - Estadísticas" required style="width: 400px;">
            </div>
            
            <div class="tabla-dinamica">
                <h3>Estadísticas de Jugadores</h3>
                <table class="wp-list-table widefat" id="tabla-jugadores">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Jugador</th>
                            <th style="width: 80px;">Goles</th>
                            <th style="width: 80px;">Asistencias</th>
                            <th style="width: 80px;">Amarillas</th>
                            <th style="width: 80px;">Rojas</th>
                            <th style="width: 300px;">Incidencia</th>
                            <th style="width: 80px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="filas-jugadores">
                        <?php 
                        if ($tabla_editar) {
                            $contenido = json_decode($tabla_editar->contenido_json, true);
                            foreach ($contenido as $index => $fila) {
                                echo '<tr>';
                                echo '<td><input type="text" name="contenido[' . $index . '][jugador]" value="' . esc_attr($fila['jugador']) . '" required placeholder="Nombre del jugador"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][goles]" value="' . esc_attr($fila['goles']) . '" min="0" required style="width: 70px;"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][asistencias]" value="' . esc_attr($fila['asistencias']) . '" min="0" required style="width: 70px;"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][amarillas]" value="' . esc_attr($fila['amarillas']) . '" min="0" required style="width: 70px;"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][rojas]" value="' . esc_attr($fila['rojas']) . '" min="0" required style="width: 70px;"></td>';
                                echo '<td><textarea name="contenido[' . $index . '][incidencia]" rows="2" placeholder="Describe incidencias...">' . esc_textarea($fila['incidencia']) . '</textarea></td>';
                                echo '<td><button type="button" class="button eliminar-fila">Eliminar</button></td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
                
                <div class="acciones-tabla">
                    <button type="button" class="button" id="agregar-fila">+ Agregar Jugador</button>
                    <button type="button" class="button" id="ordenar-goles">Ordenar por Goles</button>
                </div>
            </div>
            
            <div class="form-actions">
                <?php submit_button($tabla_editar ? 'Actualizar Tabla' : 'Guardar Tabla', 'primary'); ?>
                <?php if ($tabla_editar): ?>
                    <a href="<?php echo admin_url('admin.php?page=torneo-jugadores'); ?>" class="button">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="torneo-admin-section">
        <h2>Tablas Existentes</h2>
        
        <?php if (empty($tablas)): ?>
            <p>No hay tablas de jugadores creadas aún.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Jugadores</th>
                        <th>Fecha de Creación</th>
                        <th>Última Actualización</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tablas as $tabla): 
                        $contenido = json_decode($tabla->contenido_json, true);
                        $num_jugadores = count($contenido);
                        
                        // Calcular totales
                        $total_goles = array_sum(array_column($contenido, 'goles'));
                        $total_asistencias = array_sum(array_column($contenido, 'asistencias'));
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($tabla->nombre_tabla); ?></strong></td>
                            <td>
                                <?php echo $num_jugadores; ?> jugadores<br>
                                <small><?php echo $total_goles; ?> goles, <?php echo $total_asistencias; ?> asistencias</small>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($tabla->created_at)); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($tabla->updated_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=torneo-jugadores&edit=' . $tabla->id); ?>" 
                                   class="button button-small">Editar</a>
                                <a href="<?php echo admin_url('admin-post.php?action=torneo_export_csv&tabla_id=' . $tabla->id . '&_wpnonce=' . wp_create_nonce('torneo_csv_export')); ?>" 
                                   class="button button-small">Exportar CSV</a>
                                <a href="<?php echo admin_url('admin-post.php?action=torneo_delete_table&tabla_id=' . $tabla->id . '&tipo=jugadores&_wpnonce=' . wp_create_nonce('torneo_delete_table')); ?>" 
                                   class="button button-small button-link-delete" 
                                   onclick="return confirm('¿Estás seguro de que quieres eliminar esta tabla? Esta acción no se puede deshacer.')">
                                   Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="torneo-admin-section">
        <h2>Exportar Todas las Tablas</h2>
        <a href="<?php echo admin_url('admin-post.php?action=torneo_export_all_csv&tipo=jugadores&_wpnonce=' . wp_create_nonce('torneo_csv_export_all')); ?>" 
           class="button button-secondary">
           <span class="dashicons dashicons-download"></span>
           Descargar Todas las Estadísticas CSV
        </a>
    </div>
    
    <div class="torneo-admin-section">
        <h2>Importar desde CSV</h2>
        <p>Formato: <strong>jugador,goles,asistencias,amarillas,rojas,incidencia</strong></p>
        <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="torneo_import_csv">
            <input type="hidden" name="tipo" value="jugadores">
            <input type="text" name="nombre_tabla" placeholder="Nombre de la tabla" required style="width: 200px;">
            <input type="file" name="csv_file" accept=".csv" required>
            <?php wp_nonce_field('torneo_csv_import', 'torneo_csv_nonce'); ?>
            <br><br>
            <?php submit_button('Importar CSV', 'secondary'); ?>
        </form>
    </div>
</div>

<style>
.torneo-admin-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.torneo-admin-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.form-section {
    margin-bottom: 20px;
}

.tabla-dinamica {
    margin: 20px 0;
}

.tabla-dinamica table {
    margin-bottom: 10px;
}

.tabla-dinamica input, .tabla-dinamica textarea {
    border: 1px solid #ddd;
    padding: 4px;
    border-radius: 3px;
    width: 100%;
}

.tabla-dinamica textarea {
    resize: vertical;
    font-family: inherit;
}

.acciones-tabla {
    margin: 10px 0;
}

.acciones-tabla button {
    margin-right: 10px;
}

.form-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.eliminar-fila {
    background: #d63638 !important;
    color: white !important;
    border-color: #d63638 !important;
}

.eliminar-fila:hover {
    background: #b32d2e !important;
}

.goles-highlight {
    background-color: #e8f5e8 !important;
}

.amarillas-highlight {
    background-color: #fff9e6 !important;
}

.rojas-highlight {
    background-color: #fdf2f2 !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    var filaIndex = $('#filas-jugadores tr').length;
    
    // Agregar nueva fila
    $('#agregar-fila').on('click', function() {
        var nuevaFila = `
            <tr>
                <td><input type="text" name="contenido[${filaIndex}][jugador]" required placeholder="Nombre del jugador"></td>
                <td><input type="number" name="contenido[${filaIndex}][goles]" value="0" min="0" required style="width: 70px;"></td>
                <td><input type="number" name="contenido[${filaIndex}][asistencias]" value="0" min="0" required style="width: 70px;"></td>
                <td><input type="number" name="contenido[${filaIndex}][amarillas]" value="0" min="0" required style="width: 70px;"></td>
                <td><input type="number" name="contenido[${filaIndex}][rojas]" value="0" min="0" required style="width: 70px;"></td>
                <td><textarea name="contenido[${filaIndex}][incidencia]" rows="2" placeholder="Describe incidencias..."></textarea></td>
                <td><button type="button" class="button eliminar-fila">Eliminar</button></td>
            </tr>
        `;
        $('#filas-jugadores').append(nuevaFila);
        filaIndex++;
    });
    
    // Eliminar fila
    $(document).on('click', '.eliminar-fila', function() {
        if ($('#filas-jugadores tr').length > 1) {
            $(this).closest('tr').remove();
            reindexarFilas();
        } else {
            alert('Debe haber al menos un jugador.');
        }
    });
    
    // Reindexar nombres de inputs después de eliminar
    function reindexarFilas() {
        $('#filas-jugadores tr').each(function(index) {
            $(this).find('input, textarea').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                }
            });
        });
        filaIndex = $('#filas-jugadores tr').length;
    }
    
    // Ordenar por goles de mayor a menor
    $('#ordenar-goles').on('click', function() {
        var filas = $('#filas-jugadores tr').get();
        
        filas.sort(function(a, b) {
            var golesA = parseInt($(a).find('input[name*="[goles]"]').val()) || 0;
            var golesB = parseInt($(b).find('input[name*="[goles]"]').val()) || 0;
            return golesB - golesA; // Orden descendente
        });
        
        $.each(filas, function(index, fila) {
            $('#filas-jugadores').append(fila);
        });
        
        reindexarFilas();
    });
    
    // Destacar filas según estadísticas
    $(document).on('input', 'input[name*="[goles]"], input[name*="[amarillas]"], input[name*="[rojas]"]', function() {
        var fila = $(this).closest('tr');
        var goles = parseInt(fila.find('input[name*="[goles]"]').val()) || 0;
        var amarillas = parseInt(fila.find('input[name*="[amarillas]"]').val()) || 0;
        var rojas = parseInt(fila.find('input[name*="[rojas]"]').val()) || 0;
        
        // Remover clases existentes
        fila.removeClass('goles-highlight amarillas-highlight rojas-highlight');
        
        // Aplicar destacado según el valor más alto
        if (rojas > 0) {
            fila.addClass('rojas-highlight');
        } else if (amarillas >= 3) {
            fila.addClass('amarillas-highlight');
        } else if (goles >= 5) {
            fila.addClass('goles-highlight');
        }
    });
    
    // Validar tarjetas rojas (máximo 1 por jugador por partido)
    $(document).on('blur', 'input[name*="[rojas]"]', function() {
        var rojas = parseInt($(this).val()) || 0;
        if (rojas > 2) {
            if (!confirm('Este jugador tiene muchas tarjetas rojas. ¿Es correcto?')) {
                $(this).focus();
            }
        }
    });
    
    // Auto-completar nombres comunes
    var nombresComunes = [
        'Lionel Messi', 'Cristiano Ronaldo', 'Neymar Jr', 'Kylian Mbappé', 'Robert Lewandowski',
        'Kevin De Bruyne', 'Virgil van Dijk', 'Luka Modrić', 'Mohamed Salah', 'Sadio Mané'
    ];
    
    $(document).on('input', 'input[name*="[jugador]"]', function() {
        var input = $(this);
        var valor = input.val().toLowerCase();
        
        if (valor.length >= 3) {
            var sugerencias = nombresComunes.filter(function(nombre) {
                return nombre.toLowerCase().includes(valor);
            });
            
            // Aquí podrías implementar un dropdown de sugerencias
        }
    });
    
    // Si no hay filas, agregar una por defecto
    if ($('#filas-jugadores tr').length === 0) {
        $('#agregar-fila').click();
    }
});
</script>