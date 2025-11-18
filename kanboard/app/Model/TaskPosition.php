<?php

namespace Model;

use Event\TaskEvent;

/**
 * Task Position
 *
 * @package  model
 * @author   Frederic Guillot
 */
class TaskPosition extends Base
{
    /**
     * Move a task to another column or to another position
     *
     * @access public
     * @param  integer    $project_id        Project id
     * @param  integer    $task_id           Task id
     * @param  integer    $column_id         Column id
     * @param  integer    $position          Position (must be >= 1)
     * @param  integer    $swimlane_id       Swimlane id
     * @param  boolean    $fire_events       Fire events
     * @return boolean
     */
    public function movePosition($project_id, $task_id, $column_id, $position, $swimlane_id = 0, $fire_events = true)
    {
        $original_task = $this->taskFinder->getById($task_id);

        $result = $this->calculateAndSave($project_id, $task_id, $column_id, $position, $swimlane_id);

        if ($result) {

            if ($original_task['swimlane_id'] != $swimlane_id) {
                $this->calculateAndSave($project_id, 0, $column_id, 1, $original_task['swimlane_id']);
            }

            if ($fire_events) {
                $this->fireEvents($original_task, $column_id, $position, $swimlane_id);
            }
        }

        return $result;
    }

    /**
     * Calculate the new position of all tasks
     *
     * @access public
     * @param  integer    $project_id        Project id
     * @param  integer    $task_id           Task id
     * @param  integer    $column_id         Column id
     * @param  integer    $position          Position (must be >= 1)
     * @param  integer    $swimlane_id       Swimlane id
     * @return array|boolean
     */
    public function calculatePositions($project_id, $task_id, $column_id, $position, $swimlane_id = 0)
    {
        // The position can't be lower than 1
        if ($position < 1) {
            return false;
        }

        $board = $this->db->table(Board::TABLE)->eq('project_id', $project_id)->asc('position')->findAllByColumn('id');
        $columns = array();

        // For each column fetch all tasks ordered by position
        foreach ($board as $board_column_id) {

            $columns[$board_column_id] = $this->db->table(Task::TABLE)
                          ->eq('is_active', 1)
                          ->eq('swimlane_id', $swimlane_id)
                          ->eq('project_id', $project_id)
                          ->eq('column_id', $board_column_id)
                          ->neq('id', $task_id)
                          ->asc('position')
                          ->asc('id') // Fix Postgresql unit test
                          ->findAllByColumn('id');
        }

        // The column must exists
        if (! isset($columns[$column_id])) {
            return false;
        }

        // We put our task to the new position
        if ($task_id) {
            array_splice($columns[$column_id], $position - 1, 0, $task_id);
        }

        return $columns;
    }

    /**
     * Save task positions
     *
     * @access private
     * @param  array       $columns          Sorted tasks
     * @param  integer     $swimlane_id      Swimlane id
     * @return boolean
     */
    private function savePositions(array $columns, $swimlane_id)
    {
        return $this->db->transaction(function ($db) use ($columns, $swimlane_id) {

            foreach ($columns as $column_id => $column) {

                $position = 1;

                foreach ($column as $task_id) {

                    $result = $db->table(Task::TABLE)->eq('id', $task_id)->update(array(
                        'position' => $position,
                        'column_id' => $column_id,
                        'swimlane_id' => $swimlane_id,
                    ));

                    if (! $result) {
                        return false;
                    }

                    $position++;
                }
            }
        });
    }

    /**
     * Fire events
     *
     * @access private
     * @param  array     $task
     * @param  integer   $new_column_id
     * @param  integer   $new_position
     * @param  integer   $new_swimlane_id
     */
    /**
     * Fire events when task position changes
     * 
     * CHANGE: Modified to include full task data with recurrence fields
     * PURPOSE: Ensures RecurringTaskSubscriber has access to recurrence settings
     * 
     * WHY: When a task is moved between columns, the RecurringTaskSubscriber needs
     *      to check if recurrence is enabled and if the move matches the trigger.
     *      Without recurrence fields in the event, the subscriber can't determine
     *      if a new recurring task should be generated.
     * 
     * BEFORE: Only included specific fields (task_id, column_id, etc.)
     * AFTER: Includes full task data from database (including recurrence fields)
     */
    private function fireEvents(array $task, $new_column_id, $new_position, $new_swimlane_id)
    {
        // Get full task data including recurrence fields from database
        // This ensures the event contains all necessary data for subscribers
        $full_task = $this->taskFinder->getById($task['id']);
        
        // Merge full task data with position change data
        // Full task data includes recurrence settings needed by RecurringTaskSubscriber
        $event_data = array_merge($full_task ?: $task, array(
            'task_id' => $task['id'],
            'project_id' => $task['project_id'],
            'position' => $new_position,
            'column_id' => $new_column_id,
            'swimlane_id' => $new_swimlane_id,
            'src_column_id' => $task['column_id'],
            'dst_column_id' => $new_column_id,
            'date_moved' => $task['date_moved'],
        ));

        if ($task['swimlane_id'] != $new_swimlane_id) {
            $this->container['dispatcher']->dispatch(Task::EVENT_MOVE_SWIMLANE, new TaskEvent($event_data));
        }
        else if ($task['column_id'] != $new_column_id) {
            $this->container['dispatcher']->dispatch(Task::EVENT_MOVE_COLUMN, new TaskEvent($event_data));
        }
        else if ($task['position'] != $new_position) {
            $this->container['dispatcher']->dispatch(Task::EVENT_MOVE_POSITION, new TaskEvent($event_data));
        }
    }

    /**
     * Calculate the new position of all tasks
     *
     * @access private
     * @param  integer    $project_id        Project id
     * @param  integer    $task_id           Task id
     * @param  integer    $column_id         Column id
     * @param  integer    $position          Position (must be >= 1)
     * @param  integer    $swimlane_id       Swimlane id
     * @return boolean
     */
    private function calculateAndSave($project_id, $task_id, $column_id, $position, $swimlane_id)
    {
        $positions = $this->calculatePositions($project_id, $task_id, $column_id, $position, $swimlane_id);

        if ($positions === false || ! $this->savePositions($positions, $swimlane_id)) {
            return false;
        }

        return true;
    }
}
