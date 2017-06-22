<?php

namespace Broarm\EventTickets;

use GridFieldAddNewButton;
use GridFieldConfig_RecordEditor;

/**
 * Class TicketsGridField
 *
 * @author Bram de Leeuw
 * @package Broarm\EventTickets
 */
class TicketsGridField extends GridFieldConfig_RecordEditor
{
    public function __construct($editable = true, $itemsPerPage = null)
    {
        parent::__construct($itemsPerPage);

        if (!$editable) {
            $this->removeComponentsByType(new GridFieldAddNewButton());
        }

        $this->extend('updateConfig');
    }
}