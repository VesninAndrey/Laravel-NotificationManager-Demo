<?php

namespace App\Services\Managers\NotificationManager\Providers;

use App\Exceptions\BMBWrongImplementationException;
use App\Services\Managers\NotificationManager\DataObjects\Contracts\NotificationDataObjectInterface;
use App\Services\Managers\NotificationManager\Providers\Contracts\NotificationProviderInterface;
use Illuminate\Mail\Mailer;
use App\Services\Managers\NotificationManager\DataObjects\EmailDataObject;

/**
 * Провайдер для отправки уведомлений через Email.
 */
class EmailProvider implements NotificationProviderInterface
{
    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var array
     */
    private $status;

    /**
     * @param Mailer $mailer Сервис для отправки email
     */
    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * {@inheritdoc}
     *
     * @throws BMBWrongImplementationException
     */
    public function send(NotificationDataObjectInterface $dataObject)
    {
        /** @var EmailDataObject $dataObject */
        $this->checkForInstance($dataObject);
        $this->mailer->send(
            $dataObject->getTemplate(),
            $dataObject->getContext(),
            function ($message) use ($dataObject) {
                $message->to($dataObject->getRecipient());

                if (!empty($dataObject->getSubject())) {
                    $message->subject($dataObject->getSubject());
                }
            }
        );

        $this->setStatus([
            'success' => true,
            'message' => 'Сообщение успешно отправлено',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(array $status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    /**
     * Бросает исключение, если объект с данными не является экзепляром класса EmailDataObject.
     *
     * @param NotificationDataObjectInterface $dataObject Объект с данными
     *
     * @throws BMBWrongImplementationException
     */
    private function checkForInstance(NotificationDataObjectInterface $dataObject)
    {
        if (!($dataObject instanceof EmailDataObject)) {
            throw new BMBWrongImplementationException(
                sprintf(
                    'Wrong implementation of "NotificationDataObjectInterface" provided. Waiting for "%s", got "%s" for service "%s"',
                    EmailDataObject::class,
                    get_class($dataObject),
                    self::class
                )
            );
        }
    }
}
