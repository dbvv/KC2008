jQuery(document).ready(function() {

	jQuery(".pvzcode").change(function () {

		var $this = jQuery(this);

		if (jQuery("select#pvz option[value='"+$this.val()+"']").length > 0) {
			jQuery("select#pvz").val($this.val());
		} else {
			setTimeout(function() {
				jQuery("select#pvz").append('<option value="'+$this.val()+'">'+jQuery('#pvzname').val()+'</option>');
				jQuery("select#pvz").val($this.val());
			},1000);
		}
	});
	if (jQuery('#measoftMapBlock').length > 0) {

		var mass;
		mass = jQuery('#weight').val();
		
		var measoftObject = measoftMap.config({
			'pvzCodeSelector': '.pvzcode',
			'mapSearchZoom': 10,
			'pvzNameSelector': '#pvzname',
			'mapBlock': 'measoftMapBlock',
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
				'maxweight' : mass  // Можно добавлять acceptcash (принимают наличные), acceptcard (Принимают карты), acceptfitting (Есть примерка), acceptindividuals (Если вы-физ. лицо)
			},
			'allowedFilterParams': ['acceptcash', 'acceptcard', 'acceptfitting'],
		}).init();
	}


    // var searchRequest;
	jQuery('.ks2008_order_delivery_table #city, #delivery_option_default_city').autocomplete({
		minChars: 2,
		source: function(term, suggest){
			try { searchRequest.abort(); } catch(e){}
			searchRequest = jQuery.post(myajax.url, { search: term, action: 'ks2008_city_autocomplete' }, function(res) {
				suggest(res.data);
			});
		}
	});

	jQuery('.ks2008_order_delivery_table #adress').autocomplete({
		minChars: 2,
		source: function(term, suggest){
			try { searchRequest.abort(); } catch(e){}
            var town = jQuery('.ks2008_order_delivery_table #city').val();
			searchRequest = jQuery.post(myajax.url, { search: term, town: town, action: 'ks2008_adress_autocomplete' }, function(res) {
				suggest(res.data);
			});
		}
	});

    jQuery('.delivery_option_test_call').on('click', ()=>{

        let alertBlock = document.querySelector('.alertBlock');

		var delivery_option_login = jQuery('#delivery_option_login').val();
		var delivery_option_password = jQuery('#delivery_option_password').val();
		var delivery_option_code = jQuery('#delivery_option_code').val();

		if (!delivery_option_login || !delivery_option_password || !delivery_option_code) {
			alertBlock.innerHTML = 'Для проверки авторизации заполните все три поля!';
			alertBlock.style.color = 'red';
			return;
		}

        jQuery.ajax({
            type: "POST",
            url: myajax.url,
            data: {
                action : 'query_authorization',
				delivery_option_login: delivery_option_login,
				delivery_option_password: delivery_option_password,
				delivery_option_code: delivery_option_code
            },
            beforeSend : function ( xhr ) {
                alertBlock.innerHTML = 'Соединение...';
                alertBlock.style.color = 'black';
            },
            success: function (json) {
				if( json.success ) {
					alertBlock.innerHTML = 'Успешно';
					alertBlock.style.color = 'green';
				} else {
					alertBlock.innerHTML = json.data;
                    alertBlock.style.color = 'red';
				}
                // if (response) {
                //     if(JSON.parse(response) == 'error') {
                //         // console.log(JSON.parse(response));
                //         alertBlock.innerHTML = 'Ошибка';
                //         alertBlock.style.color = 'red';
                //     } else {
                //         // console.log(JSON.parse(response));
                //         alertBlock.innerHTML = 'Успешно';
                //         alertBlock.style.color = 'green';
                //     }
                // } else {
                //     // console.log('error: ' + response);
                //     alertBlock.innerHTML = 'Ошибка';
                //     alertBlock.style.color = 'red';
                // }
            }
        });
    });

    jQuery('#ks2008_deliveryprice_calculate').on('click', ()=>{



        let alertBlock = document.querySelector('.ks2008_deliveryprice_responce');

		var townfrom, townto, mass;


        if (jQuery('#city').val()) {
			townto = jQuery('#city').val();
		} else {
            alertBlock.innerHTML = 'Ошибка: город получателя не задан';
            alertBlock.style.cssText = "display: block; color: red;";
			return false;
        }

		if (jQuery('#weight').val()) {
			mass = jQuery('#weight').val();
		} else {
			alertBlock.innerHTML = 'Ошибка: вес получателя не задан';
            alertBlock.style.cssText = "display: block; color: red;";
			return false;
		}

		if (jQuery('#townfrom').val()) {
			townfrom = jQuery('#townfrom').val();
		} else {
			alertBlock.innerHTML = 'Ошибка: город отправителя не задан';
            alertBlock.style.cssText = "display: block; color: red;";
			return false;
		}


        jQuery.ajax({
            type: "POST",
            url: myajax.url,
            data: {
                action : 'ks2008_deliveryprice_calculate',
				townfrom : townfrom,
				townto : townto,
				mass : mass
            },
            beforeSend : function ( xhr ) {
                alertBlock.innerHTML = 'Рассчитываем...';
                alertBlock.style.color = 'black';
            },
            success: function (json) {
				if( json.success ) {
					jQuery('#deliveryprice').val(json.data);
				} else{
					alertBlock.innerHTML = 'Ошибка: '+json.data;
		            alertBlock.style.cssText = "display: block; color: red;";
				}
            },
			error: function ( e ) {
				alertBlock.innerHTML = 'Ошибка: '+e;
				alertBlock.style.cssText = "display: block; color: red;";
			}
        });
    });

    jQuery('.createOrderBtn').on('click', function(event) {

		event.preventDefault();

		var delivery_date = new Date(jQuery('.order_delivery_form #delivery_date').val());
		var weekday = delivery_date.getDay();

		if (weekday == 6 || weekday == 7) {
			alert('Нельзя создать заказ на выходной день.');
			jQuery('#adress').get(0).scrollIntoView();
			return;
        }

        // var post_id = document.querySelector('[name="order_id"]').value,
        //     deliveryprice = document.querySelector('[name="deliveryprice"]').value,
        //     delivery_date = document.querySelector('[name="delivery_date"]').value,
        //     delivery_time_from = document.querySelector('[name="delivery_time_from"]').value,
        //     delivery_time_to = document.querySelector('[name="delivery_time_to"]').value,
        //     pay_type = document.querySelector('[name="pay_type"]:checked').value,
        //     orderno = document.querySelector('[name="orderno"]').value,
        //     enclosure = document.querySelector('[name="enclosure"]').value,
        //     instruction = document.querySelector('[name="instruction"]').value,
        //     pvz = document.querySelector('[name="pvz_select"]').value;

		let alertBlock = document.querySelector('.alertBlock');

        var post_id = document.querySelector('[name="order_id"]').value,
            deliveryprice,
            delivery_date,
            delivery_time_from,
            delivery_time_to,
            pay_type = document.querySelector('[name="pay_type"]:checked').value,
            orderno,
		    enclosure = document.querySelector('[name="enclosure"]').value,
            instruction = document.querySelector('[name="instruction"]').value,
            pvz;

		if (jQuery('[name="deliveryprice"]').length > 0) {
			deliveryprice = jQuery('[name="deliveryprice"]').val();
		} else {
			alertBlock.innerHTML = 'Стоимость доставки не задана';
			alertBlock.style.color = 'red';
			return;
		}

		if (jQuery('[name="delivery_date"]').length > 0) {
			delivery_date = jQuery('[name="delivery_date"]').val();
		} else {
			alertBlock.innerHTML = 'Дата доставки не задана.';
			alertBlock.style.color = 'red';
			return;
		}

		if (jQuery('[name="delivery_time_from"]').length > 0) {
			delivery_time_from = jQuery('[name="delivery_time_from"]').val();
		} else {
			alertBlock.innerHTML = 'Время доставки не задано';
			alertBlock.style.color = 'red';
			return;
		}

		if (jQuery('[name="delivery_time_to"]').length > 0) {
			delivery_time_to = jQuery('[name="delivery_time_to"]').val();
		} else {
			alertBlock.innerHTML = 'Время доставки не задано';
			alertBlock.style.color = 'red';
			return;
		}

		if (jQuery('[name="orderno"]').length > 0 && jQuery('[name="orderno"]').val() != '') {
			orderno = jQuery('[name="orderno"]').val();
		} else {
			alertBlock.innerHTML = 'Номер заказа не задан';
			alertBlock.style.color = 'red';
			return;
		}

		if (jQuery('[name="pvz_id"]').length > 0) {
			pvz = jQuery('[name="pvz_id"]').val();
		} else {
			pvz = '';
		}


        jQuery.ajax({
            type: "POST",
            url: myajax.url,
            data: {
                action     	: 'query_create_order',
                deliveryprice: deliveryprice,
                post_id: post_id,
                delivery_date: delivery_date,
                delivery_time_from: delivery_time_from,
                delivery_time_to: delivery_time_to,
                pay_type: pay_type,
                orderno: orderno,
                enclosure: enclosure,
                instruction: instruction,
                pvz: pvz
            },
            dataType: 'json',
            beforeSend : function ( xhr ) {
                alertBlock.innerHTML = 'Отправка запроса...';
                alertBlock.style.color = 'black';
            },
            success: function (response) {
                if (response.success) {

                    alertBlock.innerHTML = 'Успешно';
                    alertBlock.style.color = 'green';

					setTimeout(function(){
						jQuery('.ks2008_order_delivery_table').before('<p>Заказ № '+response.data.orderno+' отправлен в КС2008</p>');
						jQuery('.ks2008_order_delivery_table').remove();
					},2000);
                } else {
                    alertBlock.innerHTML = response.data;
                    alertBlock.style.color = 'red';
                }
            }
        });
    });


    if (document.querySelector('.required')) {
        formValidation();
    }

    function formValidation() {
        var inputs = document.querySelectorAll('.required');
        var submit = document.querySelector('#submit') ? document.querySelector('#submit') : document.querySelector('.createOrderBtn');
        var inputsNum = inputs.length;

        submit.setAttribute('disabled', 'disabled');

        for (let i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener('keyup', checkInputs);
        }
        checkInputs();
        function checkInputs() {
            var counter = 0;
            for (let i = 0; i < inputs.length; i++) {
                if(inputs[i].value.trim() != '') {
                    counter++;
                    inputs[i].style.border = '1px solid #7e8993';
                } else {
                    inputs[i].style.border = '1px solid red';
                }
            }
            if (inputsNum == counter) {
                submit.removeAttribute('disabled');
            } else {
                submit.setAttribute('disabled', 'disabled');
            }
        }
    }

	if (jQuery('#order_shipping_line_items  optgroup option[value="KC2008_shipping_method"]').length > 0) {
		if (jQuery('#order_shipping_line_items  optgroup option[value="KC2008_shipping_method"]').attr('selected') != 'selected') {
			// console.log('closed 1');
			jQuery('#kc2008_api_box').addClass('closed');
		}
	} else {
		jQuery('#kc2008_api_box').addClass('closed');
		// console.log('closed 2');
	}

	jQuery('#ks2008_clean_pvz').on('click',function(){
		jQuery('#pvzname').val('');
		jQuery('.pvzcode').val('');
	});

});
