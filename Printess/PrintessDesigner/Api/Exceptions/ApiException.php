<?php

namespace Printess\Api\Exceptions;

use DateTime;
use DateTimeImmutable;
use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use Throwable;

class ApiException extends Exception
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var RequestInterface|null
     */
    protected $request;

    /**
     * @var ResponseInterface|null
     */
    protected $response;

    /**
     * ISO8601 representation of the moment this exception was thrown
     *
     * @var DateTimeImmutable
     */
    protected $raisedAt;

    /**
     * @var array
     */
    protected $links = [];

    /**
     * @param string $message
     * @param int $code
     * @param string|null $field
     * @param RequestInterface|null $request
     * @param ResponseInterface|null $response
     * @param Throwable|null $previous
     * @throws ApiException
     */
    public function __construct(
        $message = "",
        $code = 0,
        $field = null,
        ?RequestInterface $request = null,
        ?ResponseInterface $response = null,
        ?Throwable $previous = null
    ) {
        $this->raisedAt = new DateTimeImmutable();

        $formattedRaisedAt = $this->raisedAt->format(DateTime::ATOM);
        $message = "[$formattedRaisedAt] " . $message;

        if (! empty($field)) {
            $this->field = (string)$field;
            $message .= ". Field: $this->field";
        }

        if ($response !== null) {
            $this->response = $response;

            $object = static::parseResponseBody($this->response);

            if (isset($object->_links)) {
                foreach ($object->_links as $key => $value) {
                    $this->links[$key] = $value;
                }
            }
        }

        if ($this->hasLink('documentation')) {
            $message .= ". Documentation: {$this->getDocumentationUrl()}";
        }

        $this->request = $request;
        if ($request) {
            $requestBody = $request->getBody()->__toString();

            if ($requestBody) {
                $message .= ". Request body: $requestBody";
            }
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * @param ResponseInterface $response
     * @param RequestInterface|null $request
     * @param Throwable|null $previous
     * @return ApiException
     * @throws ApiException
     */
    public static function createFromResponse(ResponseInterface $response, ?RequestInterface $request = null, ?Throwable $previous = null): ApiException
    {
        $object = static::parseResponseBody($response);

        $field = null;
        if (! empty($object->field)) {
            $field = $object->field;
        }

        return new self(
            "Error executing API call ($object->c): $object->m",
            $response->getStatusCode(),
            $field,
            $request,
            $response,
            $previous
        );
    }

    /**
     * @return string|null
     */
    public function getField(): ?string
    {
        return $this->field;
    }

    /**
     * @return string|null
     */
    public function getDocumentationUrl(): ?string
    {
        return $this->getUrl('documentation');
    }

    /**
     * @return string|null
     */
    public function getDashboardUrl(): ?string
    {
        return $this->getUrl('dashboard');
    }

    /**
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * @return bool
     */
    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    /**
     * @param $key
     * @return bool
     */
    public function hasLink($key): bool
    {
        return array_key_exists($key, $this->links);
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function getLink($key)
    {
        if ($this->hasLink($key)) {
            return $this->links[$key];
        }

        return null;
    }

    /**
     * @param $key
     * @return null|string
     */
    public function getUrl($key): ?string
    {
        if ($this->hasLink($key)) {
            return $this->getLink($key)->href;
        }

        return null;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the ISO8601 representation of the moment this exception was thrown
     *
     * @return DateTimeImmutable
     */
    public function getRaisedAt(): DateTimeImmutable
    {
        return $this->raisedAt;
    }

    /**
     * @param ResponseInterface $response
     * @return stdClass
     * @throws ApiException
     */
    protected static function parseResponseBody(ResponseInterface $response): stdClass
    {
        $body = (string) $response->getBody();

        $object = @json_decode($body, false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new self("Unable to decode Printess response: '$body'.");
        }

        return $object;
    }
}
