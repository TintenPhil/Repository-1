<?php

namespace SimpleValidator\Validators;

use SimpleValidator\Base;
use DateTime;

class Date extends Base
{
    private $formats = array();

    public function __construct($field, $error_message, array $formats)
    {
        parent::__construct($field, $error_message);
        $this->formats = $formats;
    }

    public function execute(array $data)
    {
        if (isset($data[$this->field]) && $data[$this->field] !== '') {
            $value = trim($data[$this->field]);

            foreach ($this->formats as $format) {
                if ($this->isValidDate($value, $format) === true) {
                    return true;
                }
            }

            $timestamp = strtotime($value);

            if ($timestamp !== false && $timestamp > 0) {
                return true;
            }

            return false;
        }

        return true;
    }

    public function isValidDate($value, $format)
    {
        $date = DateTime::createFromFormat($format, $value);

        if ($date !== false) {
            $errors = DateTime::getLastErrors();
            if (is_array($errors) && $errors['error_count'] === 0 && $errors['warning_count'] === 0) {
                $timestamp = $date->getTimestamp();
                return $timestamp > 0;
            }
        }

        return false;
    }
}
