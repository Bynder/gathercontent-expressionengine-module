<div class="gc_container gc_cf">
	<div class="gc_container-header">
		<h2><?php echo lang('gathercontent_importing_files') ?></h2>
	</div>
	<div id="gc_media">
		<div class="alert alert-success">
			<?php echo lang('gathercontent_media_header') ?>
		</div>
		<label><?php echo lang('gathercontent_page') ?> <span id="gc_page_title" title="<?php echo $original_title ?>"><?php echo $page_title ?></span><img src="<?php echo $_theme_url ?>images/ajax-loader-grey.gif" alt="" /></label>
		<div id="current_page" class="progress">
			<div class="bar" style="width:0%"></div>
		</div>
		<label><?php echo lang('gathercontent_progress') ?></label>
		<div id="overall_files" class="progress">
			<div class="bar" style="width:0%"></div>
		</div>
		<div class="gc_center">
			<a href="<?php ee()->gc_functions->url('pages_import') ?>" class="gc_blue_link"><?php echo lang('gathercontent_cancel') ?></a>
		</div>
	</div>
</div>
<script type="text/javascript">
var redirect_url = '<?php ee()->gc_functions->url('finished') ?>';
</script>
