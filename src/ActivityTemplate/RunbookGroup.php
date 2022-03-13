<?php

namespace App\ActivityTemplate;

use App\Entity\RunItem;

class RunbookGroup
{
    protected $title;
    protected $instruction;
    protected $instruction_label;
    protected $template;
    protected $data = [];

    public function __construct($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setInstruction($instruction)
    {
        $this->instruction = $instruction;
        return $this;
    }

    public function getInstruction()
    {
        return $this->instruction;
    }

    public function getInstructionLabel()
    {
        return $this->instruction_label;
    }

    public function setInstructionLabel($instruction_label): void
    {
        $this->instruction_label = $instruction_label;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function addRow(RunItem $runitem, array $row)
    {
        $this->data[] = [
            'runitem' => $runitem,
            'params' => $row,
        ];
    }

    public function getData()
    {
        return $this->data;
    }
}