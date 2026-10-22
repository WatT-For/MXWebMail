<?php

namespace MXWebMailLib\HTTP;

class Response
{
	public
		$request_uri,       # The URI that was passed to the fetcher
		$final_uri,         # The result of following redirects from the request_uri
		$truncated = false; # The body hit max_response_kb and was cut short
	protected
		$status = 0,        # The HTTP status code returned from the final_uri
		$headers = array(), # The headers returned from the final_uri
		$body;              # The body returned from the final_uri

	function __construct(string $request_uri, int $status = 0, ?array $headers = null, $body = null)
	{
		if ($headers) {
			$name = null;
			$this->headers = array();
			foreach ($headers as $header) {
				if (\strpos($header, ':')) {
					list($name, $value) = \explode(':', $header, 2);
					$name = \strtolower(\trim($name));
					$value = \trim($value);
					if (isset($this->headers[$name])) {
						if (\is_array($this->headers[$name])) {
							$this->headers[$name][] = $value;
						} else {
							$this->headers[$name] = array($this->headers[$name], $value);
						}
					} else {
						$this->headers[$name] = $value;
					}
				}
			}
		}
		$this->request_uri = $request_uri;
		$this->final_uri   = $request_uri;
		$this->status      = (int) $status;
		// Un en-tête dupliqué produit un array — stripos exige une string.
		$encoding = $this->headers['content-encoding'] ?? '';
		if (\is_array($encoding)) {
			$encoding = \implode(',', $encoding);
		}
		if (\function_exists('gzinflate') && '' !== $encoding
		 && (false !== \stripos($encoding, 'gzip'))) {
			$this->body = \gzinflate(\substr($body, 10, -4));
			if (false === $this->body) {
				$err = \error_get_last() ?: ['message' => 'gzinflate failed'];
				throw new \RuntimeException("{$err['message']} for {$request_uri}");
			}
		} else {
			$this->body = $body;
		}
	}

	function __get($k)
	{
		return \property_exists($this, $k) ? $this->$k : null;
	}

	/**
	 * returns string, array or null
	 */
	public function getHeader($names)
	{
		$names = \is_array($names) ? $names : array($names);
		foreach ($names as $n) {
			$n = \strtolower($n);
			if (isset($this->headers[$n])) {
				return $this->headers[$n];
			}
		}
		return null;
	}

	public function getHeaders() : array
	{
		return $this->headers;
	}

	public function getRedirectLocation() : ?string
	{
		if ($location = $this->getHeader('location')) {
			$uri = \is_array($location) ? $location[0] : $location;
			if ('' === (string) $uri) {
				return null;
			}
			if (!\preg_match('#^([a-z][a-z0-9\+\.\-]+:)?//[^/]+#i', $uri)) {
				// no host
				if (!\preg_match('#^((?:[a-z][a-z0-9\+\.\-]+:)?//[^/]+)(/[^\?\#]*)#i',
					$this->final_uri, $match)) {
					return null; // final_uri sans hôte parseable
				}
				if ('/' === $uri[0]) {
					// absolute path
					$uri = $match[1] . $uri;
				} else {
					// relative path
					$rpos = \strrpos($match[2] ?? '/', '/');
					$uri  = $match[1] . \substr($match[2] ?? '/', 0, $rpos+1) . $uri;
				}
			}
			if ('//' === \substr($uri, 0, 2)) {
				$uri = \explode(':', $this->request_uri)[0] . ':' . $uri;
			}
			return $uri;
		}
		return null;
	}

}
