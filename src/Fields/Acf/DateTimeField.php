<?php

namespace Bond\Fields\Acf;

class DateTimeField extends Field
{
    protected string $type = 'date_time_picker';

    // more universal default format for WP admin
    public string $display_format = 'd/m/Y H:i';

    // make sure the return format can be converted without confusing month with days
    public string $return_format = 'Y-m-d H:i:s';

    public int $first_day = 1;

    // please note the Timezone is simply not saved in the database, so the date will be in the Timezone it was saved
    // TODO check if the timezone used was from WP or PHP settings

    // Note, consider this:
    // $start_date = new Carbon(get_field('start_date', $post->ID));
    // Doing this will consider the time as UTC, it will print out just fine the date and time numbers, but as soon as you convert to timestamp or another timezone it will be totally off

    // Doing as below is better:
    // $start_date = new Carbon($start_date, 'America/Sao_Paulo');
    // or with our helper:
    // $start_date = Date::carbon($start_date);

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
