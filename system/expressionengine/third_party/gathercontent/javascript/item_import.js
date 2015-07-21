;(function($){
	var item_loaded = false, submit_text = '';
	$(document).ready(function(){
		submit_text = $('.gc_ajax_submit_button:first span').text();

		$('.gc_tooltip').tooltip();

		$('.repeat_config input').change(function(){
			var $t = $(this);
			if($t.is(':checked')){
				$('.gc_overlay,.gc_repeating_modal').show();
				setTimeout(function(){
					repeat_config($t);
				},500);
			}
		});

		$('#gc_importer_step_items_import').submit(submit_item_import);

		$('.gc_field_map input.live_filter').click(function(e){
			e.preventDefault();
			e.stopImmediatePropagation();
		}).keyup(function(e){
			var v = $(this).val(),
				lis = $(this).parent().siblings('li:not(.hidden-item):not(.divider)');
			if(!v || v == ''){
				lis.show();
			} else {
				lis.hide().filter(':icontains_searchable('+$(this).val()+')').show();
			}
		}).focus(function(){
			$(this).trigger('keyup');
		});

		$('.gc_field_map').on('click','ul.dropdown-menu a',function(e){
			e.preventDefault();
			var $t = $(this),
				field = $t.closest('.gc_field_map'),
				tr = field.closest('tr'),
				item_id= tr.attr('data-item-id'),
				file_field = field.siblings('.gc_file_field');
			if($t.hasClass('show-upload-select')) {
				file_field.show();
				set_value(file_field);
			}
			else {
				file_field.hide();
			}
			if($('#gc_repeat_'+item_id).is(':checked')){
				var rows = tr.parent().find('tr.gc_table_row[data-item-id]'),
					idx = rows.index(tr),
					field_id = field.attr('id').split('_')[4],
					val = $t.attr('data-value');
				rows.filter(':gt('+idx+')').each(function(){
					var item_id = $(this).attr('data-item-id');
					if(!$('#gc_repeat_'+item_id).is(':checked')){
						$('#gc_field_map_'+item_id+'_'+field_id+' li:not(.hidden-item) a[data-value="'+val+'"]').trigger('click');
					} else {
						return false;
					}
				});
			}
		});

		$('.gc_field_map').on('keydown','li.inputting input', function(e){
			var key = e.keyCode || e.which;
			if(key == 13){
				$(this).trigger('blur');
			}
		}).on('blur','li.inputting input',function(e){
			var v = $(this).val(),
				li = $(this).parent(),
				prev = li.prev();
			if(!v || v == ''){
				li.remove();
			} else {
				$(this).parent().attr('data-post-type','normal').removeClass('inputting').html('<a href="#" />').find('a').attr('data-value',v).text(v).trigger('click');
			}
		});

		$('.gc_settings_container .gc_setting:not(.gc_category) .has_input, .gc_field_map .has_input, .gc_file_field .has_input').on('click','ul a',function(e){
			e.preventDefault();
			var input = $(this).closest('.has_input').find('a:first span:first').html($(this).html()).siblings('input').val($(this).attr('data-value')).trigger('change');
			input[$(this).hasClass('show-upload-select') ? 'addClass' : 'removeClass']('has-upload-dir');
			input[typeof $(this).attr('data-upload-dir') != 'undefined' ? 'attr' : 'removeAttr']('data-upload-dir', $(this).attr('data-upload-dir'));
		});
		$('.gc_category').on('click','ul li:not(.disabled) a:not([data-value="-1"])', function(e){
			e.preventDefault();
			e.stopImmediatePropagation();
			var $t = $(this),
				c = $t.closest('.btn-group'),
				lis = c.find('li:not(.hidden-item)'),
				v = '';
			$t.addClass('active');
			lis.find('a.active').each(function(){
				v += ','+$(this).attr('data-value');
			});
			c.find('input').val(v);
		});
		$('.gc_settings_container .has_input').on('click','ul li.disabled a',function(e){
			e.preventDefault();
			e.stopImmediatePropagation();
		});
		$('.gc_import_as .dropdown-menu a').click(function(){
			var $t = $(this),
				v = $t.attr('data-value'),
				type = $t.attr('data-structure-type'),
				c = $t.closest('.gc_settings_container'),
				to = c.find('.gc_settings_header .gc_import_to'),
				cat = c.find('.gc_category'),
				parent = c.find('.gc_parent');
			if(typeof type != 'undefined'){
				var lis = to.find('li[data-structure-type]');
				lis.filter(':not([data-structure-type="'+type+'"])').hide().addClass('hidden-item').end().filter('[data-structure-type="'+type+'"]').show().removeClass('hidden-item');
				if(type == 'item'){
					lis.filter(':not(.hidden-item)').filter(':not([data-post-type="'+v+'"])').addClass('disabled').end().filter('[data-post-type="'+v+'"]').removeClass('disabled');
				} else {
					lis.filter(':not(.hidden-item)').filter(':not([data-post-type="'+v+'"])').hide().addClass('hidden-item').end().filter('[data-post-type="'+v+'"]').show().removeClass('hidden-item');
				}
				parent[(type == 'item' ? 'show' : 'hide')]()
			} else {
				to.find('li[data-post-type]').filter('[data-post-type!="'+v+'"]').hide().addClass('hidden-item').end().filter('[data-post-type="'+v+'"]').show().removeClass('hidden-item');
			}
			set_value(to);
			set_map_to_fields(c,v);
			var length = cat.find('li').filter(':not([data-post-type*="|'+v+'|"])').hide().addClass('hidden-item').end().filter('[data-post-type*="|'+v+'|"]').show().removeClass('hidden-item').length;
			cat.length > 0 && set_cat_value(cat[(length>0?'show':'hide')]());
		}).closest('.btn-group').each(function(){
			set_value($(this).closest('.btn-group'));
			set_value($(this).closest('.gc_settings_container').find('.gc_parent'));
		});

		$('.gc_import_to input').change(function(){
			var elem = $(this).closest('.gc_settings_container');
			set_map_to_fields(elem,elem.find('.gc_import_as input').val());
		});

		$('.gc_settings_fields').sortable({
			handle: '.gc_move_field',
			update: function(e, ui) {
				var tr = ui.item.closest('tr'),
					item_id = tr.attr('data-item-id');
				if($('#gc_repeat_'+item_id).is(':checked')){
					var rows = tr.parent().find('tr.gc_table_row[data-item-id]'),
						idx = rows.index(tr),
						new_index = ui.item.index();
					rows.filter(':gt('+idx+')').each(function(){
						var item_id = $(this).attr('data-item-id');
						if(!$('#gc_repeat_'+item_id).is(':checked')){
							var field_id = ui.item.attr('id').split('_')[2],
								item = $('#field_'+item_id+'_'+field_id);
							if(item.length > 0){
								if(new_index > 0){
									item.parent().find('> .gc_settings_field:eq('+(new_index > item.index() ? new_index : (new_index-1))+')').after(item);
								} else {
									item.parent().prepend(item);
								}
							}
						} else {
							return false;
						}
					});

				}
			}
		});
		item_loaded = true;
	});

	function set_cat_value(elem){
		var v = elem.find('input:first').val(),
			lis = elem.find('li:not(.hidden-item):not(.disabled)'),
			el,
			str = '';
		v = v.split(',');
		for(var i=0,il=v.length;i<il;i++){
			str += (str == ''?'':',')+'a[data-value="'+v+'"]';
		}
		el = lis.find(str);
		if(el.length == 0){
			el = lis.filter(':first');
		}
		el.trigger('click');
	}

	function set_map_to_fields(elem,v){
		var to = to = elem.find('.gc_settings_header .gc_import_to'),
			to_val = to.find('input').val(),
			m = elem.find('.gc_settings_field .gc_field_map');
		m.each(function(){
			$(this).find('li').filter(':not([data-post-type!="all"]),:not([data-post-type*="|'+v+'|"])')
				.hide().addClass('hidden-item')
			.end().filter('[data-post-type="all"],[data-post-type*="|'+v+'|"]')
				.show().removeClass('hidden-item');
			set_value($(this));
			var length = $(this).find('li:not(.live_filter):not(:is_hidden)').length;
			$(this).find('li.live_filter')[(length > 13? 'show' : 'hide')]();
		});
	};

	function set_value(elem){
		var v = elem.find('input:first').val(),
			el = elem.find('li:not(.hidden-item):not(.disabled) a[data-value="'+v+'"]:first');
		if(elem.not(':visible')){
			elem.find('input:first').val('');
		}
		if(el.length == 0){
			el = elem.find('li:not(.hidden-item) a:first');
		}
		el.trigger('click');
	};
	function repeat_config($t){
		var c = $t.closest('tr'),
			item_id = c.attr('data-item-id'),
			table = $('#gc_items'),
			rows = table.find('.gc_table_row[data-item-id]'),
			idx = rows.index(c),
			field_rows = c.find('.gc_settings_field'),
			fields = {},
			import_as = $('#gc_import_as_'+item_id+' input').val();
		rows = rows.filter(':gt('+idx+')');
		field_rows.each(function(){
			var $t = $(this).removeClass('not-moved'),
				id = $t.attr('id').split('_')[2];
			fields[field_rows.index($t)] = [$t.find('.gc_field_map input[name*="map_to"]').val(),id];
		});

		rows.each(function(){
			var $t = $(this),
				item_id = $t.attr('data-item-id'),
				c = $('#gc_fields_'+item_id);
			if(!$('#gc_repeat_'+item_id).is(':checked')){
				c.find('> .gc_settings_field').removeClass('moved').addClass('not-moved');
				$('#gc_import_as_'+item_id+' a[data-value="'+import_as+'"]').trigger('click');
				for(var i in fields){
					if(fields.hasOwnProperty(i)){
						$('#gc_field_map_'+item_id+'_'+fields[i][1]+' li:not(.hidden-item) a[data-value="'+fields[i][0]+'"]').trigger('click');
						var field = $('#field_'+item_id+'_'+fields[i][1]).removeClass('not-moved').addClass('moved');
						if(i > 0){
							c.find('> .gc_settings_field:eq('+(i-1)+')').after(field);
						} else {
							c.prepend(field);
						}
					}
				};
			} else {
				return false;
			}
		});
		$('.gc_overlay,.gc_repeating_modal').hide();
	};

	var save = {
		"total": 0,
		"cur_counter": 0,
		"els": null,
		"waiting": null,
		"progressbar": null,
		"title": null,
		"cur_retry": 0
	};
	function reset_submit_button(){
		$('.gc_ajax_submit_button').removeClass('btn-wait').addClass('btn-success').find('span').text(submit_text);
	};
	function submit_item_import(e){
		e.preventDefault();
		save.els = $('#gc_items td.gc_checkbox :checkbox:checked');
		save.total = save.els.length;
		save.cur_counter = 0;
		save.waiting = $('.gc_importing_modal img');
		save.progressbar = $('#current_item .bar');
		save.title = $('#gc_item_title');
		if(save.total > 0){
			$('.gc_overlay,.gc_importing_modal').show();
			save_item();
		}
		return false;
	};

	function save_item(){
		$.ajax({
			url: EE.BASE + '&C=addons_modules&M=show_module_cp&module=gathercontent&method=import_item',
			data: get_item_data(save.els.filter(':eq('+save.cur_counter+')')),
			dataType: 'json',
			type: 'POST',
			timeout: 120000,
			beforeSend: function(){
				save.waiting.show();
			},
			error: function(){
				save.waiting.hide();
				if(save.cur_retry == 0){
					save.cur_retry++;
					save_item();
				} else {
					reset_submit_button();
					$('.gc_overlay,.gc_importing_modal').hide();
				}
			},
			success: function(data){
				save.waiting.hide();
				if(typeof data.error != 'undefined'){
					save.cur_retry++;
					alert(data.error);
					reset_submit_button();
					$('.gc_overlay,.gc_importing_modal').hide();
				}
				if(typeof data.success != 'undefined'){
					save.cur_retry--;
					if(typeof data.new_item_html != 'undefined') {

						$('#gc_items tr[data-parent-id="'+data.item_id+'"]').each(function(){
							var el = $('#gc_parent_'+$(this).attr('data-item-id')),
								input = el.find('input');

							if($(this).find('a[data-value="'+data.new_item_id+'"]').length == 0)
							{
								el.find('ul').append(data.new_item_html);
							}
							if(input.val() == '_imported_item_') {
								input.val(data.new_item_id);
								set_value(el);
							}
						});
					}
					save.cur_retry = 0;
					save.cur_counter++;
					save.progressbar.css('width',data.item_percent+'%');
					if(save.cur_counter == save.total){
						setTimeout(function(){
							window.location.href = EE.BASE+"&C=addons_modules&M=show_module_cp&module=gathercontent&method="+data.redirect_url;
						},1000);
					} else {
						setTimeout(save_item,1000);
					}
				}
			}
		});
	};

	function get_item_data($t){
		var tr = $t.closest('tr'),
			title = tr.find('td.gc_itemname label').text(),
			item_id = $t.val(),
			settings = $('#gc_fields_'+item_id),
			data = {
				"XID": gc_import.xid,
				"cur_retry": save.cur_retry,
				"cur_counter": save.cur_counter,
				"total": save.total
			},
			title_text = title;
		if(title_text.length > 30){
			title_text = title_text.substring(0,27)+'...';
		}
		save.title.attr('title',title).text(title_text);
		if(settings.length > 0){
			data.gc = {
				"item_id": item_id,
				"post_type": $('#gc_import_as_'+item_id+' input').val(),
				"overwrite": $('#gc_import_to_'+item_id+' input').val(),
				"category": $('#gc_category_'+item_id+' input').val(),
				"parent_id": $('#gc_parent_'+item_id+' input').val(),
				"fields": []
			};
			settings.find('> .gc_settings_field').each(function(){
				var $t = $(this),
					input = $t.find('> input'),
					map_to = $t.find('> .gc_field_map input'),
					field = {
						"field_tab": input.filter('[name^="gc[field_tab]"]').val(),
						"field_name": input.filter('[name^="gc[field_name]"]').val(),
						"map_to": map_to.filter('[name^="gc[map_to]"]').val()
					};
					if(map_to.hasClass('has-upload-dir')) {
						field['custom_upload_dir'] = $t.find('.gc_file_field input').val();
					}
					if(typeof map_to.attr('data-upload-dir') != 'undefined') {
						field['upload_dir'] = map_to.attr('data-upload-dir');
					}
				data.gc.fields.push(field);
			});
		}
		return data;
	};

	jQuery.expr[":"].icontains_searchable = function(obj,index,meta){
		return jQuery(obj).attr('data-search').toUpperCase().indexOf(meta[3].toUpperCase()) >= 0;
	};
	jQuery.expr[":"].is_hidden = function(obj,index,meta){
		return jQuery(obj).css('display') == 'none';
	};
})(jQuery);
