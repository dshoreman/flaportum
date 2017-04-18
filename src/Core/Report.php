<?php namespace Flaportum\Core;

use Exception;

class Report
{
    protected $acceptableTypes = ['users'];

    protected $reportType;

    public static function for($type)
    {
        $report = (new static)->setType($type);

        return $report;
    }

    public function setType($type)
    {
        if (!in_array($type, $this->acceptableTypes)) {
            throw new Exception("{$type} is an invalid report type.");
        }

        $this->reportType = $type;

        return $this;
    }

    public function append()
    {
        if (func_num_args() > 1) {
            $this->reports[$this->reportType][] = func_get_args();
        } elseif (!is_array(func_get_arg(0))) {
            throw new Exception("Argument must be array when only passing one");
        } else {
            foreach (func_get_arg(0) as $report) {
                $this->reports[$this->reportType][] = $report;
            }
        }

        return $this;
    }

    public function get()
    {
        return $this->reports[$this->reportType];
    }

    public function write()
    {
        $output = '';

        foreach ($this->reports[$this->reportType] as $entry) {
            $output .= implode(',', $entry);
            $output .= PHP_EOL;
        }

        $file = sprintf("cache/%s_report-%s.csv", $this->reportType, date('Ymd-His'));

        return file_put_contents(__DIR__.'/../../'.$file, $output) !== false;
    }
}
