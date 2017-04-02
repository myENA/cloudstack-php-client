<?php namespace MyENA\CloudStackClientGenerator\Generator;

use Psr\Http\Message\UriInterface;

/**
 * Class CloudStackUri
 */
class CloudStackUri implements UriInterface {

    /** @var string */
    private $scheme = '';
    /** @var string */
    private $host = '';
    /** @var int */
    private $port = 0;
    /** @var string */
    private $path = '';
    /** @var string */
    private $query = '';
    /** @var string */
    private $fragment = '';
    /** @var string */
    private $userInfo = '';

    /** @var string */
    private $_compiled = null;

    /**
     * CloudStackUri constructor.
     * @param AbstractCloudStackCommand $command
     */
    public function __construct(AbstractCloudStackCommand $command) {
        $this->scheme = $command->getConfiguration()->getScheme();
        $this->host = $command->getConfiguration()->getHost();
        $this->port = (int)$command->getConfiguration()->getPort();
        $this->path = sprintf('%s/%s', $command->getConfiguration()->getPathPrefix(), $command->getPath());
        $this->query = $command->getCompiledQuery();
    }

    public function __clone() {
        $this->_compiled = null;
    }

    /**
     * @return string The URI scheme.
     */
    public function getScheme() {
        return $this->scheme;
    }

    /**
     * @return string The URI authority, in "[user-info@]host[:port]" format.
     */
    public function getAuthority() {
        $ui = $this->getUserInfo();
        $host = $this->getHost();
        $port = $this->getPort();

        if ('' === $ui) {
            $uri = $host;
        } else {
            $uri = sprintf('%s@%s', $ui, $host);
        }

        if (null === $port || 0 === $port) {
            return $uri;
        }

        return sprintf('%s:%d', $uri, $port);
    }

    /**
     * @return string The URI user information, in "username[:password]" format.
     */
    public function getUserInfo() {
        return $this->userInfo;
    }

    /**
     * @return string The URI host.
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @return null|int The URI port.
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * @return string The URI path.
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * @return string The URI query string.
     */
    public function getQuery() {
        return $this->query;
    }

    /**
     * @return string The URI fragment.
     */
    public function getFragment() {
        return $this->fragment;
    }

    /**
     * @param string $scheme The scheme to use with the new instance.
     * @return static A new instance with the specified scheme.
     * @throws \InvalidArgumentException for invalid or unsupported schemes.
     */
    public function withScheme($scheme) {
        $scheme = strtolower($scheme);

        if ('http' === $scheme || 'https' === $scheme) {
            $clone = clone $this;
            $clone->scheme = $scheme;
            return $clone;
        }

        throw new \InvalidArgumentException(URI_INVALID_SCHEME_MSG, URI_INVALID_SCHEME);
    }

    /**
     * @param string $user The user name to use for authority.
     * @param null|string $password The password associated with $user.
     * @return static A new instance with the specified user information.
     */
    public function withUserInfo($user, $password = null) {
        $clone = clone $this;
        if (null === $password) {
            $clone->userInfo = $user;
        } else {
            $clone->userInfo = sprintf('%s:%s', $user, $password);
        }

        return $clone;
    }

    /**
     * @param string $host The hostname to use with the new instance.
     * @return static A new instance with the specified host.
     * @throws \InvalidArgumentException for invalid hostnames.
     */
    public function withHost($host) {
        if (null === $host) {
            $clone = clone $this;
            $clone->host = '';
            return $clone;
        }

        // TODO: Some kind of hostname validation
        $clone = clone $this;
        $clone->host = $host;
        return $clone;
    }

    /**
     * @param null|int $port The port to use with the new instance; a null value
     *     removes the port information.
     * @return static A new instance with the specified port.
     * @throws \InvalidArgumentException for invalid ports.
     */
    public function withPort($port) {
        if (null !== $port && (!is_int($port) || 0 >= $port || 65535 < $port)) {
            throw new \InvalidArgumentException(
                sprintf(URI_INVALID_PORT_MSG, is_int($port) ? $port : gettype($port)),
                URI_INVALID_PORT
            );
        }

        $clone = clone $this;
        $clone->port = $port;
        return $clone;
    }

    /**
     * @param string $path The path to use with the new instance.
     * @return static A new instance with the specified path.
     * @throws \InvalidArgumentException for invalid paths.
     */
    public function withPath($path) {
        if (is_string($path)) {
            $clone = clone $this;
            $clone->path = ltrim($path, "/");
            return $clone;
        }

        if (null === $path) {
            $clone = clone $this;
            $clone->path = '';
            return $clone;
        }

        throw new \InvalidArgumentException(sprintf(URI_INVALID_PATH_MSG, gettype($path)), URI_INVALID_PATH);
    }

    /**
     * @param string $query The query string to use with the new instance.
     * @return static A new instance with the specified query string.
     * @throws \InvalidArgumentException for invalid query strings.
     */
    public function withQuery($query) {
        // TODO: Some validation...?
        if (null === $query) {
            $clone = clone $this;
            $clone->query = '';
            return $clone;
        }

        if (is_string($query)) {
            $clone = clone $this;
            $clone->query = $query;
            return $clone;
        }

        throw new \InvalidArgumentException(sprintf(URI_INVALID_QUERY_MSG, gettype($query)), URI_INVALID_QUERY);
    }

    /**
     * @param string $fragment The fragment to use with the new instance.
     * @return static A new instance with the specified fragment.
     */
    public function withFragment($fragment) {
        $clone = clone $this;
        $clone->fragment = $fragment;
        return $clone;
    }

    /**
     * @return string
     */
    public function __toString() {
        if (null === $this->_compiled) {
            $s = $this->getScheme();
            $a = $this->getAuthority();
            $p = $this->getPath();
            $q = $this->getQuery();
            $f = $this->getFragment();

            if ('' === $s) {
                $uri = '';
            } else {
                $uri = sprintf('%s:', $s);
            }

            if ('' !== $a) {
                $uri = sprintf('%s//%s', $uri, $a);
            }

            if ('' !== $p) {
                if (0 === strpos($p, '/')) {
                    if ('' === $a) {
                        $uri = sprintf('%s/%s', $uri, $p);
                    } else {
                        $uri = sprintf('%s%s', $uri, $p);
                    }
                } else {
                    if ('' === $a) {
                        $uri = sprintf('%s/%s', $uri, ltrim($p, "/"));
                    } else {
                        $uri = sprintf('%s/%s', $uri, $p);
                    }
                }
            }

            if ('' !== $q) {
                $uri = sprintf('%s?%s', $uri, $q);
            }

            if ('' !== $f) {
                $uri = sprintf('%s#%s', $uri, $f);
            }

            $this->_compiled = $uri;
        }
        return $this->_compiled;
    }
}