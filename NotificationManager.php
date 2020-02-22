<?php

namespace App\Services\Managers\NotificationManager;

use App\Exceptions\BeMyBloggerException;
use App\Exceptions\BMBDataValidationException;
use App\Exceptions\BMBMissedDependencyException;
use App\Exceptions\BMBUnavailableForTestException;
use App\Exceptions\BMBWrongImplementationException;
use App\Exceptions\BMBWrongPermissionException;
use App\Managers\LogManager\LogManager;
use App\Services\Managers\NotificationManager\DataObjects\Contracts\NotificationDataObjectInterface;
use App\Services\Managers\NotificationManager\DataObjects\EmailDataObject;
use App\Services\Managers\NotificationManager\DataObjects\SMSDataObject;
use App\Services\Managers\NotificationManager\Providers\Contracts\NotificationProviderInterface;
use App\Services\Managers\NotificationManager\Providers\EmailProvider;
use App\Services\Managers\NotificationManager\Providers\SMSProvider;
use Carbon\Carbon;
use Illuminate\Container\Container;
use App\Models\User;
use App\Jobs\SendNotificationJob;

/**
 * Класс, инкапсулирующий методы для работы с уведомлениями.
 */
class NotificationManager
{
    /**
     * Строковые имена для провайдеров.
     */
    const PROVIDER_SHORTNAMES = [
        'email' => EmailProvider::class,
        'sms' => SMSProvider::class,
    ];

    /**
     * @var NotificationProviderInterface
     *
     * @see \App\Services\Managers\NotificationManager\Providers
     */
    private $provider;

    /**
     * @var LogManager
     */
    private $logger;

    /**
     * @param LogManager $logger Сервис для работы с логами
     */
    public function __construct(LogManager $logger)
    {
        $this->logger = $logger;
        $this->setLogFile(sprintf('%s%s%s', 'service/notification_', Carbon::now()->toDateString(), '.log'));
    }

    /**
     * Устанавливает лог-файл.
     * Это прокси метод. Подробнее:
     * @see \App\Managers\CustomLogManager
     *
     * @param string $logFile Файл для лога
     */
    public function setLogFile(string $logFile)
    {
        $this->logger->setLogFile($logFile);
    }

    /**
     * Устанавливает провайдера.
     *
     * @param NotificationProviderInterface $provider Провайдер уведомлений
     *
     * @return self
     */
    public function setProvider(NotificationProviderInterface $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Устанавливает провайдера по строковому имени.
     *
     * @param string $shortname Строковое имя провайдера
     *
     * @return self
     *
     * @throws BMBDataValidationException
     */
    public function setProviderByShortname(string $shortname): self
    {
        $shortname = mb_strtolower(trim($shortname));

        if (empty(self::PROVIDER_SHORTNAMES[$shortname])) {
            throw new BMBDataValidationException(sprintf('Unknown provider shortname "%s" for service %s', $shortname, self::class));
        }

        $provider = self::PROVIDER_SHORTNAMES[$shortname];
        $container = Container::getInstance();
        $this->setProvider($container->make($provider));

        return $this;
    }

    /**
     * Возвращает провайдера.
     *
     * @return NotificationProviderInterface
     */
    public function getProvider(): NotificationProviderInterface
    {
        return $this->provider;
    }

    /**
     * @param NotificationDataObjectInterface $dataObject
     *
     * @throws BMBMissedDependencyException
     * @throws BMBWrongPermissionException
     * @throws BMBWrongImplementationException
     * @throws BMBWrongPermissionException
     */
    public function send(NotificationDataObjectInterface $dataObject)
    {
        if (empty($this->provider)) {
            $this->detectProvider($dataObject);
        }

        try {
            $dataObject->validate();
            $logMsg = sprintf(
                'Starting to send message, provider: %s, recipient: %s',
                array_flip(self::PROVIDER_SHORTNAMES)[get_class($this->provider)],
                $this->stringify($dataObject->getRecipient())
            );

            $this->logger->logInfo($logMsg);

            $this->provider->send($dataObject);
            $status = $this->provider->getStatus();

            if (false === $status['success']) {
                $this->logger->logError(sprintf("Sending status: FAILED. Message: %s\n", $status['message']));
            } else {
                $this->logger->logInfo(sprintf("Sending status: OK\n"));
            }
        } catch (BMBUnavailableForTestException $unavailableForTestException) {
            $this->logger->logWarning(sprintf("[%s]: %s\n", get_class($unavailableForTestException), $unavailableForTestException->getMessage()));
        } catch (BeMyBloggerException $bex) {
            $this->logger->logError(sprintf("[%s]: %s\n", get_class($bex), $bex->getMessage()));
        }
    }

    /**
     * Отправить уведомление получателю через очередь.
     *
     * @param NotificationDataObjectInterface $dataObject Объект с даными для уведомления
     *
     * @throws BMBDataValidationException
     * @throws BMBMissedDependencyException
     * @throws BMBWrongImplementationException
     * @throws BMBWrongPermissionException
     */
    public function sendDeferred(NotificationDataObjectInterface $dataObject)
    {
        if (empty($this->provider)) {
            $this->detectProvider($dataObject);
        }

        try {
            $dataObject->validate();
            $logMsg = sprintf(
                'Pushing message to queue, provider: %s, recipient: %s',
                array_flip(self::PROVIDER_SHORTNAMES)[get_class($this->provider)],
                $this->stringify($dataObject->getRecipient())
            );
            $this->logger->logInfo($logMsg);

            SendNotificationJob::dispatch($dataObject);
        } catch (BeMyBloggerException $bex) {
            $this->logger->logError(sprintf("[%s]: %s\n", get_class($bex), $bex->getMessage()));
        }
    }



    /**
     * Определяет провайдера по переданным данным.
     *
     * @param NotificationDataObjectInterface $dataObject Объект с данными
     *
     * @throws BMBDataValidationException
     * @throws BMBWrongImplementationException
     */
    private function detectProvider(NotificationDataObjectInterface $dataObject)
    {
        $dataObjectClass = get_class($dataObject);

        switch ($dataObjectClass) {
            case ($dataObjectClass === EmailDataObject::class):
                $this->setProviderByShortname('email');
                break;
            case ($dataObjectClass === SMSDataObject::class):
                $this->setProviderByShortname('sms');
                break;
            default:
                throw new BMBWrongImplementationException(sprintf('Service %s has no registered providers for data object %s', self::class, $dataObjectClass));
        }
    }

    /**
     * Возвращает строковое представление получателя, для записи в лог.
     *
     * @param mixed $recipient Получатель уведомления
     *
     * @return string
     */
    private function stringify($recipient)
    {
        if ($recipient instanceof User) {
            $name = sprintf('User ID %d', $recipient->id);
        } else {
            $name = (string) $recipient;
        }

        return $name;
    }
}