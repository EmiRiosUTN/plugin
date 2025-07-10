jQuery(document).ready(function($) {
    
    // Confirmar eliminaciones
    $('.button-link-delete').on('click', function(e) {
        if (!confirm('¿Estás seguro de que quieres eliminar esta tabla? Esta acción no se puede deshacer.')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Validar formularios antes de enviar
    $('form').on('submit', function(e) {
        var form = $(this);
        var isValid = true;
        
        // Validar campos requeridos
        form.find('[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('error').focus();
                isValid = false;
                return false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Por favor, completa todos los campos requeridos.');
            return false;
        }
        
        // Validaciones específicas según el tipo de formulario
        if (form.attr('id') === 'form-posiciones') {
            return validarFormularioPosiciones(form);
        } else if (form.attr('id') === 'form-partidos') {
            return validarFormularioPartidos(form);
        } else if (form.attr('id') === 'form-jugadores') {
            return validarFormularioJugadores(form);
        }
        
        return true;
    });
    
    // Validar archivo CSV
    $('input[type="file"][accept=".csv"]').on('change', function() {
        var file = this.files[0];
        if (file) {
            var fileName = file.name;
            var fileExtension = fileName.split('.').pop().toLowerCase();
            
            if (fileExtension !== 'csv') {
                alert('Por favor, selecciona solo archivos CSV (.csv)');
                $(this).val('');
                return false;
            }
            
            // Verificar tamaño del archivo (máximo 2MB)
            var maxSize = 2 * 1024 * 1024; // 2MB
            if (file.size > maxSize) {
                alert('El archivo es demasiado grande. El tamaño máximo permitido es 2MB.');
                $(this).val('');
                return false;
            }
        }
    });
    
    // Mejorar UX de inputs numéricos
    $(document).on('input', 'input[type="number"]', function() {
        var value = parseInt($(this).val());
        var min = parseInt($(this).attr('min'));
        var max = parseInt($(this).attr('max'));
        
        if (min !== undefined && value < min) {
            $(this).val(min);
        }
        
        if (max !== undefined && value > max) {
            $(this).val(max);
        }
    });
    
    // Auto-guardar en localStorage para formularios largos (recuperación)
    var formSelector = '#form-posiciones, #form-partidos, #form-jugadores';
    var autoSaveKey = 'torneo_form_backup';
    
    $(formSelector + ' input, ' + formSelector + ' textarea').on('input', function() {
        clearTimeout(window.autoSaveTimeout);
        window.autoSaveTimeout = setTimeout(function() {
            saveFormData();
        }, 2000);
    });
    
    function saveFormData() {
        var formData = {};
        $(formSelector + ' input, ' + formSelector + ' textarea').each(function() {
            if ($(this).attr('name')) {
                formData[$(this).attr('name')] = $(this).val();
            }
        });
        
        try {
            localStorage.setItem(autoSaveKey, JSON.stringify(formData));
        } catch(e) {
            // LocalStorage no disponible o lleno
        }
    }
    
    function loadFormData() {
        try {
            var savedData = localStorage.getItem(autoSaveKey);
            if (savedData) {
                var formData = JSON.parse(savedData);
                Object.keys(formData).forEach(function(name) {
                    $('[name="' + name + '"]').val(formData[name]);
                });
                
                // Mostrar mensaje de recuperación
                showMessage('Se recuperaron datos guardados automáticamente.', 'info');
            }
        } catch(e) {
            // Error al cargar datos
        }
    }
    
    // Limpiar datos guardados al enviar formulario exitosamente
    $(formSelector).on('submit', function() {
        try {
            localStorage.removeItem(autoSaveKey);
        } catch(e) {
            // Error al limpiar
        }
    });
    
    // Cargar datos al inicio si estamos creando una nueva tabla
    if (!$('input[name="tabla_id"]').val()) {
        loadFormData();
    }
    
    // Función para mostrar mensajes
    function showMessage(message, type) {
        type = type || 'success';
        var messageClass = 'notice-' + type;
        var messageHtml = '<div class="notice ' + messageClass + ' is-dismissible"><p>' + message + '</p></div>';
        
        $('.wrap h1').after(messageHtml);
        
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut();
        }, 5000);
    }
    
    // Mejorar accesibilidad
    $('table').attr('role', 'table');
    $('table th').attr('role', 'columnheader');
    $('table td').attr('role', 'cell');
    
    // Indicador de carga para exportaciones
    $('a[href*="torneo_export_csv"]').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.text('Generando CSV...').addClass('updating-message');
        
        setTimeout(function() {
            button.text(originalText).removeClass('updating-message');
        }, 3000);
    });
    
    // Confirmación para importaciones
    $('form[enctype="multipart/form-data"]').on('submit', function(e) {
        var fileInput = $(this).find('input[type="file"]');
        if (fileInput.val()) {
            if (!confirm('¿Estás seguro de que quieres importar este archivo? Se creará una nueva tabla con los datos del CSV.')) {
                e.preventDefault();
                return false;
            }
        }
    });
});

// Validaciones específicas por tipo de formulario
function validarFormularioPosiciones(form) {
    var isValid = true;
    
    // Validar que PJ = PG + PE + PP para cada fila
    form.find('#filas-posiciones tr').each(function() {
        var fila = $(this);
        var pj = parseInt(fila.find('input[name*="[pj]"]').val()) || 0;
        var pg = parseInt(fila.find('input[name*="[pg]"]').val()) || 0;
        var pe = parseInt(fila.find('input[name*="[pe]"]').val()) || 0;
        var pp = parseInt(fila.find('input[name*="[pp]"]').val()) || 0;
        
        if (pj !== (pg + pe + pp) && pj > 0) {
            fila.css('background-color', '#ffeeee');
            isValid = false;
        }
    });
    
    if (!isValid) {
        alert('Error: En las filas marcadas en rojo, los partidos jugados deben ser igual a la suma de ganados + empatados + perdidos.');
        return false;
    }
    
    // Validar posiciones únicas
    var posiciones = [];
    form.find('input[name*="[posicion]"]').each(function() {
        var pos = parseInt($(this).val());
        if (posiciones.includes(pos)) {
            alert('Error: No puede haber posiciones duplicadas.');
            $(this).focus();
            return false;
        }
        posiciones.push(pos);
    });
    
    return true;
}

function validarFormularioPartidos(form) {
    var isValid = true;
    
    // Validar que un equipo no juegue contra sí mismo
    form.find('#filas-partidos tr').each(function() {
        var fila = $(this);
        var local = fila.find('input[name*="[local]"]').val().toLowerCase().trim();
        var visitante = fila.find('input[name*="[visitante]"]').val().toLowerCase().trim();
        
        if (local && visitante && local === visitante) {
            fila.css('background-color', '#ffeeee');
            isValid = false;
        }
    });
    
    if (!isValid) {
        alert('Error: Un equipo no puede jugar contra sí mismo. Revisa las filas marcadas en rojo.');
        return false;
    }
    
    return true;
}

function validarFormularioJugadores(form) {
    // Validar que no haya jugadores duplicados
    var jugadores = [];
    var isValid = true;
    
    form.find('input[name*="[jugador]"]').each(function() {
        var jugador = $(this).val().toLowerCase().trim();
        if (jugador && jugadores.includes(jugador)) {
            alert('Error: No puede haber jugadores duplicados: ' + $(this).val());
            $(this).focus();
            isValid = false;
            return false;
        }
        if (jugador) {
            jugadores.push(jugador);
        }
    });
    
    return isValid;
}

// Funciones auxiliares
function formatNumber(num) {
    return new Intl.NumberFormat('es-AR').format(num);
}

function copyToClipboard(text) {
    var temp = jQuery('<input>');
    jQuery('body').append(temp);
    temp.val(text).select();
    document.execCommand('copy');
    temp.remove();
}

// Atajos de teclado útiles
jQuery(document).on('keydown', function(e) {
    // Ctrl/Cmd + S para guardar formulario
    if ((e.ctrlKey || e.metaKey) && e.which === 83) {
        e.preventDefault();
        var submitButton = jQuery('input[type="submit"], button[type="submit"]').first();
        if (submitButton.length) {
            submitButton.click();
        }
    }
    
    // Escape para cancelar/volver
    if (e.which === 27) {
        var cancelButton = jQuery('a:contains("Cancelar")').first();
        if (cancelButton.length) {
            if (confirm('¿Quieres cancelar y perder los cambios no guardados?')) {
                window.location.href = cancelButton.attr('href');
            }
        }
    }
});

// Función para descargar tabla como CSV desde el frontend
function descargarTablaCSV(nombreTabla, tipo, datos) {
    var csv = '';
    var headers = [];
    
    // Definir headers según tipo
    if (tipo === 'posiciones') {
        headers = ['Posición', 'Club', 'PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'Pts'];
    } else if (tipo === 'partidos') {
        headers = ['Fecha', 'Hora', 'Local', 'Visitante'];
    } else if (tipo === 'jugadores') {
        headers = ['Jugador', 'Goles', 'Asistencias', 'Amarillas', 'Rojas', 'Incidencia'];
    }
    
    csv += headers.join(',') + '\n';
    
    // Agregar datos
    datos.forEach(function(fila) {
        var row = [];
        headers.forEach(function(header) {
            var key = header.toLowerCase().replace(' ', '_');
            row.push(fila[key] || '');
        });
        csv += row.join(',') + '\n';
    });
    
    // Descargar archivo
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    var url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', nombreTabla + '_' + tipo + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}