<?php

namespace App\Services\Managers\NotificationManager\DataObjects\Contracts;

/**
 * Интерфейс для объектов, инкапсулирующих данные для отправки уведомлений.
 */
interface NotificationDataObjectInterface
{
    /**
     * Устанавливает получателя уведомления.
     *
     * @param mixed $recipient Получатель
     *
     * @return $this
     */
    public function setRecipient($recipient);

    /**
     * Возвращает получателя уведомления.
     *
     * @return mixed
     */
    public function getRecipient();

    /**
     * Валидирует данные объекта.
     */
    public function validate();
}
