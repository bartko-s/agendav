<?php

namespace AgenDAV\Controller;

/*
 * Copyright 2015 Jorge López Pérez <jorge@adobo.org>
 *
 *  This file is part of AgenDAV.
 *
 *  AgenDAV is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  any later version.
 *
 *  AgenDAV is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with AgenDAV.  If not, see <http://www.gnu.org/licenses/>.
 */

use AgenDAV\CalDAV\Client;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * This class is used to find all accessible calendars for an user
 */
abstract class JSONController
{

    /**
     * @var \AgenDAV\CalDAV\Client
     */
    protected $client;

    /**
     * @var string HTTP method
     */
    protected $method = 'POST';

    /**
     * @var array
     */
    protected $headers;

    /**
     * Builds a new JSONController
     */
    public function __construct()
    {
        $this->headers = [];
    }

    /**
     * Executes the action assigned to this controller
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    public function doAction(Request $request, Application $app)
    {
        $this->client = $app['caldav.client'];

        // Read input
        if ($this->method === 'POST') {
            $input = $request->request;
        }

        if ($this->method === 'GET') {
            $input = $request->query;
        }

        if (!$this->validateInput($input)) {
            return $this->generateException(
                $app['translator']->trans('messages.error_invalidinput')
            );
        }

        return $this->controlledExecution($input, $app);
    }

    /**
     * Proceeds to execute this action, taking care of possible exceptions
     *
     * @param Symfony\Component\HttpFoundation\ParameterBag $input
     * @param Silex\Application $app
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function controlledExecution(ParameterBag $input, Application $app)
    {
        try {
            $result = $this->execute($input, $app);
            return $result;

        } catch (\AgenDAV\Exception\PermissionDenied $exception) {
            return $this->generateException(
                $app['translator']->trans('messages.error_denied')
            );

        } catch (\AgenDAV\Exception\NotFound $exception) {
            return $this->generateException(
                $app['translator']->trans('messages.error_element_not_found')
            );

        } catch (\AgenDAV\Exception\ElementModified $exception) {
            return $this->generateException(
                $app['translator']->trans('messages.error_element_changed')
            );

        } catch (\AgenDAV\Exception\ConnectionProblem $exception) {
            $app['monolog']->addError(sprintf(
                "Having issues contacting the CalDAV server: %s",
                var_export($exception->getMessage(), true)
            ));

            $message = $app['translator']->trans('messages.error_network_issues');

            return $this->generateError($message, 503);

        } catch (\AgenDAV\Exception $exception) {
            $app['monolog']->addWarning(sprintf(
                "Received unexpected HTTP code %d (%s) for input: %s",
                $exception->getCode(),
                $exception->getMessage(),
                var_export($input, true)
            ));

            return $this->generateError(
                $app['translator']->trans('messages.error_unexpectedhttpcode', ['%code%' => $exception->getCode()])
            );

        } catch (\Exception $exception) {
            $app['monolog']->addError(sprintf(
                "Received unexpected exception %s for input: %s",
                var_export($exception->getMessage(), true),
                var_export($input, true)
            ));

            return $this->generateError(
                $app['translator']->trans('messages.internal_server_error')
            );
        }
    }

    /**
     * Validates user input
     *
     * @param Symfony\Component\HttpFoundation\ParameterBag $input
     * @return bool
     */
    protected function validateInput(ParameterBag $input)
    {
        return true;
    }

    /**
     * Performs an operation using the information from input
     *
     * @param Symfony\Component\HttpFoundation\ParameterBag $input
     * @param Silex\Application $app
     * @return array
     */
    abstract protected function execute(ParameterBag $input, Application $app);

    /**
     * Generates an exception message
     *
     * @param string $message
     * @param int $code Optional HTTP response code
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function generateException($message, $code = 400)
    {
        $result = [
            'result' => 'EXCEPTION',
            'message' => $message
        ];

        return new JsonResponse($result, $code, $this->headers);
    }

    /**
     * Generates an error message
     *
     * @param string $message
     * @param int $code Optional HTTP response code
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function generateError($message, $code = 500)
    {
        $result = [
            'result' => 'ERROR',
            'message' => $message
        ];

        return new JsonResponse($result, $code, $this->headers);
    }
    /**
     * Generates a success message
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function generateSuccess($message = '')
    {
        $result = [
            'result' => 'SUCCESS',
            'message' => $message
        ];

        return new JsonResponse($result, 200, $this->headers);
    }

    /**
     * Adds a header to this response
     *
     * @param string $name
     * @param string $value
     */
    protected function addHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

}
