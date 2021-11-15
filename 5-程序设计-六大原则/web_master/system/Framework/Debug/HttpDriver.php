<?php

namespace Framework\Debug;

use DebugBar\HttpDriverInterface;
use Framework\Http\Response;
use Framework\Session\Session;

class HttpDriver implements HttpDriverInterface
{
    private $session;
    private $response;

    public function __construct(Session $session, Response $response)
    {
        $this->session = $session;
        $this->response = $response;
    }

    /**
     * @inheritDoc
     */
    function setHeaders(array $headers)
    {
        if ($this->response) {
            $this->response->headers->add($headers);
        }
    }

    /**
     * @inheritDoc
     */
    function isSessionStarted()
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        return $this->session->isStarted();
    }

    /**
     * @inheritDoc
     */
    function setSessionValue($name, $value)
    {
        $this->session->set($name, $value);
    }

    /**
     * @inheritDoc
     */
    function hasSessionValue($name)
    {
        return $this->session->has($name);
    }

    /**
     * @inheritDoc
     */
    function getSessionValue($name)
    {
        return $this->session->get($name);
    }

    /**
     * @inheritDoc
     */
    function deleteSessionValue($name)
    {
        $this->session->remove($name);
    }
}
