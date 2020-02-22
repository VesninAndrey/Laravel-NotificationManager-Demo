<?php

namespace App\Services\Managers\NotificationManager\DataObjects;

use App\Services\Managers\NotificationManager\DataObjects\Contracts\NotificationDataObjectInterface;

/**
 * Класс, инкапсулирующий данные для отправки sms.
 */
class SMSDataObject implements NotificationDataObjectInterface
{
    /**
     * @var string
     */
    private $recipient;

    /**
     * @var string
     */
    private $message;

    /**
     * @param string $recipient Номер получателя
     *
     * @return $this
     */
    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;

        return $this;
    }

    /**
     * @return string
     */
    public function getRecipient(): string
    {
        return $this->recipient;
    }

    /**
     * @param string $message Текст СМС
     *
     * @return $this
     */
    public function setMessage(string $message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function validate()
    {
    }
}
