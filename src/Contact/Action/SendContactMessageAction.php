<?php

declare(strict_types=1);

namespace App\Contact\Action;

use App\Contact\Mailer\ContactMailer;
use App\Contact\Request\ContactRequest;

final class SendContactMessageAction
{
    public function __construct(
        private readonly ContactMailer $contactMailer,
    ) {
    }

    public function execute(ContactRequest $request): void
    {
        $this->contactMailer->sendContactMessage($request);
    }
}
