<?php

namespace App\Services\Managers\NotificationManager\DataObjects;

use App\Services\Managers\NotificationManager\DataObjects\Contracts\NotificationDataObjectInterface;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\BeseedDataValidationException;

/**
 * Класс, инкапсулирующий данные для отправки email.
 */
class EmailDataObject implements NotificationDataObjectInterface
{
    /**
     * @var string
     */
    private $recipient;

    /**
     * @var string
     */
    private $template;

    /**
     * @var array
     */
    private $context;

    /**
     * @var string
     */
    private $subject;

    /**
     * @inheritdoc
     */
    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getRecipient(): string
    {
        return $this->recipient;
    }

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @param string $template Имя шаблона для email
     *
     * @return $this
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array $context Массив данных для подстановки в шаблон
     *
     * @return $this
     */
    public function setContext(array $context)
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @param string $subject Тема письма
     *
     * @return $this
     */
    public function setSubject(string $subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws BeseedDataValidationException
     */
    public function validate()
    {
        $validation = Validator::make(
            [
                'template' => $this->template,
                'email' => $this->recipient,
            ],
            [
                'template' => ['required'],
                'email' => ['required', 'string', 'email'],
            ]
        );

        if ($validation->fails()) {
            $errors = $validation->messages()->all();
        }

        if (!empty($errors)) {
            $errorString = '';

            foreach ($errors as $errorMessage) {
                $errorString .= sprintf("[%s Validation Error]: %s; ", self::class, $errorMessage);
            }

            throw new BeseedDataValidationException($errorString);
        }
    }
}
