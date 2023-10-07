<?php namespace Digiom\Psr7wp\Exceptions;

defined('ABSPATH') || exit;

use Psr\Http\Message\RequestInterface;

/**
 * Interface RequestExceptionInterface - Exception for when a request failed.
 *
 * Examples:
 *      - Request is invalid (e.g. method is missing)
 *      - Runtime request errors (e.g. the body stream is not seekable)
 *
 * @package Digiom\Psr7wp\Exceptions
 */
interface RequestExceptionInterface extends ClientExceptionInterface
{
    /**
     * Returns the request.
     *
     * The request object MAY be a different object from the one passed to ClientInterface::sendRequest()
     *
     * @return RequestInterface
     */
    public function getRequest();
}
