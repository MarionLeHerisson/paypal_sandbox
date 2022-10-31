<?php

namespace App\Payment;

use App\Entity\Order;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PayPal
{
    public const INTENT_AUTHORIZE = 'AUTHORIZE';
    public const INTENT_CAPTURE = 'CAPTURE';

    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_COMPLETED = 'COMPLETED';

    /** @var string */
    private $clientId;

    /** @var string */
    private $debug;

    /** @var string */
    private $endpoint;

    /** @var HttpClientInterface */
    private $authClient;

    /** @var HttpClientInterface */
    private $orderClient;

    /** @var HttpClientInterface */
    private $paymentClient;

    public function __construct(
        string $clientId,
        string $debug,
        string $endpoint,
        HttpClientInterface $authClient,
        HttpClientInterface $orderClient,
        HttpClientInterface $paymentClient
    ) {
        $this->clientId = $clientId;
        $this->debug = $debug;
        $this->endpoint = $endpoint;
        
        $this->authClient = $authClient;
        $this->orderClient = $orderClient;
        $this->paymentClient = $paymentClient;
    }

    public function getQueryParametersForJsSdk(): string
    {
        $payPalParameters = [
            'client-id' => $this->clientId,
            'components' => 'buttons',
            'currency' => 'EUR',
            'debug' => $this->debug,
            'enable-funding' => 'paylater', // Enables the "Pay Later" button (US, UK), which is "4X PayPal" in France, see https://developer.paypal.com/sdk/js/configuration/#link-enablefunding
            'integration-date' => '2022-10-31', // Do not update this date, it ensures backward-compat
            'intent' => 'capture',
        ];

        return http_build_query($payPalParameters);
    }

    public function handlePayment(Order $order, array $requestData): bool
    {
        $paypalOrderId = $requestData['orderID'];

        // https://developer.paypal.com/docs/api/orders/v2/#orders_get
        $responseBody = $this->orderClient->request('GET', $this->getGetEndpointUrl($paypalOrderId), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ],
        ]);

        $responseHttpCode = $responseBody->getStatusCode();

        if ($responseHttpCode !== 200) {
            return false;
        }

        $paypalOrder = $responseBody->toArray();

        if ($this->paidRightAmount($paypalOrder, $order)
            && \is_array($paypalOrder)
            && !empty($paypalOrder)
            && $paypalOrder['intent'] === self::INTENT_CAPTURE
            && $paypalOrder['status'] === self::STATUS_APPROVED
        ) {
            if ($this->capturePayment($paypalOrderId, $order)) {
                $order->setStatus(Order::STATUS_PAID);
                // Here, you can redirect the user to a confirmation page,
                // send an email, etc.
                
                return true;
            }
        }

        // Log your errors !
        return false;
    }

    public function paidRightAmount(array $paypalOrder, Order $order): bool
    {
        // Check if the purchase currency is the right one
        if ($paypalOrder['purchase_units'][0]['amount']['currency_code'] !== 'EUR') {
            return false;
        }
        // Check if the amount paid is the right one 
        if ((int) $paypalOrder['purchase_units'][0]['amount']['value'] !== $order->getAmount()) {
            $order->setStatus(Order::STATUS_FRAUD_SUSPECTED);
    
            return false;
        }
    
        return true;
    }
    
    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        // https://developer.paypal.com/api/rest/authentication/
        $responseBody = $this->authClient->request('POST', $this->getAuthEndpointUrl(), [
            'body' => 'grant_type=client_credentials',
        ]);

        $this->accessToken = $responseBody->toArray()['access_token'];

        return $this->accessToken;
    }

    private function capturePayment(string $paypalOrderId, Order $order): bool
    {
        // https://developer.paypal.com/docs/api/orders/v2/#orders_capture
        $responseBody = $this->paymentClient->request('POST', $this->getCaptureEndpointUrl($paypalOrderId), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Paypal-Request-Id' => $order->getId(),
            ],
        ]);

        $responseHttpCode = $responseBody->getStatusCode();

        $response = $responseBody->toArray();

        if ($responseHttpCode !== 201
            || !\array_key_exists('status', $response)
            || $response['status'] !== self::STATUS_COMPLETED
        ) {
            return false;
        }

        return true;
    }

    private function getAuthEndpointUrl(): string
    {
        return sprintf('%s/v1/oauth2/token', $this->endpoint);
    }

    private function getGetEndpointUrl($id): string
    {
        return sprintf('%s/v2/checkout/orders/%s', $this->endpoint, $id);
    }

    private function getCaptureEndpointUrl($id): string
    {
        return sprintf('%s/v2/checkout/orders/%s/capture', $this->endpoint, $id);
    }
}