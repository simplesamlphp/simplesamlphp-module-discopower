<?php

declare(strict_types=1);

namespace SimpleSAML\Module\discopower\Controller;

use Exception;
use SimpleSAML\Error;
use SimpleSAML\Module\discopower\PowerIdPDisco;
use SimpleSAML\Session;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response, StreamedResponse};

class DiscoPower
{
    /**
     * @param \Symfony\Component\HttpFoundation\Request $request The current request.
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function main(Request $request): StreamedResponse
    {
        try {
            $discoHandler = new PowerIdPDisco(
                ['saml20-idp-remote'],
                'poweridpdisco',
            );
        } catch (Exception $exception) {
            // An error here should be caused by invalid query parameters
            throw new Error\Error('DISCOPARAMS', $exception);
        }

        try {
            return new StreamedResponse([$discoHandler, 'handleRequest']);
        } catch (Exception $exception) {
            // An error here should be caused by metadata
            throw new Error\Error('METADATA', $exception);
        }
    }

    /**
     * An AJAX handler to retrieve a list of disco tabs from the session.
     * This allows us to dynamically update the tab list without inline javascript.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request The current request.
     */
    public function tablist(Request $request): Response
    {
        $session = Session::getSessionFromRequest();
        $tabs = $session->getData('discopower:tabList', 'tabs');
        $faventry = $session->getData('discopower:tabList', 'faventry');
        $defaulttab = $session->getData('discopower:tabList', 'defaulttab');

        if (!is_array($tabs)) {
            throw new Error\Exception('Could not get tab list from session');
        }

        $response = new JsonResponse();

        // handle JSONP requests
        if ($request->query->has('callback')) {
            $callback = $request->query->get('callback');
            if (!preg_match('/^[a-z0-9_]+$/i', $callback)) {
                throw new Error\Exception('Unsafe JSONP callback function name ' . var_export($callback, true));
            }
            $response->setCallback($callback);
        }

        $response->setData(
            [
                'faventry' => $faventry,
                'default' => $defaulttab,
                'tabs' => $tabs,
            ],
        );
        return $response;
    }
}
