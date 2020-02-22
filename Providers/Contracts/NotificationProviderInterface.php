<?php

namespace App\Services\Managers\NotificationManager\Providers\Contracts;

use App\Services\Managers\NotificationManager\DataObjects\Contracts\NotificationDataObjectInterface;

/**
 * Интерфейс для провайдера уведомлений.
 */
interface NotificationProviderInterface
{
    /**
     * Отправка уведомления.
     *
     * @param NotificationDataObjectInterface $dataObject Объект с данными для отправки уведомления
     */
    public function send(NotificationDataObjectInterface $dataObject);

    /**
     * Устанавливает статус отправки.
     *
     * @param array $status Статус отправки
     */
    public function setStatus(array $status);

    /**
     * Возвращает статус отправки.
     *
     * @return array
     */
    public function getStatus(): array;
}
