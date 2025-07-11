<div class="torneo-admin-section">
        <h2>Importar desde CSV</h2>
        <p>Formato: <strong>posicion,club,pj,pg,pe,pp,gf,gc,pts</strong></p>
        <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="torneo_import_csv">
            <input type="hidden" name="tipo" value="posiciones">
            <input type="text" name="nombre_tabla" placeholder="Nombre de la tabla" required style="width: 200px;">
            <input type="file" name="csv_file" accept=".csv" required>
            <?php wp_nonce_field('torneo_csv_import', 'torneo_csv_nonce'); ?>
            <br><br>
            <?php submit_button('Importar CSV', 'secondary'); ?>
        </form>
    </div>
</div><?php
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
        "SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE id = %d AND tipo = 'posiciones'",
        $tabla_id
    ));
}

// Obtener todas las tablas de posiciones
$tablas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}torneo_tablas WHERE tipo = 'posiciones' ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1>Tablas de Posiciones</h1>
    
    <div class="torneo-admin-section">
        <h2><?php echo $tabla_editar ? 'Editar Tabla' : 'Crear Nueva Tabla'; ?></h2>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="form-posiciones">
            <input type="hidden" name="action" value="torneo_save_table">
            <input type="hidden" name="tipo_tabla" value="posiciones">
            <?php if ($tabla_editar): ?>
                <input type="hidden" name="tabla_id" value="<?php echo $tabla_editar->id; ?>">
            <?php endif; ?>
            <?php wp_nonce_field('torneo_save_table', 'torneo_nonce'); ?>
            
            <div class="form-section">
                <label for="nombre_tabla"><strong>Nombre de la tabla:</strong></label>
                <input type="text" id="nombre_tabla" name="nombre_tabla" 
                       value="<?php echo $tabla_editar ? esc_attr($tabla_editar->nombre_tabla) : ''; ?>" 
                       placeholder="Ej: Liga Sábado, Liga Domingo" required style="width: 300px;">
            </div>
            
            <div class="tabla-dinamica">
                <h3>Posiciones</h3>
                <table class="wp-list-table widefat" id="tabla-posiciones">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Pos</th>
                            <th style="width: 200px;">Club</th>
                            <th style="width: 60px;">PJ</th>
                            <th style="width: 60px;">PG</th>
                            <th style="width: 60px;">PE</th>
                            <th style="width: 60px;">PP</th>
                            <th style="width: 60px;">GF</th>
                            <th style="width: 60px;">GC</th>
                            <th style="width: 60px;">Pts</th>
                            <th style="width: 80px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="filas-posiciones">
                        <?php 
                        if ($tabla_editar) {
                            $contenido = json_decode($tabla_editar->contenido_json, true);
                            foreach ($contenido as $index => $fila) {
                                echo '<tr>';
                                echo '<td><input type="number" name="contenido[' . $index . '][posicion]" value="' . esc_attr($fila['posicion']) . '" min="1" required style="width: 50px;"></td>';
                                echo '<td><input type="text" name="contenido[' . $index . '][club]" value="' . esc_attr($fila['club']) . '" required></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][pj]" value="' . esc_attr($fila['pj']) . '" min="0" required style="width: 50px;"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][pg]" value="' . esc_attr($fila['pg']) . '" min="0" required style="width: 50px;"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][pe]" value="' . esc_attr($fila['pe']) . '" min="0" required style="width: 50px;"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][pp]" value="' . esc_attr($fila['pp']) . '" min="0" required style="width: 50px;"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][gf]" value="' . esc_attr($fila['gf']) . '" min="0" required style="width: 50px;"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][gc]" value="' . esc_attr($fila['gc']) . '" min="0" required style="width: 50px;"></td>';
                                echo '<td><input type="number" name="contenido[' . $index . '][pts]" value="' . esc_attr($fila['pts']) . '" min="0" required style="width: 50px;"></td>';
                                echo '<td><button type="button" class="button eliminar-fila">Eliminar</button></td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
                
                <div class="acciones-tabla">
                    <button type="button" class="button" id="agregar-fila">+ Agregar Fila</button>
                    <button type="button" class="button" id="calcular-puntos">Calcular Puntos Automático</button>
                </div>
            </div>
            
            <div class="form-actions">
                <?php submit_button($tabla_editar ? 'Actualizar Tabla' : 'Guardar Tabla', 'primary'); ?>
                <?php if ($tabla_editar): ?>
                    <a href="<?php echo admin_url('admin.php?page=torneo-posiciones'); ?>" class="button">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="torneo-admin-section">
        <h2>Tablas Existentes</h2>
        
        <?php if (empty($tablas)): ?>
            <p>No hay tablas de posiciones creadas aún.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Equipos</th>
                        <th>Fecha de Creación</th>
                        <th>Última Actualización</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tablas as $tabla): 
                        $contenido = json_decode($tabla->contenido_json, true);
                        $num_equipos = count($contenido);
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($tabla->nombre_tabla); ?></strong></td>
                            <td><?php echo $num_equipos; ?> equipos</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($tabla->created_at)); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($tabla->updated_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=torneo-posiciones&edit=' . $tabla->id); ?>" 
                                   class="button button-small">Editar</a>
                                <a href="<?php echo admin_url('admin-post.php?action=torneo_export_csv&tabla_id=' . $tabla->id . '&_wpnonce=' . wp_create_nonce('torneo_csv_export')); ?>" 
                                   class="button button-small">Exportar CSV</a>
                                <a href="<?php echo admin_url('admin-post.php?action=torneo_delete_table&tabla_id=' . $tabla->id . '&tipo=posiciones&_wpnonce=' . wp_create_nonce('torneo_delete_table')); ?>" 
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
        <p>Descarga todas las tablas de posiciones en un solo archivo CSV.</p>
        <a href="<?php echo admin_url('admin-post.php?action=torneo_export_all_csv&tipo=posiciones&_wpnonce=' . wp_create_nonce('torneo_csv_export_all')); ?>" 
           class="button button-secondary">
           <span class="dashicons dashicons-download"></span>
           Descargar Todas las Posiciones CSV
        </a>
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
    var filaIndex = $('#filas-posiciones tr').length;
    
    // Agregar nueva fila
    $('#agregar-fila').on('click', function() {
        var nuevaFila = `
            <tr>
                <td><input type="number" name="contenido[${filaIndex}][posicion]" value="${filaIndex + 1}" min="1" required style="width: 50px;"></td>
                <td><input type="text" name="contenido[${filaIndex}][club]" required></td>
                <td><input type="number" name="contenido[${filaIndex}][pj]" value="0" min="0" required style="width: 50px;"></td>
                <td><input type="number" name="contenido[${filaIndex}][pg]" value="0" min="0" required style="width: 50px;"></td>
                <td><input type="number" name="contenido[${filaIndex}][pe]" value="0" min="0" required style="width: 50px;"></td>
                <td><input type="number" name="contenido[${filaIndex}][pp]" value="0" min="0" required style="width: 50px;"></td>
                <td><input type="number" name="contenido[${filaIndex}][gf]" value="0" min="0" required style="width: 50px;"></td>
                <td><input type="number" name="contenido[${filaIndex}][gc]" value="0" min="0" required style="width: 50px;"></td>
                <td><input type="number" name="contenido[${filaIndex}][pts]" value="0" min="0" required style="width: 50px;"></td>
                <td><button type="button" class="button eliminar-fila">Eliminar</button></td>
            </tr>
        `;
        $('#filas-posiciones').append(nuevaFila);
        filaIndex++;
    });
    
    // Eliminar fila
    $(document).on('click', '.eliminar-fila', function() {
        if ($('#filas-posiciones tr').length > 1) {
            $(this).closest('tr').remove();
            reindexarFilas();
        } else {
            alert('Debe haber al menos una fila.');
        }
    });
    
    // Reindexar nombres de inputs después de eliminar
    function reindexarFilas() {
        $('#filas-posiciones tr').each(function(index) {
            $(this).find('input').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                }
            });
            $(this).find('input[name*="[posicion]"]').val(index + 1);
        });
        filaIndex = $('#filas-posiciones tr').length;
    }
    
    // Calcular puntos automáticamente (3 por victoria, 1 por empate)
    $('#calcular-puntos').on('click', function() {
        $('#filas-posiciones tr').each(function() {
            var pg = parseInt($(this).find('input[name*="[pg]"]').val()) || 0;
            var pe = parseInt($(this).find('input[name*="[pe]"]').val()) || 0;
            var puntos = (pg * 3) + (pe * 1);
            $(this).find('input[name*="[pts]"]').val(puntos);
        });
    });
    
    // Validar que PJ = PG + PE + PP
    $(document).on('blur', 'input[name*="[pj]"], input[name*="[pg]"], input[name*="[pe]"], input[name*="[pp]"]', function() {
        var fila = $(this).closest('tr');
        var pj = parseInt(fila.find('input[name*="[pj]"]').val()) || 0;
        var pg = parseInt(fila.find('input[name*="[pg]"]').val()) || 0;
        var pe = parseInt(fila.find('input[name*="[pe]"]').val()) || 0;
        var pp = parseInt(fila.find('input[name*="[pp]"]').val()) || 0;
        
        if (pj !== (pg + pe + pp) && pj > 0) {
            fila.css('background-color', '#ffeeee');
        } else {
            fila.css('background-color', '');
        }
    });
    
    // Si no hay filas, agregar una por defecto
    if ($('#filas-posiciones tr').length === 0) {
        $('#agregar-fila').click();
    }
    
    // Auto-recargar después de CSV exitoso
    if (window.location.href.indexOf('success=1') > -1) {
        setTimeout(function() {
            window.location.href = 'admin.php?page=torneo-posiciones';
        }, 2000);
    }
});
</script>