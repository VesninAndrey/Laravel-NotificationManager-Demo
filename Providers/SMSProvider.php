<?php

namespace App\Services\Managers\NotificationManager\Providers;

use App\Events\ErrorSendSmsEvent;
use App\Exceptions\BMBMissedConfigurationException;
use App\Exceptions\BMBUnavailableForTestException;
use App\Exceptions\BMBWrongImplementationException;
use App\Services\Managers\NotificationManager\DataObjects\Contracts\NotificationDataObjectInterface;
use App\Services\Managers\NotificationManager\DataObjects\SMSDataObject;
use App\Services\Managers\NotificationManager\Providers\Contracts\NotificationProviderInterface;
use Carbon\Carbon;

/**
 * Провайдер для отправки уведомлений по СМС.
 */
class SMSProvider implements NotificationProviderInterface
{
    /**
     * Ошибка отправки СМС, когда BeSeed блокируется СМС-сервисом.
     */
    const ERROR_BLOCKED_BY_SERVICE = '241';

    /**
     * Расшифровка статусов СМС от Smspilot.
     *
     * @see http://www.smspilot.ru/download/SMSPilotRu-HTTP-v1.9.14.pdf
     */
    const SENT_STATUSES = [
        '-2' => 'ошибка',
        '-1' => 'не доставлено',
        '0'  => 'новое',
        '1'  => 'в очереди',
        '2'  => 'доставлено',
        '3'  => 'отложено',
    ];

    /**
     * @var \Smspilot
     */
    private $smsSender;

    /**
     * @var mixed
     */
    private $status;

    /**
     * {@inheritdoc}
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(array $status)
    {
        $this->status = $status;
    }

    /**
     * @param NotificationDataObjectInterface $dataObject
     *
     * @throws BMBMissedConfigurationException
     * @throws BMBUnavailableForTestException
     * @throws BMBWrongImplementationException
     */
    public function send(NotificationDataObjectInterface $dataObject)
    {
        $this->checkForInstance($dataObject);

        if (empty($this->smsSender)) {
            $this->initSMSSender();
        }

        /** @var SMSDataObject $dataObject */
        $response = $this->smsSender->send([$dataObject->getRecipient()], $dataObject->getMessage());
        $this->handleResponse($response, $dataObject);
    }

    /**
     * Инициализирует настройки сервиса для отправки СМС.
     * Бросает исключение, если конфиги не настроены.
     *
     * @throws BMBMissedConfigurationException
     * @throws BMBUnavailableForTestException
     */
    public function initSMSSender()
    {
        if (!config('smspilot.active')) {
            throw new BMBMissedConfigurationException(sprintf('Config value "%s" resolved to FALSE', 'smspilot.active'));
        }

        if (!config('app.debug', true)) {
            $apiKey = config('smspilot.apikey');
            $from = config('smspilot.from');

            if (!empty($apiKey)) {
                $this->smsSender = new \Smspilot($apiKey, 'UTF-8', $from);
            } else {
                throw new BMBMissedConfigurationException(sprintf('Empty required config value for "%s"', 'smspilot.apikey'));
            }
        } else {
            throw new BMBUnavailableForTestException('Can not use SMS service with app.debug = true');
        }
    }

    /**
     * Обработка ответа от сервиса отправки СМС.
     * Устанавливает статус отправки в виде массива:
     *  [
     *     'success' => (bool),
     *     'message' => (string),
     *  ]
     *
     * , где success - флаг успешности отправки,
     *       message - информация о сообщении.
     *
     * Флаг success == true не означает успешной доставки сообщения, а лишь свидетельствует об отсутствии ошибок во время отправки.
     * Информация о доставке (в т.ч. ошибки) сохраняется в message.
     *
     * @param mixed         $response   Ответ сервиса
     * @param SMSDataObject $dataObject Объект с данными для отправки
     */
    private function handleResponse($response, SMSDataObject $dataObject)
    {
        $error = $this->smsSender->error ?? null;

        if (false === $response) {
            $this->setStatus([
                'success' => false,
                'message' => sprintf('Ошибка %d при отправке сообщения на номер %s', $error, $dataObject->getRecipient()),
            ]);
        } elseif (true === is_array($response)) {
            $message = '';

            foreach ($response as $status) {
                $message .= sprintf(
                    '%s >>> Информация по сообщению | id > %s | телефон > %s | стоимость > %s | статус > %s | статус расшифровка > %s |\n',
                    Carbon::now()->toDateTimeString(),
                    $status['id'],
                    $status['phone'],
                    $status['price'],
                    $status['status'],
                    self::SENT_STATUSES[$status['status']]
                );
            }

            $this->setStatus([
                'success' => true,
                'message' => $message,
            ]);
        }

        if (!empty($error) && (strpos($error, self::ERROR_BLOCKED_BY_SERVICE) !== false)) {
            event(new ErrorSendSmsEvent($dataObject->getRecipient(), $error));
        }
    }

    /**
     * Бросает исключение, если объект с данными не является экзепляром класса SMSDataObject.
     *
     * @param NotificationDataObjectInterface $dataObject Объект с данными
     *
     * @throws BMBWrongImplementationException
     */
    private function checkForInstance(NotificationDataObjectInterface $dataObject)
    {
        if (!($dataObject instanceof SMSDataObject)) {
            throw new BMBWrongImplementationException(
                sprintf(
                    'Wrong implementation of "NotificationDataObjectInterface" provided. Waiting for "%s", got "%s" for service "%s"',
                    SMSDataObject::class,
                    get_class($dataObject),
                    self::class
                )
            );
        }
    }
}
