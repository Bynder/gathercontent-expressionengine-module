<div class="gc_container">
    <?php echo form_open($action_url, array('id'=>'gc_importer_step_auth')) ?>
    <?php
    $errs = validation_errors();
    if(!empty($errs))
    {
        echo '
        <div class="alert alert-danger">'.lang('gathercontent_review_errors').'</div>';
    }
    ?>
        <div class="gc_api_url">
            <label for="gc_api_url"><?php echo lang('api_url_title') ?></label>
            <span class="gc_domainprefix">https://</span>
            <?php
            $err = form_error('api_url', ' ', ' ');
            $data = array(
                'name' => 'api_url',
                'value' => set_value('api_url',$api_url),
                'id' => 'gc_api_url',
            );
            if(!empty($err))
            {
                $data['class'] = 'error';
                $data['title'] = $err;
            }
            echo form_input($data);
            ?>
            <span class="gc_domain">.gathercontent.com</span>
        </div>
        <div>
            <label for="gc_api_key"><?php echo lang('api_key') ?><a href="#" class="gc-ajax-tooltip" title="<?php echo lang('api_key_tooltip') ?>"></a></label>
            <?php
            $err = form_error('api_key', ' ', ' ');
            $data = array(
                'name' => 'api_key',
                'value' => set_value('api_key',$api_key),
                'id' => 'gc_api_key',
            );
            if(!empty($err))
            {
                $data['class'] = 'error';
                $data['title'] = $err;
            }
            echo form_input($data);
            ?>
        </div>
        <div class="gc_cf">
            <?php echo $submit_button ?>
        </div>
    </form>
</div>
