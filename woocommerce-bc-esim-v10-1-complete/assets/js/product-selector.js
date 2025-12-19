jQuery(document).ready(function($){
    
    // Solo en página de producto BC
    if(!$('body').hasClass('single-product')) return;
    if($('.bc-price-options').length === 0) return;
    
    const $container = $('.bc-price-options');
    const options = JSON.parse($container.attr('data-options') || '[]');
    const planType = $container.attr('data-plan-type');
    const productId = $container.attr('data-product-id');
    
    if(options.length === 0) return;
    
    console.log('BC eSIM: Inicializando selector', {
        options: options,
        planType: planType
    });
    
    // Label según tipo de plan
    let label = '';
    let infoText = '';
    
    if(planType === '1'){
        // Plan DIARIO: Datos renovables cada día
        label = '📅 Selecciona cuántos días necesitas:';
        infoText = 'Recibirás datos nuevos cada día durante el período seleccionado';
    } else {
        // PAQUETE: Datos totales para usar en X días
        label = '⏰ Selecciona la validez del paquete:';
        infoText = 'Los datos totales estarán disponibles durante el período seleccionado';
    }
    
    // Crear selector
    let html = '<div class="bc-options-selector" style="background:#f8f9fa;padding:25px;border-radius:12px;margin:20px 0;border:2px solid #0073aa;">';
    html += '<label for="bc_option" style="display:block;font-size:18px;font-weight:700;color:#333;margin-bottom:10px;">' + label + '</label>';
    html += '<p style="font-size:14px;color:#666;margin-bottom:15px;font-style:italic;">' + infoText + '</p>';
    html += '<select id="bc_option" name="bc_option" class="bc-option-select" style="width:100%;padding:15px;font-size:16px;border:2px solid #0073aa;border-radius:8px;background:#fff;cursor:pointer;">';
    
    options.forEach(function(opt, idx){
        const days = parseInt(opt.copies);
        const price = parseFloat(opt.price);
        
        // SIEMPRE mostrar como días
        const optionLabel = days === 1 ? '1 día' : days + ' días';
        const selected = idx === 0 ? ' selected' : '';
        
        html += '<option value="' + idx + '" data-copies="' + days + '" data-price="' + price + '"' + selected + '>';
        html += '🗓️ ' + optionLabel + ' → $' + price.toFixed(2) + ' USD';
        html += '</option>';
    });
    
    html += '</select>';
    html += '<div class="bc-selected-info" style="margin-top:20px;padding:25px;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#fff;border-radius:12px;text-align:center;box-shadow:0 8px 16px rgba(0,0,0,0.2);"></div>';
    html += '</div>';
    
    // Insertar en el contenedor
    $container.html(html);
    
    // Función para actualizar precio
    function updatePrice(){
        const $selected = $('#bc_option option:selected');
        const days = $selected.data('copies');
        const price = $selected.data('price');
        
        const dayLabel = days === 1 ? 'día' : 'días';
        
        let html = '';
        
        if(planType === '1'){
            // Plan DIARIO
            html += '<div style="font-size:16px;margin-bottom:8px;">Tu plan incluye:</div>';
            html += '<div style="font-size:32px;font-weight:800;margin:10px 0;">🗓️ ' + days + ' ' + dayLabel + ' con datos</div>';
            html += '<div style="font-size:14px;margin-top:8px;opacity:0.9;">Datos renovables cada día</div>';
        } else {
            // PAQUETE
            html += '<div style="font-size:16px;margin-bottom:8px;">Paquete válido por:</div>';
            html += '<div style="font-size:32px;font-weight:800;margin:10px 0;">⏰ ' + days + ' ' + dayLabel + '</div>';
            html += '<div style="font-size:14px;margin-top:8px;opacity:0.9;">Para usar tus datos totales</div>';
        }
        
        html += '<div style="border-top:2px solid rgba(255,255,255,0.3);margin:15px 0;"></div>';
        html += '<div style="font-size:18px;margin-top:8px;opacity:0.9;">Total a pagar:</div>';
        html += '<div style="font-size:48px;font-weight:900;margin-top:5px;">$' + price.toFixed(2) + '</div>';
        html += '<div style="font-size:16px;margin-top:5px;opacity:0.9;">USD</div>';
        
        $('.bc-selected-info').html(html);
        
        // Actualizar precio principal del producto
        $('.woocommerce-Price-amount').first().html('$' + price.toFixed(2) + ' <span class="woocommerce-Price-currencySymbol">USD</span>');
        
        console.log('BC eSIM: Precio actualizado', {days: days, price: price});
    }
    
    // Actualizar al cargar
    updatePrice();
    
    // Actualizar al cambiar opción
    $('#bc_option').on('change', updatePrice);
    
    // Guardar datos al añadir al carrito
    $('form.cart').on('submit', function(e){
        const $selected = $('#bc_option option:selected');
        const copies = $selected.data('copies');
        const price = $selected.data('price');
        
        console.log('BC eSIM: Añadiendo al carrito', {copies: copies, price: price});
        
        // Agregar campos hidden
        $(this).append('<input type="hidden" name="bc_copies" value="' + copies + '">');
        $(this).append('<input type="hidden" name="bc_price" value="' + price + '">');
        $(this).append('<input type="hidden" name="bc_sku_id" value="' + productId + '">');
    });
    
});
