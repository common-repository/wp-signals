(function($)
{
	'use strict';
	function getHref() {
		if (window.location.href.indexOf('#') >= 0) {
			return window.location.href.substring(0, window.location.href.indexOf('#'));
		}

		return window.location.href;
	}

	function showAlert(title, message, type, options={}) {
		$('.wp-signals-alert').remove();

		let fields = '';
		if (options.fields) {
			options.fields.forEach(field => {

				fields += `<div class="form-group"${(field.title ? '' : ' style="margin-top: -55px !important;"')}>`;

				if (field.type === 'input') {
					if (field.title) {
						fields += `<label for="${field.name}">${field.title}</label>`;
					}

					fields += `<input type="text"${field.autocomplete ? ' autocomplete="' + field.autocomplete + '"' : ''} name="${field.name}" value="" />`
				}

				if (field.type === 'select') {
					fields += `<label for="${field.name}">${field.title}</label>`;
					fields += `<select name="${field.name}">`

					field.options.forEach(option => {
						fields += `<option value="${option.value.replace(new RegExp(' ', 'g'), '-')}">${option.text || option.value}</option>`;
					})

					fields += '</select>';
				}

				fields += '</div>';
			})
		}

		$('.wp-signals-screen').append(`<div class="wp-signals-alert wp-signals-alert-${type}">
			<div class="alert-contents">
			<span class="close">&times;</span>
			<h3>${title}</h3>			
			<p>${message}</p>
			
			${fields}
		
			${(options.onConfirm) ?  '<button class="close">' + (options.cancel || 'Cancel') + '</button><button class="confirm">' + (options.confirm || 'Confirm') + '</button>' : '<button class="close">Got it.</button>'}
			</div>
			</div>
		`);

		$('.wp-signals-alert').css('display', 'block');
		$('.wp-signals-alert .confirm').unbind('click').click(function(event) {
			event.preventDefault();

			let blockedExecution = false;
			let fields = {};
			$('.wp-signals-alert input').css('box-shadow', 'none');
			if (options.fields) {
				if ($('.wp-signals-alert').find('input').filter(function() { return !this.value; }).length > 0) {
					blockedExecution = true;
					$('.wp-signals-alert').find('input').filter(function() { return !this.value; }).css('box-shadow', '2px 2px 2px red');
				}

				$('.wp-signals-alert').find('input, select').each(function(){
					fields[$(this).attr('name')] = $(this).val();
				});
			}

			if (!blockedExecution) {
				if (options.onConfirm) {
					options.onConfirm(fields);
				}

				$('.wp-signals-alert').remove();
			}
		});

		$('.wp-signals-alert .close').unbind('click').click(function(event) {
			event.preventDefault();
			$('.wp-signals-alert').remove();
		});
	}


	$(document).ready(() => {
		if ($('.wp-signals-settings-screen').length > 0 &&  $('input[name="redirector"]').length > 0) {
			window.location.href = $('input[name="redirector"]').val();
		}

		$('.wp-signals-settings-screen form.pixel-form select[name="ad-account"]').on('change', function() {
			$('.wp-signals-settings-screen form.pixel-form select[name="pixel-id"] option[data-account-id]').hide();
			$('.wp-signals-settings-screen form.pixel-form select[name="pixel-id"] option[data-account-id="' + $(this).val() + '"]').show();
			$('.wp-signals-settings-screen form.pixel-form select[name="pixel-id"]').val('choose');
		}).trigger('change');

		$('.wp-signals-settings-screen a.create-pixel-btn').click(function(event){
			event.preventDefault();

			if ($('.wp-signals-settings-screen form.pixel-form select[name="ad-account"] option:selected').val() === 'choose') {
				showAlert('Ad account', 'Please select an ad account (from the first dropdown) and try again.', 'error');
				return ;
			}

			const accountId = $('.wp-signals-settings-screen form.pixel-form select[name="ad-account"]').val();
			const adAccountDisabled = $('.wp-signals-settings-screen form.pixel-form select[name="ad-account"] option:selected').data('disabled');
			if (!accountId || (adAccountDisabled && (adAccountDisabled + '') === 'true')) {
				showAlert('Ad account', 'You cannot add a pixel on a business account. Please select a different ad account and try again.', 'error');
				return ;
			} else {

				showAlert('Add pixel', "To create a new pixel, please enter it's name in the input box below... The pixel will be created in your selected ad account.", 'error', {
					fields: [{
							type: 'input',
							title: 'Pixel name',
							autocomplete: 'off',
							name: 'identifier'
						}],

					confirm: "Add pixel",
					onConfirm: (data) => {
						if (data && data.identifier) {

							$.post(getHref(), {
								action: 'pixel',
								name: data.identifier,
								account: accountId

							}).then(result => {

								if (result && typeof result === 'string' && result.indexOf('{') >= 0) {
									result = JSON.parse(result);
								}

								if (result.id && !result.error) {
									const pixelId = result.id;
									$('.wp-signals-settings-screen form.pixel-form select[name="pixel-id"]').append(`<option data-account-id="${accountId}" value="${pixelId}">${data.identifier} (#${pixelId})</option>`);
									$('.wp-signals-settings-screen form.pixel-form select[name="pixel-id"]').val(pixelId);
								}

								if (result.error) {
									showAlert('Failed', 'Failed to create pixel. You might already have a pixel for this ad account, or you are missing the necessary permissions. Response: ' + result.error, 'error');
								}
							})


						}
					}
				});

			}

		})

		$('.wp-signals-dashboard-screen div.dashboard-status div.image img[data-status]').css('cursor', 'pointer').click(function(event){
			event.preventDefault();

			let nextStatus = 'off';
			if ($(this).data('status') === 'off') {
				nextStatus = 'on';
			}

			$.post(getHref(), {
				'status': nextStatus
			}).then(() => {
				window.location.href = getHref() + '#updated';
				window.location.reload();
			});
		})

		$('.wp-signals-settings-screen .manual-pixel-setup-btn').css('cursor', 'pointer').click(event => {
			event.preventDefault();

			showAlert('Confirmation needed', 'Using the manual pixel setup only requires your pixel ID, but it will make it harder to setup the Conversions API and load Facebook analytics (you might need to generate a Facebook access token manually).<br /><br /> We strongly recommend using the Wizard to connect your Facebook account to our plugin, unless you cannot find/load your pixel with the wizard.', 'error', {
				confirm: "Use the manual setup anyway",
				onConfirm: () => {
					const params = new URLSearchParams(location.search || '');
					params.set('result', 'success');
					params.set('setup', 'manual');
					window.location.href = window.location.pathname + '?' + params.toString();
				}
			});
		})

		$('.wp-signals-settings-screen .manual-pixel-link').css('cursor', 'pointer').click(event => {
			event.preventDefault();

			const params = new URLSearchParams(location.search || '');
			params.set('result', 'success');
			params.set('setup', 'manual');
			window.location.href = window.location.pathname + '?' + params.toString();
		})

		$('.wp-signals-settings-screen .btn-setup-pixel').click((event) => {

			$('form.pixel-form input[name="pixel-limited"]').val('false');

			if ($('form.pixel-form input[name="simple-pixel-id"]').length > 0 && $('form.pixel-form input[name="simple-pixel-id"]').val().length === 0) {
				event.preventDefault();
				event.stopPropagation();

				showAlert('Incomplete data', 'You must enter a valid pixel id to complete the setup procedure. Please try again.', 'error');
			}

			if ($('form.pixel-form input[name="simple-pixel-id"]').length === 0) {
				if ((!$('form.pixel-form select[name="pixel-id"]').val() || $('form.pixel-form select[name="pixel-id"]').val() === 'choose')) {
					event.preventDefault();
					event.stopPropagation();

					showAlert('Incomplete data', 'You must select a pixel to complete the setup procedure. Please try again.', 'error');
				} else {
					$('form.pixel-form input[name="pixel-name"]').val($('form.pixel-form select[name="pixel-id"] option:selected').text())

					if ($('form.pixel-form select[name="pixel-id"] option:selected').data('disabled') == 'true') {
						$('form.pixel-form input[name="pixel-limited"]').val('true');
					}
				}
			}

			if (!$('form.pixel-form input[name="pixel-name"]').val()) {
				$('form.pixel-form input[name="pixel-name"]').val('FB Pixel');
			}

			if ($('form.pixel-form input[name="simple-pixel-id"]').length > 0 && $('form.pixel-form input[name="simple-pixel-id"]').val()) {
				const pixelId = '(' + $('form.pixel-form input[name="simple-pixel-id"]').val() + ')';
				$('form.pixel-form input[name="pixel-name"]').val($('form.pixel-form input[name="pixel-name"]').val() + ' ' + pixelId);
			}
		});


		$('.wp-signals-settings-screen button.btn-delete-data').css('cursor', 'pointer').click(event => {
			event.preventDefault();

			showAlert('Confirmation needed', 'Are you sure you want to reset your plugin by starting from scratch and clearing all settings?', 'error', {
				onConfirm: () => {
					$.post(getHref(), {
						delete: 'data'
					}).then(() => {
						window.location.href = getHref() + '#updated';
						window.location.reload();
					})
				}
			})
		});

		$('.wp-signals-settings-screen div.form-check, .wp-signals-events-screen div.form-check').css('cursor', 'pointer').click(function(event){
			event.preventDefault();

			if ($(this).attr('data-value') == 'false' && $(this).attr('data-available') == 'false' && $(this).attr('data-error-title') && $(this).attr('data-error-message')) {
				showAlert($(this).attr('data-error-title'), $(this).attr('data-error-message'), 'error');
				return;
			}

			$(this).attr('data-value', $(this).attr('data-value') == 'true' ? 'false' : 'true');

			$.post(getHref(), {
				[$(this).attr('data-id')]: $(this).attr('data-value') == 'true' ? 'true' : 'false'
			})

			if ($(this).attr('data-id') === 'sse') {

				if ($(this).attr('data-value') == 'true') {
					$('div.manual-sse-div-info').show();
				} else {
					$('div.manual-sse-div-info').hide();
				}

			}
		});


		$('.wp-signals-settings-screen textarea[data-id="sse-manual-token"]').bind('input propertychange', function() {

			const value = $(this).val().trim().replace(/ /g,'')

			if (value.length === 0 || (value && value.length >= 50)) {
				$(this).css('box-shadow', '3px 2px 5px rgba(100, 255, 100, 0.6)')

				if (value.length === 0) {
					$(this).css('box-shadow', 'none')
				}

				$.post(getHref(), {
					[$(this).attr('data-id')]: $(this).val().trim()
				})
			} else {
				$(this).css('box-shadow', '3px 2px 5px rgba(255, 100, 100, 0.6)')
			}
		});


		function updateEventsTable() {
			$('.wp-signals-events-screen table.events').each(function(){

				if ($(this).find('tr').length > 1) {
					$(this).find('tr.no-events').addClass('hidden');
				} else {
					$(this).find('tr.no-events').removeClass('hidden');
				}

				$(this).find('img.remove-event').unbind('click').css('cursor', 'pointer').click(function(event){
					event.preventDefault();

					let data = {
						event: $(this).closest('tr').find('td').eq(0).text(),
						path: $(this).closest('tr').find('td').eq(1).text(),
					}


					showAlert('Confirmation needed', 'Are you sure you want to delete this custom event trigger?', 'error', {
						onConfirm: () => {

							$.post(getHref(), {
								action: 'remove',
								event: data.event,
								path: data.path
							});

							$(this).closest('tr').remove();
							updateEventsTable();
						}
					})
				});

			});
		}

		updateEventsTable();
		$('.wp-signals-events-screen button.btn-add-event').css('cursor', 'pointer').click(event => {

			event.preventDefault();

			showAlert('Add event', "To create an event trigger, select the event type you want to send, and the 'path' for which you want to trigger the event. For example, using /contact/ will trigger the event for all page urls containing the pattern /contact/.", 'error', {
				fields: [{
					type: 'select',
					name: 'event',
					title: 'Facebook event:',
					options: [
						{ value: 'AddToCart' },
						{ value: 'AddToWishlist' },
						{ value: 'CompleteRegistration' },
						{ value: 'Contact' },
						{ value: 'Donate' },
						{ value: 'InitiateCheckout' },
						{ value: 'Lead' },
						{ value: 'Purchase' },
						{ value: 'Search' },
						{ value: 'StartTrial' },
						{ value: 'SubmitApplication' },
						{ value: 'Subscribe' },
						{ value: 'ViewContent' }
					]
				}, {
					type: 'select',
					name: 'matching',
					title: 'Page url:',
					options: [
						{ value: 'starts with' },
						{ value: 'contains' },
						{ value: 'matches' },
						{ value: 'ends with' }
					]
				},
					{
					type: 'input',
						autocomplete: 'off',
					name: 'path'
				}],

				confirm: "Add event",
				onConfirm: (data) => {
					if (data && data.event && data.matching && data.path) {

						data.path = (data.matching === 'starts-with' || data.matching === 'contains' ? '*' : '')
							+ data.path
							+ (data.matching === 'ends-with' || data.matching === 'contains' ? '*' : '');

						$.post(getHref(), {
							action: 'add',
							event: data.event,
							path: data.path
						});

						//add to list
						const iconsPath = $('table.events').data('icons');
						$('table.events').append(`<tr>
							<td>${data.event}</td>
							<td>${data.path}</td>
							<td style="width: 100px; text-align: center;"><img class="remove-event" src="${iconsPath}/delete.png" /></td>
						</tr>`)

						updateEventsTable();
					}
				}
			});

		});



		$('.wp-signals-screen .button-option[data-href]').css('cursor', 'pointer').click(function(){
			window.location.href = $(this).data('href');
		})


		function sortKeysByDate(keys) {
			const result = [];
			keys.forEach(key => {
				const smartKey = key.split('-').map(i => (i.length === 1 ? '0' + i : i)).join('-');
				result.push(smartKey);
			});

			return result.sort();
		}

        function extractData(info, addMissingKeys = false) {
            let obj = undefined;
            if (typeof info === 'string' || info instanceof String) {
                obj = JSON.parse(info.replace(new RegExp("'", 'g'), '"'));
            } else {
                obj = info;
            }

            const result = [];
            Object.keys(obj).forEach(key => {
                const smartKey = key.split('-').map(i => (i.length === 1 ? '0' + i : i)).join('-');
                result.push([smartKey, obj[key]]);
            });

            if (addMissingKeys && $('.wp-signals-analytics-screen #chart').data('information')) {
                const previousKeys = extractData($('.wp-signals-analytics-screen #chart').data('information'));
                for (let previousKeyData of previousKeys) {
                    const previousKey = previousKeyData[0];
                    if (!result.find(i => i[0] === previousKey)) {
                        result.push([previousKey, 0]);
                    }
                }
            }

            result.sort((a, b) => {
                return (a < b) ? -1 : (a > b ? 1 : 0);
            });

            return result;
        }

		$('.wp-signals-analytics-screen #chart').each(() => {

			var options = {
				xAxis: {
					type: 'category',
					boundaryGap: false
				},
				yAxis: {
					type: 'value',
					boundaryGap: [0, '30%']
				},
				tooltip: {
					show: true,
					trigger: 'axis'
				},
				visualMap: {
					type: 'piecewise',
					show: false,
					dimension: 0,
					seriesIndex: 0,
					pieces: [{
						gt: 1,
						lt: 3,
						color: 'rgba(0, 180, 0, 0.5)'
					}, {
						gt: 5,
						lt: 7,
						color: 'rgba(0, 180, 0, 0.5)'
					}]
				},
				series: [
					{
						name: 'Auto tracking',
						type: 'line',
						smooth: 0.6,
						symbol: 'circle',
						color: 'green',
						lineStyle: {
							color: 'green',
							width: 5
						},
						markLine: {
							symbol: ['none', 'none'],
							label: {show: false},
							data: [
								{xAxis: 1},
								{xAxis: 3},
								{xAxis: 5},
								{xAxis: 7}
							]
						},
						areaStyle: { color: 'rgba(0, 180, 0, 0.5)' },
						data: extractData($('.wp-signals-analytics-screen #chart').data('information'))
					}
				]
			};





			if ($('.wp-signals-analytics-screen #chart').data('pixel-limited')) {
				var myChart = echarts.init($('.wp-signals-analytics-screen #chart')[0]);
				myChart.setOption(options);

			} else {
				$.get(getHref() + '&data=json&operation=fb-analytics')
					.then(result => {
						if (result && Object.keys(result).length > 0 && !result.error) {
							options.series.push({
									name: 'Facebook tracking',
									type: 'line',
									smooth: 0.6,
									symbol: 'rect',
									color: 'blue',
									lineStyle: {
										color: 'blue',
										width: 5
									},
									markLine: {
										symbol: ['none', 'none'],
										label: {show: false},
										data: [
											{xAxis: 1},
											{xAxis: 3},
											{xAxis: 5},
											{xAxis: 7}
										]
									},
									areaStyle: { color: 'rgba(0, 0, 180, 0.1)' },
									data: extractData(result, true)
								});
						}
					})
					.always(() => {
						var myChart = echarts.init($('.wp-signals-analytics-screen #chart')[0]);
						myChart.setOption(options);
					})
			}


		})



		$('.wp-signals-analytics-screen #keyschart').each(() => {

			var options = undefined;

			$.get(getHref() + '&data=json&operation=fb-match-keys')
				.then(result => {

					if (typeof result === 'string' || result instanceof String) {
						result = JSON.parse(result.replace(new RegExp("'", 'g'), '"'));
					}

					if (result.error) {
						delete result.error;
					}

					if (Object.keys(result).length === 0) {
						options = undefined;
					} else {
						options = {
                            xAxis: {
                                type: 'category',
                                boundaryGap: false,
                                data: sortKeysByDate(Object.keys(result[Object.keys(result)[0]]))
                            },
                            yAxis: {
                                type: 'value'
                            },
                            tooltip: {
                                trigger: 'axis'
                            },
                            legend: {
                                data: Object.keys(result)
                            },

                            series: Object.keys(result).map((key, index) => {
                                return {
                                    name: key,
                                    data: extractData(result[key]).map(([k, v]) => v),
                                    itemStyle: {color: ['#c25652', '#51616d', '#77a8ae', '#429da3'][index % 4]},
                                    smooth: false,
                                    type: 'line'
                                }
                            })
                        };

                    }
				})
				.always(() => {
					if (options) {
						$('.wp-signals-analytics-screen .keyschart-detail').css('display', 'block');
						var myChart = echarts.init($('.wp-signals-analytics-screen #keyschart')[0]);
						myChart.setOption(options);
					} else {
						$('.wp-signals-analytics-screen #keyschart').html('<p class="no-info-chart">No information. Please make sure your pixel is setup correctly, and wait for Facebook to process your data (might take up to 30 minutes).</p>')
						$('.wp-signals-analytics-screen .keyschart-detail').remove()
					}
				})
		})
	});


})(jQuery);
