<?php

namespace Bond\Fields\Acf;

class DateField extends Field
{
    protected string $type = 'date_picker';

    // more universal default format for WP admin
    public string $display_format = 'd/m/Y';

    // make sure the return format can be converted without confusing month with days
    public string $return_format = 'Y-m-d';

    public int $first_day = 1;


    public function displayFormat(string $format): self
    {
        $this->display_format = $format;
        return $this;
    }

    public function returnFormat(string $format): self
    {
        $this->return_format = $format;
        return $this;
    }

    public function weekStarts(int $weekday): self
    {
        $this->first_day = $weekday;
        return $this;
    }
}
