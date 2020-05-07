jQuery(document).ready(function() {

    jQuery('#calc_shipping_city, #billing_city, #shipping_city').autocomplete({
        minChars: 2,
        source: function(term, suggest){
            try { searchRequest.abort(); } catch(e){}
            searchRequest = jQuery.post(myajax.url, { search: term, action: 'ks2008_city_autocomplete' }, function(res) {
                suggest(res.data);
            });
        }
    });

    jQuery('#billing_address_1, #shipping_address_1').autocomplete({
        minChars: 2,
        source: function(term, suggest){
            try { searchRequest.abort(); } catch(e){}
            var town = jQuery('#shipping_city').val();
            if (!town) {
                town = jQuery('#billing_city').val();
            }
            searchRequest = jQuery.post(myajax.url, { search: term, town: town, action: 'ks2008_adress_autocomplete' }, function(res) {
                suggest(res.data);
            });
        }
    });

    jQuery('body').on('click', '.ui-menu-item', function() {
        jQuery(document.body).trigger("update_checkout");
    });

    jQuery('.woocommerce-shipping-methods input').on('change', function(){
        if(jQuery(this).val() == 'KC2008_shipping_method') {
            jQuery('.ks2008_pvzname, .ks2008_pvzcode,').css('display','block');
        }
    });

    if(jQuery('.woocommerce-shipping-methods input[value="KC2008_shipping_method"]').is(':checked')) {
        console.log('checked');
        jQuery('.ks2008_pvzname, .ks2008_pvzcode').css('display','block');
    }

    jQuery(document.body).on("update_checkout", function(){
        console.log('update_checkout');
        if(jQuery('.woocommerce-shipping-methods input[value="KC2008_shipping_method"]').is(':checked')) {
            jQuery('.ks2008_pvzname, .ks2008_pvzcode').css('display','block');
            console.log('if');
        } else {
            jQuery('.ks2008_pvzname, .ks2008_pvzcode').css('display','none');
            console.log('else');
        }
    });

    if (jQuery('#ks2008_pvz_map_block').length > 0) {
        if (!ks2008client.weight) {
			ks2008client.weight = 0.1;
		}
        var measoftObject = measoftMap.config({
    		'pvzCodeSelector': '#pvzcode',
    		'mapSearchZoom': 10,
    		'pvzNameSelector': '#pvzname',
    		'mapBlock': 'ks2008_pvz_map_block',
    		'client_id': ks2008client.id,					// Сюда нужно указать код extra курьерской службы
    		'mapSize': {						// Размер карты
    			'width': ks2008client.map_width,
    			'height': ks2008client.map_height
    		},
    		'centerCoords': ['55.755814', '37.617635'],
    		'showMapButton': '1',
    		'showMapButtonCaption': 'Выбор на карте',
    		'filter': {
    			'acceptcard': 'YES',
				'maxweight' : ks2008client.weight
    		},
    		'allowedFilterParams': ['acceptcash', 'acceptcard', 'acceptfitting'],
    	}).init();

        var ks2008_pvz_checkout = jQuery('#ks2008_pvz_checkout');
        if (jQuery('#billing_city_field').length > 0) {
            jQuery('#ks2008_pvz_checkout').remove();
            jQuery('#billing_city_field').before(ks2008_pvz_checkout);
            ks2008_pvz_checkout.css('display','block');
            var ks2008_pvz_map_block = jQuery('#ks2008_pvz_map_block');
            jQuery('#ks2008_pvz_map_block').remove();
            jQuery('.ks2008_pvzcode').append(ks2008_pvz_map_block);

        } else if (jQuery('#billing_address_2_field').length > 0) {
            jQuery('#ks2008_pvz_checkout').remove();
            jQuery('#billing_address_2_field').after(ks2008_pvz_checkout);
            ks2008_pvz_checkout.css('display','block');
            var ks2008_pvz_map_block = jQuery('#ks2008_pvz_map_block');
            jQuery('#ks2008_pvz_map_block').remove();
            jQuery('.ks2008_pvzcode').append(ks2008_pvz_map_block);
        }

        jQuery("#pvzcode").change(function () {

    		var $this = jQuery(this);

    		if (jQuery("select#pvz_select option[value='"+$this.val()+"']").length > 0) {
    			jQuery("select#pvz_select").val($this.val());
    		} else {
    			setTimeout(function() {
    				jQuery("select#pvz_select").append('<option value="'+$this.val()+'">'+jQuery('#pvzname').val()+'</option>');
    				jQuery("select#pvz_select").val($this.val());
    			},1000);
    		}
        });
    }

});
