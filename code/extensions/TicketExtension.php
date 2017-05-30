<?php
/**
 * TicketExtension.php
 *
 * @author Bram de Leeuw
 * Date: 09/03/17
 */

namespace Broarm\EventTickets;

use CalendarEvent_Controller;
use DataExtension;
use FieldList;
use GridField;
use GridFieldAddNewButton;
use GridFieldConfig_RecordEditor;
use HtmlEditorField;
use LiteralField;
use NumericField;
use SiteConfig;

/**
 * Class TicketExtension
 *
 * @package Broarm\EventTickets
 *
 * @property TicketExtension|\CalendarEvent $owner
 * @property int                            Capacity
 * @property int                            OrderMin
 * @property int                            OrderMax
 * @property string                         SuccessMessage
 * @property string                         SuccessMessageMail
 *
 * @method \HasManyList Tickets()
 * @method \HasManyList Reservations()
 * @method \HasManyList Attendees()
 * @method \HasManyList WaitingList()
 * @method \HasManyList Fields()
 */
class TicketExtension extends DataExtension
{
    /**
     * @var CalendarEvent_Controller
     */
    protected $controller;

    private static $db = array(
        'Capacity' => 'Int',
        'OrderMin' => 'Int',
        'OrderMax' => 'Int',
        'SuccessMessage' => 'HTMLText',
        'SuccessMessageMail' => 'HTMLText'
    );

    private static $has_many = array(
        'Tickets' => 'Broarm\EventTickets\Ticket.Event',
        'Reservations' => 'Broarm\EventTickets\Reservation.Event',
        'Attendees' => 'Broarm\EventTickets\Attendee.Event',
        'WaitingList' => 'Broarm\EventTickets\WaitingListRegistration.Event',
        'Fields' => 'Broarm\EventTickets\AttendeeExtraField.Event'
    );

    private static $defaults = array(
        'Capacity' => 50,
        'OrderMin' => 1,
        'OrderMax' => 5
    );

    private static $translate = array(
        'SuccessMessage',
        'SuccessMessageMail'
    );

    public function updateCMSFields(FieldList $fields)
    {
        $gridFieldConfig = GridFieldConfig_RecordEditor::create();

        // If the event dates are in the past remove ability to create new tickets, reservations and attendees.
        // This is done here instead of in the canCreate method because of the lack of context there.
        if (!$this->canCreateTickets()) {
            $gridFieldConfig->removeComponentsByType(new GridFieldAddNewButton());
        }

        $ticketLabel = _t('TicketExtension.Tickets', 'Tickets');
        $fields->addFieldsToTab(
            "Root.$ticketLabel", array(
            GridField::create('Tickets', $ticketLabel, $this->owner->Tickets(), $gridFieldConfig),
            NumericField::create('Capacity', _t('TicketExtension.Capacity', 'Capacity')),
            NumericField::create('OrderMin', _t('TicketExtension.OrderMin', 'Minimum amount of tickets required per reservation')),
            NumericField::create('OrderMax', _t('TicketExtension.OrderMax', 'Maximum amount of tickets allowed per reservation')),
            HtmlEditorField::create('SuccessMessage',
                _t('TicketExtension.SuccessMessage', 'Success message'))->setRows(4),
            HtmlEditorField::create('SuccessMessageMail', _t('TicketExtension.MailMessage', 'Mail message'))->setRows(4)
        ));

        if ($this->owner->Reservations()->exists()) {
            $reservationLabel = _t('TicketExtension.Reservations', 'Reservations');
            $fields->addFieldToTab(
                "Root.$reservationLabel",
                GridField::create('Reservations', $reservationLabel, $this->owner->Reservations(), $gridFieldConfig)
            );
        }

        if ($this->owner->Attendees()->exists()) {
            $guestListLabel = _t('TicketExtension.GuestList', 'GuestList');
            $fields->addFieldToTab(
                "Root.$guestListLabel",
                GridField::create('Attendees', $guestListLabel, $this->owner->Attendees(), $gridFieldConfig)
            );
        }

        if ($this->owner->WaitingList()->exists()) {
            $waitingListLabel = _t('TicketExtension.WaitingList', 'WaitingList');
            $fields->addFieldToTab(
                "Root.$waitingListLabel",
                GridField::create('WaitingList', $waitingListLabel, $this->owner->WaitingList(), $gridFieldConfig)
            );
        }


        $extraFieldsLabel = _t('TicketExtension.ExtraFields', 'Attendee fields');
        $fields->addFieldToTab(
            "Root.$extraFieldsLabel",
            GridField::create('WaitingList', $extraFieldsLabel, $this->owner->Fields(),
                GridFieldConfig_Fields::create())
        );

    }

    /**
     * Trigger actions after write
     */
    public function onAfterWrite()
    {
        $this->createDefaultFields();
        parent::onAfterWrite();
    }

    /**
     * Creates and sets up the default fields
     */
    public function createDefaultFields()
    {
        $fields = Attendee::config()->get('default_fields');
        if (!$this->owner->Fields()->exists()) {
            foreach ($fields as $fieldName => $fieldType) {
                $field = AttendeeExtraField::create();
                $field->Title = _t("AttendeeField.$fieldName", $fieldName);
                $field->FieldName = $fieldName;
                $field->Required = true;
                $field->Editable = false;

                if (is_array($fieldType)) {
                    foreach ($fieldType as $property => $value) {
                        $field->setField($property, $value);
                    }
                } else {
                    $field->FieldType = $fieldType;
                }

                $this->owner->Fields()->add($field);
            }
        }
    }

    /**
     * Extend the page actions with an start check in action
     *
     * @param FieldList $actions
     */
    public function updateCMSActions(FieldList $actions)
    {
        $checkInButton = new LiteralField('StartCheckIn',
            "<a class='action ss-ui-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'
                id='Edit_StartCheckIn'
                role='button'
                href='{$this->owner->Link('checkin')}'
                target='_blank'>
                Start check in
            </a>"
        );

        if ($this->owner->Attendees()->exists()) {
            $actions->push($checkInButton);
        }
    }

    /**
     * Get the leftover capacity
     *
     * @return int
     */
    public function getAvailability()
    {
        return $this->owner->Capacity - $this->owner->Attendees()->count();
    }

    /**
     * Get the success message
     *
     * @return mixed|string
     */
    public function getSuccessContent()
    {
        if (!empty($this->owner->SuccessMessage)) {
            return $this->owner->dbObject('SuccessMessage');
        } else {
            return SiteConfig::current_site_config()->dbObject('SuccessMessage');
        }
    }

    /**
     * Get the mail message
     *
     * @return mixed|string
     */
    public function getMailContent()
    {
        if (!empty($this->owner->SuccessMessageMail)) {
            return $this->owner->dbObject('SuccessMessageMail');
        } else {
            return SiteConfig::current_site_config()->dbObject('SuccessMessageMail');
        }
    }

    /**
     * Get the Ticket logo
     *
     * @return \Image
     */
    public function getMailLogo()
    {
        return SiteConfig::current_site_config()->TicketLogo();
    }

    /**
     * Check if the current event can have tickets
     *
     * @return bool
     */
    public function canCreateTickets()
    {
        $currentDate = $this->owner->getController()->CurrentDate();
        if ($currentDate && $currentDate->exists()) {
            return $currentDate->dbObject('StartDate')->InFuture();
        }

        return false;
    }

    /**
     * Get the calendar controller
     *
     * @return CalendarEvent_Controller
     */
    public function getController()
    {
        return $this->controller
            ? $this->controller
            : $this->controller = CalendarEvent_Controller::create($this->owner);
    }
}
