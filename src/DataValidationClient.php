<?php

namespace CultuurNet\DataValidation;

use CultuurNet\DataValidation\RealtimeValidationStatus;
use CultuurNet\DataValidation\Result\GetEmailValidationResult;
use CultuurNet\DataValidation\Item\EmailValidationResult;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\EntityBodyInterface;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use Symfony\Component\HttpFoundation\ParameterBag;

class DataValidationClient implements DataValidationClientInterface
{
    /**
     * @var string
     */
    private $baseUrl = 'https://dv3.datavalidation.com/api/v2/';

    /**
     * @var ClientInterface
     */
    protected $guzzleClient;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var array
     */
    private $responseCache = [];

    /**
     * DataValidationClient constructor.
     * @param ClientInterface $guzzleClient
     * @param $apiKey
     */
    public function __construct(ClientInterface $guzzleClient, $apiKey)
    {
        $this->guzzleClient = $guzzleClient;
        $this->guzzleClient->setBaseUrl($this->baseUrl);

        $this->apiKey = $apiKey;
    }

    /**
     * Returns a cache key for a given request
     *
     * @param $method
     * @param $uri
     * @param ParameterBag $query
     * @return string
     */
    private function getRequestCacheKey($method, $uri, ParameterBag $query = null)
    {
        $key = $method . $uri;
        if (!empty($query)) {
            $key += json_encode($query->all());
        }

        return md5($key);
    }

    /**
     * Send and handle a request.
     * @param string $method
     * @param string $uri
     * @param ParameterBag $query
     * @param string|resource|array|EntityBodyInterface $body
     * @return Response
     */
    private function request($method, $uri, ParameterBag $query = null, $body = null)
    {
        $cacheKey = null;
        if ($method === 'GET') {
            $cacheKey = $this->getRequestCacheKey($method, $uri, $query);
        }

        if (!$cacheKey || !isset($this->responseCache[$cacheKey])) {
            $options = [];
            if (!empty($query)) {
                $options['query'] = $query->all();
            }

            $headers = [
                'Authorization' => 'bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ];

            $response = $this->guzzleClient->createRequest($method, $uri, $headers, $body, $options)->send();

            // @codeCoverageIgnoreStart
            if (!$cacheKey) {
                return $response;
            } else {
                $this->responseCache[$cacheKey] = $response;
            }
            // @codeCoverageIgnoreEnd
        }

        return $this->responseCache[$cacheKey];
    }

    /**
     * {@inheritdoc}
     */
    public function validateEmail($email)
    {
        try {
            $response = $this->request(RequestInterface::GET, 'realtime/', new ParameterBag(['email' => $email]));
            return GetEmailValidationResult::parseToResult($response);
        }
        catch (\Exception $e) {
            // Since there is no data to parse, return an error validation result.
            $emailValidationResult =  new EmailValidationResult();
            $emailValidationResult->setStatus(RealtimeValidationStatus::ERROR);
            return $emailValidationResult;
        }
    }
}
