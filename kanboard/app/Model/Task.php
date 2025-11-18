<?php

namespace Model;

/**
 * Task model
 *
 * @package  model
 * @author   Frederic Guillot
 */
class Task extends Base
{
    /**
     * SQL table name
     *
     * @var string
     */
    const TABLE               = 'tasks';

    /**
     * Task status
     *
     * @var integer
     */
    const STATUS_OPEN         = 1;
    const STATUS_CLOSED       = 0;

    /**
     * Events
     *
     * @var string
     */
    const EVENT_MOVE_PROJECT    = 'task.move.project';
    const EVENT_MOVE_COLUMN     = 'task.move.column';
    const EVENT_MOVE_POSITION   = 'task.move.position';
    const EVENT_MOVE_SWIMLANE   = 'task.move.swimlane';
    const EVENT_UPDATE          = 'task.update';
    const EVENT_CREATE          = 'task.create';
    const EVENT_CLOSE           = 'task.close';
    const EVENT_OPEN            = 'task.open';
    const EVENT_CREATE_UPDATE   = 'task.create_update';
    const EVENT_ASSIGNEE_CHANGE = 'task.assignee_change';

    /**
     * Recurrence status constants
     * 
     * CHANGE: Added recurrence status constants
     * PURPOSE: Define the possible states of task recurrence
     * - NONE (0): Recurrence is disabled
     * - PENDING (1): Recurrence is enabled and waiting for trigger
     * - PROCESSED (2): Recurrence has been triggered and new task created
     *
     * @var integer
     */
    const RECURRING_STATUS_NONE      = 0;
    const RECURRING_STATUS_PENDING   = 1;
    const RECURRING_STATUS_PROCESSED = 2;

    /**
     * Recurrence trigger constants
     * 
     * CHANGE: Added recurrence trigger constants
     * PURPOSE: Define when a new recurring task should be generated
     * - CLOSE (0): Generate when task is closed
     * - FIRST_COLUMN (1): Generate when task is moved from first column
     * - LAST_COLUMN (2): Generate when task is moved to last column
     *
     * @var integer
     */
    const RECURRING_TRIGGER_CLOSE        = 0;
    const RECURRING_TRIGGER_FIRST_COLUMN = 1;
    const RECURRING_TRIGGER_LAST_COLUMN  = 2;

    /**
     * Recurrence timeframe constants
     * 
     * CHANGE: Added recurrence timeframe constants
     * PURPOSE: Define the unit of time for calculating the new due date
     * - DAYS (0): Calculate in days
     * - MONTHS (1): Calculate in months
     * - YEARS (2): Calculate in years
     *
     * @var integer
     */
    const RECURRING_TIMEFRAME_DAYS   = 0;
    const RECURRING_TIMEFRAME_MONTHS = 1;
    const RECURRING_TIMEFRAME_YEARS  = 2;

    /**
     * Recurrence base date constants
     * 
     * CHANGE: Added recurrence base date constants
     * PURPOSE: Define which date to use as the base for calculating the new due date
     * - DUE_DATE (0): Use the existing task's due date
     * - ACTION_DATE (1): Use the date when the trigger event happened (current date)
     *
     * @var integer
     */
    const RECURRING_BASEDATE_DUE_DATE   = 0;
    const RECURRING_BASEDATE_ACTION_DATE = 1;

    /**
     * Remove a task
     *
     * @access public
     * @param  integer   $task_id   Task id
     * @return boolean
     */
    public function remove($task_id)
    {
        if (! $this->taskFinder->exists($task_id)) {
            return false;
        }

        $this->file->removeAll($task_id);

        return $this->db->table(self::TABLE)->eq('id', $task_id)->remove();
    }

    /**
     * Get a the task id from a text
     *
     * Example: "Fix bug #1234" will return 1234
     *
     * @access public
     * @param  string   $message   Text
     * @return integer
     */
    public function getTaskIdFromText($message)
    {
        if (preg_match('!#(\d+)!i', $message, $matches) && isset($matches[1])) {
            return $matches[1];
        }

        return 0;
    }

    /**
     * Get recurrence status list for dropdown
     * 
     * CHANGE: Added method to return recurrence status options
     * PURPOSE: Provides the "Yes"/"No" options for the "Generate recurrent task" dropdown
     * USAGE: Used in the recurrence settings form template
     *
     * @access public
     * @return array Array mapping constant values to translated labels
     */
    public function getRecurrenceStatusList()
    {
        return array(
            self::RECURRING_STATUS_NONE => t('No'),
            self::RECURRING_STATUS_PENDING => t('Yes'),
        );
    }

    /**
     * Get recurrence trigger list for dropdown
     * 
     * CHANGE: Added method to return recurrence trigger options
     * PURPOSE: Provides the three trigger options for the "Trigger to generate recurrent task" dropdown
     * USAGE: Used in the recurrence settings form template
     *
     * @access public
     * @return array Array mapping constant values to translated labels
     */
    public function getRecurrenceTriggerList()
    {
        return array(
            self::RECURRING_TRIGGER_CLOSE => t('When task is closed'),
            self::RECURRING_TRIGGER_FIRST_COLUMN => t('When task is moved from first column'),
            self::RECURRING_TRIGGER_LAST_COLUMN => t('When task is moved to last column'),
        );
    }

    /**
     * Get recurrence timeframe list for dropdown
     * 
     * CHANGE: Added method to return recurrence timeframe options
     * PURPOSE: Provides the timeframe options (Day(s), Month(s), Year(s)) for the dropdown
     * USAGE: Used in the recurrence settings form template
     *
     * @access public
     * @return array Array mapping constant values to translated labels
     */
    public function getRecurrenceTimeframeList()
    {
        return array(
            self::RECURRING_TIMEFRAME_DAYS => t('Day(s)'),
            self::RECURRING_TIMEFRAME_MONTHS => t('Month(s)'),
            self::RECURRING_TIMEFRAME_YEARS => t('Year(s)'),
        );
    }

    /**
     * Get recurrence base date list for dropdown
     * 
     * CHANGE: Added method to return recurrence base date options
     * PURPOSE: Provides the base date options (Existing due date, Action date) for the dropdown
     * USAGE: Used in the recurrence settings form template
     *
     * @access public
     * @return array Array mapping constant values to translated labels
     */
    public function getRecurrenceBasedateList()
    {
        return array(
            self::RECURRING_BASEDATE_DUE_DATE => t('Existing due date'),
            self::RECURRING_BASEDATE_ACTION_DATE => t('Action date'),
        );
    }
}
