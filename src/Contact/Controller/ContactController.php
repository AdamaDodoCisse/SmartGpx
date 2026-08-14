<?php

declare(strict_types=1);

namespace App\Contact\Controller;

use App\Contact\Action\SendContactMessageAction;
use App\Contact\Form\ContactFormType;
use App\Contact\Request\ContactRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.contact')]
        private readonly RateLimiterFactory $contactLimiterFactory,
    ) {
    }

    #[Route(['en' => '/contact', 'fr' => '/fr/contact'], name: 'app_contact')]
    public function index(Request $request, SendContactMessageAction $sendContactMessageAction): Response
    {
        $dto = new ContactRequest();
        $form = $this->createForm(ContactFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $this->contactLimiterFactory->create($request->getClientIp());

            if ($limiter->consume(1)->isAccepted()) {
                $sendContactMessageAction->execute($dto);
                $this->addFlash('success', 'contact.flash.sent');
            } else {
                $this->addFlash('error', 'contact.flash.rate_limited');
            }

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form,
        ]);
    }
}
