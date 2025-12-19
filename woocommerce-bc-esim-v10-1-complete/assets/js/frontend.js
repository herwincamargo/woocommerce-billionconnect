jQuery(document).ready(function($){
    let currentPlans = [];
    
    // Búsqueda
    $('#bcesim-search').on('input', function(){
        const term = $(this).val().toLowerCase();
        let found = false;
        
        $('.bcesim-card').each(function(){
            const name = $(this).data('name');
            if(name.indexOf(term) !== -1){
                $(this).show();
                found = true;
            } else {
                $(this).hide();
            }
        });
        
        $('.bcesim-no-results').toggle(!found);
    });
    
    // Filtro continentes
    $('.bcesim-cont-btn').on('click', function(){
        const cont = $(this).data('cont');
        
        $('.bcesim-cont-btn').removeClass('active');
        $(this).addClass('active');
        
        if(cont === 'all'){
            $('.bcesim-card').show();
        } else {
            $('.bcesim-card').each(function(){
                if($(this).data('cont') === cont){
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
        
        $('#bcesim-search').val('');
        $('.bcesim-no-results').hide();
    });
    
    // Abrir modal
    $('.bcesim-view-btn').on('click', function(){
        const countryCode = $(this).data('country');
        const countryName = $(this).data('name');
        
        $('#bcesim-country-name').text(countryName);
        $('#bcesim-plans-container').html('<div class="bcesim-loading">Cargando planes...</div>');
        $('#bcesim-modal').fadeIn(300);
        
        $.ajax({
            url: bcesim_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bcesim_get_plans',
                nonce: bcesim_ajax.nonce,
                country_code: countryCode
            },
            success: function(res){
                if(res.success){
                    currentPlans = res.data.plans;
                    console.log('Planes cargados:', currentPlans); // DEBUG
                    renderPlans(currentPlans, 'all');
                } else {
                    $('#bcesim-plans-container').html('<p style="text-align:center;padding:40px;">' + res.data.msg + '</p>');
                }
            },
            error: function(xhr, status, error){
                console.error('Error AJAX:', status, error); // DEBUG
                $('#bcesim-plans-container').html('<p style="text-align:center;padding:40px;">Error al cargar planes</p>');
            }
        });
    });
    
    // Filtro planType - CORREGIDO
    $('.bcesim-filter-btn').on('click', function(){
        const filter = $(this).data('filter');
        
        $('.bcesim-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        console.log('Filtro seleccionado:', filter); // DEBUG
        renderPlans(currentPlans, filter);
    });
    
    // Renderizar planes - CORREGIDO
    function renderPlans(plans, filter){
        console.log('Renderizando con filtro:', filter, 'Total planes:', plans.length); // DEBUG
        
        let html = '';
        let count = 0;
        
        plans.forEach(function(plan){
            console.log('Plan:', plan.name, 'planType:', plan.planType, 'filter:', filter); // DEBUG
            
            // CORRECCIÓN: Comparar strings
            if(filter !== 'all' && plan.planType !== String(filter)) {
                console.log('  → Filtrado'); // DEBUG
                return;
            }
            
            count++;
            console.log('  → Mostrado'); // DEBUG
            
            html += '<div class="bcesim-plan-item" data-type="' + plan.planType + '">';
            html += '<h3 class="bcesim-plan-name">' + plan.name + '</h3>';
            
            // Badge del tipo de plan
            if(plan.planType === '1'){
                html += '<div style="display:inline-block;background:#0073aa;color:#fff;padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;margin-bottom:10px;">📅 PLAN DIARIO</div>';
            } else {
                html += '<div style="display:inline-block;background:#00a32a;color:#fff;padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;margin-bottom:10px;">📦 PAQUETE DE DATOS</div>';
            }
            
            html += '<p class="bcesim-plan-coverage" style="margin:10px 0;font-size:14px;color:#666;">🌍 ' + plan.coverage + '</p>';
            
            // Descripción simplificada y clara
            if(plan.desc && plan.desc.length > 0){
                // Extraer solo las primeras 3 características
                var descParts = plan.desc.split('•').slice(0, 3);
                html += '<div style="margin:15px 0;font-size:14px;line-height:1.8;">';
                descParts.forEach(function(part){
                    if(part.trim()){
                        html += '<div style="margin:5px 0;">• ' + part.trim() + '</div>';
                    }
                });
                html += '</div>';
            }
            
            if(plan.planType === '1'){
                html += '<div class="bcesim-plan-price" style="font-size:28px;font-weight:700;color:#0073aa;margin:15px 0;">$' + plan.price.toFixed(2) + ' <span style="font-size:16px;">USD/día</span></div>';
                html += '<p style="font-size:13px;color:#666;margin:10px 0;">💡 Selecciona los días que necesitas en la página del producto</p>';
            } else {
                html += '<div class="bcesim-plan-price" style="font-size:28px;font-weight:700;color:#00a32a;margin:15px 0;">Desde $' + plan.price.toFixed(2) + ' <span style="font-size:16px;">USD</span></div>';
                html += '<p style="font-size:13px;color:#666;margin:10px 0;">💡 Precio varía según días seleccionados</p>';
            }
            
            html += '<a href="' + plan.url + '" class="bcesim-plan-link" style="display:inline-block;background:#0073aa;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;margin-top:10px;transition:all 0.3s;">Ver Detalles →</a>';
            html += '</div>';
        });
        
        if(html === ''){
            html = '<p style="text-align:center;padding:40px;">No hay planes con este filtro (' + count + ' encontrados)</p>';
        }
        
        console.log('Total mostrados:', count); // DEBUG
        $('#bcesim-plans-container').html(html);
    }
    
    // Cerrar modal
    $('.bcesim-modal-close, .bcesim-modal').on('click', function(e){
        if(e.target === this){
            $('#bcesim-modal').fadeOut(300);
        }
    });
});
