<?php

namespace GusApi\Adapter\Soap;

use GusApi\Adapter\AdapterInterface;
use GusApi\Client\SoapClient;
use GusApi\Exception\NotFoundException;
use GusApi\RegonConstantsInterface;
use SimpleXMLElement;

/**
 * Class SoapAdapter SoapAdapter for
 *
 * @package GusApi\Adapter\Soap
 */
class SoapAdapter implements AdapterInterface
{
    /**
     * @var SoapClient gus soap client
     */
    protected $client;

    /**
     * @var string base url address
     */
    protected $baseUrl;

    /**
     * @var string base address to http header
     */
    protected $address;

    /**
     * Create Gus soap adapter
     *
     * @param string $baseUrl
     * @param string $address
     * @param array  $contextOptions
     */
    public function __construct(string $baseUrl, string $address, array $contextOptions = null)
    {
        $this->baseUrl = $baseUrl;
        $this->address = $address;

        $this->client = new SoapClient($this->baseUrl, $address, [
            'soap_version' => SOAP_1_2,
            'trace' => true,
            'style' => SOAP_DOCUMENT,
        ], $contextOptions);
    }

    /**
     * @return SoapClient
     */
    public function getClient(): SoapClient
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function login(string $userKey): string
    {
        $this->prepareSoapHeader('http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/Zaloguj', $this->address);
        $result = $this->client->Zaloguj([
            RegonConstantsInterface::PARAM_USER_KEY => $userKey,
        ]);

        $sid = $result->ZalogujResult;

        return $sid;
    }

    /**
     * {@inheritdoc}
     */
    public function logout(string $sid): bool
    {
        $this->prepareSoapHeader('http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/Wyloguj', $this->address);
        $result = $this->client->Wyloguj([
            RegonConstantsInterface::PARAM_SESSION_ID => $sid,
        ]);

        return (bool) $result->WylogujResult;
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $sid, array $parameters)
    {
        $this->prepareSoapHeader('http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/DaneSzukaj', $this->address, $sid);

        $result = $this->client->DaneSzukaj([
            RegonConstantsInterface::PARAM_SEARCH => $parameters,
        ]);

        try {
            $result = $this->decodeResponse($result->DaneSzukajResult);
        } catch (\Exception $e) {
            throw new NotFoundException('No data found');
        }

        return $result->dane;
    }

    /**
     * {@inheritdoc}
     */
    public function getFullData(string $sid, string $regon, string $reportType)
    {
        $this->prepareSoapHeader(
            'http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/DanePobierzPelnyRaport',
            $this->address,
            $sid
        );
        $result = $this->client->DanePobierzPelnyRaport([
            RegonConstantsInterface::PARAM_REGON => $regon,
            RegonConstantsInterface::PARAM_REPORT_NAME => $reportType,
        ]);

        try {
            $result = $this->decodeResponse($result->DanePobierzPelnyRaportResult);
        } catch (\Exception $e) {
            throw new NotFoundException('No data found');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(?string $sid, string $param)
    {
        $this->prepareSoapHeader('http://CIS/BIR/2014/07/IUslugaBIR/GetValue', $this->address, $sid);
        $result = $this->client->GetValue([
            RegonConstantsInterface::PARAM_PARAM_NAME => $param,
        ]);

        return $result->GetValueResult;
    }

    /**
     * Prepare soap necessary header
     *
     * @param string      $action
     * @param string      $to
     * @param null|string $sid    session id
     */
    protected function prepareSoapHeader(string $action, string $to, ?string $sid = null)
    {
        $this->clearHeader();
        $header[] = $this->setHeader('http://www.w3.org/2005/08/addressing', 'Action', $action);
        $header[] = $this->setHeader('http://www.w3.org/2005/08/addressing', 'To', $to);
        $this->client->__setSoapHeaders($header);

        if (null !== $sid) {
            $this->client->__setHttpHeader([
                'header' => 'sid: '.$sid,
            ]);
        }
    }

    /**
     * Clear soap header
     */
    protected function clearHeader(): bool
    {
        return $this->client->__setSoapHeaders(null);
    }

    /**
     * Set soap header
     *
     * @param string $namespace
     * @param string $name
     * @param mixed  $data
     * @param bool   $mustUnderstand
     *
     * @return \SoapHeader
     */
    protected function setHeader(string $namespace, string $name, $data = null, bool $mustUnderstand = false)
    {
        return new \SoapHeader($namespace, $name, $data, $mustUnderstand);
    }

    /**
     * @param string $response xml string
     *
     * @return SimpleXMLElement
     */
    protected function decodeResponse(string $response): SimpleXMLElement
    {
        return new SimpleXMLElement($response);
    }
}
