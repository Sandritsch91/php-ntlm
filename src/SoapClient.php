<?php

declare(strict_types=1);

/**
 * Contains \jamesiarmes\PhpNtlm\SoapClient
 */

namespace jamesiarmes\PhpNtlm;

use CurlHandle;

/**
 * Soap Client using Microsoft's NTLM Authentication.
 *
 * @package php-ntlm\Soap
 */
class SoapClient extends \SoapClient
{
    /**
     * cURL handle used to make the SOAP request.
     */
    protected ?CurlHandle $ch = null;

    /**
     * Options passed to the client constructor.
     */
    protected array $options;

    private ?string $__last_request = null;

    /**
     * @var string[]|null
     */
    private ?array $__last_request_headers = null;

    private ?string $__last_response = null;

    private ?string $__last_response_headers = null;

    /**
     * {@inheritdoc}
     *
     * Additional options:
     * - user (string): The user to authenticate with.
     * - password (string): The password to use when authenticating the user.
     * - curlopts (array): Array of options to set on the curl handler when
     *   making the request.
     * - strip_bad_chars (boolean, default true): Whether or not to strip
     *   invalid characters from the XML response. This can lead to content
     *   being returned differently than it actually is on the host service, but
     *   can also prevent the "looks like we got no XML document" SoapFault when
     *   the response includes invalid characters.
     * - warn_on_bad_chars (boolean, default false): Trigger a warning if bad
     *   characters are stripped. This has no affect unless strip_bad_chars is
     *   true.
     */
    public function __construct(?string $wsdl, array $options = [])
    {
        // Set missing indexes to their default value.
        $options += [
            'user' => null,
            'password' => null,
            'curlopts' => [],
            'strip_bad_chars' => true,
            'warn_on_bad_chars' => false,
        ];
        $this->options = $options;

        // Verify that a user name and password were entered.
        if (empty($options['user']) || empty($options['password'])) {
            throw new \BadMethodCallException(
                'A username and password is required.'
            );
        }

        parent::__construct($wsdl, $options);
    }

    /**
     * {@inheritdoc}
     *
     * The $uriParserClass parameter was added to \SoapClient::__doRequest() in
     * PHP 8.5. It is accepted here for signature compatibility but unused, as
     * the request is made through cURL.
     */
    public function __doRequest(
        string $request,
        string $location,
        string $action,
        int $version,
        bool $oneWay = false,
        ?string $uriParserClass = null
    ): ?string {
        $headers = $this->buildHeaders($action);
        $this->__last_request = $request;
        $this->__last_request_headers = $headers;

        // Only reinitialize curl handle if the location is different.
        if ($this->ch === null
            || curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL) !== $location) {
            $handle = curl_init($location);
            if ($handle === false) {
                throw new \RuntimeException('Unable to initialize cURL.');
            }
            $this->ch = $handle;
        }

        curl_setopt_array($this->ch, $this->curlOptions($action, $request));
        $response = curl_exec($this->ch);

        // If the response is false then there was an error and we should
        // throw an exception.
        if ($response === false) {
            $this->__last_response = $this->__last_response_headers = null;
            throw new \RuntimeException(
                'Curl error: ' . curl_error($this->ch),
                curl_errno($this->ch)
            );
        }

        $this->parseResponse((string)$response);
        $this->cleanResponse();

        return $this->__last_response;
    }

    /**
     * {@inheritdoc}
     */
    public function __getLastRequest(): ?string
    {
        return $this->__last_request;
    }

    /**
     * {@inheritdoc}
     */
    public function __getLastRequestHeaders(): ?string
    {
        if ($this->__last_request_headers === null) {
            return null;
        }

        return implode("\n", $this->__last_request_headers) . "\n";
    }

    /**
     * {@inheritdoc}
     */
    public function __getLastResponse(): ?string
    {
        return $this->__last_response;
    }

    /**
     * {@inheritdoc}
     */
    public function __getLastResponseHeaders(): ?string
    {
        return $this->__last_response_headers;
    }

    /**
     * Returns the response code from the last request
     *
     * @throws \BadMethodCallException
     *   If no cURL resource has been initialized.
     */
    public function getResponseCode(): int
    {
        if ($this->ch === null) {
            throw new \BadMethodCallException('No cURL resource has been '
                . 'initialized. This is probably because no request has '
                . 'been made.');
        }

        return (int)curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
    }

    /**
     * Builds the headers for the request.
     *
     * @return string[]
     */
    protected function buildHeaders(string $action): array
    {
        return [
            'Method: POST',
            'Connection: Keep-Alive',
            'User-Agent: PHP-SOAP-CURL',
            'Content-Type: text/xml; charset=utf-8',
            "SOAPAction: \"$action\"",
            'Expect: 100-continue',
        ];
    }

    /**
     * Cleans the response body by stripping bad characters if instructed to.
     */
    protected function cleanResponse(): void
    {
        // If the option to strip bad characters is not set, or there is no
        // response, then we shouldn't do anything here.
        if (!$this->options['strip_bad_chars'] || $this->__last_response === null) {
            return;
        }

        // Strip invalid characters from the XML response body.
        $count = 0;
        $this->__last_response = preg_replace(
            '/(?!&#x0?(9|A|D))(&#x[0-1]?[0-9A-F];)/',
            ' ',
            $this->__last_response,
            -1,
            $count
        );

        // If the option to warn on bad characters is set, and some characters
        // were stripped, then trigger a warning.
        if ($this->options['warn_on_bad_chars'] && $count > 0) {
            trigger_error(
                'Invalid characters were stripped from the XML SOAP response.',
                E_USER_WARNING
            );
        }
    }

    /**
     * Builds an array of curl options for the request
     */
    protected function curlOptions(string $action, string $request): array
    {
        $options = $this->options['curlopts'] + [
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $this->buildHeaders($action),
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC | CURLAUTH_NTLM,
                CURLOPT_USERPWD => $this->options['user'] . ':'
                    . $this->options['password'],
            ];

        // We shouldn't allow these options to be overridden.
        $options[CURLOPT_HEADER] = true;
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $request;

        return $options;
    }

    /**
     * Parses the response from a successful request.
     *
     * @param string $response
     *   The response from the cURL request, including headers and body.
     */
    public function parseResponse(string $response): void
    {
        // Parse the response and set the last response and headers.
        $headerSize = (int)curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $this->__last_response_headers = substr($response, 0, $headerSize);
        $this->__last_response = substr($response, $headerSize);
    }
}
