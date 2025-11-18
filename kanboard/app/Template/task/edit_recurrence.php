<!-- 
    CHANGE: Updated recurrence settings form template
    PURPOSE: Displays the recurrence settings panel with all required fields
    STRUCTURE: Form with 5 fields + Save/Cancel buttons as specified in requirements
-->
<div class="page-header">
    <h2><?= t('Edit recurrence') ?></h2>
</div>
<section id="task-section">
<form method="post" action="<?= $this->u('task', 'recurrence', array('task_id' => $task['id'], 'project_id' => $task['project_id'], 'ajax' => $ajax)) ?>" autocomplete="off">

    <?= $this->formCsrf() ?>

    <div class="form-column">
        <?= $this->formHidden('id', $values) ?>
        <?= $this->formHidden('project_id', $values) ?>

        <!-- 
            FIELD 1: Generate recurrent task (Main on/off switch)
            TYPE: Dropdown
            OPTIONS: "Yes" (enables recurrence), "No" (disables recurrence)
            DEFAULT: "No"
        -->
        <?= $this->formLabel(t('Generate recurrent task'), 'recurrence_status') ?>
        <?= $this->formSelect('recurrence_status', isset($recurrence_status_list) ? $recurrence_status_list : array(), isset($values) ? $values : array(), isset($errors) ? $errors : array()) ?><br/>

        <!-- 
            FIELD 2: Trigger to generate recurrent task
            TYPE: Dropdown
            OPTIONS: 
            - When task is closed
            - When task is moved from first column
            - When task is moved to last column
        -->
        <?= $this->formLabel(t('Trigger to generate recurrent task'), 'recurrence_trigger') ?>
        <?= $this->formSelect('recurrence_trigger', isset($recurrence_trigger_list) ? $recurrence_trigger_list : array(), isset($values) ? $values : array(), isset($errors) ? $errors : array()) ?><br/>

        <!-- 
            FIELD 3: Factor to calculate new due date
            TYPE: Numeric input (integer)
            EXAMPLE: 4
            VALIDATION: Minimum value of 0
        -->
        <?= $this->formLabel(t('Factor to calculate new due date'), 'recurrence_factor') ?>
        <?= $this->formNumber('recurrence_factor', isset($values) ? $values : array(), isset($errors) ? $errors : array(), array('min="0"')) ?><br/>

        <!-- 
            FIELD 4: Timeframe to calculate new due date
            TYPE: Dropdown
            OPTIONS: Day(s), Month(s), Year(s)
        -->
        <?= $this->formLabel(t('Timeframe to calculate new due date'), 'recurrence_timeframe') ?>
        <?= $this->formSelect('recurrence_timeframe', isset($recurrence_timeframe_list) ? $recurrence_timeframe_list : array(), isset($values) ? $values : array(), isset($errors) ? $errors : array()) ?><br/>

        <!-- 
            FIELD 5: Base date to calculate new due date
            TYPE: Dropdown
            OPTIONS: 
            - Existing due date (uses previous task's due date)
            - Action date (uses date when trigger event happened)
        -->
        <?= $this->formLabel(t('Base date to calculate new due date'), 'recurrence_basedate') ?>
        <?= $this->formSelect('recurrence_basedate', isset($recurrence_basedate_list) ? $recurrence_basedate_list : array(), isset($values) ? $values : array(), isset($errors) ? $errors : array()) ?><br/>
    </div>

    <!-- 
        FORM ACTIONS: Save and Cancel buttons
        Save: Submits form to save recurrence settings
        Cancel: Returns to task view (or board if AJAX)
    -->
    <div class="form-actions">
        <input type="submit" value="<?= t('Save') ?>" class="btn btn-blue"/>
        <?= t('or') ?>
        <?php if ($ajax): ?>
            <?= $this->a(t('cancel'), 'board', 'show', array('project_id' => $task['project_id']), false, 'close-popover') ?>
        <?php else: ?>
            <?= $this->a(t('cancel'), 'task', 'show', array('task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>
        <?php endif ?>
    </div>
</form>
</section>