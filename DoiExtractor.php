<?php
require_once __DIR__ . '/vendor/autoload.php';

use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Promise;
use GuzzleHttp\TransferStats;


class DoiExtractor
{
	private $doiUrl;

	private $client;

	private $redirectURL;

	private $result;

	public function __construct($doi)
	{
		$this->redirectURL = null;
		$this->result = [];
		$this->doiUrl = "https://doi.org/$doi";
		$this->client = GuzzleFactory::make([], 200);
		$this->handle();
	}

	private function handle(): void
	{
		try {
			$promises = $this->getPromises();
			$responses = Promise\unwrap($promises);
			$this->handleXmlResponse($responses['xml']);
			$this->handleDomResponse($responses['dom']);
		} catch (Exception $e) {
			echo 'Something went wrong! Try again';
		}
	}

	private function getPromises(): array
	{
		$xmlPromise = $this->client->requestAsync('GET', $this->doiUrl, [
			'headers' => ['Accept' => 'application/vnd.citationstyles.csl+json;q=1.0'],
			'allow_redirects' => true
		]);

		$jar = new CookieJar;

		$domPromise = $this->client->requestAsync('GET', $this->doiUrl, [
			'cookies' => $jar,
			'headers' => [
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36'
			],
			'on_stats' => function (TransferStats $stats) {
				$this->setRedirectUrl($stats->getEffectiveUri());
			},
			'allow_redirects' => ['max' => 10]
		]);

		return [
			'xml' => $xmlPromise,
			'dom' => $domPromise
		];
	}

	private function handleXmlResponse($response): void
	{
		$res = json_decode((string) $response->getBody());
		if (
			isset($res->link) &&
			'array' === gettype($res->link) &&
			count($res->link) > 0 &&
			$this->isScienceDirectUrl($res)
		) {
			$links = $res->link;
			$contentType = "content-type";
			foreach ($links as $link) {
				try {
					if ('text/xml' === $link->$contentType) {
						$scienceDirectOptions = ['allow_redirects' => true];
						$res = $this->client->request('GET', $link->URL, $scienceDirectOptions);
						$xmlData = (string) $res->getBody();
						$xmls = simplexml_load_string($xmlData)->coredata->link;
						foreach ($xmls as $xml) {
							if ('self' !== (string) $xml->attributes()['rel']) {
								$this->set((string) $xml->attributes()['href']);
							}
						}
					} else {
						continue;
					}
				} catch (Exception $e) {
					continue;
				}
			}
		}
	}

	private function handleDomResponse($response): void
	{
		if (strpos($this->redirectURL, 'linkinghub') !== false) {
			$httpData = (string) $response->getBody();
			$dom = new DomDocument();
			$dom->loadHtml($httpData);
			$this->setRedirectUrl($dom->getElementById('redirectURL')->getAttribute('value'));
		}
		$this->set($this->removeQueryParam(urldecode($this->redirectURL)));
	}

	private function setRedirectUrl($redirectUrl): void
	{
		$this->redirectURL = $redirectUrl;
	}

	private function isScienceDirectUrl($response): bool
	{
		if (
			isset($response->{"content-domain"}) &&
			isset($response->{"content-domain"}->domain) &&
			in_array('sciencedirect.com', $response->{"content-domain"}->domain)
		) {
			return true;
		} else {
			return false;
		}
	}

	private function removeQueryParam($url): string
	{
		if (strpos($url, 'cell.com') && strpos($url, '_returnURL')) {
			return explode('?', $url)[0];
		}
		return $url;
	}

	private function set($url): void
	{
		array_push($this->result, $url);
	}

	public function get(): array
	{
		return $this->result;
	}
}
