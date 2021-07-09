<?php

namespace Bond\Fields\Acf;

class TimeField extends Field
{
  protected string $type = 'time_picker';

  // more universal default format
  public string $display_format = 'H:i';
  public string $return_format = 'H:i';


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
}
