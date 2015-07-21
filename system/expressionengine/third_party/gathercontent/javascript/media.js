;(function($){
	var progressbar1, progressbar2, cur_num = 1, cur_total = 1, cur_retry = 0, title;
	$(document).ready(function(){
		progressbar1 = $('#current_item .bar');
		progressbar2 = $('#overall_files .bar');
		waiting = $('#gc_media img');

		title = $('#gc_item_title');

		get_image();
	});
	function get_image(){
		$.ajax({
			url: EE.BASE + '&C=addons_modules&M=show_module_cp&module=gathercontent&method=import_media',
			data: {
				'cur_num': cur_num,
				'cur_total': cur_total,
				'cur_retry': cur_retry
			},
			dataType: 'json',
			timeout: 120000,
			beforeSend: function(){
				waiting.show();
			},
			error: function(){
				waiting.hide();
				if(cur_retry == 0){
					cur_retry++;
					get_image();
				} else {
					//alert error
				}

			},
			success: function(data){
				waiting.hide();
				if(typeof data.error != 'undefined'){
					alert(data.error);
					cur_retry++;
				}
				if(typeof data.success != 'undefined'){
					cur_retry = 0;
					if(typeof data.retry != 'undefined'){
						cur_retry = cur_retry++;
						setTimeout(get_image,1000);
					} else {
						progressbar1.css('width',data.item_percent+'%');
						progressbar2.css('width',data.overall_percent+'%');
						cur_num = data.cur_num;
						cur_total = data.cur_total;
						if(data.item_percent == '100' && data.overall_percent != '100'){
							setTimeout(function(){
								progressbar1.css('width','0%');
								title.html(data.item_title).attr('title',data.original_title);
							},1000);
						}
						if(data.overall_percent == '100'){
							setTimeout(function(){
								window.location.href = EE.BASE+"&C=addons_modules&M=show_module_cp&module=gathercontent&method=finished";
							},1000);
						} else {
							setTimeout(get_image,1000);
						}
					}
				}
			}
		});
	};
})(jQuery);
