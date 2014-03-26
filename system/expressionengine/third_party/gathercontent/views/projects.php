<div class="gc_container">
    <?php echo form_open($action_url, array('id'=>'gc_importer_step_projects')) ?>
    <?php
    $errs = validation_errors();
    if(!empty($errs))
    {
        echo '
        <div class="alert alert-danger">'.lang('gathercontent_review_errors').'</div>';
    }
    ?>
        <?php if(count($projects) > 0): ?>
        <ul class="gc_list">
        <?php foreach($projects as $id => $info):
        if(empty($project_id))
        {
            $project_id = $id;
        }
        $fieldid = 'gc_project_'.$id; ?>
            <li>
                <?php
                $data = array(
                    'class' => 'gc_radio',
                    'name' => 'project_id',
                    'id' => $fieldid,
                    'value' => $id,
                    'checked' => ($project_id == $id),
                );
                echo form_radio($data);
                ?>
                <label for="<?php echo $fieldid ?>" class="gc_label"><?php echo $info['name'] ?> &mdash; <span class="page-count"><?php echo $info['page_count'].' page'.($info['page_count'] == '1'?'':'s') ?></span></label>
            </li>
        <?php endforeach ?>
        </ul>
        <?php else: ?>
            <p class="gc_error"><?php lang('gathercontent_no_projects') ?></p>
        <?php endif ?>
        <?php if(count($projects) > 0): ?>
        <div class="gc_left">
            <?php echo $submit_button ?>
        </div>
        <?php endif ?>
    </form>
</div>
