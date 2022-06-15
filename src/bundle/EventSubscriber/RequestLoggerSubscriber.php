<?php

namespace VidarL\Bundle\IbexaUtil\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class RequestLoggerSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $logText;

    /**
     * @var int
     */
    private $stack = 0;

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => [
                ['onKernelRequest', 999997],
            ],
            ResponseEvent::class => [
                ['onKernelResponse', -999999],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $logText = '';
        ++$this->stack;
        if ($this->stack === 1) {
            $logText = "\n\n#### Session START  ####\n";
        }
        $logText .= " #### Request start : {$_SERVER['REQUEST_URI']} ####\n";
        if ($event->getRequest()->attributes->has('_controller')) {
            $logText .= " SUB REQUEST!  : {$event->getRequest()->attributes->get('_controller')}\n";
        }

        $isFromTrustedProxy = $event->getRequest()->isFromTrustedProxy()? 'True': 'False';
        $logText .=   " IsFromTrustedProxy : $isFromTrustedProxy\n";

        foreach (['HTTP_HOST', 'REMOTE_PORT', 'REMOTE_ADDR', 'HTTP_X_USER_CONTEXT_HASH', 'HTTP_X_USER_HASH', 'HTTP_COOKIE'] as $header) {
            $logText .= array_key_exists($header, $_SERVER) ? "  - $header : " . $_SERVER[$header] . "\n" : '';
        }

        foreach ($_SERVER as $key => $value) {
            if (stripos($key, 'X_FORWARD')) {
                $logText .= "  - $key : $value\n";
            }
            if (stripos($key, 'X_VIDAR')) {
                $logText .= "  - $key : $value\n";
            }
            // If you wanna log all headers, enable :
            //$logText .= "  - $key : $value\n";
        }
        $logText .= " #### Request end ####\n";
        $this->logText .= $logText;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        --$this->stack;

        $logText = "\n ### Response start : " . $event->getRequest()->getRequestUri() .  " ###\n";
        $response = $event->getResponse();
        foreach ($response->headers->all() as $header => $valueArray) {
            foreach ($valueArray as $value) {
                $logText .= "  - $header: $value\n";
            }
        }
        $logText .= " ### Response end ###\n";
        if ($this->stack === 0) {
            $logText .= "### Session END ###\n";
        }

        $this->logText .= $logText;

        if ($this->stack === 0) {
            file_put_contents('/tmp/log.txt', $this->logText , FILE_APPEND);
            $this->logText = '';
        }
    }

}
