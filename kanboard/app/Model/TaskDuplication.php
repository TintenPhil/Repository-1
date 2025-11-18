<?php

namespace Model;

use Event\TaskEvent;

/**
 * Task Duplication
 *
 * @package  model
 * @author   Frederic Guillot
 */
class TaskDuplication extends Base
{
    /**
     * Fields to copy when duplicating a task
     *
     * @access private
     * @var array
     */
    private $fields_to_duplicate = array(
        'title',
        'description',
        'date_due',
        'color_id',
        'project_id',
        'column_id',
        'owner_id',
        'score',
        'category_id',
        'time_estimated',
        'swimlane_id',
    );

    /**
     * Duplicate a task to the same project
     *
     * @access public
     * @param  integer             $task_id      Task id
     * @return boolean|integer                   Duplicated task id
     */
    public function duplicate($task_id)
    {
        return $this->save($task_id, $this->copyFields($task_id));
    }

    /**
     * Duplicate a task to another project
     *
     * @access public
     * @param  integer             $task_id         Task id
     * @param  integer             $project_id      Project id
     * @return boolean|integer                      Duplicated task id
     */
    public function duplicateToProject($task_id, $project_id)
    {
        $values = $this->copyFields($task_id);
        $values['project_id'] = $project_id;
        $values['column_id'] = $this->board->getFirstColumn($project_id);

        $this->checkDestinationProjectValues($values);

        return $this->save($task_id, $values);
    }

    /**
     * Move a task to another project
     *
     * @access public
     * @param  integer    $task_id              Task id
     * @param  integer    $project_id           Project id
     * @return boolean
     */
    public function moveToProject($task_id, $project_id)
    {
        $task = $this->taskFinder->getById($task_id);

        $values = array();
        $values['is_active'] = 1;
        $values['project_id'] = $project_id;
        $values['column_id'] = $this->board->getFirstColumn($project_id);
        $values['position'] = $this->taskFinder->countByColumnId($project_id, $values['column_id']) + 1;
        $values['owner_id'] = $task['owner_id'];
        $values['category_id'] = $task['category_id'];
        $values['swimlane_id'] = $task['swimlane_id'];

        $this->checkDestinationProjectValues($values);

        if ($this->db->table(Task::TABLE)->eq('id', $task['id'])->update($values)) {
            $this->container['dispatcher']->dispatch(
                Task::EVENT_MOVE_PROJECT,
                new TaskEvent(array_merge($task, $values, array('task_id' => $task['id'])))
            );
        }

        return true;
    }

    /**
     * Check if the assignee and the category are available in the destination project
     *
     * @access private
     * @param  array      $values
     */
    private function checkDestinationProjectValues(&$values)
    {
        // Check if the assigned user is allowed for the destination project
        if ($values['owner_id'] > 0 && ! $this->projectPermission->isUserAllowed($values['project_id'], $values['owner_id'])) {
            $values['owner_id'] = 0;
        }

        // Check if the category exists for the destination project
        if ($values['category_id'] > 0) {
            $values['category_id'] = $this->category->getIdByName(
                $values['project_id'],
                $this->category->getNameById($values['category_id'])
            );
        }

        // Check if the swimlane exists for the destination project
        if ($values['swimlane_id'] > 0) {
            $values['swimlane_id'] = $this->swimlane->getIdByName(
                $values['project_id'],
                $this->swimlane->getNameById($values['swimlane_id'])
            );
        }
    }

    /**
     * Duplicate fields for the new task
     *
     * @access private
     * @param  integer       $task_id      Task id
     * @return array
     */
    private function copyFields($task_id)
    {
        $task = $this->taskFinder->getById($task_id);
        $values = array();

        foreach ($this->fields_to_duplicate as $field) {
            $values[$field] = $task[$field];
        }

        return $values;
    }

    /**
     * Create the new task and duplicate subtasks
     *
     * @access private
     * @param  integer            $task_id      Task id
     * @param  array              $values       Form values
     * @return boolean|integer
     */
    private function save($task_id, array $values)
    {
        $new_task_id = $this->taskCreation->create($values);

        if ($new_task_id) {
            $this->subtask->duplicate($task_id, $new_task_id);
        }

        return $new_task_id;
    }

    /**
     * Duplicate a recurring task when trigger condition is met
     * 
     * CHANGE: Added method to handle automatic task duplication for recurring tasks
     * PURPOSE: Creates a new task when recurrence trigger conditions are met
     * 
     * PROCESS:
     * 1. Validates that task exists and has recurrence enabled (PENDING status)
     * 2. Copies all task properties (title, description, tags, etc.)
     * 3. Calculates new due date using: base_date + (factor × timeframe)
     * 4. Places new task in first column with OPEN status
     * 5. Copies recurrence settings to new task (so it can recur again)
     * 6. Marks original task as PROCESSED (prevents duplicate generation)
     * 
     * CALLED BY: RecurringTaskSubscriber when trigger events occur
     *
     * @access public
     * @param  integer   $task_id   Task id of the recurring task
     * @return boolean|integer     New task id on success, false on failure
     */
    public function duplicateRecurringTask($task_id)
    {
        // Get the full task data including recurrence settings
        $task = $this->taskFinder->getById($task_id);

        // Validate: task must exist and have recurrence enabled (PENDING status)
        // If already PROCESSED, recurrence has already been triggered
        if (empty($task) || $task['recurrence_status'] != Task::RECURRING_STATUS_PENDING) {
            return false;
        }

        // Copy all task fields (title, description, color, category, etc.)
        // This ensures the new task inherits all properties from the original
        $values = $this->copyFields($task_id);

        // Calculate new due date based on recurrence settings
        // Formula: new_due_date = base_date + (factor × timeframe)
        // Base date is determined by recurrence_basedate setting
        $base_date = $this->getBaseDate($task);
        $new_due_date = $this->calculateNewDueDate(
            $base_date,
            $task['recurrence_factor'],
            $task['recurrence_timeframe']
        );

        // Set the new due date (this is the only property that changes)
        $values['date_due'] = $new_due_date;
        // Place new task in first column (starting position)
        $values['column_id'] = $this->board->getFirstColumn($task['project_id']);
        // New task starts as OPEN (not closed)
        $values['is_active'] = Task::STATUS_OPEN;
        // Clear completion date
        $values['date_completed'] = 0;

        // Copy recurrence settings to new task
        // This allows the new task to also recur when its trigger is met
        $values['recurrence_status'] = Task::RECURRING_STATUS_PENDING;
        $values['recurrence_trigger'] = $task['recurrence_trigger'];
        $values['recurrence_factor'] = $task['recurrence_factor'];
        $values['recurrence_timeframe'] = $task['recurrence_timeframe'];
        $values['recurrence_basedate'] = $task['recurrence_basedate'];

        // Create the new task (also duplicates subtasks)
        $new_task_id = $this->save($task_id, $values);

        if ($new_task_id) {
            // Mark original task as PROCESSED
            // This prevents the same task from generating multiple recurring tasks
            // The original task can still be used, but won't trigger again
            $this->db->table(Task::TABLE)
                ->eq('id', $task_id)
                ->update(array('recurrence_status' => Task::RECURRING_STATUS_PROCESSED));
        }

        return $new_task_id;
    }

    /**
     * Get the base date for calculating new due date
     * 
     * CHANGE: Added helper method to determine base date for calculation
     * PURPOSE: Returns the appropriate base date based on recurrence_basedate setting
     * 
     * LOGIC:
     * - If DUE_DATE: Use the original task's due date (if set)
     * - If ACTION_DATE: Use current timestamp (when trigger event happened)
     * 
     * EXAMPLE:
     * - Task due date: Jan 1, factor: 4, timeframe: months
     * - DUE_DATE: New due date = Jan 1 + 4 months = May 1
     * - ACTION_DATE: New due date = Today + 4 months
     *
     * @access private
     * @param  array    $task   Task data including recurrence settings
     * @return integer  Unix timestamp of the base date
     */
    private function getBaseDate(array $task)
    {
        if ($task['recurrence_basedate'] == Task::RECURRING_BASEDATE_DUE_DATE) {
            // Use existing due date from the original task
            // If no due date exists, fall back to current time
            return !empty($task['date_due']) ? $task['date_due'] : time();
        } else {
            // Use action date (current date when trigger event happened)
            // This is the date when the task was closed or moved
            return time();
        }
    }

    /**
     * Calculate new due date: base_date + (factor × timeframe)
     * 
     * CHANGE: Added method to calculate the new due date for recurring tasks
     * PURPOSE: Implements the formula: new_due_date = base_date + (factor × timeframe)
     * 
     * CALCULATION:
     * - Uses PHP DateTime for accurate date arithmetic
     * - Handles days, months, and years correctly
     * - Months and years handle edge cases (e.g., Feb 29, month-end dates)
     * 
     * EXAMPLES:
     * - base_date: Jan 1, factor: 4, timeframe: DAYS → Jan 5
     * - base_date: Jan 1, factor: 4, timeframe: MONTHS → May 1
     * - base_date: Jan 1, factor: 2, timeframe: YEARS → Jan 1 (2 years later)
     *
     * @access private
     * @param  integer   $base_date      Base timestamp (from getBaseDate)
     * @param  integer   $factor         Factor (integer, e.g., 4)
     * @param  integer   $timeframe      Timeframe constant (DAYS, MONTHS, or YEARS)
     * @return integer  New timestamp for the calculated due date
     */
    private function calculateNewDueDate($base_date, $factor, $timeframe)
    {
        // If factor is 0 or negative, return base date unchanged
        if ($factor <= 0) {
            return $base_date;
        }

        // Create DateTime object from base timestamp
        $base_datetime = new \DateTime();
        $base_datetime->setTimestamp($base_date);

        // Add the appropriate time period based on timeframe
        switch ($timeframe) {
            case Task::RECURRING_TIMEFRAME_DAYS:
                // Add days: e.g., +4 days
                $base_datetime->modify('+' . $factor . ' days');
                break;
            case Task::RECURRING_TIMEFRAME_MONTHS:
                // Add months: e.g., +4 months
                // DateTime handles edge cases (e.g., Jan 31 + 1 month = Feb 28/29)
                $base_datetime->modify('+' . $factor . ' months');
                break;
            case Task::RECURRING_TIMEFRAME_YEARS:
                // Add years: e.g., +2 years
                $base_datetime->modify('+' . $factor . ' years');
                break;
            default:
                // Fallback to days if timeframe is invalid
                $base_datetime->modify('+' . $factor . ' days');
        }

        // Return the calculated timestamp
        return $base_datetime->getTimestamp();
    }
}
