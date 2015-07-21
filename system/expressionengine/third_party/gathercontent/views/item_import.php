
<div class="gc_container gc_wide gc_cf">
    <div class="gc_overlay"></div>
    <div class="gc_container gc_modal gc_importing_modal">
        <h2><?php echo lang('gathercontent_importing') ?></h2>
        <label><?php echo lang('gathercontent_items') ?> <span id="gc_item_title"></span><img src="<?php echo $_theme_url ?>images/ajax-loader-grey.gif" alt="" /></label>
        <div id="current_item" class="progress">
            <div class="bar" style="width:0%"></div>
        </div>
    </div>
    <div class="gc_container gc_modal gc_repeating_modal">
        <h2><?php echo lang('gathercontent_repeating') ?></h2>
        <img src="<?php echo $_theme_url ?>images/ajax_loader_blue.gif" alt="" />
    </div>
    <?php echo form_open($action_url, array('id'=>'gc_importer_step_items_import')) ?>
        <div class="gc_main_content">
            <div class="gc_search_items gc_cf">
                <div class="gc_left">
                    <a href="<?php echo $_base_url.AMP.'method=items'  ?>" class="gc_option"><?php echo lang('gathercontent_select_items') ?></a>
                </div>
                <div class="gc_right">
                    <?php echo $item_count > 0 ? $submit_button : '' ?>
                </div>
            </div>
            <table class="gc_items" id="gc_items" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th></th>
                        <th class="gc_th_item_name"><?php echo lang('gathercontent_items') ?></th>
                        <th><input type="checkbox" id="toggle_all" checked="checked" /></th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $item_settings ?>
                </tbody>
            </table>
        </div>
        <div class="gc_subfooter gc_cf">
            <div class="gc_right">
                <?php echo $item_count > 0 ? $submit_button : '' ?>
            </div>
        </div>
    </form>
</div>
