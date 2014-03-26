<div class="gc_container gc_wide" id="gc_pagelist_container">
    <?php echo form_open($action_url, array('id'=>'gc_importer_step_pages')) ?>
    <?php
    $errs = validation_errors();
    if(!empty($errs))
    {
        echo '
        <div class="alert alert-danger">'.lang('gathercontent_review_errors').'</div>';
    }
    ?>
        <div class="gc_main_content">
            <div class="gc_search_pages gc_cf">
                <div class="gc_left">
                    <?php echo $projects_dropdown ?>
                </div>
                <div class="gc_right">
                    <?php echo $state_dropdown ?>
                    <?php
                    $data = array(
                        'type' => 'text',
                        'name' => 'search',
                        'id' => 'gc_live_filter',
                        'placeholder' => lang('gathercontent_search'),
                    );
                    echo form_input($data);
                    ?>
                    <?php echo $page_count > 0 ? $submit_button : '' ?>
                </div>
            </div>
            <table class="gc_pages gc_pagelist" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th></th>
                        <th class="gc_th_page_name"><?php echo lang('gathercontent_pages') ?></th>
                        <th><input type="checkbox" id="toggle_all" /></th>
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
