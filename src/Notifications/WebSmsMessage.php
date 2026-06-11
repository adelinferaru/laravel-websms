<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Notifications;

use Adelinferaru\LaravelWebSms\DataCoding;
use DateTimeInterface;

class WebSmsMessage
{
    final public function __construct(
        public string $content = '',
        public ?string $from = null,
        public string|DataCoding $encoding = DataCoding::Gsm,
        public ?DateTimeInterface $scheduledFor = null,
    ) {}

    public static function create(string $content = ''): static
    {
        return new static($content);
    }

    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function from(string $from): static
    {
        $this->from = $from;

        return $this;
    }

    public function encoding(string|DataCoding $encoding): static
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * Send the message as UCS2 (Unicode), e.g. for Greek or Cyrillic text.
     */
    public function unicode(): static
    {
        $this->encoding = DataCoding::Ucs2;

        return $this;
    }

    public function scheduledFor(DateTimeInterface $scheduledFor): static
    {
        $this->scheduledFor = $scheduledFor;

        return $this;
    }
}
