<?php

namespace App\Controller;

use App\Payment\PayPal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    /**
     * @Route("/example1", name="example1")
     */
    public function example1(PayPal $payPal): Response
    {
        return $this->render('example1.html.twig', [
            'client_id' => 'AZBt3VHyOgWMdjZzSuSUJA65WTU30PYOXaXQVtPb57b3P7ZULT_eYivwYEJ-xAGxzGsgC-PxzP9QcIVF',
        ]);
    }

    /**
     * @Route("/example2", name="example2")
     */
    public function example2(PayPal $payPal): Response
    {
        return $this->render('example2.html.twig', [
            'parameters' => $payPal->getQueryParametersForJsSdk(),
        ]);
    }

    /**
     * @Route("/example3", name="example3")
     */
    public function example3(string $paypalFormAction): Response
    {
        return $this->render('example3.html.twig', [
            'paypal_form_action' => $paypalFormAction,
            'business_id' => 'sb-yajaj16135710@business.example.com', 
        ]);
    }

    /**
     * @Route("/confirmation", name="confirmation")
     */
    public function confirmation(Request $request, PayPal $payPal): Response
    {
        // Some logic to verify if the captured payment is valid
        // ... 

        return $this->render('messages/confirmation.html.twig');
    }
}