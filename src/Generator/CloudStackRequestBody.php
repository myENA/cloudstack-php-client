<?php namespace MyENA\CloudStackClientGenerator\Generator;

use Psr\Http\Message\StreamInterface;

/**
 * Class CloudStackRequestBody
 */
class CloudStackRequestBody implements StreamInterface
{
    const STATE_OPEN = 0;
    const STATE_CLOSED = 1;
    const STATE_DETACHED = 2;

    /** @var int */
    private $_state;

    /** @var CloudStackConfiguration */
    private $configuration;

    /** @var array */
    private $parameters = [];

    /** @var resource */
    private $stream = null;

    /** @var null|array */
    private $meta = null;

    /** @var int */
    private $size = null;

    /**
     * CloudStackRequestBody constructor.
     *
     * @param CloudStackConfiguration $configuration
     * @param string $command
     * @param array $commandParameters
     * @throws \InvalidArgumentException
     */
    public function __construct(CloudStackConfiguration $configuration, $command, array $commandParameters = [])
    {
        // TODO: Move this out of constructor, maybe...?

        $this->configuration = $configuration;
        $this->parameters = $commandParameters;

        $params = [];
        foreach($commandParameters as $k => $v)
        {
            $paramStr = null;
            switch(gettype($v)) {
                case 'boolean':
                    $paramStr = $v ? 'true' : 'false';
                    break;
                case 'integer':
                case 'double':
                    $paramStr = strval($v);
                    break;
                case 'string':
                    $paramStr = $v;
                    break;
            }

            if (!is_string($paramStr))
                throw new \InvalidArgumentException(sprintf(WRONG_ARGUMENT_TYPE_MSG, $k, 'string', gettype($v)), WRONG_ARGUMENT_TYPE);

            if ('' === $paramStr)
                continue;

            $params[strtolower($k)] = $paramStr;
        }

        $params = ['apikey' => $configuration->getApiKey(), 'command' => $command, 'response' => 'json'] + $params;
        ksort($params);

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $query = sprintf('%s&signature=%s', $query, $this->configuration->buildSignature($query));

        $this->size = mb_strlen($query, '8bit');

        $this->stream = fopen('php://memory', 'w+b');
        fwrite($this->stream, $query);

        $this->meta = stream_get_meta_data($this->stream);

        $this->_state = self::STATE_OPEN;
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString()
    {
        if (null === $this->stream)
            return '';

        $this->rewind();
        $str = '';
        while(!$this->eof() && $data = $this->read(8192))
        {
            $str .= $data;
        }

        return $str;
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close()
    {
        if (self::STATE_OPEN === $this->_state)
        {
            fclose($this->stream);
            $this->stream = null;
            $this->size = 0;
            $this->_state = self::STATE_CLOSED;
        }
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        if (self::STATE_OPEN === $this->_state)
            $stream = $this->stream;
        else
            $stream = null;

        $this->stream = null;
        $this->size = 0;
        $this->_state = self::STATE_DETACHED;
        return $stream;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize()
    {
        if (self::STATE_OPEN !== $this->_state)
            return null;

        return $this->size;
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell()
    {
        if (self::STATE_OPEN === $this->_state)
            return ftell($this->stream);

        throw new \RuntimeException();
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof()
    {
        if (null === $this->stream)
            return true;

        return feof($this->stream);
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable()
    {
        return self::STATE_OPEN === $this->_state;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws \RuntimeException on failure.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if ($this->isSeekable())
            fseek($this->stream, $offset, $whence);
        else
            throw $this->createStateException();
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @see seek()
     * @link http://www.php.net/manual/en/function.fseek.php
     * @throws \RuntimeException on failure.
     */
    public function rewind()
    {
        if ($this->isSeekable())
            rewind($this->stream);
        else
           throw $this->createStateException();
    }

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable()
    {
        return false;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write($string)
    {
        throw new \RuntimeException(REQUEST_BODY_NOT_WRITABLE_MSG, REQUEST_BODY_NOT_WRITABLE);
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable()
    {
        return self::STATE_OPEN === $this->_state;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read($length)
    {
        if (self::STATE_OPEN === $this->_state)
            return (string)fread($this->stream, $length);

        throw $this->createStateException();
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents()
    {
        if (self::STATE_OPEN === $this->_state)
        {
            $str = '';
            while(!$this->eof() && $data = $this->read(8192))
            {
                $str .= $data;
            }

            return $str;
        }

        throw $this->createStateException();
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null)
    {
        if (self::STATE_OPEN === $this->_state)
        {
            if (null === $key)
                return $this->meta;

            if (isset($this->meta[$key]))
                return $this->meta[$key];

            return null;
        }

        return null;
    }

    /**
     * @return \RuntimeException
     */
    private function createStateException()
    {
        if (self::STATE_DETACHED === $this->_state)
            return new \RuntimeException(REQUEST_BODY_DETACHED_MSG, REQUEST_BODY_DETACHED);

        return new \RuntimeException(REQUEST_BODY_CLOSED_MSG, REQUEST_BODY_CLOSED);
    }
}