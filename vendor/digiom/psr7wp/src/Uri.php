<?php namespace Digiom\Psr7wp;

defined('ABSPATH') || exit;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Digiom\Psr7wp\Exceptions\MalformedUriException;
use function strtr;

/**
 * Class Uri
 *
 * @package Digiom\Psr7wp
 */
class Uri implements UriInterface
{
	/**
	 * Absolute http and https URIs require a host per RFC 7230 Section 2.7
	 * but in generic URIs the host can be empty. So for http(s) URIs
	 * we apply this default host when no host is given yet to form a
	 * valid URI.
	 */
	const HTTP_DEFAULT_HOST = 'localhost';

	/**
	 * Default ports for services
	 */
	const DEFAULT_PORTS =
	[
		'http'  => 80,
		'https' => 443,
		'ftp' => 21,
		'gopher' => 70,
		'nntp' => 119,
		'news' => 119,
		'telnet' => 23,
		'tn3270' => 23,
		'imap' => 143,
		'pop' => 110,
		'ldap' => 389,
	];

	/**
	 * Unreserved characters for use in a regex.
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-2.3
	 */
	const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

	/**
	 * Sub-delims for use in a regex.
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-2.2
	 */
	const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
	const QUERY_SEPARATORS_REPLACEMENT = ['=' => '%3D', '&' => '%26'];

	/**
	 * @var string Uri scheme.
	 */
	private $scheme = '';

	/**
	 * @var string Uri user info.
	 */
	private $userInfo = '';

	/**
	 * @var string Uri host.
	 */
	private $host = '';

	/**
	 * @var int|null Uri port.
	 */
	private $port;

	/**
	 * @var string Uri path.
	 */
	private $path = '';

	/**
	 * @var string Uri query string.
	 */
	private $query = '';

	/**
	 * @var string Uri fragment.
	 */
	private $fragment = '';

	/**
	 * @var string String representation
	 */
	private $composedComponents;

	/**
	 * Uri constructor.
	 *
	 * @param string $uri
	 */
	public function __construct($uri = '')
	{
		if($uri !== '')
		{
			$parts = self::parse($uri);

			if ($parts === false)
			{
				throw new MalformedUriException("Unable to parse URI: $uri");
			}

			$this->applyParts($parts);
		}
	}
	/**
	 * UTF-8 aware \parse_url() replacement.
	 *
	 * The internal function produces broken output for non ASCII domain names
	 * (IDN) when used with locales other than "C".
	 *
	 * On the other hand, cURL understands IDN correctly only when UTF-8 locale
	 * is configured ("C.UTF-8", "en_US.UTF-8", etc.).
	 *
	 * @see https://bugs.php.net/bug.php?id=52923
	 * @see https://www.php.net/manual/en/function.parse-url.php#114817
	 * @see https://curl.haxx.se/libcurl/c/CURLOPT_URL.html#ENCODING
	 *
	 * @return array|false
	 */
	private static function parse($url)
	{
		// If IPv6
		$prefix = '';

		if(preg_match('%^(.*://\[[0-9:a-f]+\])(.*?)$%', $url, $matches))
		{
			/**
			 * @var array{0:string, 1:string, 2:string} $matches
			 */
			$prefix = $matches[1];
			$url = $matches[2];
		}

		/**
		 * @var string
		 */
		$encodedUrl = preg_replace_callback
		(
			'%[^:/@?&=#]+%usD', static function ($matches)
			{
				return urlencode($matches[0]);
			},
			$url
		);

		$result = parse_url($prefix . $encodedUrl);

		if($result === false)
		{
			return false;
		}

		return array_map('urldecode', $result);
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		if($this->composedComponents === null)
		{
			$this->composedComponents = self::composeComponents
			(
				$this->scheme,
				$this->getAuthority(),
				$this->path,
				$this->query,
				$this->fragment
			);
		}

		if(!is_null($this->composedComponents))
		{
			return $this->composedComponents;
		}

		return '';
	}

	/**
	 * Composes a URI reference string from its various components.
	 *
	 * Usually this method does not need to be called manually but instead is used indirectly via
	 * `Psr\Http\Message\UriInterface::__toString`.
	 *
	 * PSR-7 UriInterface treats an empty component the same as a missing component as
	 * getQuery(), getFragment() etc. always return a string. This explains the slight
	 * difference to RFC 3986 Section 5.3.
	 *
	 * Another adjustment is that the authority separator is added even when the authority is missing/empty
	 * for the "file" scheme. This is because PHP stream functions like `file_get_contents` only work with
	 * `file:///myfile` but not with `file:/myfile` although they are equivalent according to RFC 3986. But
	 * `file:///` is the more common syntax for the file scheme anyway (Chrome for example redirects to
	 * that format).
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-5.3
	 */
	public static function composeComponents($scheme = null, $authority = null, $path = '', $query  = null, $fragment = null)
	{
		$uri = '';

		// weak type checks to also accept null until we can add scalar type hints
		if($scheme !== '')
		{
			$uri .= $scheme . ':';
		}

		if($authority !== ''|| $scheme === 'file')
		{
			$uri .= '//' . $authority;
		}

		$uri .= $path;

		if($query !== '')
		{
			$uri .= '?' . $query;
		}

		if($fragment !== '')
		{
			$uri .= '#' . $fragment;
		}

		return $uri;
	}

	/**
	 * Whether the URI has the default port of the current scheme.
	 *
	 * `Psr\Http\Message\UriInterface::getPort` may return null or the standard port. This method can be used
	 * independently of the implementation.
	 */
	public static function isDefaultPort(UriInterface $uri)
	{
		$ports = self::DEFAULT_PORTS;
		$scheme = $uri->getScheme();

		return $uri->getPort() === null || (isset($ports[$scheme]) && $uri->getPort() === $ports[$scheme]);
	}

	/**
	 * Whether the URI is absolute, i.e. it has a scheme.
	 *
	 * An instance of UriInterface can either be an absolute URI or a relative reference. This method returns true
	 * if it is the former. An absolute URI has a scheme. A relative reference is used to express a URI relative
	 * to another URI, the base URI. Relative references can be divided into several forms:
	 * - network-path references, e.g. '//example.com/path'
	 * - absolute-path references, e.g. '/path'
	 * - relative-path references, e.g. 'subpath'
	 *
	 * @see Uri::isNetworkPathReference
	 * @see Uri::isAbsolutePathReference
	 * @see Uri::isRelativePathReference
	 * @link https://tools.ietf.org/html/rfc3986#section-4
	 */
	public static function isAbsolute(UriInterface $uri)
	{
		return $uri->getScheme() !== '';
	}

	/**
	 * Whether the URI is a network-path reference.
	 *
	 * A relative reference that begins with two slash characters is termed an network-path reference.
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-4.2
	 */
	public static function isNetworkPathReference(UriInterface $uri)
	{
		return $uri->getScheme() === '' && $uri->getAuthority() !== '';
	}

	/**
	 * Whether the URI is a absolute-path reference.
	 *
	 * A relative reference that begins with a single slash character is termed an absolute-path reference.
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-4.2
	 */
	public static function isAbsolutePathReference(UriInterface $uri)
	{
		return $uri->getScheme() === ''
		       && $uri->getAuthority() === ''
		       && isset($uri->getPath()[0])
		       && $uri->getPath()[0] === '/';
	}

	/**
	 * Whether the URI is a relative-path reference.
	 *
	 * A relative reference that does not begin with a slash character is termed a relative-path reference.
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-4.2
	 */
	public static function isRelativePathReference(UriInterface $uri)
	{
		return $uri->getScheme() === ''
		       && $uri->getAuthority() === ''
		       && (!isset($uri->getPath()[0]) || $uri->getPath()[0] !== '/');
	}

	/**
	 * Whether the URI is a same-document reference.
	 *
	 * A same-document reference refers to a URI that is, aside from its fragment
	 * component, identical to the base URI. When no base URI is given, only an empty
	 * URI reference (apart from its fragment) is considered a same-document reference.
	 *
	 * @param UriInterface $uri  The URI to check
	 * @param UriInterface|null $base An optional base URI to compare against
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-4.4
	 */
	public static function isSameDocumentReference(UriInterface $uri, UriInterface $base = null)
	{
		if($base !== null)
		{
			$uri = UriResolver::resolve($base, $uri);

			return ($uri->getScheme() === $base->getScheme())
			       && ($uri->getAuthority() === $base->getAuthority())
			       && ($uri->getPath() === $base->getPath())
			       && ($uri->getQuery() === $base->getQuery());
		}

		return $uri->getScheme() === '' && $uri->getAuthority() === '' && $uri->getPath() === '' && $uri->getQuery() === '';
	}

	/**
	 * Creates a new URI with a specific query string value removed.
	 *
	 * Any existing query string values that exactly match the provided key are
	 * removed.
	 *
	 * @param UriInterface $uri URI to use as a base.
	 * @param string $key Query string key to remove.
	 */
	public static function withoutQueryValue(UriInterface $uri, $key)
	{
		$result = self::getFilteredQueryString($uri, [$key]);

		return $uri->withQuery(implode('&', $result));
	}

	/**
	 * Creates a new URI with a specific query string value.
	 *
	 * Any existing query string values that exactly match the provided key are
	 * removed and replaced with the given key value pair.
	 *
	 * A value of null will set the query string key without a value, e.g. "key"
	 * instead of "key=value".
	 *
	 * @param UriInterface $uri URI to use as a base.
	 * @param string $key Key to set.
	 * @param string|null $value Value to set
	 */
	public static function withQueryValue(UriInterface $uri, $key, $value = null)
	{
		$result = self::getFilteredQueryString($uri, [$key]);

		$result[] = self::generateQueryString($key, $value);

		return $uri->withQuery(implode('&', $result));
	}

	/**
	 * Creates a new URI with multiple specific query string values.
	 *
	 * It has the same behavior as withQueryValue() but for an associative array of key => value.
	 *
	 * @param UriInterface $uri URI to use as a base.
	 * @param array<string, string|null> $keyValueArray Associative array of key and values
	 */
	public static function withQueryValues(UriInterface $uri, array $keyValueArray)
	{
		$result = self::getFilteredQueryString($uri, array_keys($keyValueArray));

		foreach($keyValueArray as $key => $value)
		{
			$result[] = self::generateQueryString((string) $key, $value !== null ? (string) $value : null);
		}

		return $uri->withQuery(implode('&', $result));
	}

	/**
	 * Creates a URI from a hash of `parse_url` components.
	 *
	 * @link http://php.net/manual/en/function.parse-url.php
	 *
	 * @throws MalformedUriException If the components do not form a valid URI.
	 */
	public static function fromParts(array $parts)
	{
		$uri = new self();
		$uri->applyParts($parts);
		$uri->validateState();

		return $uri;
	}

	public function getScheme()
	{
		return $this->scheme;
	}

	public function getAuthority()
	{
		$authority = $this->host;
		if($this->userInfo !== '')
		{
			$authority = $this->userInfo . '@' . $authority;
		}

		if($this->port !== null)
		{
			$authority .= ':' . $this->port;
		}

		return $authority;
	}

	public function getUserInfo()
	{
		return $this->userInfo;
	}

	public function getHost()
	{
		return $this->host;
	}

	public function getPort()
	{
		return $this->port;
	}

	public function getPath()
	{
		return $this->path;
	}

	public function getQuery()
	{
		return $this->query;
	}

	public function getFragment()
	{
		return $this->fragment;
	}

	/**
	 * @param string $scheme
	 *
	 * @return $this|Uri
	 */
	public function withScheme($scheme)
	{
		$scheme = $this->filterScheme($scheme);

		if($this->scheme === $scheme)
		{
			return $this;
		}

		$new = clone $this;
		$new->scheme = $scheme;
		$new->composedComponents = null;
		$new->removeDefaultPort();
		$new->validateState();

		return $new;
	}

	/**
	 * @param string $user
	 * @param null $password
	 *
	 * @return $this|Uri
	 */
	public function withUserInfo($user, $password = null)
	{
		$info = $this->filterUserInfoComponent($user);
		if($password !== null)
		{
			$info .= ':' . $this->filterUserInfoComponent($password);
		}

		if ($this->userInfo === $info) {
			return $this;
		}

		$new = clone $this;
		$new->userInfo = $info;
		$new->composedComponents = null;
		$new->validateState();

		return $new;
	}

	/**
	 * @param string $host
	 *
	 * @return $this|Uri
	 */
	public function withHost($host)
	{
		$host = $this->filterHost($host);

		if($this->host === $host)
		{
			return $this;
		}

		$new = clone $this;
		$new->host = $host;
		$new->composedComponents = null;
		$new->validateState();

		return $new;
	}

	/**
	 * @param int|null $port
	 *
	 * @return $this|Uri
	 */
	public function withPort($port)
	{
		$port = $this->filterPort($port);

		if($this->port === $port)
		{
			return $this;
		}

		$new = clone $this;
		$new->port = $port;
		$new->composedComponents = null;
		$new->removeDefaultPort();
		$new->validateState();

		return $new;
	}

	/**
	 * @param string $path
	 *
	 * @return $this|Uri
	 */
	public function withPath($path)
	{
		$path = $this->filterPath($path);

		if($this->path === $path)
		{
			return $this;
		}

		$new = clone $this;
		$new->path = $path;
		$new->composedComponents = null;
		$new->validateState();

		return $new;
	}

	/**
	 * @param string $query
	 *
	 * @return $this|Uri
	 */
	public function withQuery($query)
	{
		$query = $this->filterQueryAndFragment($query);

		if($this->query === $query)
		{
			return $this;
		}

		$new = clone $this;
		$new->query = $query;
		$new->composedComponents = null;

		return $new;
	}

	/**
	 * @param string $fragment
	 *
	 * @return $this|Uri
	 */
	public function withFragment($fragment)
	{
		$fragment = $this->filterQueryAndFragment($fragment);

		if($this->fragment === $fragment)
		{
			return $this;
		}

		$new = clone $this;
		$new->fragment = $fragment;
		$new->composedComponents = null;

		return $new;
	}

	/**
	 * Apply parse_url parts to a URI.
	 *
	 * @param array $parts Array of parse_url parts to apply.
	 */
	private function applyParts(array $parts)
	{
		$this->scheme = isset($parts['scheme']) ? $this->filterScheme($parts['scheme']) : '';
		$this->userInfo = isset($parts['user']) ? $this->filterUserInfoComponent($parts['user']) : '';
		$this->host = isset($parts['host']) ? $this->filterHost($parts['host']) : '';
		$this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
		$this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
		$this->query = isset($parts['query']) ? $this->filterQueryAndFragment($parts['query']) : '';
		$this->fragment = isset($parts['fragment']) ? $this->filterQueryAndFragment($parts['fragment']) : '';

		if(isset($parts['pass']))
		{
			$this->userInfo .= ':' . $this->filterUserInfoComponent($parts['pass']);
		}

		$this->removeDefaultPort();
	}

	/**
	 * @param mixed $scheme
	 *
	 * @throws InvalidArgumentException If the scheme is invalid.
	 */
	private function filterScheme($scheme)
	{
		if(!is_string($scheme))
		{
			throw new InvalidArgumentException('Scheme must be a string');
		}

		return strtr($scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
	}

	/**
	 * @param mixed $component
	 *
	 * @throws InvalidArgumentException If the user info is invalid.
	 */
	private function filterUserInfoComponent($component)
	{
		if(!is_string($component))
		{
			throw new InvalidArgumentException('User info must be a string');
		}

		return preg_replace_callback('/(?:[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ']+|%(?![A-Fa-f0-9]{2}))/',
			[$this, 'rawurlencodeMatchZero'],
			$component
		);
	}

	/**
	 * @param mixed $host
	 *
	 * @throws InvalidArgumentException If the host is invalid.
	 */
	private function filterHost($host)
	{
		if(!is_string($host))
		{
			throw new InvalidArgumentException('Host must be a string');
		}

		return strtr($host, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
	}

	/**
	 * @param mixed $port
	 *
	 * @throws InvalidArgumentException If the port is invalid.
	 */
	private function filterPort($port)
	{
		if($port === null)
		{
			return null;
		}

		$port = (int) $port;
		if (0 > $port || 0xffff < $port)
		{
			throw new InvalidArgumentException(sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
		}

		return $port;
	}

	/**
	 * @param string[] $keys
	 *
	 * @return string[]
	 */
	private static function getFilteredQueryString(UriInterface $uri, array $keys)
	{
		$current = $uri->getQuery();

		if($current === '')
		{
			return [];
		}

		$decodedKeys = array_map('rawurldecode', $keys);

		return array_filter(explode('&', $current), static function ($part) use ($decodedKeys) {
			return !in_array(rawurldecode(explode('=', $part)[0]), $decodedKeys, true);
		});
	}

	/**
	 * @param $key
	 * @param null $value
	 *
	 * @return string
	 */
	private static function generateQueryString($key, $value = null)
	{
		// Query string separators ("=", "&") within the key or value need to be encoded
		// (while preventing double-encoding) before setting the query string. All other
		// chars that need percent-encoding will be encoded by withQuery().
		$queryString = strtr($key, self::QUERY_SEPARATORS_REPLACEMENT);

		if($value !== null)
		{
			$queryString .= '=' . strtr($value, self::QUERY_SEPARATORS_REPLACEMENT);
		}

		return $queryString;
	}

	/**
	 * @return void
	 */
	private function removeDefaultPort()
	{
		if($this->port !== null && self::isDefaultPort($this))
		{
			$this->port = null;
		}
	}

	/**
	 * Filters the path of a URI
	 *
	 * @param mixed $path
	 *
	 * @throws InvalidArgumentException If the path is invalid.
	 */
	private function filterPath($path)
	{
		if(!is_string($path))
		{
			throw new InvalidArgumentException('Path must be a string');
		}

		return preg_replace_callback(
			'/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
			[$this, 'rawurlencodeMatchZero'],
			$path
		);
	}

	/**
	 * Filters the query string or fragment of a URI.
	 *
	 * @param mixed $str
	 *
	 * @throws InvalidArgumentException If the query or fragment is invalid.
	 */
	private function filterQueryAndFragment($str)
	{
		if(!is_string($str))
		{
			throw new InvalidArgumentException('Query and fragment must be a string');
		}

		return preg_replace_callback
		(
			'/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
			[$this, 'rawurlencodeMatchZero'],
			$str
		);
	}

	/**
	 * @param array $match
	 *
	 * @return string
	 */
	private function rawurlencodeMatchZero(array $match)
	{
		return rawurlencode($match[0]);
	}

	private function validateState()
	{
		if($this->host === '' && ($this->scheme === 'http' || $this->scheme === 'https'))
		{
			$this->host = self::HTTP_DEFAULT_HOST;
		}

		if($this->getAuthority() === '')
		{
			if(0 === strpos($this->path, '//'))
			{
				throw new MalformedUriException('The path of a URI without an authority must not start with two slashes "//"');
			}
			if($this->scheme === '' && false !== strpos(explode('/', $this->path, 2)[0], ':'))
			{
				throw new MalformedUriException('A relative URI must not have a path beginning with a segment containing a colon');
			}
		}
		elseif(isset($this->path[0]) && $this->path[0] !== '/')
		{
			throw new MalformedUriException('The path of a URI with an authority must start with a slash "/" or be empty');
		}
	}
}