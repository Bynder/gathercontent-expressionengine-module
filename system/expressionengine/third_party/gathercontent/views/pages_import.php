
<div class="gc_container gc_wide gc_cf">
    <div class="gc_overlay"></div>
    <div class="gc_container gc_modal gc_importing_modal">
        <h2><?php echo lang('gathercontent_importing') ?></h2>
        <label><?php echo lang('gathercontent_pages') ?> <span id="gc_page_title"></span><img src="<?php echo $_theme_url ?>images/ajax-loader-grey.gif" alt="" /></label>
        <div id="current_page" class="progress">
            <div class="bar" style="width:0%"></div>
        </div>
    </div>
    <div class="gc_container gc_modal gc_repeating_modal">
        <h2><?php echo lang('gathercontent_repeating') ?></h2>
        <img src="<?php echo $_theme_url ?>images/ajax_loader_blue.gif" alt="" />
    </div>
    <?php echo form_open($action_url, array('id'=>'gc_importer_step_pages_import')) ?>
        <div class="gc_main_content">
            <div class="gc_search_pages gc_cf">
                <div class="gc_left">
                    <a href="<?php echo $_base_url.AMP.'method=pages'  ?>" class="gc_option"><?php echo lang('gathercontent_select_pages') ?></a>
                </div>
                <div class="gc_right">
                    <?php echo $page_count > 0 ? $submit_button : '' ?>
                </div>
            </div>
            <table class="gc_pages" id="gc_pages" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th></th>
                        <th class="gc_th_page_name"><?php echo lang('gathercontent_pages') ?></th>
                        <th><input type="checkbox" id="toggle_all" checked="checked" /></th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $page_settings ?>
                </tbody>
            </table>
        </div>
        <div class="gc_subfooter gc_cf">
            <div class="gc_right">
                <?php echo $page_count > 0 ? $submit_button : '' ?>
            </div>
        </div>
    </form>
</div>
