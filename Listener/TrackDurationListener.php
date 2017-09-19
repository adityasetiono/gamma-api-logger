<?php

namespace Gamma\ApiLoggerBundle\Listener;

use Gamma\ApiLoggerBundle\Service\LoggerStopwatch;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class TrackDurationListener
{
    private $stopwatchLogger;
    private $uri;
    private $requestMethod;
    private $requestApiContentRaw;
    private $requestApiContentForm;

    /**
     * TrackDurationListener constructor.
     *
     * @param $stopwatchLogger
     */
    public function __construct(LoggerStopwatch $stopwatchLogger)
    {
        $this->stopwatchLogger = $stopwatchLogger;
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return false;
        }

        $request = $event->getRequest();
        $this->uri = $request->getRequestUri();

        if (!preg_match('/\/api/', $this->uri)) {
            return false;
        }

        $this->stopwatchLogger->start($this->uri);
        $this->requestMethod = $request->getMethod();
        $receivedRawData = $request->getContent();

        if ($receivedRawData) {
            $parsedData = json_decode($receivedRawData, true);
            $this->requestApiContentRaw = $parsedData;
        }

        $receivedFormData = $request->request->all();

        if (sizeof($receivedFormData)) {
            $this->requestApiContentForm = urldecode(http_build_query($receivedFormData));
        }
    }

    /**
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest() || !preg_match('/\/api/', $this->getUri())) {
            return;
        }

        $params = array('method' => $this->requestMethod);
        $response = $event->getResponse();

        if ($this->requestApiContentRaw) {
            $params['RawRequest'] = $this->requestApiContentRaw;
        }
        if ($this->requestApiContentForm) {
            $params['FormRequest'] = $this->requestApiContentForm;
        }
        $params['response'] = json_decode($response->getContent(), true);
        $params['status'] = $response->getStatusCode();

        $this->stopwatchLogger->stop($this->getUri(), $params);
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param $uri
     *
     * @return string
     */
    public function setUri($uri)
    {
        return $this->uri = $uri;
    }
}
