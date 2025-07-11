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
        "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE id = %d AND tipo = 'partidos'",
        $tabla_id
    ));
}

// Obtener todas las tablas de partidos
$tablas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE tipo = 'partidos' ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1>Próximos Partidos</h1>
    
    <div class="torneo-admin-section">
        <h2><?php echo $tabla_editar ? 'Editar Tabla' : 'Crear Nueva Tabla'; ?></h2>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="form-partidos">
            <input type="hidden" name="action" value="torneo_save_table">
            <input type="hidden" name="tipo_tabla" value="partidos">
            <?php if ($tabla_editar): ?>
                <input type="hidden" name="tabla_id" value="<?php echo $tabla_editar->id; ?>">
            <?php endif; ?>
            <?php wp_nonce_field('torneo_save_table', 'torneo_nonce'); ?>
            
            <div class="form-section">
                <label for="nombre_tabla"><strong>Nombre de la tabla:</strong></label>
                <input type="text" id="nombre_tabla" name="nombre_tabla" 
                       value="<?php echo $tabla_editar ? esc_attr($tabla_editar->nombre_tabla) : ''; ?>" 
                       placeholder="Ej: Liga Sábado - Fecha 5, Liga Domingo - Jornada 3" required style="width: 400px;">
            </div>
            
            <div class="tabla-dinamica">
                <h3>Partidos</h3>
                <table class="wp-list-table widefat" id="tabla-partidos">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Fecha</th>
                            <th style="width: 80px;">Hora</th>
                            <th style="width: 200px;">Equipo Local</th>
                            <th style="width: 200px;">Equipo Visitante</th>
                            <th style="width: 80px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="filas-partidos">
                        <?php 
                        if ($tabla_editar) {
                            $contenido = json_decode($tabla_editar->contenido_json, true);
                            foreach ($contenido as $index => $fila) {
                                echo '<tr>';
                                echo '<td><input type="date" name="contenido[' . $index . '][fecha]" value="' . esc_attr($fila['fecha']) . '" required></td>';
                                echo '<td><input type="time" name="contenido[' . $index . '][hora]" value="' . esc_attr($fila['hora']) . '" required></td>';
                                echo '<td><input type="text" name="contenido[' . $index . '][local]" value="' . esc_attr($fila['local']) . '" required placeholder="Equipo Local"></td>';
                                echo '<td><input type="text" name="contenido[' . $index . '][visitante]" value="' . esc_attr($fila['visitante']) . '" required placeholder="Equipo Visitante"></td>';
                                echo '<td><button type="button" class="button eliminar-fila">Eliminar</button></td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
                
                <div class="acciones-tabla">
                    <button type="button" class="button" id="agregar-fila">+ Agregar Partido</button>
                    <button type="button" class="button" id="rellenar-fechas">Rellenar Fechas Automático</button>
                </div>
            </div>
            
            <div class="form-actions">
                <?php submit_button($tabla_editar ? 'Actualizar Tabla' : 'Guardar Tabla', 'primary'); ?>
                <?php if ($tabla_editar): ?>
                    <a href="<?php echo admin_url('admin.php?page=torneo-partidos'); ?>" class="button">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="torneo-admin-section">
        <h2>Tablas Existentes</h2>
        
        <?php if (empty($tablas)): ?>
            <p>No hay tablas de partidos creadas aún.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Partidos</th>
                        <th>Fecha de Creación</th>
                        <th>Última Actualización</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tablas as $tabla): 
                        $contenido = json_decode($tabla->contenido_json, true);
                        $num_partidos = count($contenido);
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($tabla->nombre_tabla); ?></strong></td>
                            <td><?php echo $num_partidos; ?> partidos</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($tabla->created_at)); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($tabla->updated_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=torneo-partidos&edit=' . $tabla->id); ?>" 
                                   class="button button-small">Editar</a>
                                <a href="<?php echo admin_url('admin-post.php?action=torneo_export_csv&tabla_id=' . $tabla->id . '&_wpnonce=' . wp_create_nonce('torneo_csv_export')); ?>" 
                                   class="button button-small">Exportar CSV</a>
                                <a href="<?php echo admin_url('admin-post.php?action=torneo_delete_table&tabla_id=' . $tabla->id . '&tipo=partidos&_wpnonce=' . wp_create_nonce('torneo_delete_table')); ?>" 
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
        <a href="<?php echo admin_url('admin-post.php?action=torneo_export_all_csv&tipo=partidos&_wpnonce=' . wp_create_nonce('torneo_csv_export_all')); ?>" 
           class="button button-secondary">
           <span class="dashicons dashicons-download"></span>
           Descargar Todos los Partidos CSV
        </a>
    </div>
    
    <div class="torneo-admin-section">
        <h2>Importar desde CSV</h2>
        <p>Formato: <strong>fecha,hora,local,visitante</strong></p>
        <p class="description">Formato de fecha: YYYY-MM-DD, Formato de hora: HH:MM</p>
        <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="torneo_import_csv">
            <input type="hidden" name="tipo" value="partidos">
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

.tabla-dinamica input {
    border: 1px solid #ddd;
    padding: 4px;
    border-radius: 3px;
    width: 100%;
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
</style>

<script>
jQuery(document).ready(function($) {
    var filaIndex = $('#filas-partidos tr').length;
    
    // Agregar nueva fila
    $('#agregar-fila').on('click', function() {
        var hoy = new Date().toISOString().split('T')[0];
        var nuevaFila = `
            <tr>
                <td><input type="date" name="contenido[${filaIndex}][fecha]" value="${hoy}" required></td>
                <td><input type="time" name="contenido[${filaIndex}][hora]" value="15:00" required></td>
                <td><input type="text" name="contenido[${filaIndex}][local]" required placeholder="Equipo Local"></td>
                <td><input type="text" name="contenido[${filaIndex}][visitante]" required placeholder="Equipo Visitante"></td>
                <td><button type="button" class="button eliminar-fila">Eliminar</button></td>
            </tr>
        `;
        $('#filas-partidos').append(nuevaFila);
        filaIndex++;
    });
    
    // Eliminar fila
    $(document).on('click', '.eliminar-fila', function() {
        if ($('#filas-partidos tr').length > 1) {
            $(this).closest('tr').remove();
            reindexarFilas();
        } else {
            alert('Debe haber al menos un partido.');
        }
    });
    
    // Reindexar nombres de inputs después de eliminar
    function reindexarFilas() {
        $('#filas-partidos tr').each(function(index) {
            $(this).find('input').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                }
            });
        });
        filaIndex = $('#filas-partidos tr').length;
    }
    
    // Rellenar fechas automáticamente (sábados y domingos)
    $('#rellenar-fechas').on('click', function() {
        if (!confirm('¿Quieres rellenar automáticamente las fechas con los próximos sábados y domingos? Esto sobrescribirá las fechas existentes.')) {
            return;
        }
        
        var fechaInicio = new Date();
        var contador = 0;
        
        $('#filas-partidos tr').each(function() {
            // Buscar el próximo sábado (6) o domingo (0)
            var fechaPartido = new Date(fechaInicio);
            fechaPartido.setDate(fechaInicio.getDate() + contador);
            
            while (fechaPartido.getDay() !== 6 && fechaPartido.getDay() !== 0) {
                fechaPartido.setDate(fechaPartido.getDate() + 1);
            }
            
            var fechaFormateada = fechaPartido.toISOString().split('T')[0];
            $(this).find('input[name*="[fecha]"]').val(fechaFormateada);
            
            // Alternar horarios
            var hora = contador % 2 === 0 ? '15:00' : '17:00';
            $(this).find('input[name*="[hora]"]').val(hora);
            
            contador += 7; // Siguiente semana
        });
    });
    
    // Validar formato de fecha y hora en JavaScript
    $(document).on('change', 'input[type="date"], input[type="time"]', function() {
        var fila = $(this).closest('tr');
        var fecha = fila.find('input[name*="[fecha]"]').val();
        var hora = fila.find('input[name*="[hora]"]').val();
        
        // Validar que la fecha no esté en el pasado
        if (fecha && hora) {
            var fechaCompleta = new Date(fecha + 'T' + hora);
            var ahora = new Date();
            
            if (fechaCompleta < ahora) {
                fila.css('background-color', '#fff3cd');
                fila.find('.fecha-warning').remove();
                fila.find('td:first').append('<div class="fecha-warning" style="color: #856404; font-size: 11px;">⚠️ Fecha en el pasado</div>');
            } else {
                fila.css('background-color', '');
                fila.find('.fecha-warning').remove();
            }
        }
    });
    
    // Validar que un equipo no juegue contra sí mismo
    $(document).on('blur', 'input[name*="[local]"], input[name*="[visitante]"]', function() {
        var fila = $(this).closest('tr');
        var local = fila.find('input[name*="[local]"]').val().toLowerCase().trim();
        var visitante = fila.find('input[name*="[visitante]"]').val().toLowerCase().trim();
        
        if (local && visitante && local === visitante) {
            fila.css('background-color', '#ffeeee');
            alert('Un equipo no puede jugar contra sí mismo.');
            $(this).focus();
        } else {
            fila.css('background-color', '');
        }
    });
    
    // Si no hay filas, agregar una por defecto
    if ($('#filas-partidos tr').length === 0) {
        $('#agregar-fila').click();
    }
    
    // Auto-recargar después de CSV exitoso
    if (window.location.href.indexOf('success=1') > -1) {
        setTimeout(function() {
            window.location.href = 'admin.php?page=torneo-partidos';
        }, 2000);
    }
});

    
</script>